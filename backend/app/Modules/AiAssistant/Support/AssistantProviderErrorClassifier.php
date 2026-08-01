<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use Throwable;

/**
 * Maps provider failures to stable error codes and user-facing chat notices.
 */
final class AssistantProviderErrorClassifier
{
    public const OPENAI_QUOTA_EXCEEDED = 'openai_quota_exceeded';

    public const CURSOR_RATE_LIMIT_EXCEEDED = 'cursor_rate_limit_exceeded';

    public static function classify(Throwable $e): ?string
    {
        if ($e instanceof AssistantProviderQuotaExceededException) {
            return match ($e->provider) {
                'openai', 'chatgpt' => self::OPENAI_QUOTA_EXCEEDED,
                'cursor', 'cursor_ai' => self::CURSOR_RATE_LIMIT_EXCEEDED,
                default => null,
            };
        }

        $message = mb_strtolower($e->getMessage());

        if (
            str_contains($message, 'insufficient_quota')
            || str_contains($message, 'exceeded your current quota')
            || (str_contains($message, 'http 429') && str_contains($message, 'openai'))
        ) {
            return self::OPENAI_QUOTA_EXCEEDED;
        }

        if (str_contains($message, 'http 429') && str_contains($message, 'cursor')) {
            return self::CURSOR_RATE_LIMIT_EXCEEDED;
        }

        return null;
    }

    /**
     * @return array{provider: string, title: string, message: string, admin_action: string}|null
     */
    public static function noticeFor(?string $errorCode): ?array
    {
        return match ($errorCode) {
            self::OPENAI_QUOTA_EXCEEDED => [
                'provider' => 'openai',
                'title' => 'OpenAI quota exceeded',
                'message' => 'Ask TowerOS cannot answer right now because the configured OpenAI API key has exceeded its quota or billing limit.',
                'admin_action' => 'Ask your workspace administrator to restore OpenAI billing or credits, then try again.',
            ],
            self::CURSOR_RATE_LIMIT_EXCEEDED => [
                'provider' => 'cursor',
                'title' => 'Cursor API limit reached',
                'message' => 'Ask TowerOS cannot answer right now because the configured Cursor API key hit a rate or usage limit.',
                'admin_action' => 'Ask your workspace administrator to check Cursor billing/limits or retry in a few minutes.',
            ],
            default => null,
        };
    }

    public static function chatAnswerFor(?string $errorCode): ?string
    {
        $notice = self::noticeFor($errorCode);
        if ($notice === null) {
            return null;
        }

        return sprintf(
            '%s %s %s',
            $notice['title'].'.',
            $notice['message'],
            $notice['admin_action'],
        );
    }

    public static function isQuotaExceeded(Throwable $e): bool
    {
        return in_array(self::classify($e), [self::OPENAI_QUOTA_EXCEEDED, self::CURSOR_RATE_LIMIT_EXCEEDED], true);
    }

    public static function openAiBodyIndicatesQuotaExceeded(string $body): bool
    {
        $lower = mb_strtolower($body);

        return str_contains($lower, 'insufficient_quota')
            || str_contains($lower, 'exceeded your current quota');
    }
}
