<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\EntraGraphAppService;
use App\Modules\Identity\Support\EntraDirectoryPerson;
use Illuminate\Support\Facades\Schema;
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
            $lookup = $this->graph->lookupManagerForEmail($requestorEmail);
        } catch (\Throwable) {
            return null;
        }

        if (! $lookup->ok || $lookup->manager === null || $lookup->manager->email === '') {
            return null;
        }

        $existing = $this->findApproverUser($lookup->manager);
        if ($existing !== null) {
            return (string) $existing->id;
        }

        if (! $this->settings->provisionManagerUsers()) {
            return null;
        }

        return $this->provisionApprover($lookup->manager);
    }

    private function findApproverUser(EntraDirectoryPerson $manager): ?TenantUser
    {
        if ($manager->entraId !== '' && Schema::connection('tenant')->hasColumn('users', 'entra_id')) {
            $byEntra = TenantUser::query()
                ->where('entra_id', $manager->entraId)
                ->where('is_active', true)
                ->first();
            if ($byEntra instanceof TenantUser) {
                return $byEntra;
            }
        }

        $byEmail = TenantUser::query()
            ->whereRaw('LOWER(email) = ?', [TenantUser::normalizeEmail($manager->email)])
            ->where('is_active', true)
            ->first();
        if ($byEmail instanceof TenantUser) {
            return $byEmail;
        }

        $local = strstr($manager->email, '@', true);
        if (! is_string($local) || $local === '') {
            return null;
        }

        $matches = TenantUser::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(email) LIKE ?', [strtolower($local).'@%'])
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function provisionApprover(EntraDirectoryPerson $manager): string
    {
        $payload = [
            'id' => (string) Str::uuid(),
            'name' => $manager->displayName !== '' ? $manager->displayName : $manager->email,
            'email' => TenantUser::normalizeEmail($manager->email),
            'password' => Str::random(32),
            'is_active' => true,
        ];
        if (Schema::connection('tenant')->hasColumn('users', 'entra_id')) {
            $payload['entra_id'] = $manager->entraId !== '' ? $manager->entraId : null;
            $payload['job_title'] = $manager->jobTitle;
        }

        /** @var TenantUser $user */
        $user = TenantUser::query()->create($payload);

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
