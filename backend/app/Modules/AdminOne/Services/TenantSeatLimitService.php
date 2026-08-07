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
        $current = tenant();
        if ($current instanceof Tenant) {
            return $this->entitlements->effectiveSeatLimit($current);
        }

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

    /**
     * @return array{
     *   seat_used: int,
     *   seat_limit: int,
     *   seats_available: int,
     *   viewer_seats_used: int,
     *   paid_seats_full: bool,
     *   active_users: int
     * }
     */
    public function usageSnapshot(): array
    {
        $used = $this->activeSeatCount();
        $limit = $this->seatLimit();

        return [
            'seat_used' => $used,
            'seat_limit' => $limit,
            'seats_available' => max(0, $limit - $used),
            'viewer_seats_used' => $this->activeViewerCount(),
            'paid_seats_full' => $used >= $limit,
            'active_users' => TenantUser::query()->where('is_active', true)->count(),
        ];
    }

    public function seatsAvailable(): int
    {
        return max(0, $this->seatLimit() - $this->activeSeatCount());
    }

    /**
     * @param  list<string>  $roles
     */
    public function rolesConsumePaidSeat(array $roles): bool
    {
        return ! $this->isViewerOnlyRoles($roles);
    }

    /**
     * Block promoting an active viewer-only (or unpaid) user into a paid seat when the limit is full.
     *
     * @param  list<string>  $nextRoles
     */
    public function assertCanTransitionToRoles(TenantUser $user, array $nextRoles): void
    {
        if (! $user->isActive()) {
            return;
        }

        $currentRoles = $user->getRoleNames()->all();
        $currentlyPaid = $this->rolesConsumePaidSeat($currentRoles);
        $nextPaid = $this->rolesConsumePaidSeat($nextRoles);

        if ($currentlyPaid || ! $nextPaid) {
            return;
        }

        if ($this->activeSeatCount() >= $this->seatLimit()) {
            throw ValidationException::withMessages([
                'roles' => [
                    __(
                        'Paid seat limit reached (:used / :limit). Deactivate a user, keep a viewer-only role, or ask TowerOS to increase your seat limit.',
                        ['used' => $this->activeSeatCount(), 'limit' => $this->seatLimit()],
                    ),
                ],
            ]);
        }
    }

    /**
     * @param  list<string>  $roles
     */
    public function assertCanAddActiveUser(array $roles = []): void
    {
        // Viewer-only accounts stay free while capacity remains.
        if ($this->isViewerOnlyRoles($roles) && $this->activeSeatCount() < $this->seatLimit()) {
            return;
        }

        // At or over paid capacity: block every new active account (including viewers)
        // until seats are raised or paid users are deactivated.
        if ($this->activeSeatCount() >= $this->seatLimit()) {
            throw ValidationException::withMessages([
                'email' => [
                    __(
                        'Paid seat limit reached (:used / :limit). Deactivate a user or ask TowerOS to increase your seat limit before adding accounts.',
                        ['used' => $this->activeSeatCount(), 'limit' => $this->seatLimit()],
                    ),
                ],
            ]);
        }
    }
}
