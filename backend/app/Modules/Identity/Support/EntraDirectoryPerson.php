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

        return new self(
            entraId: $id,
            email: $email,
            displayName: $name !== '' ? $name : $email,
            jobTitle: $title !== '' ? $title : null,
            assignedSkuIds: EntraLicenseCatalog::skuIdsFromGraph($payload),
        );
    }

    public function isLicensed(): bool
    {
        return $this->assignedSkuIds !== [];
    }
}
