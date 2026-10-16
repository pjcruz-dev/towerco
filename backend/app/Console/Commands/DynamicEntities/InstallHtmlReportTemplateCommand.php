<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Support\DynHtmlReportTemplateCatalog;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Console\Command;

final class InstallHtmlReportTemplateCommand extends Command
{
    protected $signature = 'dyn:install-html-report-template {key : Template key} {--tenant= : Tenant UUID}';

    protected $description = 'Install one HTML report template into Manage HTML Reports';

    public function handle(): int
    {
        $tenantId = (string) ($this->option('tenant') ?: '');
        $key = trim((string) $this->argument('key'));
        if ($tenantId === '') {
            $this->error('Provide --tenant=<uuid>.');

            return self::FAILURE;
        }

        $template = DynHtmlReportTemplateCatalog::find($key);
        if ($template === null) {
            $this->error('Unknown template ['.$key.'].');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $sources = DynHtmlReportTemplateCatalog::sources($key);
        tenancy()->initialize($tenant);

        try {
            $actor = TenantUser::query()->where('is_active', true)->orderBy('created_at')->first();
            $actorId = $actor instanceof TenantUser ? (string) $actor->id : null;
            $report = DynHtmlReport::query()->where('slug', $template['slug'])->first();
            $payload = [
                'name' => $template['name'],
                'description' => $template['description'],
                'html_source' => $sources['html'],
                'css_source' => $sources['css'],
                'js_source' => $sources['js'],
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
                'slug' => $template['slug'],
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
