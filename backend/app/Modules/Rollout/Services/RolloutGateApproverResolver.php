<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Support\Collection;

final class RolloutGateApproverResolver
{
    public function __construct(
        private readonly RolloutGateApprovalDelegationService $delegations,
    ) {}

    /**
     * @return list<TenantUser>
     */
    public function usersForRole(RolloutProgram $program, string $roleKey): array
    {
        /** @var Collection<int, TenantUser> $users */
        $users = collect();

        $ownerId = match ($roleKey) {
            'saq' => $program->saq_owner_id,
            'pmo' => $program->pmo_owner_id,
            'cme', 'cme_power' => $program->cme_pm_id,
            default => null,
        };

        if ($ownerId !== null) {
            $owner = TenantUser::query()->find($ownerId);
            if ($owner !== null) {
                $users->push($owner);
            }
        }

        $permissionUsers = match ($roleKey) {
            'saq', 'saq_engineering' => TenantUser::permission('project_one:saq:manage')->get(),
            'cme', 'cme_power' => TenantUser::permission('project_one:cme:manage')->get(),
            'engineering', 'pmo' => TenantUser::permission('project_one:rollout:manage')->get(),
            'tenant_admin' => TenantUser::role('tenant_admin')->get(),
            'mno' => TenantUser::role('manager')->get(),
            default => TenantUser::permission('project_one:rollout:manage')->get(),
        };

        foreach ($permissionUsers as $user) {
            if (! $users->contains('id', $user->id)) {
                $users->push($user);
            }
        }

        return $users->values()->all();
    }

    public function canActOnStep(TenantUser $user, RolloutProgram $program, string $roleKey): bool
    {
        if ($user->can('project_one:rollout:gate:approve')) {
            return true;
        }

        if ($this->userMatchesApproverRole($user, $roleKey)) {
            return true;
        }

        foreach ($this->usersForRole($program, $roleKey) as $approver) {
            if ($approver->id === $user->id) {
                return true;
            }

            if ($this->delegations->isActiveDelegateFor($user, $approver, $roleKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * When the viewer may act only via delegation, returns the delegator they represent.
     *
     * @return array{id: string, name: string}|null
     */
    public function actingForDelegator(TenantUser $user, RolloutProgram $program, string $roleKey): ?array
    {
        if (! $this->canActOnStep($user, $program, $roleKey)) {
            return null;
        }

        if ($user->can('project_one:rollout:gate:approve') || $this->userMatchesApproverRole($user, $roleKey)) {
            return null;
        }

        foreach ($this->usersForRole($program, $roleKey) as $approver) {
            if ($approver->id === $user->id) {
                return null;
            }
        }

        foreach ($this->usersForRole($program, $roleKey) as $approver) {
            if ($this->delegations->isActiveDelegateFor($user, $approver, $roleKey)) {
                return [
                    'id' => $approver->id,
                    'name' => $approver->name,
                ];
            }
        }

        return null;
    }

    /**
     * Direct permission/role match for the current chain step (keeps inbox in sync with Timeline can_act).
     */
    private function userMatchesApproverRole(TenantUser $user, string $roleKey): bool
    {
        return match ($roleKey) {
            'saq', 'saq_engineering' => $user->can('project_one:saq:manage'),
            'cme', 'cme_power' => $user->can('project_one:cme:manage'),
            'engineering', 'pmo' => $user->can('project_one:rollout:manage'),
            'tenant_admin' => $user->hasRole('tenant_admin'),
            'mno' => $user->hasRole('manager') || $user->can('project_one:rollout:manage'),
            default => $user->can('project_one:rollout:manage'),
        };
    }
}
