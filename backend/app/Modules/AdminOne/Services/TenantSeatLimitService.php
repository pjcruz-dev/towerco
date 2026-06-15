<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Models\Tenant;
use App\Modules\Billing\Services\TenantPlanEntitlementsService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

final class TenantSeatLimitService
{
    public function __construct(
        private readonly TenantPlanEntitlementsService $entitlements,
    ) {}

    public function activeSeatCount(): int
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->where(fn ($query) => $this->scopePaidSeatUsers($query))
            ->count();
    }

    public function activeViewerCount(): int
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($roles): void {
                $roles->where('name', 'viewer');
            })
            ->whereDoesntHave('roles', function ($roles): void {
                $roles->where('name', '!=', 'viewer');
            })
            ->count();
    }

    private function scopePaidSeatUsers($query): void
    {
        $query->where(function ($paid): void {
            $paid->whereDoesntHave('roles')
                ->orWhereHas('roles', function ($roles): void {
                    $roles->where('name', '!=', 'viewer');
                });
        });
    }

    /**
     * @param  list<string>  $roles
     */
    private function isViewerOnlyRoles(array $roles): bool
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $role): string => strtolower(trim((string) $role)),
            $roles,
        )));

        return $normalized !== [] && count($normalized) === 1 && $normalized[0] === 'viewer';
    }

    public function seatLimit(): int
    {
        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey === null) {
            return 25;
        }

        /** @var Tenant|null $central */
        $central = Tenant::query()->find((string) $tenantKey);

        if ($central instanceof Tenant) {
            return $this->entitlements->effectiveSeatLimit($central);
        }

        return 25;
    }

    public function seatsAvailable(): int
    {
        return max(0, $this->seatLimit() - $this->activeSeatCount());
    }

    /**
     * @param  list<string>  $roles
     */
    public function assertCanAddActiveUser(array $roles = []): void
    {
        if ($this->isViewerOnlyRoles($roles)) {
            return;
        }

        if ($this->activeSeatCount() >= $this->seatLimit()) {
            throw ValidationException::withMessages([
                'email' => [
                    __(
                        'Paid seat limit reached (:used / :limit). Deactivate a user, use a viewer role, or ask TowerOS to increase your seat limit.',
                        ['used' => $this->activeSeatCount(), 'limit' => $this->seatLimit()],
                    ),
                ],
            ]);
        }
    }
}
