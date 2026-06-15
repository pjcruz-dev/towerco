<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TicketingSettingsService
{
    public const IT_SUPPORT_EMAIL = 'it_support_email';

    public const NOTIFY_IT_ON_CREATE = 'notify_it_on_create';

    public const NOTIFY_IT_ON_REOPEN = 'notify_it_on_reopen';

    public const NOTIFY_REQUESTOR_ON_RESOLVE = 'notify_requestor_on_resolve';

    public const NOTIFY_ASSIGNEE_ON_ASSIGN = 'notify_assignee_on_assign';

    public const CATEGORIES = 'categories';

    public const SLA_ENABLED = 'sla_enabled';

    public const SLA_RESPONSE_MINUTES = 'sla_response_minutes';

    public const SLA_ESCALATION_MINUTES = 'sla_escalation_minutes';

    public const TEAMS_WEBHOOK_URL = 'teams_webhook_url';

    public const NOTIFY_TEAMS_ON_CREATE = 'notify_teams_on_create';

    public const NOTIFY_TEAMS_ON_SLA_REMINDER = 'notify_teams_on_sla_reminder';

    public const NOTIFY_TEAMS_ON_SLA_ESCALATION = 'notify_teams_on_sla_escalation';

    public function getString(string $key, ?string $default = null): ?string
    {
        $row = DB::connection('tenant')->table('ticketing_settings')->where('key', $key)->first();
        if ($row === null || $row->value === null) {
            return $default;
        }

        return (string) $row->value;
    }

    public function setString(string $key, string $value): void
    {
        DB::connection('tenant')->table('ticketing_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function getBool(string $key, bool $default): bool
    {
        $raw = $this->getString($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        return in_array(strtolower($raw), ['true', '1', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default): int
    {
        $raw = $this->getString($key);
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return $default;
        }

        return max(1, (int) $raw);
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return app(\App\Modules\Ticketing\Support\TicketingCategoryCatalog::class)->resolve();
    }

    /**
     * @return list<string>
     */
    public function itSupportEmails(): array
    {
        $raw = trim((string) $this->getString(self::IT_SUPPORT_EMAIL, ''));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = [];
        foreach ($parts as $part) {
            $email = trim((string) $part);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $mailer = (string) config('toweros.notifications_mail_mailer', config('mail.default'));

        return [
            'it_support_email' => $this->getString(self::IT_SUPPORT_EMAIL, ''),
            'notify_it_on_create' => $this->getBool(self::NOTIFY_IT_ON_CREATE, true),
            'notify_it_on_reopen' => $this->getBool(self::NOTIFY_IT_ON_REOPEN, true),
            'notify_requestor_on_resolve' => $this->getBool(self::NOTIFY_REQUESTOR_ON_RESOLVE, true),
            'notify_assignee_on_assign' => $this->getBool(self::NOTIFY_ASSIGNEE_ON_ASSIGN, true),
            'categories' => $this->categories(),
            'sla_enabled' => $this->getBool(self::SLA_ENABLED, true),
            'sla_response_minutes' => $this->getInt(self::SLA_RESPONSE_MINUTES, 480),
            'sla_escalation_minutes' => $this->getInt(self::SLA_ESCALATION_MINUTES, 1440),
            'teams_webhook_url' => $this->getString(self::TEAMS_WEBHOOK_URL, ''),
            'notify_teams_on_create' => $this->getBool(self::NOTIFY_TEAMS_ON_CREATE, false),
            'notify_teams_on_sla_reminder' => $this->getBool(self::NOTIFY_TEAMS_ON_SLA_REMINDER, true),
            'notify_teams_on_sla_escalation' => $this->getBool(self::NOTIFY_TEAMS_ON_SLA_ESCALATION, true),
            'notifications_mailer' => $mailer,
            'notifications_mailer_ready' => $mailer !== 'log' && $mailer !== 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        if (array_key_exists('it_support_email', $values)) {
            $email = trim((string) $values['it_support_email']);
            if ($email !== '') {
                foreach ($this->parseEmails($email) as $parsed) {
                    if (! filter_var($parsed, FILTER_VALIDATE_EMAIL)) {
                        throw ValidationException::withMessages([
                            'it_support_email' => [__('Enter a valid IT group email address.')],
                        ]);
                    }
                }
            }
            $this->setString(self::IT_SUPPORT_EMAIL, $email);
        }

        foreach ([
            self::NOTIFY_IT_ON_CREATE => 'notify_it_on_create',
            self::NOTIFY_IT_ON_REOPEN => 'notify_it_on_reopen',
            self::NOTIFY_REQUESTOR_ON_RESOLVE => 'notify_requestor_on_resolve',
            self::NOTIFY_ASSIGNEE_ON_ASSIGN => 'notify_assignee_on_assign',
            self::SLA_ENABLED => 'sla_enabled',
            self::NOTIFY_TEAMS_ON_CREATE => 'notify_teams_on_create',
            self::NOTIFY_TEAMS_ON_SLA_REMINDER => 'notify_teams_on_sla_reminder',
            self::NOTIFY_TEAMS_ON_SLA_ESCALATION => 'notify_teams_on_sla_escalation',
        ] as $key => $input) {
            if (array_key_exists($input, $values)) {
                $this->setString($key, filter_var($values[$input], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false');
            }
        }

        if (array_key_exists('sla_response_minutes', $values)) {
            $this->setString(self::SLA_RESPONSE_MINUTES, (string) max(1, (int) $values['sla_response_minutes']));
        }

        if (array_key_exists('sla_escalation_minutes', $values)) {
            $this->setString(self::SLA_ESCALATION_MINUTES, (string) max(1, (int) $values['sla_escalation_minutes']));
        }

        if (array_key_exists('teams_webhook_url', $values)) {
            $url = trim((string) $values['teams_webhook_url']);
            if ($url !== '' && ! filter_var($url, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages([
                    'teams_webhook_url' => [__('Enter a valid Teams or webhook URL.')],
                ]);
            }
            $this->setString(self::TEAMS_WEBHOOK_URL, $url);
        }

        if (array_key_exists('categories', $values) && is_array($values['categories'])) {
            $slugs = [];
            foreach ($values['categories'] as $item) {
                $slug = strtolower(trim((string) $item));
                if ($slug === '' || ! preg_match('/^[a-z0-9_]+$/', $slug)) {
                    throw ValidationException::withMessages([
                        'categories' => [__('Categories must use lowercase letters, numbers, and underscores only.')],
                    ]);
                }
                $slugs[] = $slug;
            }
            $this->setString(self::CATEGORIES, json_encode(array_values(array_unique($slugs)), JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @return list<string>
     */
    private function parseEmails(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }
}
