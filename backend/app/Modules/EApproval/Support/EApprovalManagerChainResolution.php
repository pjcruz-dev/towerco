<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * People on a requestor's Entra reporting line, nearest manager first.
 */
final class EApprovalManagerChainResolution
{
    /**
     * @param  list<array{user_id: string, role_label: string}>  $people
     */
    public function __construct(
        public readonly array $people,
        public readonly ?string $failure,
    ) {}

    public static function empty(): self
    {
        return new self([], null);
    }

    /**
     * @param  list<array{user_id: string, role_label: string}>  $people
     */
    public static function failed(string $failure, array $people = []): self
    {
        return new self($people, $failure);
    }
}
