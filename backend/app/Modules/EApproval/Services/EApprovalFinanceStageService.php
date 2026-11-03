<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalFormMailConfig;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class EApprovalFinanceStageService
{
    public function __construct(
        private readonly EApprovalCashAdvanceService $cashAdvances,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalSettingsService $settings,
    ) {}

    public function label(EApprovalSubmission $submission): string
    {
        $this->ensureFormMetadata($submission);
        $submission->loadMissing('form');
        $stage = $this->stage($submission);
        if ($stage === 'for_return') {
            return 'For return';
        }
        if ($stage === 'funds_returned') {
            return 'Funds returned';
        }

        $status = (string) $submission->status;
        $family = $this->family($submission->form);

        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];

        if ($status === EApprovalSubmissionStatus::PENDING) {
            $proofLabel = $this->proofStatusLabel($submission);
            if ($proofLabel === 'On hold') {
                return $proofLabel;
            }

            $balance = $this->settlementBalance($submission);
            if (is_array($balance) && ($balance['closed'] ?? false) !== true) {
                return ($balance['direction'] ?? '') === 'surrender' ? 'To receive' : 'To pay';
            }

            if ($proofLabel !== null) {
                return $proofLabel;
            }

            $stepLabel = $this->currentStepLabel($submission);
            $word = EApprovalFormMailConfig::statusWord($metadata, EApprovalSubmissionStatus::PENDING);

            return $stepLabel !== '' ? $word.' — '.$stepLabel : $word;
        }

        if ($status === EApprovalSubmissionStatus::APPROVED) {
            if ($family === 'cash_advance') {
                return $this->isOverdue($submission) ? 'Released — Overdue' : 'Released';
            }
            if ($family === 'liquidation') {
                return 'Settled';
            }
            if ($family === 'reimbursement') {
                return 'Paid';
            }
        }

        return EApprovalFormMailConfig::statusWord($metadata, $status);
    }

    /**
     * The latest proof on the current step replaces Pending until the request moves on.
     */
    private function proofStatusLabel(EApprovalSubmission $submission): ?string
    {
        $finance = $this->finance($submission);
        $entries = array_merge(
            is_array($finance['payment_proofs'] ?? null) ? $finance['payment_proofs'] : [],
            is_array($finance['settlement_captures'] ?? null) ? $finance['settlement_captures'] : [],
        );
        $latest = null;
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $proofStep = array_key_exists('step_order', $entry)
                ? (int) $entry['step_order']
                : (int) $submission->current_step;
            if ($proofStep === (int) $submission->current_step) {
                $latest = $entry;
            }
        }
        if ($latest === null) {
            return null;
        }

        return match (strtolower(trim((string) ($latest['status'] ?? '')))) {
            'hold' => 'On hold',
            'released' => 'Released',
            'paid' => 'Paid',
            'received' => 'Received',
            'settled' => 'Settled',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function captureOnApprove(EApprovalSubmission $submission, ?EApprovalWorkflowStep $step, array $input): void
    {
        $action = $this->stepAction($step);
        if (! in_array($action, ['release', 'settlement', 'payment'], true)) {
            return;
        }

        $finance = $this->finance($submission);
        $submission->loadMissing(['form', 'values.field']);

        if ($action === 'release') {
            $reference = trim((string) ($input['release_reference'] ?? ''));
            $date = trim((string) ($input['release_date'] ?? ''));
            if ($reference === '' || $date === '') {
                throw ValidationException::withMessages([
                    'release_reference' => [__('Enter the release reference and the date the cash was handed over.')],
                ]);
            }
            $finance['release_reference'] = $reference;
            $finance['release_date'] = $date;
            $finance['released_at'] = now()->toIso8601String();
            $submission->finance_json = $finance;
            $submission->save();

            return;
        }

        $amount = $this->moneyAmount($submission, $action);
        if (abs($amount) < 0.005) {
            $finance['settlement_amount'] = '0.00';
            $submission->finance_json = $finance;
            $submission->save();

            return;
        }

        $reference = trim((string) ($input['settlement_reference'] ?? ''));
        if ($reference === '' && $this->settlementAlreadyMoved($submission)) {
            return;
        }
        if ($reference === '') {
            throw ValidationException::withMessages([
                'settlement_reference' => [__('Enter the reference for the cash received or the payment.')],
            ]);
        }

        $finance['settlement_reference'] = $reference;
        $finance['settlement_direction'] = $amount > 0.004 ? 'payment' : ($amount < -0.004 ? 'surrender' : 'settled');
        $finance['settlement_amount'] = number_format($amount, 2, '.', '');
        $finance['settled_at'] = now()->toIso8601String();
        $submission->finance_json = $finance;
        $submission->save();
    }

    /**
     * @param  array<string, mixed>  $proof
     */
    public function recordPaymentProof(EApprovalSubmission $submission, array $proof, TenantUser $actor): void
    {
        $status = strtolower(trim((string) ($proof['status'] ?? '')));
        if (! in_array($status, ['released', 'hold'], true)) {
            throw ValidationException::withMessages([
                'payment_proof.status' => [__('Choose Released or Hold.')],
            ]);
        }

        $comment = trim((string) ($proof['comment'] ?? ''));
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            is_array($proof['attachment_ids'] ?? null) ? $proof['attachment_ids'] : [],
        ))));
        if (count($ids) > 4) {
            throw ValidationException::withMessages([
                'payment_proof.attachment_ids' => [__('Attach at most 4 files.')],
            ]);
        }
        if ($ids !== []) {
            $found = EApprovalAttachment::query()
                ->where('submission_id', $submission->id)
                ->whereIn('id', $ids)
                ->count();
            if ($found !== count($ids)) {
                throw ValidationException::withMessages([
                    'payment_proof.attachment_ids' => [__('One of those files is not on this request.')],
                ]);
            }
        }

        $finance = $this->finance($submission);
        $entries = is_array($finance['payment_proofs'] ?? null) ? $finance['payment_proofs'] : [];
        $entries[] = [
            'status' => $status,
            'comment' => $comment,
            'attachment_ids' => $ids,
            'user_id' => (string) $actor->id,
            'step_order' => isset($proof['step_order']) ? (int) $proof['step_order'] : (int) $submission->current_step,
            'recorded_at' => now()->toIso8601String(),
        ];
        $finance['payment_proofs'] = $entries;
        $submission->finance_json = $finance;
        $submission->save();
    }

    /**
     * Paid and Received move cash and continue. Settled records a zero difference.
     * Hold stays on this step.
     *
     * @param  array<string, mixed>  $capture
     */
    public function recordSettlementCapture(EApprovalSubmission $submission, array $capture, TenantUser $actor): void
    {
        $status = strtolower(trim((string) ($capture['status'] ?? '')));
        $balance = $this->settlementBalance($submission);
        $move = $balance ?? $this->settlementMove($submission);
        $allowed = match ($move['direction']) {
            'payment' => ['paid', 'hold'],
            'surrender' => ['received', 'hold'],
            default => ['settled'],
        };
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'settlement_capture.status' => [__('Choose the settlement that matches this request.')],
            ]);
        }

        $comment = trim((string) ($capture['comment'] ?? ''));
        $reference = trim((string) ($capture['reference'] ?? ''));
        $date = trim((string) ($capture['date'] ?? ''));
        $movesCash = in_array($status, ['paid', 'received'], true);
        if ($status === 'hold' && $comment === '') {
            throw ValidationException::withMessages([
                'settlement_capture.comment' => [__('A comment is required to hold this step.')],
            ]);
        }
        if ($movesCash && ($reference === '' || $date === '')) {
            throw ValidationException::withMessages([
                'settlement_capture.reference' => [__('Enter the reference and the date the cash moved.')],
            ]);
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            is_array($capture['attachment_ids'] ?? null) ? $capture['attachment_ids'] : [],
        ))));
        if (count($ids) > 4) {
            throw ValidationException::withMessages([
                'settlement_capture.attachment_ids' => [__('Attach at most 4 files.')],
            ]);
        }
        if ($movesCash && $ids === []) {
            throw ValidationException::withMessages([
                'settlement_capture.attachment_ids' => [__('Attach the proof before continuing.')],
            ]);
        }
        if ($ids !== []) {
            $found = EApprovalAttachment::query()
                ->where('submission_id', $submission->id)
                ->whereIn('id', $ids)
                ->count();
            if ($found !== count($ids)) {
                throw ValidationException::withMessages([
                    'settlement_capture.attachment_ids' => [__('One of those files is not on this request.')],
                ]);
            }
        }

        $finance = $this->finance($submission);
        $entries = is_array($finance['settlement_captures'] ?? null) ? $finance['settlement_captures'] : [];
        $signed = $balance !== null
            ? round((float) $balance['current'] - (float) $balance['net'], 2)
            : (float) $move['amount'];
        if ($status !== 'hold') {
            $this->backfillLegacyCaptureAmounts($entries, $finance);
        }
        $row = [
            'status' => $status,
            'comment' => $comment,
            'reference' => $reference,
            'date' => $date,
            'attachment_ids' => $ids,
            'user_id' => (string) $actor->id,
            'step_order' => isset($capture['step_order']) ? (int) $capture['step_order'] : (int) $submission->current_step,
            'recorded_at' => now()->toIso8601String(),
        ];
        if ($status !== 'hold') {
            $row['amount'] = number_format($signed, 2, '.', '');
        }
        $entries[] = $row;
        $finance['settlement_captures'] = $entries;
        if ($status !== 'hold') {
            if ($reference !== '') {
                $finance['settlement_reference'] = $reference;
            }
            if ($date !== '') {
                $finance['settlement_date'] = $date;
            }
            $finance['settlement_direction'] = $move['direction'];
            $finance['settlement_amount'] = number_format(((float) ($balance['net'] ?? 0)) + $signed, 2, '.', '');
            $finance['settled_at'] = now()->toIso8601String();
        }
        $submission->finance_json = $finance;
        $submission->save();
    }

    /**
     * Paid, Received, or Settled already on the request. A later Hold does not count.
     */
    public function settlementAlreadyMoved(EApprovalSubmission $submission): bool
    {
        $finance = $this->finance($submission);
        $entries = is_array($finance['settlement_captures'] ?? null) ? $finance['settlement_captures'] : [];
        $latest = null;
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $status = strtolower(trim((string) ($entry['status'] ?? '')));
            if (in_array($status, ['paid', 'received', 'settled', 'hold'], true)) {
                $latest = $status;
            }
        }

        return in_array($latest, ['paid', 'received', 'settled'], true);
    }

    /**
     * What is still open after payments already recorded.
     * Closed when the new difference matches the net already moved.
     *
     * @return array{
     *     direction: string,
     *     amount: string,
     *     net: string,
     *     current: string,
     *     closed: bool,
     *     prior_status: string|null,
     *     prior_reference: string|null,
     *     prior_date: string|null,
     *     prior_amount: string|null
     * }|null
     */
    public function settlementBalance(EApprovalSubmission $submission): ?array
    {
        $this->ensureFormMetadata($submission);
        $submission->loadMissing('form');
        if (! in_array($this->family($submission->form), ['liquidation', 'reimbursement'], true)) {
            return null;
        }

        $this->ensureSettlementAmounts($submission);

        if (! $this->settlementAlreadyMoved($submission)) {
            return null;
        }

        try {
            $move = $this->settlementMove($submission);
        } catch (ValidationException) {
            return null;
        }

        $finance = $this->finance($submission);
        $current = round((float) $move['amount'], 2);
        $net = $this->settlementNetMoved($finance);
        $gap = round($current - $net, 2);
        $direction = $gap > 0.004 ? 'payment' : ($gap < -0.004 ? 'surrender' : 'settled');
        $prior = $this->latestSettlementCapture($finance);

        return [
            'direction' => $direction,
            'amount' => number_format(abs($gap), 2, '.', ''),
            'net' => number_format($net, 2, '.', ''),
            'current' => number_format($current, 2, '.', ''),
            'closed' => abs($gap) < 0.005,
            'prior_status' => $prior['status'],
            'prior_reference' => $prior['reference'],
            'prior_date' => $prior['date'],
            'prior_amount' => $prior['amount'],
        ];
    }

    /**
     * @param  array<string, mixed>  $finance
     */
    private function settlementNetMoved(array $finance): float
    {
        $entries = is_array($finance['settlement_captures'] ?? null) ? $finance['settlement_captures'] : [];
        $explicit = 0.0;
        $hasExplicit = false;
        $legacy = false;
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $status = strtolower(trim((string) ($entry['status'] ?? '')));
            if (! in_array($status, ['paid', 'received', 'settled'], true)) {
                continue;
            }
            if (isset($entry['amount']) && is_numeric($entry['amount'])) {
                $explicit += (float) $entry['amount'];
                $hasExplicit = true;

                continue;
            }
            $legacy = true;
        }

        if (! $hasExplicit) {
            return $legacy ? round((float) ($finance['settlement_amount'] ?? 0), 2) : 0.0;
        }

        return round($explicit, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>  $finance
     */
    private function backfillLegacyCaptureAmounts(array &$entries, array $finance): void
    {
        $legacy = [];
        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $status = strtolower(trim((string) ($entry['status'] ?? '')));
            if (! in_array($status, ['paid', 'received', 'settled'], true)) {
                continue;
            }
            if (isset($entry['amount']) && is_numeric($entry['amount'])) {
                continue;
            }
            $legacy[] = $index;
        }
        if ($legacy === []) {
            return;
        }

        $lump = number_format((float) ($finance['settlement_amount'] ?? 0), 2, '.', '');
        $last = $legacy[array_key_last($legacy)];
        foreach ($legacy as $index) {
            $entries[$index]['amount'] = $index === $last ? $lump : '0.00';
        }
    }

    /**
     * @param  array<string, mixed>  $finance
     * @return array{status: string|null, reference: string|null, date: string|null, amount: string|null}
     */
    private function latestSettlementCapture(array $finance): array
    {
        $entries = is_array($finance['settlement_captures'] ?? null) ? $finance['settlement_captures'] : [];
        $latest = null;
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $status = strtolower(trim((string) ($entry['status'] ?? '')));
            if (in_array($status, ['paid', 'received', 'settled'], true)) {
                $latest = $entry;
                $latest['status'] = $status;
            }
        }
        if ($latest === null) {
            return ['status' => null, 'reference' => null, 'date' => null, 'amount' => null];
        }

        $reference = trim((string) ($latest['reference'] ?? $finance['settlement_reference'] ?? ''));
        $date = trim((string) ($latest['date'] ?? $finance['settlement_date'] ?? ''));
        $amount = isset($latest['amount']) && is_numeric($latest['amount'])
            ? number_format((float) $latest['amount'], 2, '.', '')
            : (is_numeric($finance['settlement_amount'] ?? null)
                ? number_format(abs((float) $finance['settlement_amount']), 2, '.', '')
                : null);

        return [
            'status' => (string) $latest['status'],
            'reference' => $reference !== '' ? $reference : null,
            'date' => $date !== '' ? $date : null,
            'amount' => $amount,
        ];
    }

    /**
     * @return array{direction: string, amount: float}
     */
    public function settlementMove(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['form', 'values.field']);
        if ($this->family($submission->form) === 'reimbursement') {
            $actual = $this->fieldAmount($submission, 'total_reimbursement');
            if ($actual === null) {
                throw ValidationException::withMessages([
                    'settlement_capture' => [__('The amount to pay is not on this request yet.')],
                ]);
            }

            return ['direction' => 'payment', 'amount' => round(max(0, $actual), 2)];
        }

        $actual = $this->fieldAmount($submission, 'total_reimbursement');
        $released = $this->fieldAmount($submission, 'cash_advance_amount');
        if ($actual === null || $released === null) {
            throw ValidationException::withMessages([
                'settlement_capture' => [__('The settlement amounts are not on this request yet.')],
            ]);
        }

        $delta = round($actual - $released, 2);
        if ($delta > 0.004) {
            return ['direction' => 'payment', 'amount' => $delta];
        }
        if ($delta < -0.004) {
            return ['direction' => 'surrender', 'amount' => $delta];
        }

        return ['direction' => 'settled', 'amount' => 0.0];
    }

    public function cancelTrip(EApprovalSubmission $submission, TenantUser $actor): EApprovalSubmission
    {
        $this->assertRequestorOrManager($submission, $actor);
        $submission->loadMissing('form');
        if ($this->family($submission->form) !== 'cash_advance') {
            throw ValidationException::withMessages([
                'submission' => [__('Only a cash advance can be cancelled as a trip.')],
            ]);
        }
        if ((string) $submission->status !== EApprovalSubmissionStatus::APPROVED) {
            throw ValidationException::withMessages([
                'submission' => [__('Cancel the trip after the cash has been released.')],
            ]);
        }
        if ($this->cashAdvances->hasLiveLiquidation((string) $submission->id)) {
            throw ValidationException::withMessages([
                'submission' => [__('A liquidation is already filed. Finish that liquidation.')],
            ]);
        }

        $finance = $this->finance($submission);
        $finance['stage'] = 'for_return';
        $submission->finance_json = $finance;
        $submission->save();

        return $submission->refresh();
    }

    public function confirmReturn(EApprovalSubmission $submission, TenantUser $actor, string $reference): EApprovalSubmission
    {
        if (! $actor->can('e_approval:approve') && ! $actor->can('e_approval:forms:manage')) {
            throw ValidationException::withMessages([
                'submission' => [__('You cannot confirm this return.')],
            ]);
        }

        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages([
                'settlement_reference' => [__('Enter the reference for the cash received.')],
            ]);
        }

        if ($this->stage($submission) !== 'for_return') {
            throw ValidationException::withMessages([
                'submission' => [__('This cash advance is not waiting for a return.')],
            ]);
        }

        $finance = $this->finance($submission);
        $finance['stage'] = 'funds_returned';
        $finance['settlement_reference'] = $reference;
        $finance['returned_at'] = now()->toIso8601String();
        $submission->finance_json = $finance;
        $submission->save();

        return $submission->refresh();
    }

    public function assertCanSubmitCashAdvance(EApprovalForm $form, TenantUser $requestor): void
    {
        if ($this->family($form) !== 'cash_advance') {
            return;
        }
        if (! $this->settings->getBool(EApprovalSettingsService::BLOCK_NEW_CASH_ADVANCE, false)) {
            return;
        }

        $open = EApprovalSubmission::query()
            ->with('form')
            ->where('requestor_id', $requestor->id)
            ->where('status', EApprovalSubmissionStatus::APPROVED)
            ->get();

        foreach ($open as $candidate) {
            if ($this->family($candidate->form) !== 'cash_advance') {
                continue;
            }
            if ($this->stage($candidate) === 'funds_returned') {
                continue;
            }
            $settled = EApprovalSubmission::query()
                ->where('parent_submission_id', $candidate->id)
                ->where('status', EApprovalSubmissionStatus::APPROVED)
                ->exists();
            if ($settled) {
                continue;
            }

            $liveLiquidation = EApprovalSubmission::query()
                ->where('parent_submission_id', $candidate->id)
                ->whereIn('status', [
                    EApprovalSubmissionStatus::DRAFT,
                    ...EApprovalSubmissionStatus::open(),
                ])
                ->orderByDesc('updated_at')
                ->first();
            $target = $liveLiquidation ?? $candidate;
            $kind = $liveLiquidation !== null
                ? 'liquidation'
                : ($this->stage($candidate) === 'for_return' ? 'for_return' : 'cash_advance');
            $documentNo = trim((string) $candidate->document_no);
            $targetDocumentNo = trim((string) ($target->document_no ?? ''));

            throw ValidationException::withMessages([
                'form_id' => [
                    $documentNo !== ''
                        ? __('Liquidate or return the open cash advance :document before requesting another.', ['document' => $documentNo])
                        : __('Liquidate or return the open cash advance before requesting another.'),
                ],
                '_link_submission_id' => [(string) $target->id],
                '_link_kind' => [$kind],
                '_link_document_no' => [$targetDocumentNo !== '' ? $targetDocumentNo : $documentNo],
            ]);
        }
    }

    /**
     * @return array{reminders: int, overdue: int}
     */
    public function remind(): array
    {
        $dueDays = $this->settings->getIntAllowingZero(EApprovalSettingsService::LIQUIDATION_DUE_DAYS, 0);
        if ($dueDays <= 0) {
            return ['reminders' => 0, 'overdue' => 0];
        }

        $remindDays = $this->settings->getIntAllowingZero(EApprovalSettingsService::LIQUIDATION_REMIND_DAYS, 0);
        $reminders = 0;
        $overdue = 0;
        $today = now()->toDateString();

        $advances = EApprovalSubmission::query()
            ->with('form')
            ->where('status', EApprovalSubmissionStatus::APPROVED)
            ->whereNotNull('finance_json')
            ->get();

        foreach ($advances as $advance) {
            if ($this->family($advance->form) !== 'cash_advance') {
                continue;
            }
            if (in_array($this->stage($advance), ['for_return', 'funds_returned'], true)) {
                continue;
            }

            $settled = EApprovalSubmission::query()
                ->where('parent_submission_id', $advance->id)
                ->where('status', EApprovalSubmissionStatus::APPROVED)
                ->exists();
            if ($settled) {
                continue;
            }

            $finance = $this->finance($advance);
            $releasedAt = trim((string) ($finance['released_at'] ?? ''));
            if ($releasedAt === '') {
                continue;
            }

            $releasedOn = substr($releasedAt, 0, 10);
            $dueOn = date('Y-m-d', strtotime($releasedOn.' + '.$dueDays.' days'));
            $remindOn = date('Y-m-d', strtotime($dueOn.' - '.$remindDays.' days'));
            $changed = false;

            if ($today >= $dueOn && ($finance['overdue'] ?? false) !== true) {
                $finance['overdue'] = true;
                $changed = true;
                $overdue++;
            }

            if ($today >= $remindOn && ($finance['reminded_on'] ?? '') !== $today) {
                $finance['reminded_on'] = $today;
                $changed = true;
                $this->inApp->notify(
                    (string) $advance->requestor_id,
                    'liquidation_due',
                    (string) $advance->id,
                    __('Cash advance :doc is due for liquidation.', ['doc' => $advance->document_no]),
                    submission: $advance,
                );
                $financeUserId = $this->financeStepUserId($advance);
                if ($financeUserId !== '') {
                    $this->inApp->notify(
                        $financeUserId,
                        'liquidation_due',
                        (string) $advance->id,
                        __('Cash advance :doc is due for liquidation.', ['doc' => $advance->document_no]),
                        submission: $advance,
                    );
                }
                $reminders++;
            }

            if ($changed) {
                $advance->finance_json = $finance;
                $advance->save();
            }
        }

        return ['reminders' => $reminders, 'overdue' => $overdue];
    }

    private function financeStepUserId(EApprovalSubmission $advance): string
    {
        $advance->loadMissing('form.workflowTemplate.steps');
        $steps = $advance->form?->workflowTemplate?->steps;
        if ($steps === null) {
            return '';
        }

        foreach ($steps as $step) {
            if (! $step instanceof EApprovalWorkflowStep) {
                continue;
            }
            if ($step->compiled_for_submission_id !== null) {
                continue;
            }
            $condition = is_array($step->condition) ? $step->condition : [];
            $action = strtolower(trim((string) ($condition['finance_action'] ?? '')));
            if (! in_array($action, ['review', 'validate', 'release'], true)) {
                continue;
            }
            if ((string) $step->approver_type !== 'user') {
                continue;
            }
            $userId = trim((string) $step->approver_id);
            if ($userId !== '') {
                return $userId;
            }
        }

        return '';
    }

    private function moneyAmount(EApprovalSubmission $submission, string $action): float
    {
        if ($action === 'payment') {
            return $this->fieldAmount($submission, 'total_reimbursement') ?? 0.0;
        }

        $actual = $this->fieldAmount($submission, 'total_reimbursement') ?? 0.0;
        $released = $this->fieldAmount($submission, 'cash_advance_amount') ?? 0.0;

        return round($actual - $released, 2);
    }

    private function fieldAmount(EApprovalSubmission $submission, string $name): ?float
    {
        foreach ($submission->values as $value) {
            if ((string) ($value->field?->name ?? '') !== $name) {
                continue;
            }
            $raw = trim(str_replace(',', '', (string) ($value->value ?? '')));
            if ($raw === '' || ! is_numeric($raw)) {
                return null;
            }

            return (float) $raw;
        }

        return null;
    }

    private function currentStepLabel(EApprovalSubmission $submission): string
    {
        $order = (int) $submission->current_step;
        $snapshot = $submission->workflow_snapshot_json;
        $decoded = is_string($snapshot) ? json_decode($snapshot, true) : (is_array($snapshot) ? $snapshot : []);
        $steps = is_array($decoded['steps'] ?? null) ? $decoded['steps'] : [];
        foreach ($steps as $step) {
            if (! is_array($step) || (int) ($step['step_order'] ?? 0) !== $order) {
                continue;
            }
            $label = trim((string) ($step['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return '';
    }

    private function isOverdue(EApprovalSubmission $submission): bool
    {
        return ($this->finance($submission)['overdue'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function finance(EApprovalSubmission $submission): array
    {
        return is_array($submission->finance_json) ? $submission->finance_json : [];
    }

    private function stage(EApprovalSubmission $submission): string
    {
        return strtolower(trim((string) ($this->finance($submission)['stage'] ?? '')));
    }

    /**
     * List queries load the form as id and name only. The family lives in metadata.
     */
    private function ensureFormMetadata(EApprovalSubmission $submission): void
    {
        $form = $submission->relationLoaded('form') ? $submission->getRelation('form') : null;
        if (! $form instanceof EApprovalForm || array_key_exists('metadata_json', $form->getAttributes())) {
            return;
        }

        $metadata = EApprovalForm::query()->whereKey($form->getKey())->value('metadata_json');
        $form->setAttribute('metadata_json', is_string($metadata) ? json_decode($metadata, true) : $metadata);
    }

    /**
     * List queries load subsidiary and department only. The remainder needs the totals.
     */
    private function ensureSettlementAmounts(EApprovalSubmission $submission): void
    {
        $needed = ['total_reimbursement', 'cash_advance_amount'];
        $present = [];
        if ($submission->relationLoaded('values')) {
            foreach ($submission->values as $value) {
                $present[(string) ($value->field?->name ?? '')] = true;
            }
        }

        $missing = array_values(array_filter(
            $needed,
            static fn (string $name): bool => ! isset($present[$name]),
        ));
        if ($missing === [] && $submission->relationLoaded('values')) {
            return;
        }

        $rows = $submission->values()
            ->whereHas('field', static fn ($field) => $field->whereIn('name', $missing === [] ? $needed : $missing))
            ->with(['field:id,name'])
            ->get();

        if ($submission->relationLoaded('values')) {
            $submission->setRelation('values', $submission->values->concat($rows)->unique('id')->values());

            return;
        }

        $submission->setRelation('values', $rows);
    }

    private function family(?EApprovalForm $form): ?string
    {
        $metadata = is_array($form?->metadata_json) ? $form->metadata_json : [];
        $family = trim((string) ($metadata['form_family'] ?? ''));

        return $family !== '' ? $family : null;
    }

    private function stepAction(?EApprovalWorkflowStep $step): string
    {
        $condition = is_array($step?->condition) ? $step->condition : [];

        return strtolower(trim((string) ($condition['finance_action'] ?? '')));
    }

    private function assertRequestorOrManager(EApprovalSubmission $submission, TenantUser $actor): void
    {
        if ((string) $submission->requestor_id === (string) $actor->id || $actor->can('e_approval:forms:manage')) {
            return;
        }

        throw ValidationException::withMessages([
            'submission' => [__('You cannot cancel this trip.')],
        ]);
    }
}
