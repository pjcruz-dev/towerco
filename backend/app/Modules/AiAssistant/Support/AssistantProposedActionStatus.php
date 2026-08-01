<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

final class AssistantProposedActionStatus
{
    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';
}
