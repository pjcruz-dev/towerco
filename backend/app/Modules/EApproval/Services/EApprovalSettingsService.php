<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use Illuminate\Support\Facades\DB;

final class EApprovalSettingsService
{
    public const SLA_REMINDER_MINUTES = 'sla_reminder_minutes';

    public const SLA_ESCALATION_MINUTES = 'sla_escalation_minutes';

    public const SLA_USE_WORKING_DAYS = 'sla_use_working_days';

    public const MANUAL_FOLLOW_UP_COOLDOWN_MINUTES = 'manual_follow_up_cooldown_minutes';

    public const FEATURE_DELEGATION_UI = 'feature_delegation_ui';

    public const PROVISION_MANAGER_USERS = 'provision_manager_users';

    public const NOTIFY_EXTERNAL_ON_RECEIVED = 'notify_external_on_received';

    public const NOTIFY_EXTERNAL_ON_APPROVED = 'notify_external_on_approved';

    public const NOTIFY_EXTERNAL_ON_REJECTED = 'notify_external_on_rejected';

    public const NOTIFY_EXTERNAL_ON_RETURNED = 'notify_external_on_returned';

    public const TEAMS_WEBHOOK_URL = 'teams_webhook_url';

    public const NOTIFY_TEAMS_ON_EXTERNAL_SUBMIT = 'notify_teams_on_external_submit';

    public const LIQUIDATION_REQUIRES_PARENT = 'liquidation_requires_parent';

    public const LIQUIDATION_OVERSPEND_MODE = 'liquidation_overspend_mode';

    public const LIQUIDATION_MAX_OVERSPEND_PERCENT = 'liquidation_max_overspend_percent';

    public const LIQUIDATION_DUE_DAYS = 'liquidation_due_days';

    public const LIQUIDATION_REMIND_DAYS = 'liquidation_remind_days';

    public const BLOCK_NEW_CASH_ADVANCE = 'block_new_cash_advance';

    public const VAT_RATE_PERCENT = 'vat_rate_percent';

    public const VAT_AMOUNT_INCLUDES_VAT = 'vat_amount_includes_vat';

    public const PER_DIEM_MAX_PER_DAY = 'per_diem_max_per_day';

    public const HOTEL_ACCOMMODATION_MAX = 'hotel_accommodation_max';

    public const SITE_SEARCH_SCOPE = 'site_search_scope';

    public const FINANCE_ROLE = 'finance_role';

    public const CFO_ROLE = 'cfo_role';

    public const TREASURY_ROLE = 'treasury_role';

    public const RELEASE_OFFICE = 'release_office';

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

    /**
     * Like getInt, but keeps an explicit 0 (used for "off" day counts and empty caps).
     */
    public function getIntAllowingZero(string $key, int $default): int
    {
        $raw = $this->getString($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        return max(0, (int) $raw);
    }

    public function getOptionalAmount(string $key): ?float
    {
        $raw = trim((string) $this->getString($key, ''));
        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
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

    public function siteSearchScope(): string
    {
        $raw = strtolower(trim((string) $this->getString(self::SITE_SEARCH_SCOPE, 'request')));

        return in_array($raw, ['request', 'all', 'atc', 'adic'], true) ? $raw : 'request';
    }

    /**
     * @return array<string, string>
     */
    public function publicUiFlags(): array
    {
        $perDiemMax = $this->getOptionalAmount(self::PER_DIEM_MAX_PER_DAY);
        $hotelMax = $this->getOptionalAmount(self::HOTEL_ACCOMMODATION_MAX);

        return [
            self::FEATURE_DELEGATION_UI => $this->getString(self::FEATURE_DELEGATION_UI, 'false') === 'true' ? 'true' : 'false',
            self::PER_DIEM_MAX_PER_DAY => $perDiemMax === null ? '' : number_format($perDiemMax, 2, '.', ''),
            self::HOTEL_ACCOMMODATION_MAX => $hotelMax === null ? '' : number_format($hotelMax, 2, '.', ''),
            self::SITE_SEARCH_SCOPE => $this->siteSearchScope(),
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
            self::SLA_USE_WORKING_DAYS,
            self::MANUAL_FOLLOW_UP_COOLDOWN_MINUTES,
            self::FEATURE_DELEGATION_UI,
            self::PROVISION_MANAGER_USERS,
            self::NOTIFY_EXTERNAL_ON_RECEIVED,
            self::NOTIFY_EXTERNAL_ON_APPROVED,
            self::NOTIFY_EXTERNAL_ON_REJECTED,
            self::NOTIFY_EXTERNAL_ON_RETURNED,
            self::TEAMS_WEBHOOK_URL,
            self::NOTIFY_TEAMS_ON_EXTERNAL_SUBMIT,
            self::LIQUIDATION_REQUIRES_PARENT,
            self::LIQUIDATION_OVERSPEND_MODE,
            self::LIQUIDATION_MAX_OVERSPEND_PERCENT,
            self::LIQUIDATION_DUE_DAYS,
            self::LIQUIDATION_REMIND_DAYS,
            self::BLOCK_NEW_CASH_ADVANCE,
            self::VAT_RATE_PERCENT,
            self::VAT_AMOUNT_INCLUDES_VAT,
            self::PER_DIEM_MAX_PER_DAY,
            self::HOTEL_ACCOMMODATION_MAX,
            self::SITE_SEARCH_SCOPE,
            self::FINANCE_ROLE,
            self::CFO_ROLE,
            self::TREASURY_ROLE,
            self::RELEASE_OFFICE,
        ];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value = (string) $values[$key];
            if ($key === self::SITE_SEARCH_SCOPE) {
                $value = strtolower(trim($value));
                if (! in_array($value, ['request', 'all', 'atc', 'adic'], true)) {
                    $value = 'request';
                }
            }
            $this->setString($key, $value);
        }
    }

    public function notifyExternalOnReceived(): bool
    {
        return $this->getBool(self::NOTIFY_EXTERNAL_ON_RECEIVED, false);
    }

    public function notifyExternalOnApproved(): bool
    {
        return $this->getBool(self::NOTIFY_EXTERNAL_ON_APPROVED, false);
    }

    public function notifyExternalOnRejected(): bool
    {
        return $this->getBool(self::NOTIFY_EXTERNAL_ON_REJECTED, false);
    }

    public function notifyExternalOnReturned(): bool
    {
        return $this->getBool(self::NOTIFY_EXTERNAL_ON_RETURNED, false);
    }

    public function notifyTeamsOnExternalSubmit(): bool
    {
        return $this->getBool(self::NOTIFY_TEAMS_ON_EXTERNAL_SUBMIT, false);
    }

    public function teamsWebhookUrl(): string
    {
        return trim((string) $this->getString(self::TEAMS_WEBHOOK_URL, ''));
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
