<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use App\Modules\Rollout\Data\RolloutEmailNotificationPolicyDefaults;
use App\Modules\Rollout\Data\RolloutGateApprovalPolicyFullCoverage;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Services\TenantPlaybookSyncService;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Console\Command;

class CreateFullGateApprovalPolicyBundle extends Command
{
    protected $signature = 'rollout:policy:create-full-gate-approval
        {--code=towerco-full-gate-approval : Policy bundle code slug}
        {--name=TowerCo Full Gate Approval : Display name}
        {--publish : Publish immediately after create/update}
        {--assign-domain= : Assign published bundle to tenant domain after publish}
        {--with-rbac : Refresh tenant RBAC when assigning}
    ';

    protected $description = 'Create or refresh a rollout policy bundle with formal gate approval enabled on every timeline phase (v2 playbook).';

    public function handle(
        RolloutPolicyBundleService $policyBundles,
        TenantPlaybookSyncService $sync,
        TenantRbacBaselineService $rbac,
    ): int {
        $code = (string) $this->option('code');
        $name = trim((string) $this->option('name'));

        /** @var RolloutPlaybookVersion|null $playbook */
        $playbook = RolloutPlaybookVersion::query()
            ->where('version', RolloutPlaybookV2Definition::VERSION)
            ->where('status', 'published')
            ->first();

        if ($playbook === null) {
            $this->error('Published playbook v'.RolloutPlaybookV2Definition::VERSION.' not found. Run: php artisan rollout-playbook:publish-v2');

            return self::FAILURE;
        }

        /** @var RolloutPolicyBundle|null $existing */
        $existing = RolloutPolicyBundle::query()->where('code', $code)->first();

        if ($existing !== null && $existing->status === RolloutPolicyBundle::STATUS_PUBLISHED) {
            $this->warn("Policy [{$code}] is already published (id: {$existing->id}).");
            $this->line('Assign with: php artisan tenants:assign-rollout-policy --policy='.$code.' --domain=YOUR-TENANT-DOMAIN');

            if ($this->option('assign-domain')) {
                return $this->assignToTenant($policyBundles, $sync, $rbac, $existing);
            }

            return self::SUCCESS;
        }

        if ($existing !== null) {
            $bundle = $existing;
            $this->info("Updating existing draft policy bundle [{$code}].");
        } else {
            $bundle = $policyBundles->createDraft($playbook, $code, $name);
            $this->info("Created draft policy bundle [{$code}].");
        }

        $timelineTemplates = is_array($playbook->timeline_templates) ? $playbook->timeline_templates : [];
        $gatePolicies = RolloutGateApprovalPolicyFullCoverage::fromTimelineTemplates($timelineTemplates);

        $bundle = $policyBundles->updateDraft($bundle, [
            'name' => $name,
            'timeline_templates' => $timelineTemplates,
            'delivery_periods' => is_array($playbook->delivery_periods) ? $playbook->delivery_periods : [],
            'gate_approval_policies' => $gatePolicies,
            'email_notification_policies' => RolloutEmailNotificationPolicyDefaults::all(),
            'changelog' => 'Full gate approval on all timeline phases (SAQ/PMO/CME chains by phase owner).',
        ]);

        $this->table(
            ['Template', 'Phases with approval'],
            collect($gatePolicies)
                ->map(static fn (array $phases, string $template) => [$template, (string) count($phases)])
                ->values()
                ->all(),
        );

        if (! $this->option('publish')) {
            $this->line('Publish with: php artisan rollout:policy:publish '.$code);

            return self::SUCCESS;
        }

        $published = $policyBundles->publish($policyBundles->find($bundle->id));
        $this->info("Published: {$published->code} — {$published->name}");

        if ($this->option('assign-domain')) {
            return $this->assignToTenant($policyBundles, $sync, $rbac, $published);
        }

        $this->line('Assign to tenant: php artisan tenants:assign-rollout-policy --policy='.$code.' --domain=YOUR-TENANT-DOMAIN --with-rbac');

        return self::SUCCESS;
    }

    private function assignToTenant(
        RolloutPolicyBundleService $policyBundles,
        TenantPlaybookSyncService $sync,
        TenantRbacBaselineService $rbac,
        RolloutPolicyBundle $bundle,
    ): int {
        $domain = (string) $this->option('assign-domain');
        $tenant = Tenant::query()->whereHas('domains', static fn ($q) => $q->where('domain', $domain))->first();

        if ($tenant === null) {
            $this->error("Tenant not found for domain [{$domain}].");

            return self::FAILURE;
        }

        $binding = $policyBundles->assignToTenant($tenant, $policyBundles->find($bundle->id));
        $sync->syncBindingToTenantDatabase($tenant, $binding);
        $this->info("Assigned policy {$bundle->code} → tenant {$domain}");

        if ($this->option('with-rbac')) {
            $tenant->run(static function () use ($rbac): void {
                $rbac->ensure();
            });
            $this->line('RBAC baseline refreshed.');
        }

        return self::SUCCESS;
    }
}
