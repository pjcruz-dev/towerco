<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use Illuminate\Support\Facades\DB;

final class EApprovalSettingsService
{
    public const SLA_REMINDER_MINUTES = 'sla_reminder_minutes';

    public const SLA_ESCALATION_MINUTES = 'sla_escalation_minutes';

    public const MANUAL_FOLLOW_UP_COOLDOWN_MINUTES = 'manual_follow_up_cooldown_minutes';

    public const FEATURE_DELEGATION_UI = 'feature_delegation_ui';

    public const PROVISION_MANAGER_USERS = 'provision_manager_users';

    public const LIQUIDATION_REQUIRES_PARENT = 'liquidation_requires_parent';

    public const LIQUIDATION_OVERSPEND_MODE = 'liquidation_overspend_mode';

    public const LIQUIDATION_MAX_OVERSPEND_PERCENT = 'liquidation_max_overspend_percent';

    public const PO_OVERSPEND_MODE = 'po_overspend_mode';

    public const PO_MAX_OVERSPEND_PERCENT = 'po_max_overspend_percent';

    public function getString(string $key, ?string $default = null): ?string
    {
        $row = DB::connection('tenant')->table('e_approval_settings')->where('key', $key)->first();
        if ($row === null || $row->value === null) {
            return $default;
        }

        return (string) $row->value;
    }

    public function setString(string $key, string $value): void
    {
        DB::connection('tenant')->table('e_approval_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value],
        );
    }

    public function getInt(string $key, int $default): int
    {
        $raw = $this->getString($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $n = (int) $raw;

        return $n > 0 ? $n : $default;
    }

    public function getBool(string $key, bool $default): bool
    {
        $raw = $this->getString($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        return in_array(strtolower($raw), ['true', '1', 'yes', 'on'], true);
    }

    public function provisionManagerUsers(): bool
    {
        return $this->getBool(self::PROVISION_MANAGER_USERS, true);
    }

    /**
     * @return array<string, string>
     */
    public function publicUiFlags(): array
    {
        return [
            self::FEATURE_DELEGATION_UI => $this->getString(self::FEATURE_DELEGATION_UI, 'false') === 'true' ? 'true' : 'false',
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    public function updateAdminSettings(array $values): void
    {
        $allowed = [
            self::SLA_REMINDER_MINUTES,
            self::SLA_ESCALATION_MINUTES,
            self::MANUAL_FOLLOW_UP_COOLDOWN_MINUTES,
            self::FEATURE_DELEGATION_UI,
            self::PROVISION_MANAGER_USERS,
            self::LIQUIDATION_REQUIRES_PARENT,
            self::LIQUIDATION_OVERSPEND_MODE,
            self::LIQUIDATION_MAX_OVERSPEND_PERCENT,
            self::PO_OVERSPEND_MODE,
            self::PO_MAX_OVERSPEND_PERCENT,
        ];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $this->setString($key, (string) $values[$key]);
        }
    }

    public function userSignatureKey(string $userId): string
    {
        return 'user_signature_'.$userId;
    }

    public function getUserSignature(string $userId): ?string
    {
        return $this->getString($this->userSignatureKey($userId));
    }

    public function setUserSignature(string $userId, ?string $signature): void
    {
        $key = $this->userSignatureKey($userId);
        if ($signature === null || trim($signature) === '') {
            $this->delete($key);

            return;
        }

        $this->setString($key, $signature);
    }

    public function getJson(string $key, ?array $default = null): ?array
    {
        $row = DB::connection('tenant')->table('e_approval_settings')->where('key', $key)->first();
        if ($row === null || $row->value === null || $row->value === '') {
            return $default;
        }

        $decoded = json_decode((string) $row->value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public function setJson(string $key, array $value): void
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        DB::connection('tenant')->table('e_approval_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $encoded],
        );
    }

    public function delete(string $key): void
    {
        DB::connection('tenant')->table('e_approval_settings')->where('key', $key)->delete();
    }
}
