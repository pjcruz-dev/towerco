<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalNotificationCategory;
use App\Modules\Notifications\Support\TeamsWebhookCardFactory;
use App\Modules\Notifications\Support\TeamsWebhookHttpPoster;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;

final class EApprovalWebhookDispatcher
{
    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    public function dispatchExternalSubmittedIfEnabled(EApprovalSubmission $submission): void
    {
        if (! $submission->isExternalSubmission()) {
            return;
        }

        if (! $this->settings->notifyTeamsOnExternalSubmit()) {
            return;
        }

        $url = $this->settings->teamsWebhookUrl();
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $submission->loadMissing(['form:id,name', 'requestor:id,name']);
        $resolver = app(TenantAppUrlResolver::class);
        $path = EApprovalNotificationCategory::hrefFor(
            'external_received',
            (string) $submission->id,
        );
        $openUrl = $resolver->urlForCurrentTenant($path);
        $submitter = $submission->mailSubmitterContext();

        $facts = [
            ['title' => __('Document'), 'value' => (string) $submission->document_no],
            ['title' => __('Form'), 'value' => (string) ($submission->form?->name ?? __('Form'))],
            ['title' => __('Submitted by'), 'value' => $submitter['name']],
        ];

        if ($submitter['email'] !== null) {
            $facts[] = ['title' => __('Contact'), 'value' => $submitter['email']];
        }

        if ($submitter['internal_sponsor'] !== null) {
            $facts[] = ['title' => __('Sponsor'), 'value' => $submitter['internal_sponsor']];
        }

        $payload = TeamsWebhookCardFactory::build(
            title: __('External E-Approval submission'),
            bodyText: __('A public form was submitted and is awaiting review.'),
            facts: $facts,
            accentColor: 'Accent',
            actionUrl: $openUrl,
            actionLabel: __('Open submission'),
        );

        $timeout = (int) config('e_approval.teams.http_timeout_seconds', 10);

        TeamsWebhookHttpPoster::postOrLog(
            $url,
            $payload,
            $timeout,
            'e_approval.webhook_failed',
            [
                'event' => 'external_submit',
                'submission_id' => $submission->id,
            ],
        );
    }
}
