<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Horizon\Horizon;

/**
 * Single list of fleet jobs. Artisan and the platform Schedules page both read this
 * so a web request does not depend on Laravel's Artisan-only schedule callback.
 */
final class TowerOsSchedule
{
    /**
     * @return list<array{command: string, module: string, label: string, cadence: string}>
     */
    public static function definitions(): array
    {
        $backupTime = (string) config('toweros.tenant_database_backup.schedule_time', '02:30');
        if ($backupTime === '') {
            $backupTime = '02:30';
        }

        $jobs = [];
        if (class_exists(Horizon::class)) {
            $jobs[] = self::job('horizon:snapshot', 'Platform', 'Queue snapshot', 'Every 5 minutes');
        }

        return [
            ...$jobs,
            self::job('toweros:subscriptions:process', 'Billing', 'Subscriptions', 'Hourly'),
            self::job('tenants:backup-schedule', 'Platform', 'Tenant database backup', 'Daily at '.$backupTime),
            self::job('tenants:backup-prune', 'Platform', 'Backup cleanup', 'Daily at 03:50'),
            self::job('dyn:run-scheduled-tasks', 'Platform', 'Saved cron jobs', 'Every minute'),
        ];
    }

    public static function register(Schedule $schedule): void
    {
        $backupTime = (string) config('toweros.tenant_database_backup.schedule_time', '02:30');
        if ($backupTime === '') {
            $backupTime = '02:30';
        }

        if (class_exists(Horizon::class)) {
            $schedule->command('horizon:snapshot')->everyFiveMinutes();
        }

        $schedule->command('toweros:subscriptions:process')->hourly()->withoutOverlapping();
        $schedule->command('tenants:backup-schedule')->dailyAt($backupTime)->withoutOverlapping();
        $schedule->command('tenants:backup-prune')->dailyAt('03:50')->withoutOverlapping();
        $schedule->command('dyn:run-scheduled-tasks')->everyMinute()->withoutOverlapping();
    }

    /**
     * @return array{command: string, module: string, label: string, cadence: string}
     */
    private static function job(string $command, string $module, string $label, string $cadence): array
    {
        return [
            'command' => $command,
            'module' => $module,
            'label' => $label,
            'cadence' => $cadence,
        ];
    }
}
