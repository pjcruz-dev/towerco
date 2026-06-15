<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalDelegation;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class EApprovalDelegationService
{
    /**
     * @return list<EApprovalDelegation>
     */
    public function listForUser(TenantUser $user): array
    {
        return EApprovalDelegation::query()
            ->with(['delegator:id,name,email', 'delegate:id,name,email'])
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
    public function create(TenantUser $delegator, array $input): EApprovalDelegation
    {
        $delegateId = (string) ($input['delegate_id'] ?? '');

        if ($delegateId === '' || $delegateId === $delegator->id) {
            throw ValidationException::withMessages([
                'delegate_id' => [__('Select a different user as acting approver.')],
            ]);
        }

        if (TenantUser::query()->where('id', $delegateId)->doesntExist()) {
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

        /** @var EApprovalDelegation $delegation */
        $delegation = EApprovalDelegation::query()->create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegateId,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'notes' => isset($input['notes']) ? trim((string) $input['notes']) : null,
            'is_active' => true,
        ]);

        return $delegation->fresh(['delegator', 'delegate']) ?? $delegation;
    }

    public function revoke(EApprovalDelegation $delegation, TenantUser $actor): EApprovalDelegation
    {
        if ($delegation->delegator_id !== $actor->id && ! $actor->can('e_approval:settings:manage')) {
            throw ValidationException::withMessages([
                'delegation' => [__('You cannot revoke this delegation.')],
            ]);
        }

        $delegation->is_active = false;
        $delegation->save();

        return $delegation->fresh(['delegator', 'delegate']) ?? $delegation;
    }

    public function canActForApprover(TenantUser $actor, string $assignedApproverId): bool
    {
        if ((string) $actor->id === (string) $assignedApproverId) {
            return true;
        }

        $today = Carbon::today();

        return EApprovalDelegation::query()
            ->where('delegator_id', $assignedApproverId)
            ->where('delegate_id', $actor->id)
            ->where('is_active', true)
            ->whereDate('valid_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
            })
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function present(EApprovalDelegation $delegation): array
    {
        $delegation->loadMissing(['delegator:id,name,email', 'delegate:id,name,email']);

        return [
            'id' => (string) $delegation->id,
            'delegator' => $delegation->delegator ? [
                'id' => (string) $delegation->delegator->id,
                'name' => $delegation->delegator->name,
                'email' => $delegation->delegator->email,
            ] : null,
            'delegate' => $delegation->delegate ? [
                'id' => (string) $delegation->delegate->id,
                'name' => $delegation->delegate->name,
                'email' => $delegation->delegate->email,
            ] : null,
            'valid_from' => $delegation->valid_from?->toDateString(),
            'valid_until' => $delegation->valid_until?->toDateString(),
            'notes' => $delegation->notes,
            'is_active' => $delegation->is_active,
        ];
    }
}
