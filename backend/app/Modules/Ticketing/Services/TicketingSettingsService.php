<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Notifications\Support\TeamsWebhookUrl;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use App\Modules\Ticketing\Support\TicketingCategoryPackCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TicketingSettingsService
{
    public const IT_SUPPORT_EMAIL = 'it_support_email';

    public const NOTIFY_IT_ON_CREATE = 'notify_it_on_create';

    public const NOTIFY_IT_ON_REOPEN = 'notify_it_on_reopen';

    public const NOTIFY_REQUESTOR_ON_RESOLVE = 'notify_requestor_on_resolve';

    public const NOTIFY_ASSIGNEE_ON_ASSIGN = 'notify_assignee_on_assign';

    public const NOTIFY_ON_STATUS_CHANGE = 'notify_on_status_change';

    public const EMAIL_NO_REPLY_MESSAGE = 'email_no_reply_message';

    public const IT_ASSIGNEE_USER_IDS = 'it_assignee_user_ids';

    public const CATEGORIES = 'categories';

    public const ASSIGNMENT_RULES = 'assignment_rules';

    public const SLA_ENABLED = 'sla_enabled';

    public const SLA_RESPONSE_MINUTES = 'sla_response_minutes';

    public const SLA_ESCALATION_MINUTES = 'sla_escalation_minutes';

    public const TEAMS_WEBHOOK_URL = 'teams_webhook_url';

    public const NOTIFY_TEAMS_ON_CREATE = 'notify_teams_on_create';

    public const NOTIFY_TEAMS_ON_SLA_REMINDER = 'notify_teams_on_sla_reminder';

    public const NOTIFY_TEAMS_ON_SLA_ESCALATION = 'notify_teams_on_sla_escalation';

    public function getString(string $key, ?string $default = null): ?string
    {
        if (! $this->settingsTableExists()) {
            return $default;
        }

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
        return app(TicketingCategoryCatalog::class)->resolve();
    }

    /**
     * @return list<array{id: string, label: string, sla_response_minutes: ?int, sla_escalation_minutes: ?int}>
     */
    public function categoryOptions(): array
    {
        return app(TicketingCategoryCatalog::class)->resolveOptions();
    }

    /**
     * @return list<array{category: string, assignee_id: string, enabled: bool}>
     */
    public function assignmentRules(): array
    {
        $raw = $this->getString(self::ASSIGNMENT_RULES);
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return app(TicketingAssignmentService::class)->parseStoredRules($decoded);
    }

    /**
     * Configured IT assignee pool. null = not configured (legacy: all active users assignable).
     *
     * @return list<string>|null
     */
    public function itAssigneeUserIds(): ?array
    {
        $raw = $this->getString(self::IT_ASSIGNEE_USER_IDS);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $ids = [];
        foreach ($decoded as $value) {
            if (is_string($value) && $value !== '') {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    public function assertAssigneeAllowed(?string $assigneeId): void
    {
        if ($assigneeId === null || $assigneeId === '') {
            return;
        }

        $pool = $this->itAssigneeUserIds();
        if ($pool === null) {
            return;
        }

        if (! in_array($assigneeId, $pool, true)) {
            throw ValidationException::withMessages([
                'assignee_id' => [__('Assignee must be an IT user from Ticketing settings.')],
            ]);
        }

        $exists = TenantUser::query()
            ->whereKey($assigneeId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'assignee_id' => [__('Selected assignee was not found or is inactive.')],
            ]);
        }
    }

    /**
     * @param  list<string>  $ids
     */
    public function persistItAssigneeUserIds(array $ids): void
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (is_string($id) && $id !== '') {
                $normalized[] = $id;
            }
        }
        $normalized = array_values(array_unique($normalized));

        if ($normalized !== []) {
            $valid = TenantUser::query()
                ->whereIn('id', $normalized)
                ->where('is_active', true)
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->all();
            $normalized = array_values(array_intersect($normalized, $valid));
        }

        $this->setString(self::IT_ASSIGNEE_USER_IDS, json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array{category: string, assignee_id: string, enabled: bool}>  $rules
     */
    public function persistAssignmentRules(array $rules): void
    {
        $this->setString(self::ASSIGNMENT_RULES, json_encode(array_values($rules), JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string>
     */
    public function applyCategoryPack(string $packId): array
    {
        $catalog = app(TicketingCategoryPackCatalog::class);
        if (! $catalog->isValid($packId)) {
            throw ValidationException::withMessages([
                'apply_category_pack' => [__('Unknown category pack.')],
            ]);
        }

        $byId = [];
        foreach ($this->categoryOptions() as $option) {
            $byId[$option['id']] = $option;
        }

        foreach ($catalog->categoriesFor($packId) as $slug) {
            if (! isset($byId[$slug])) {
                $byId[$slug] = [
                    'id' => $slug,
                    'label' => TicketingCategoryCatalog::labelFor($slug),
                    'sla_response_minutes' => null,
                    'sla_escalation_minutes' => null,
                ];
            }
        }

        $merged = array_values($byId);
        $this->persistCategoryOptions($merged);

        return array_column($merged, 'id');
    }

    /**
     * @param  list<array{id: string, label: string, sla_response_minutes?: ?int, sla_escalation_minutes?: ?int}>  $options
     */
    public function persistCategoryOptions(array $options): void
    {
        if ($options === []) {
            throw ValidationException::withMessages([
                'categories' => [__('Add at least one ticket category.')],
            ]);
        }

        $this->setString(self::CATEGORIES, json_encode($options, JSON_THROW_ON_ERROR));
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
            'notify_on_status_change' => $this->getBool(self::NOTIFY_ON_STATUS_CHANGE, false),
            'email_no_reply_message' => $this->getString(self::EMAIL_NO_REPLY_MESSAGE, ''),
            'it_assignee_user_ids' => $this->itAssigneeUserIds() ?? [],
            'it_assignee_pool_configured' => $this->itAssigneeUserIds() !== null,
            'categories' => $this->categories(),
            'category_options' => $this->categoryOptions(),
            'category_packs' => app(TicketingCategoryPackCatalog::class)->all(),
            'assignment_rules' => $this->assignmentRules(),
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
            self::NOTIFY_ON_STATUS_CHANGE => 'notify_on_status_change',
            self::SLA_ENABLED => 'sla_enabled',
            self::NOTIFY_TEAMS_ON_CREATE => 'notify_teams_on_create',
            self::NOTIFY_TEAMS_ON_SLA_REMINDER => 'notify_teams_on_sla_reminder',
            self::NOTIFY_TEAMS_ON_SLA_ESCALATION => 'notify_teams_on_sla_escalation',
        ] as $key => $input) {
            if (array_key_exists($input, $values)) {
                $this->setString($key, filter_var($values[$input], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false');
            }
        }

        if (array_key_exists('email_no_reply_message', $values)) {
            $this->setString(self::EMAIL_NO_REPLY_MESSAGE, trim((string) $values['email_no_reply_message']));
        }

        if (array_key_exists('it_assignee_user_ids', $values) && is_array($values['it_assignee_user_ids'])) {
            $this->persistItAssigneeUserIds($values['it_assignee_user_ids']);
        }

        if (array_key_exists('sla_response_minutes', $values)) {
            $this->setString(self::SLA_RESPONSE_MINUTES, (string) max(1, (int) $values['sla_response_minutes']));
        }

        if (array_key_exists('sla_escalation_minutes', $values)) {
            $this->setString(self::SLA_ESCALATION_MINUTES, (string) max(1, (int) $values['sla_escalation_minutes']));
        }

        if (array_key_exists('teams_webhook_url', $values)) {
            $url = TeamsWebhookUrl::normalize((string) $values['teams_webhook_url']);
            if ($url !== '' && ! TeamsWebhookUrl::isValid($url)) {
                throw ValidationException::withMessages([
                    'teams_webhook_url' => [__('Enter a valid Teams Workflows (Power Automate) webhook URL.')],
                ]);
            }
            $this->setString(self::TEAMS_WEBHOOK_URL, $url);
        }

        if (array_key_exists('categories', $values) && is_array($values['categories'])) {
            $options = TicketingCategoryCatalog::normalizeList($values['categories']);
            if ($options === []) {
                throw ValidationException::withMessages([
                    'categories' => [__('Could not save categories. Each category needs a valid slug (lowercase letters, numbers, underscores).')],
                ]);
            }
            $this->persistCategoryOptions($options);
        }

        if (array_key_exists('assignment_rules', $values) && is_array($values['assignment_rules'])) {
            $rules = app(TicketingAssignmentService::class)->normalizeRulesForPersist($values['assignment_rules']);
            $this->persistAssignmentRules($rules);
        }

        if (array_key_exists('apply_category_pack', $values)) {
            $packId = strtolower(trim((string) $values['apply_category_pack']));
            if ($packId !== '') {
                $this->applyCategoryPack($packId);
            }
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

    private function settingsTableExists(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        try {
            $exists = Schema::connection('tenant')->hasTable('ticketing_settings');
        } catch (Throwable) {
            $exists = false;
        }

        return $exists;
    }
}
