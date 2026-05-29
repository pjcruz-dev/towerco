<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class DomainException extends RuntimeException
{
    public function __construct(
        string $message = '',
        private readonly string $errorCode = 'domain_error',
        private readonly int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
