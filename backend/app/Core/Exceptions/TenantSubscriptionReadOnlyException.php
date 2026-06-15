<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

final class TenantSubscriptionReadOnlyException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct(
            $message,
            'subscription_read_only',
            Response::HTTP_FORBIDDEN,
        );
    }
}
