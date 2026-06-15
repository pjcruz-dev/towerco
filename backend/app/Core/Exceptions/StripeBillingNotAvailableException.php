<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

final class StripeBillingNotAvailableException extends DomainException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? __('Online billing is not enabled. Contact TowerOS support.'),
            'stripe_billing_unavailable',
            Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
