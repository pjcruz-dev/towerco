<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Notifications\Support\TeamsWebhookCardFactory;
use App\Modules\Notifications\Support\TeamsWebhookHttpPoster;
use Illuminate\Validation\ValidationException;

final class EApprovalSettingsTestWebhookService
{
    public function __construct(
        private readonly EApprovalSettingsService $settings,
    ) {}

    /**
     * @return array{sent: bool, message: string}
     */
    public function send(): array
    {
        $url = $this->settings->teamsWebhookUrl();
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'teams_webhook_url' => [__('Configure a valid Teams or webhook URL before testing.')],
            ]);
        }

        $timeout = (int) config('e_approval.teams.http_timeout_seconds', 10);

        TeamsWebhookHttpPoster::postOrThrow(
            $url,
            TeamsWebhookCardFactory::build(
                title: __('TowerOS E-Approval test'),
                bodyText: __('This is a test message from the E-Approval module webhook integration.'),
            ),
            $timeout,
        );

        return [
            'sent' => true,
            'message' => __('Test webhook sent successfully.'),
        ];
    }
}
