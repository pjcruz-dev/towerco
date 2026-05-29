<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class EntityNotFoundException extends DomainException
{
    public function __construct(string $entityName, ?string $id = null)
    {
        $message = $id !== null
            ? sprintf('%s with identifier %s was not found.', $entityName, $id)
            : sprintf('%s was not found.', $entityName);

        parent::__construct(
            message: $message,
            errorCode: 'entity_not_found',
            statusCode: Response::HTTP_NOT_FOUND,
        );
    }
}
