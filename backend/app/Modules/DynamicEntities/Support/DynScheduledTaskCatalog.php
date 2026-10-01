<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Allowlisted scheduled job definitions for Manage Automation & Cron Jobs.
 *
 * @phpstan-type JobDef array{
 *   key: string,
 *   name: string,
 *   description: string,
 *   schedule: string,
 *   cron_expression: string,
 *   artisan: string,
 *   execution_label: string,
 *   tenant_scoped: bool
 * }
 */
final class DynScheduledTaskCatalog
{
    /**
     * @return list<JobDef>
     */
    public static function builtins(): array
    {
        return [
            [
                'key' => 'e_approval_sla',
                'name' => 'E-Approval SLA Runner',
                'description' => 'Send SLA reminders and escalations for pending approvals.',
                'schedule' => 'every_5_min',
                'cron_expression' => '*/5 * * * *',
                'artisan' => 'e-approval:sla-run',
                'execution_label' => 'e-approval:sla-run',
                'tenant_scoped' => true,
            ],
            [
                'key' => 'ticketing_sla',
                'name' => 'Ticketing SLA Runner',
                'description' => 'Evaluate ticket SLA timers and escalate overdue items.',
                'schedule' => 'every_5_min',
                'cron_expression' => '*/5 * * * *',
                'artisan' => 'ticketing:sla-run',
                'execution_label' => 'ticketing:sla-run',
                'tenant_scoped' => true,
            ],
            [
                'key' => 'e_approval_reports',
                'name' => 'E-Approval Scheduled Reports',
                'description' => 'Run saved E-Approval report schedules for this tenant.',
                'schedule' => 'hourly',
                'cron_expression' => '0 * * * *',
                'artisan' => 'e-approval:reports-run-scheduled',
                'execution_label' => 'e-approval:reports-run-scheduled',
                'tenant_scoped' => true,
            ],
            [
                'key' => 'audit_prune',
                'name' => 'Workspace Audit Prune',
                'description' => 'Prune aged workspace audit log entries per retention policy.',
                'schedule' => 'daily',
                'cron_expression' => '40 3 * * *',
                'artisan' => 'workspace:audit-prune',
                'execution_label' => 'workspace:audit-prune',
                'tenant_scoped' => true,
            ],
            [
                'key' => 'exports_prune',
                'name' => 'E-Approval Exports Prune',
                'description' => 'Clean up expired E-Approval export files.',
                'schedule' => 'daily',
                'cron_expression' => '15 3 * * *',
                'artisan' => 'e-approval:exports-prune',
                'execution_label' => 'e-approval:exports-prune',
                'tenant_scoped' => true,
            ],
            [
                'key' => 'search_index_repair',
                'name' => 'Search Index Health Check',
                'description' => 'Repair dyn record search/filter indexes when drift is detected.',
                'schedule' => 'hourly',
                'cron_expression' => '15 * * * *',
                'artisan' => 'dyn:search-index-repair',
                'execution_label' => 'dyn:search-index-repair',
                'tenant_scoped' => true,
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string, cron_expression: string}>
     */
    public static function schedulePresets(): array
    {
        return [
            ['value' => 'every_5_min', 'label' => 'Every 5 minutes', 'cron_expression' => '*/5 * * * *'],
            ['value' => 'every_10_min', 'label' => 'Every 10 minutes', 'cron_expression' => '*/10 * * * *'],
            ['value' => 'every_15_min', 'label' => 'Every 15 minutes', 'cron_expression' => '*/15 * * * *'],
            ['value' => 'hourly', 'label' => 'Hourly', 'cron_expression' => '0 * * * *'],
            ['value' => 'daily', 'label' => 'Daily', 'cron_expression' => '0 0 * * *'],
            ['value' => 'custom', 'label' => 'Custom cron', 'cron_expression' => ''],
        ];
    }

    /**
     * @return JobDef|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::builtins() as $def) {
            if ($def['key'] === $key) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @return list<JobDef>
     */
    public static function allowlistedCommands(): array
    {
        return self::builtins();
    }

    public static function resolveCron(string $schedule, ?string $customCron): string
    {
        if ($schedule === 'custom') {
            $cron = trim((string) $customCron);
            if ($cron === '') {
                return '0 * * * *';
            }

            return $cron;
        }

        foreach (self::schedulePresets() as $preset) {
            if ($preset['value'] === $schedule && $preset['cron_expression'] !== '') {
                return $preset['cron_expression'];
            }
        }

        return '0 * * * *';
    }

    public static function displaySchedule(string $schedule, string $cronExpression): string
    {
        return match ($schedule) {
            'every_5_min', 'every_10_min', 'every_15_min', 'custom' => $cronExpression,
            'hourly' => 'hourly',
            'daily' => 'daily',
            default => $cronExpression !== '' ? $cronExpression : $schedule,
        };
    }
}
