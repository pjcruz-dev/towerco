<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

final class AssistantFeedbackRating
{
    public const UP = 'up';

    public const DOWN = 'down';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [self::UP, self::DOWN];
    }
}
