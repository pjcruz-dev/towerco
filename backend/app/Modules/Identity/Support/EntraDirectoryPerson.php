<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

final class EntraDirectoryPerson
{
    /**
     * @param  list<string>  $assignedSkuIds
     */
    public function __construct(
        public readonly string $entraId,
        public readonly string $email,
        public readonly string $displayName,
        public readonly ?string $jobTitle = null,
        public readonly array $assignedSkuIds = [],
        public readonly ?string $department = null,
        public readonly ?string $givenName = null,
        public readonly ?string $surname = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromGraph(array $payload): ?self
    {
        $id = trim((string) ($payload['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        $mail = strtolower(trim((string) ($payload['mail'] ?? '')));
        $upn = strtolower(trim((string) ($payload['userPrincipalName'] ?? '')));
        $email = $mail !== '' ? $mail : $upn;
        if ($email === '') {
            return null;
        }

        $name = trim((string) ($payload['displayName'] ?? ''));
        $title = trim((string) ($payload['jobTitle'] ?? ''));
        $department = trim((string) ($payload['department'] ?? ''));
        $given = trim((string) ($payload['givenName'] ?? ''));
        $surname = trim((string) ($payload['surname'] ?? ''));

        return new self(
            entraId: $id,
            email: $email,
            displayName: $name !== '' ? $name : $email,
            givenName: $given !== '' ? $given : null,
            surname: $surname !== '' ? $surname : null,
            jobTitle: $title !== '' ? $title : null,
            assignedSkuIds: EntraLicenseCatalog::skuIdsFromGraph($payload),
            department: $department !== '' ? $department : null,
        );
    }

    public function isLicensed(): bool
    {
        return $this->assignedSkuIds !== [];
    }
}
