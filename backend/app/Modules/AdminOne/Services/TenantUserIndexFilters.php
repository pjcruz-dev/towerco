<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

final class TenantUserIndexFilters
{
    public const NONE = '__none__';

    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $lastActive = null,
        public readonly ?string $mfa = null,
        public readonly ?string $role = null,
        public readonly ?string $department = null,
        public readonly ?string $managerId = null,
        public readonly ?string $license = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        $status = isset($validated['status']) ? (string) $validated['status'] : null;
        $lastActive = isset($validated['last_active']) ? (string) $validated['last_active'] : null;
        $mfa = isset($validated['mfa']) ? (string) $validated['mfa'] : null;
        $role = isset($validated['role']) ? trim((string) $validated['role']) : null;
        $department = isset($validated['department']) ? trim((string) $validated['department']) : null;
        $managerId = isset($validated['manager_id']) ? trim((string) $validated['manager_id']) : null;
        $license = isset($validated['license']) ? trim((string) $validated['license']) : null;

        return new self(
            status: $status === 'all' ? null : $status,
            lastActive: $lastActive === 'all' ? null : $lastActive,
            mfa: $mfa === 'all' ? null : $mfa,
            role: $role === '' || $role === 'all' ? null : $role,
            department: $department === '' || $department === 'all' ? null : $department,
            managerId: $managerId === '' || $managerId === 'all' ? null : $managerId,
            license: $license === '' || $license === 'all' ? null : $license,
        );
    }
}
