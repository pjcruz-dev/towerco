<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraGraphAppService;
use Illuminate\Support\Str;

final class EApprovalManagerApproverResolver
{
    public function __construct(
        private readonly EntraGraphAppService $graph,
        private readonly EApprovalSettingsService $settings,
    ) {}

    /**
     * Resolve TowerOS user id for the requestor's Entra manager.
     */
    public function resolveForSubmission(EApprovalSubmission $submission): ?string
    {
        $submission->loadMissing('requestor');
        $requestor = $submission->requestor;
        if ($requestor === null || trim((string) $requestor->email) === '') {
            return null;
        }

        return $this->resolveForEmail((string) $requestor->email);
    }

    public function resolveForEmail(string $requestorEmail): ?string
    {
        try {
            $managerEmail = $this->graph->getManagerEmailForUser($requestorEmail);
        } catch (\Throwable) {
            return null;
        }

        if ($managerEmail === null) {
            return null;
        }

        $existing = TenantUser::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($managerEmail)])
            ->where('is_active', true)
            ->first();

        if ($existing !== null) {
            return (string) $existing->id;
        }

        if (! $this->settings->provisionManagerUsers()) {
            return null;
        }

        return $this->provisionApprover($managerEmail);
    }

    private function provisionApprover(string $email): string
    {
        $localPart = explode('@', $email)[0] ?? 'manager';
        $name = str_replace(['.', '_'], ' ', $localPart);
        $name = ucwords($name);

        /** @var TenantUser $user */
        $user = TenantUser::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => strtolower($email),
            'password' => Str::random(32),
            'is_active' => true,
        ]);

        if ($user->getRoleNames()->isEmpty()) {
            try {
                $user->assignRole('e_approval_approver');
            } catch (\Throwable) {
                // Role may not exist on all tenants; user record is still usable for assignment.
            }
        }

        return (string) $user->id;
    }
}
