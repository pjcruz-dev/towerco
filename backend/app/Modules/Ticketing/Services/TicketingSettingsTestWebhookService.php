<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use Illuminate\Support\Facades\Http;
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

        Http::timeout(10)->post($url, [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'summary' => __('Ticketing webhook test'),
            'themeColor' => '2563EB',
            'title' => __('TowerOS Ticketing test'),
            'text' => __('This is a test message from the Ticketing module webhook integration.'),
        ])->throw();

        return ['sent' => true];
    }
}
