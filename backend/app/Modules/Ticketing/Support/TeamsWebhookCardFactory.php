<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

use App\Modules\Notifications\Support\TeamsWebhookCardFactory as SharedTeamsWebhookCardFactory;

/**
 * @deprecated Use App\Modules\Notifications\Support\TeamsWebhookCardFactory
 */
final class TeamsWebhookCardFactory
{
    /**
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
        return SharedTeamsWebhookCardFactory::build(
            $title,
            $bodyText,
            $facts,
            $accentColor,
            $actionUrl,
            $actionLabel,
        );
    }
}
