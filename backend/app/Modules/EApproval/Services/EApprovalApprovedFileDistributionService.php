<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalApprovedFilesNotification;
use App\Modules\EApproval\Support\EApprovalFormMailConfig;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\SafeMailNotificationSender;
use Illuminate\Support\Facades\Log;

final class EApprovalApprovedFileDistributionService
{
    public function send(EApprovalSubmission $submission): void
    {
        $submission->loadMissing('form');
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (! EApprovalFormMailConfig::eventEnabled($metadata, 'approved_files')) {
            return;
        }

        $choice = $this->choiceOnSubmission($submission);
        if ($choice === null) {
            return;
        }

        $recipients = $choice['audience'] === 'everyone'
            ? $this->everyone()
            : $this->selectedPeople($choice['ids']);

        if ($recipients === []) {
            Log::warning('e_approval.approved_files_no_recipients', [
                'submission_id' => (string) $submission->id,
                'audience' => $choice['audience'],
            ]);

            return;
        }

        $notification = new EApprovalApprovedFilesNotification(
            (string) $submission->id,
            tenant()?->getTenantKey(),
        );

        SafeMailNotificationSender::sendAfterResponse($recipients, $notification);

        Log::info('e_approval.approved_files_sent', [
            'submission_id' => (string) $submission->id,
            'audience' => $choice['audience'],
            'recipients' => count($recipients),
        ]);
    }

    /**
     * @return array{audience: string, ids: list<string>}|null
     */
    private function choiceOnSubmission(EApprovalSubmission $submission): ?array
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

        if (! is_array($decoded)) {
            return null;
        }

        $audience = $decoded['approved_file_audience'] ?? null;
        if (! in_array($audience, ['everyone', 'specific_person', 'selected_users'], true)) {
            return null;
        }

        $ids = [];
        foreach (is_array($decoded['approved_file_recipient_ids'] ?? null) ? $decoded['approved_file_recipient_ids'] : [] as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return [
            'audience' => $audience,
            'ids' => array_values(array_unique($ids)),
        ];
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
     * @param  list<string>  $ids
     * @return list<TenantUser>
     */
    private function selectedPeople(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return TenantUser::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get()
            ->all();
    }
}
