<?php

declare(strict_types=1);

namespace App\Console\Commands\AssetManagement;

use App\Models\Tenant;
use App\Modules\AssetManagement\Services\AssetReminderService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

class AssetAccountabilityReminderCommand extends Command
{
    protected $signature = 'assets:accountability-reminders {--tenants=* : Tenant UUID(s)}';

    protected $description = 'Remind employees about unsigned asset accountability forms.';

    public function handle(AssetReminderService $reminders, TenantEnabledModulesResolver $modules): int
    {
        $ids = array_values(array_filter((array) $this->option('tenants'), static fn ($id): bool => is_string($id) && $id !== ''));
        if ($ids === []) {
            $ids = Tenant::query()->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        }
        foreach ($ids as $id) {
            $tenant = Tenant::query()->find($id);
            if ($tenant === null) {
                continue;
            }
            $tenant->run(function () use ($reminders, $modules, $tenant): void {
                if (! in_array('asset_management', $modules->resolveForTenant($tenant), true)) {
                    return;
                }
                $result = $reminders->run();
                if ($result['reminders'] > 0 || $result['escalations'] > 0) {
                    $this->line($tenant->id.' reminders '.$result['reminders'].' escalations '.$result['escalations']);
                }
            });
        }

        return self::SUCCESS;
    }
}
