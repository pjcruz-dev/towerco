<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Notifications\Support\TeamsWebhookCardFactory;
use App\Modules\Notifications\Support\TeamsWebhookHttpPoster;
use Illuminate\Validation\ValidationException;

final class TicketingSettingsTestWebhookService
{
    /**
     * @return array{sent: bool}
     */
    public function send(): array
    {
        $url = trim((string) app(TicketingSettingsService::class)->getString(TicketingSettingsService::TEAMS_WEBHOOK_URL, ''));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'teams_webhook_url' => [__('Configure a valid Teams or webhook URL before testing.')],
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
