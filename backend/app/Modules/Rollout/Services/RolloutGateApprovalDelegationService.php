<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutGateApprovalDelegation;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class RolloutGateApprovalDelegationService
{
    /**
     * @return list<RolloutGateApprovalDelegation>
     */
    public function listForUser(TenantUser $user): array
    {
        return RolloutGateApprovalDelegation::query()
            ->with(['delegator', 'delegate'])
            ->where(function ($query) use ($user): void {
                $query->where('delegator_id', $user->id)
                    ->orWhere('delegate_id', $user->id);
            })
            ->where('is_active', true)
            ->orderByDesc('valid_from')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(TenantUser $delegator, array $input): RolloutGateApprovalDelegation
    {
        $delegateId = (string) ($input['delegate_id'] ?? '');

        if ($delegateId === '' || $delegateId === $delegator->id) {
            throw ValidationException::withMessages([
                'delegate_id' => [__('Select a different user as acting approver.')],
            ]);
        }

        /** @var TenantUser|null $delegate */
        $delegate = TenantUser::query()->find($delegateId);
        if ($delegate === null) {
            throw ValidationException::withMessages([
                'delegate_id' => [__('Delegate user not found.')],
            ]);
        }

        $validFrom = isset($input['valid_from'])
            ? Carbon::parse((string) $input['valid_from'])->startOfDay()
            : Carbon::today();
        $validUntil = isset($input['valid_until']) && $input['valid_until'] !== null && $input['valid_until'] !== ''
            ? Carbon::parse((string) $input['valid_until'])->startOfDay()
            : null;

        if ($validUntil !== null && $validUntil->lt($validFrom)) {
            throw ValidationException::withMessages([
                'valid_until' => [__('End date must be on or after start date.')],
            ]);
        }

        /** @var RolloutGateApprovalDelegation $delegation */
        $delegation = RolloutGateApprovalDelegation::query()->create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'role_key' => isset($input['role_key']) && $input['role_key'] !== ''
                ? (string) $input['role_key']
                : null,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'notes' => isset($input['notes']) ? trim((string) $input['notes']) : null,
            'is_active' => true,
        ]);

        return $delegation->fresh(['delegator', 'delegate']) ?? $delegation;
    }

    public function revoke(RolloutGateApprovalDelegation $delegation, TenantUser $actor): RolloutGateApprovalDelegation
    {
        if ($delegation->delegator_id !== $actor->id && ! $actor->can('project_one:playbook:configure')) {
            throw ValidationException::withMessages([
                'delegation' => [__('You cannot revoke this delegation.')],
            ]);
        }

        $delegation->is_active = false;
        $delegation->save();

        return $delegation->fresh(['delegator', 'delegate']) ?? $delegation;
    }

    public function isActiveDelegateFor(TenantUser $delegate, TenantUser $delegator, string $roleKey): bool
    {
        $today = Carbon::today();

        return RolloutGateApprovalDelegation::query()
            ->where('delegator_id', $delegator->id)
            ->where('delegate_id', $delegate->id)
            ->where('is_active', true)
            ->where(function ($query) use ($roleKey): void {
                $query->whereNull('role_key')->orWhere('role_key', $roleKey);
            })
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(RolloutGateApprovalDelegation $delegation): array
    {
        return [
            'id' => $delegation->id,
            'role_key' => $delegation->role_key,
            'valid_from' => $delegation->valid_from?->toDateString(),
            'valid_until' => $delegation->valid_until?->toDateString(),
            'notes' => $delegation->notes,
            'is_active' => $delegation->is_active,
            'delegator' => $delegation->delegator ? [
                'id' => $delegation->delegator->id,
                'name' => $delegation->delegator->name,
            ] : null,
            'delegate' => $delegation->delegate ? [
                'id' => $delegation->delegate->id,
                'name' => $delegation->delegate->name,
            ] : null,
        ];
    }
}
