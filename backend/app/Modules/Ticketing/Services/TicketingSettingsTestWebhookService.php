<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Notifications\Support\TeamsWebhookCardFactory;
use App\Modules\Notifications\Support\TeamsWebhookHttpPoster;
use App\Modules\Notifications\Support\TeamsWebhookUrl;
use Illuminate\Validation\ValidationException;

final class TicketingSettingsTestWebhookService
{
    /**
     * @return array{sent: bool}
     */
    public function send(?string $overrideUrl = null): array
    {
        $stored = trim((string) app(TicketingSettingsService::class)->getString(TicketingSettingsService::TEAMS_WEBHOOK_URL, ''));
        $url = TeamsWebhookUrl::normalize($overrideUrl !== null && trim($overrideUrl) !== '' ? $overrideUrl : $stored);

        if (! TeamsWebhookUrl::isValid($url)) {
            throw ValidationException::withMessages([
                'teams_webhook_url' => [__('Paste a Teams Workflows webhook URL, click Save settings, then try again.')],
            ]);
        }

        TeamsWebhookHttpPoster::postOrThrow(
            $url,
            TeamsWebhookCardFactory::build(
                title: __('TowerOS Ticketing test'),
                bodyText: __('This is a test message from the Ticketing module webhook integration.'),
            ),
            10,
        );

        return ['sent' => true];
    }
}
