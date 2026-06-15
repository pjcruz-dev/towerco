<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\Tenant;
use App\Modules\Tenancy\Support\TenantOperatorAccessMode;
use Illuminate\Support\Carbon;

final class TenantSubscriptionLifecycleService
{
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    /**
     * @return list<string>
     */
    public function validStatuses(): array
    {
        return [
            self::STATUS_TRIAL,
            self::STATUS_ACTIVE,
            self::STATUS_PAST_DUE,
            self::STATUS_CANCELED,
        ];
    }

    public function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return in_array($normalized, $this->validStatuses(), true)
            ? $normalized
            : self::STATUS_ACTIVE;
    }

    /**
     * @return array{
     *   status: string,
     *   access_mode: string,
     *   access_allowed: bool,
     *   trial_ends_at: string|null,
     *   past_due_grace_ends_at: string|null,
     *   canceled_at: string|null,
     *   subscription_locked_at: string|null,
     *   days_until_trial_end: int|null,
     *   days_until_grace_end: int|null,
     *   message: string|null
     * }
     */
    public function snapshot(Tenant $tenant): array
    {
        $status = $this->normalizeStatus($tenant->subscription_status);
        $now = now();

        if ($status === self::STATUS_TRIAL && $tenant->trial_ends_at !== null && $now->greaterThan($tenant->trial_ends_at)) {
            $status = $this->normalizeStatus((string) config('billing.subscription.on_trial_expire', self::STATUS_ACTIVE));
        }

        $operatorMode = TenantOperatorAccessMode::normalize($tenant->operator_access_mode);

        $locked = $tenant->subscription_locked_at !== null
            || $status === self::STATUS_CANCELED;

        if ($status === self::STATUS_PAST_DUE) {
            $graceEnd = $tenant->past_due_grace_ends_at;
            if ($graceEnd !== null && $now->greaterThan($graceEnd)) {
                $locked = true;
            }
        }

        if ($operatorMode === TenantOperatorAccessMode::BLOCKED) {
            $locked = true;
        }

        $accessMode = match (true) {
            $locked => 'blocked',
            $operatorMode === TenantOperatorAccessMode::READ_ONLY => 'read_only',
            $status === self::STATUS_PAST_DUE => 'grace',
            default => 'full',
        };

        $message = match ($accessMode) {
            'blocked' => $operatorMode === TenantOperatorAccessMode::BLOCKED
                ? __('This organization has been suspended by TowerOS operations. Contact support to restore access.')
                : ($status === self::STATUS_CANCELED
                    ? __('This organization subscription has been canceled. Contact TowerOS to restore access.')
                    : __('Subscription access is suspended after the payment grace period. Contact TowerOS billing.')),
            'read_only' => __('This organization is in read-only mode. You can view data but cannot make changes.'),
            'grace' => __('Subscription is past due. Update billing before :date to avoid suspension.', [
                'date' => $tenant->past_due_grace_ends_at?->toFormattedDateString() ?? 'the grace deadline',
            ]),
            default => null,
        };

        return [
            'status' => $status,
            'access_mode' => $accessMode,
            'operator_access_mode' => $operatorMode,
            'access_allowed' => $accessMode !== 'blocked',
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'past_due_grace_ends_at' => $tenant->past_due_grace_ends_at?->toIso8601String(),
            'canceled_at' => $tenant->canceled_at?->toIso8601String(),
            'subscription_locked_at' => $tenant->subscription_locked_at?->toIso8601String(),
            'days_until_trial_end' => $this->daysUntil($tenant->trial_ends_at, $now),
            'days_until_grace_end' => $this->daysUntil($tenant->past_due_grace_ends_at, $now),
            'message' => $message,
        ];
    }

    public function accessAllowed(Tenant $tenant): bool
    {
        return $this->snapshot($tenant)['access_allowed'];
    }

    /**
     * Apply subscription fields when platform updates billing (status + optional dates).
     *
     * @param  array<string, mixed>  $data
     */
    public function applyPlatformUpdate(Tenant $tenant, array $data): void
    {
        if (! array_key_exists('subscription_status', $data)) {
            if (array_key_exists('trial_ends_at', $data) && $this->normalizeStatus($tenant->subscription_status) === self::STATUS_TRIAL) {
                $trialEnds = $data['trial_ends_at'];
                $tenant->trial_ends_at = $trialEnds !== null && $trialEnds !== ''
                    ? Carbon::parse((string) $trialEnds)
                    : null;
            }
            if (array_key_exists('past_due_grace_ends_at', $data) && $this->normalizeStatus($tenant->subscription_status) === self::STATUS_PAST_DUE) {
                $graceEnds = $data['past_due_grace_ends_at'];
                $tenant->past_due_grace_ends_at = $graceEnds !== null && $graceEnds !== ''
                    ? Carbon::parse((string) $graceEnds)
                    : null;
            }

            return;
        }

        $status = $this->normalizeStatus((string) $data['subscription_status']);
        $tenant->subscription_status = $status;

        if ($status === self::STATUS_TRIAL) {
            $trialEnds = $data['trial_ends_at'] ?? $tenant->trial_ends_at;
            $tenant->trial_ends_at = $trialEnds !== null && $trialEnds !== ''
                ? ($trialEnds instanceof Carbon ? $trialEnds : Carbon::parse((string) $trialEnds))
                : now()->addDays(max(1, (int) config('billing.subscription.trial_days', 14)));
            $tenant->past_due_grace_ends_at = null;
            $tenant->canceled_at = null;
            $tenant->subscription_locked_at = null;

            return;
        }

        $tenant->trial_ends_at = null;

        if ($status === self::STATUS_ACTIVE) {
            $tenant->past_due_grace_ends_at = null;
            $tenant->canceled_at = null;
            $tenant->subscription_locked_at = null;

            return;
        }

        if ($status === self::STATUS_PAST_DUE) {
            $graceEnds = $data['past_due_grace_ends_at'] ?? $tenant->past_due_grace_ends_at;
            $tenant->past_due_grace_ends_at = $graceEnds !== null && $graceEnds !== ''
                ? ($graceEnds instanceof Carbon ? $graceEnds : Carbon::parse((string) $graceEnds))
                : now()->addDays(max(1, (int) config('billing.subscription.past_due_grace_days', 7)));
            $tenant->canceled_at = null;
            $tenant->subscription_locked_at = null;

            return;
        }

        if ($status === self::STATUS_CANCELED) {
            $tenant->past_due_grace_ends_at = null;
            $tenant->canceled_at = $tenant->canceled_at ?? now();
            $tenant->subscription_locked_at = $tenant->subscription_locked_at ?? now();
        }
    }

    /**
     * @return array{trials_expired: int, past_due_locked: int, errors: list<string>}
     */
    public function processScheduledTransitions(): array
    {
        $result = ['trials_expired' => 0, 'past_due_locked' => 0, 'errors' => []];
        $now = now();
        $onTrialExpire = $this->normalizeStatus((string) config('billing.subscription.on_trial_expire', self::STATUS_ACTIVE));

        foreach (Tenant::query()->cursor() as $tenant) {
            try {
                if (
                    $this->normalizeStatus($tenant->subscription_status) === self::STATUS_TRIAL
                    && $tenant->trial_ends_at !== null
                    && $now->greaterThan($tenant->trial_ends_at)
                ) {
                    $tenant->subscription_status = $onTrialExpire;
                    $tenant->trial_ends_at = null;
                    if ($onTrialExpire === self::STATUS_PAST_DUE) {
                        $tenant->past_due_grace_ends_at = $now->copy()->addDays(
                            max(1, (int) config('billing.subscription.past_due_grace_days', 7)),
                        );
                    }
                    $tenant->save();
                    $result['trials_expired']++;
                }

                if (
                    $this->normalizeStatus($tenant->subscription_status) === self::STATUS_PAST_DUE
                    && $tenant->past_due_grace_ends_at !== null
                    && $now->greaterThan($tenant->past_due_grace_ends_at)
                    && $tenant->subscription_locked_at === null
                ) {
                    $tenant->subscription_locked_at = $now;
                    $tenant->save();
                    $result['past_due_locked']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = "{$tenant->id}: {$e->getMessage()}";
            }
        }

        return $result;
    }

    /**
     * Default subscription fields for newly provisioned tenants.
     */
    public function applyProvisioningDefaults(Tenant $tenant): void
    {
        $default = $this->normalizeStatus((string) config('billing.subscription.default_status', self::STATUS_ACTIVE));
        $tenant->subscription_status = $default;

        if ($default === self::STATUS_TRIAL) {
            $tenant->trial_ends_at = now()->addDays(max(1, (int) config('billing.subscription.trial_days', 14)));
        }
    }

    private function daysUntil(?Carbon $endsAt, Carbon $now): ?int
    {
        if ($endsAt === null || $now->greaterThanOrEqualTo($endsAt)) {
            return null;
        }

        return (int) $now->diffInDays($endsAt, false);
    }
}
