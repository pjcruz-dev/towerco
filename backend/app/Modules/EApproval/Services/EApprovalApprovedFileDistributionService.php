<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalApprovedFilesNotification;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalFormWorkflowOptions;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use Illuminate\Support\Facades\Log;

final class EApprovalApprovedFileDistributionService
{
    public function send(EApprovalSubmission $submission): void
    {
        $submission->loadMissing('form');
        if (! EApprovalFormWorkflowOptions::sendApprovedFiles(
            is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : null,
        )) {
            return;
        }

        $audience = $this->audienceChosenOnSubmission($submission);
        if ($audience === null) {
            return;
        }

        $recipients = $audience === 'everyone'
            ? $this->everyone()
            : $this->departmentTeams($submission);

        if ($recipients === []) {
            return;
        }

        $notification = new EApprovalApprovedFilesNotification(
            (string) $submission->id,
            tenant()?->getTenantKey(),
        );

        SafeMailNotificationSender::sendAfterResponse($recipients, $notification);

        Log::info('e_approval.approved_files_sent', [
            'submission_id' => (string) $submission->id,
            'audience' => $audience,
            'recipients' => count($recipients),
        ]);
    }

    private function audienceChosenOnSubmission(EApprovalSubmission $submission): ?string
    {
        $raw = $submission->workflow_snapshot_json;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $audience = is_array($decoded) ? ($decoded['approved_file_audience'] ?? null) : null;

        return $audience === 'everyone' || $audience === 'department_teams' ? $audience : null;
    }

    /**
     * @return list<TenantUser>
     */
    private function everyone(): array
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->all();
    }

    /**
     * Step 1 reviewers and approvers, plus everyone under them on the organization chart.
     *
     * @return list<TenantUser>
     */
    private function departmentTeams(EApprovalSubmission $submission): array
    {
        $cycle = max(1, (int) ($submission->approval_cycle ?: 1));
        $headIds = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where(function ($query) use ($cycle): void {
                $query->where('approval_cycle', $cycle)->orWhereNull('approval_cycle');
            })
            ->whereNotIn('status', [
                EApprovalApprovalStatus::SUPERSEDED,
                EApprovalApprovalStatus::INVALIDATED,
                EApprovalApprovalStatus::CANCELLED,
            ])
            ->whereHas('step', static fn ($query) => $query->where('step_order', 1))
            ->pluck('approver_id')
            ->map(static fn ($id): string => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();

        $ids = [];
        $queue = $headIds;
        while ($queue !== []) {
            $id = array_shift($queue);
            if ($id === null || isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            $children = TenantUser::query()
                ->where('manager_id', $id)
                ->where('is_active', true)
                ->pluck('id');
            foreach ($children as $child) {
                $queue[] = (string) $child;
            }
        }

        if ($ids === []) {
            return [];
        }

        return TenantUser::query()
            ->whereIn('id', array_keys($ids))
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->all();
    }
}
