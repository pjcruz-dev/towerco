<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class InstallProcurementHtmlReportCommand extends Command
{
    protected $signature = 'dyn:install-procurement-report {--tenant= : Tenant UUID}';

    protected $description = 'Install the procurement approval pipeline as a Manage HTML Report';

    public function handle(): int
    {
        $tenantId = (string) ($this->option('tenant') ?: '');
        if ($tenantId === '') {
            $this->error('Provide --tenant=<uuid>.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $dir = app_path('Modules/DynamicEntities/Support/html-reports');
        $html = (string) file_get_contents($dir.'/procurement-approval-pipeline.html');
        $css = (string) file_get_contents($dir.'/procurement-approval-pipeline.css');
        $js = (string) file_get_contents($dir.'/procurement-approval-pipeline.js');
        if ($html === '' || $js === '') {
            $this->error('Report source files are missing.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            $actor = TenantUser::query()->where('is_active', true)->orderBy('created_at')->first();
            $actorId = $actor instanceof TenantUser ? (string) $actor->id : null;
            $report = DynHtmlReport::query()->where('slug', 'procurement-approval-pipeline')->first();
            $payload = [
                'name' => 'Procurement & Approval Pipeline',
                'description' => 'Pending approvals by level and process stage. HTML, CSS, and JavaScript from the procurement dashboard guide.',
                'html_source' => $html,
                'css_source' => $css,
                'js_source' => $js,
                'builder_json' => null,
                'updated_by' => $actorId,
            ];
            if ($report instanceof DynHtmlReport) {
                $report->fill($payload);
                $report->save();
                $this->info('Updated '.$report->id);

                return self::SUCCESS;
            }

            $created = DynHtmlReport::query()->create([
                ...$payload,
                'slug' => 'procurement-approval-pipeline',
                'is_system' => false,
                'created_by' => $actorId,
            ]);
            $this->info('Created '.$created->id);

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
