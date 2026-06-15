<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;

final class EApprovalSubmissionFinanceAuditService
{
    public function __construct(
        private readonly EApprovalAuditLogger $audit,
    ) {}

    public function logParentLinkChange(
        string $childSubmissionId,
        ?string $previousParentId,
        ?string $newParentId,
        ?TenantUser $actor = null,
    ): void {
        $previousParentId = $this->normalizeId($previousParentId);
        $newParentId = $this->normalizeId($newParentId);

        if ($newParentId === null) {
            if ($previousParentId === null) {
                return;
            }

            $this->audit->log(
                'parent_submission_unlinked',
                $childSubmissionId,
                $this->encodeRemarks([
                    'previous_parent_submission_id' => $previousParentId,
                    'previous_parent_document_no' => $this->documentNo($previousParentId),
                ]),
                $actor,
            );

            return;
        }

        if ($previousParentId === $newParentId) {
            return;
        }

        $action = $previousParentId === null ? 'parent_submission_linked' : 'parent_submission_changed';

        $this->audit->log(
            $action,
            $childSubmissionId,
            $this->encodeRemarks([
                'parent_submission_id' => $newParentId,
                'parent_document_no' => $this->documentNo($newParentId),
                'parent_form_family' => $this->formFamily($newParentId),
                'previous_parent_submission_id' => $previousParentId,
                'previous_parent_document_no' => $previousParentId !== null ? $this->documentNo($previousParentId) : null,
            ]),
            $actor,
        );
    }

    /**
     * @param  array{
     *     policy_kind: string,
     *     parent_submission_id: string,
     *     amount: float,
     *     strict_open_balance: float|null,
     *     policy_max_amount: float|null,
     *     warning: string
     * }  $context
     */
    public function logOverspendPolicyAllowed(array $context, string $submissionId, ?TenantUser $actor = null): void
    {
        $kind = (string) ($context['policy_kind'] ?? '');
        $action = $kind === 'liquidation' ? 'liquidation_overspend_allowed' : 'po_overspend_allowed';

        $this->audit->log(
            $action,
            $submissionId,
            $this->encodeRemarks([
                'policy_kind' => $kind,
                'parent_submission_id' => (string) ($context['parent_submission_id'] ?? ''),
                'parent_document_no' => $this->documentNo((string) ($context['parent_submission_id'] ?? '')),
                'amount' => round((float) ($context['amount'] ?? 0), 2),
                'strict_open_balance' => $context['strict_open_balance'] !== null
                    ? round((float) $context['strict_open_balance'], 2)
                    : null,
                'policy_max_amount' => $context['policy_max_amount'] !== null
                    ? round((float) $context['policy_max_amount'], 2)
                    : null,
                'message' => (string) ($context['warning'] ?? ''),
            ]),
            $actor,
        );
    }

    private function normalizeId(?string $id): ?string
    {
        if ($id === null || trim($id) === '') {
            return null;
        }

        return trim($id);
    }

    private function documentNo(string $submissionId): ?string
    {
        if ($submissionId === '') {
            return null;
        }

        /** @var EApprovalSubmission|null $submission */
        $submission = EApprovalSubmission::query()->select(['id', 'document_no'])->find($submissionId);

        return $submission?->document_no;
    }

    private function formFamily(string $submissionId): ?string
    {
        if ($submissionId === '') {
            return null;
        }

        /** @var EApprovalSubmission|null $submission */
        $submission = EApprovalSubmission::query()->with('form:id,metadata_json')->find($submissionId);
        if ($submission === null || $submission->form === null) {
            return null;
        }

        $metadata = is_array($submission->form->metadata_json) ? $submission->form->metadata_json : [];
        $family = $metadata['form_family'] ?? null;

        return is_string($family) && trim($family) !== '' ? trim($family) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodeRemarks(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
