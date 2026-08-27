<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalSubmissionShareLink;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EApprovalSubmissionShareLinkService
{
    public function __construct(
        private readonly EApprovalSubmissionService $submissions,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalFormValueDisplayService $valueDisplay,
        private readonly TenantAppUrlResolver $urls,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForSubmission(EApprovalSubmission $submission): array
    {
        return EApprovalSubmissionShareLink::query()
            ->with('createdBy:id,name,email')
            ->where('submission_id', $submission->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (EApprovalSubmissionShareLink $link): array => $link->toAdminRow())
            ->values()
            ->all();
    }

    /**
     * @return array{link: array<string, mixed>, url: string, plain_token: string}
     */
    public function create(
        EApprovalSubmission $submission,
        TenantUser $actor,
        ?string $label = null,
        ?int $ttlDays = null,
    ): array {
        $this->assertApproved($submission);

        $defaultDays = max(1, (int) config('e_approval.submission_share.ttl_days', 14));
        $maxDays = max($defaultDays, (int) config('e_approval.submission_share.max_ttl_days', 90));
        $days = $ttlDays !== null ? max(1, min($ttlDays, $maxDays)) : $defaultDays;

        $plain = Str::random(48);
        $link = EApprovalSubmissionShareLink::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'created_by_user_id' => $actor->id,
            'token_hash' => hash('sha256', $plain),
            'label' => $label !== null && trim($label) !== '' ? trim(mb_substr($label, 0, 120)) : null,
            'expires_at' => now()->addDays($days),
        ]);

        $url = $this->publicUrl($plain);

        $this->audit->log(
            'submission_share_link_created',
            (string) $submission->id,
            __('Share link created (expires :date).', ['date' => $link->expires_at?->toDateString() ?? '']),
            $actor,
            metadata: [
                'share_link_id' => (string) $link->id,
                'expires_at' => $link->expires_at?->toIso8601String(),
            ],
            entityLabel: $submission->document_no,
        );

        $link->load('createdBy:id,name,email');

        return [
            'link' => $link->toAdminRow($url),
            'url' => $url,
            'plain_token' => $plain,
        ];
    }

    public function revoke(EApprovalSubmissionShareLink $link, TenantUser $actor): void
    {
        if ($link->revoked_at !== null) {
            return;
        }

        $link->revoked_at = now();
        $link->save();

        $this->audit->log(
            'submission_share_link_revoked',
            (string) $link->submission_id,
            __('Share link revoked.'),
            $actor,
            metadata: ['share_link_id' => (string) $link->id],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(string $plainToken): array
    {
        $link = $this->resolveActiveLink($plainToken);
        $submission = $link->submission;
        if ($submission === null) {
            throw ValidationException::withMessages([
                'token' => [__('This share link is invalid.')],
            ]);
        }

        $this->assertApproved($submission);
        $this->touchAccess($link);

        $submission->loadMissing(['form:id,name', 'requestor:id,name', 'values.field', 'attachments', 'approvals.approver']);

        return [
            'document_no' => $submission->document_no,
            'status' => $submission->status,
            'form_name' => $submission->form?->name,
            'submitted_at' => $submission->created_at?->toIso8601String(),
            'requestor_name' => $submission->requestor?->name,
            'values' => $this->valueDisplay->mapForApi($submission->values),
            'approvals' => $submission->approvals
                ->filter(static fn ($a) => in_array((string) $a->status, ['approved', 'rejected', 'returned'], true))
                ->map(static fn ($a) => [
                    'status' => $a->status,
                    'approver_name' => $a->approver?->name,
                    'remarks' => $a->remarks,
                    'decided_at' => $a->acted_at?->toIso8601String() ?? $a->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'attachments' => $submission->attachments->map(fn (EApprovalAttachment $a): array => [
                'id' => (string) $a->id,
                'field_name' => $a->field_name,
                'file_name' => $a->file_name,
                'download_url' => $this->urls->urlForCurrentTenant(
                    '/api/v1/public/e-approval/shared/'.$plainToken.'/attachments/'.$a->id,
                ),
            ])->values()->all(),
            'expires_at' => $link->expires_at?->toIso8601String(),
            'brand_label' => $this->urls->mailBrandLabel(),
        ];
    }

    public function streamAttachment(string $plainToken, string $attachmentId): StreamedResponse
    {
        $link = $this->resolveActiveLink($plainToken);
        $submission = $link->submission;
        if ($submission === null) {
            throw ValidationException::withMessages([
                'token' => [__('This share link is invalid.')],
            ]);
        }

        $this->assertApproved($submission);
        $this->touchAccess($link);

        /** @var EApprovalAttachment|null $attachment */
        $attachment = EApprovalAttachment::query()
            ->where('submission_id', $submission->id)
            ->where('id', $attachmentId)
            ->first();

        if ($attachment === null) {
            abort(404);
        }

        $diskName = (string) config('toweros.tenant_files.disk', 'local');
        $disk = Storage::disk($diskName);
        $path = (string) $attachment->file_path;

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->download($path, (string) $attachment->file_name);
    }

    public function assertCanManageShares(EApprovalSubmission $submission, TenantUser $actor): void
    {
        $canViewAll = $actor->can('e_approval:forms:manage');
        $this->submissions->assertCanView($submission, $actor, $canViewAll);

        $isRequestor = (string) $submission->requestor_id === (string) $actor->id;
        if (! $isRequestor && ! $canViewAll && ! $actor->can('e_approval:audit:view')) {
            throw ValidationException::withMessages([
                'submission' => [__('Only the requestor or an E-Approval administrator can create share links.')],
            ]);
        }
    }

    private function resolveActiveLink(string $plainToken): EApprovalSubmissionShareLink
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '' || strlen($plainToken) < 20) {
            throw ValidationException::withMessages([
                'token' => [__('This share link is invalid or has expired.')],
            ]);
        }

        /** @var EApprovalSubmissionShareLink|null $link */
        $link = EApprovalSubmissionShareLink::query()
            ->with('submission')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($link === null || ! $link->isActive()) {
            throw ValidationException::withMessages([
                'token' => [__('This share link is invalid or has expired.')],
            ]);
        }

        return $link;
    }

    private function touchAccess(EApprovalSubmissionShareLink $link): void
    {
        $link->forceFill([
            'last_accessed_at' => now(),
            'access_count' => ((int) $link->access_count) + 1,
        ])->save();
    }

    private function assertApproved(EApprovalSubmission $submission): void
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::APPROVED) {
            throw ValidationException::withMessages([
                'submission' => [__('Share links are only available for approved requests.')],
            ]);
        }
    }

    private function publicUrl(string $plainToken): string
    {
        return $this->urls->urlForCurrentTenant('/public/e-approval/shared/'.$plainToken);
    }
}
