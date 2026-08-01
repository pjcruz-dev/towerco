<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

/**
 * Ask-response lifecycle statuses for the tenant assistant API.
 */
final class AssistantAskStatus
{
    public const PLACEHOLDER = 'placeholder';

    public const COMPLETED = 'completed';

    public const INSUFFICIENT_CONTEXT = 'insufficient_context';

    public const PROVIDER_QUOTA_EXCEEDED = 'provider_quota_exceeded';

    public const FAILED = 'failed';
}
