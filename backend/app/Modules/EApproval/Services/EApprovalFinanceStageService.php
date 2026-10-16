<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
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

        if ($status === EApprovalSubmissionStatus::PENDING) {
            $stepLabel = $this->currentStepLabel($submission);

            return $stepLabel !== '' ? 'Pending — '.$stepLabel : 'Pending';
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

        return EApprovalSubmissionStatus::label($status);
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

            throw ValidationException::withMessages([
                'form_id' => [__('Liquidate or return the open cash advance before requesting another.')],
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
