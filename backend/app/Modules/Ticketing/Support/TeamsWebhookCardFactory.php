<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

final class TeamsWebhookCardFactory
{
    /**
     * Build a Teams-compatible message envelope carrying an Adaptive Card.
     *
     * This targets Power Automate "Workflows" incoming webhooks (the supported
     * successor to the retired Office 365 "MessageCard" connectors). The
     * `type: message` + `attachments` envelope is Microsoft's documented shape
     * for posting an Adaptive Card through a Workflows webhook URL.
     *
     * @param  list<array{title: string, value: string}>  $facts
     * @return array<string, mixed>
     */
    public static function build(
        string $title,
        ?string $bodyText = null,
        array $facts = [],
        ?string $accentColor = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): array {
        $body = [
            [
                'type' => 'TextBlock',
                'size' => 'Large',
                'weight' => 'Bolder',
                'text' => $title,
                'wrap' => true,
                'color' => $accentColor ?? 'Default',
            ],
        ];

        if ($bodyText !== null && $bodyText !== '') {
            $body[] = [
                'type' => 'TextBlock',
                'text' => $bodyText,
                'wrap' => true,
            ];
        }

        if ($facts !== []) {
            $body[] = [
                'type' => 'FactSet',
                'facts' => $facts,
            ];
        }

        $content = [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.4',
            'body' => $body,
        ];

        if ($actionUrl !== null && $actionUrl !== '') {
            $content['actions'] = [
                [
                    'type' => 'Action.OpenUrl',
                    'title' => $actionLabel ?? $actionUrl,
                    'url' => $actionUrl,
                ],
            ];
        }

        return [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => $content,
                ],
            ],
        ];
    }
}
