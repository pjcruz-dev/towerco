<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

final class TenantSubscriptionSuspendedException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct(
            $message,
            'subscription_suspended',
            Response::HTTP_PAYMENT_REQUIRED,
        );
    }
}
