<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Modules\Platform\Models\RolloutPlaybookVersion;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Models\TenantPlaybookBinding;
use App\Modules\Rollout\Data\RolloutEmailNotificationPolicyDefaults;
use App\Modules\Rollout\Data\RolloutGateApprovalPolicyDefaults;
use App\Modules\Rollout\Data\RolloutGateApprovalPolicyFullCoverage;
use App\Modules\Rollout\Data\RolloutPlaybookMilestoneDeriver;
use App\Modules\Rollout\Services\RolloutEmailNotificationPolicyService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RolloutPolicyBundleService
{
    public function __construct(
        private readonly RolloutPlaybookCatalogService $catalog,
        private readonly RolloutPolicyBundleValidator $validator,
    ) {}

    /**
     * @return list<RolloutPolicyBundle>
     */
    public function list(?string $status = null): array
    {
        $query = RolloutPolicyBundle::query()
            ->with('playbookVersion:id,version,name')
            ->orderByDesc('updated_at');

        if ($status !== null && $status !== 'all') {
            $query->where('status', $status);
        }

        return $query->get()->all();
    }

    public function find(string $id): RolloutPolicyBundle
    {
        /** @var RolloutPolicyBundle $bundle */
        $bundle = RolloutPolicyBundle::query()
            ->with('playbookVersion')
            ->findOrFail($id);

        return $bundle;
    }

    public function createDraft(RolloutPlaybookVersion $playbookVersion, string $code, string $name): RolloutPolicyBundle
    {
        $normalizedCode = Str::slug($code, '-');
        if ($normalizedCode === '') {
            throw ValidationException::withMessages([
                'code' => [__('Policy code is required.')],
            ]);
        }

        if (RolloutPolicyBundle::query()->where('code', $normalizedCode)->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('A policy with this code already exists.')],
            ]);
        }

        if ($playbookVersion->status !== 'published') {
            throw ValidationException::withMessages([
                'playbook_version_id' => [__('Draft policies must be based on a published playbook version.')],
            ]);
        }

        /** @var RolloutPolicyBundle $bundle */
        $bundle = RolloutPolicyBundle::query()->create([
            'code' => $normalizedCode,
            'name' => trim($name),
            'status' => RolloutPolicyBundle::STATUS_DRAFT,
            'playbook_version_id' => $playbookVersion->id,
            'timeline_templates' => $playbookVersion->timeline_templates ?? [],
            'hidden_phases' => [],
            'gate_approval_policies' => RolloutGateApprovalPolicyDefaults::all(),
            'email_notification_policies' => RolloutEmailNotificationPolicyDefaults::all(),
            'delivery_periods' => $playbookVersion->delivery_periods ?? [],
            'changelog' => 'Draft rollout policy bundle created from playbook v'.$playbookVersion->version.'.',
        ]);

        return $bundle->fresh(['playbookVersion']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(RolloutPolicyBundle $bundle, array $input): RolloutPolicyBundle
    {
        if ($bundle->status !== RolloutPolicyBundle::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'bundle' => [__('Only draft policies can be edited.')],
            ]);
        }

        if (isset($input['name'])) {
            $bundle->name = trim((string) $input['name']);
        }

        if (isset($input['timeline_templates']) && is_array($input['timeline_templates'])) {
            $bundle->timeline_templates = $this->normalizeTimelineTemplates($input['timeline_templates']);
        }

        if (array_key_exists('hidden_phases', $input) && is_array($input['hidden_phases'])) {
            $bundle->hidden_phases = $input['hidden_phases'];
        }

        if (array_key_exists('gate_approval_policies', $input) && is_array($input['gate_approval_policies'])) {
            $bundle->gate_approval_policies = $input['gate_approval_policies'];
        }

        if (array_key_exists('email_notification_policies', $input) && is_array($input['email_notification_policies'])) {
            $bundle->email_notification_policies = app(RolloutEmailNotificationPolicyService::class)
                ->normalizeTenantInput($input['email_notification_policies']);
        }

        if (array_key_exists('delivery_periods', $input) && is_array($input['delivery_periods'])) {
            $bundle->delivery_periods = $input['delivery_periods'];
        }

        if (isset($input['changelog'])) {
            $bundle->changelog = (string) $input['changelog'];
        }

        $deliveryPeriods = $bundle->delivery_periods ?? $bundle->playbookVersion?->delivery_periods ?? [];
        $this->validator->validate($bundle->timeline_templates ?? [], $deliveryPeriods);

        $bundle->save();

        return $bundle->fresh(['playbookVersion']);
    }

    public function publish(RolloutPolicyBundle $bundle): RolloutPolicyBundle
    {
        if ($bundle->status !== RolloutPolicyBundle::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'bundle' => [__('Only draft policies can be published.')],
            ]);
        }

        $deliveryPeriods = $bundle->delivery_periods ?? $bundle->playbookVersion?->delivery_periods ?? [];
        $this->validator->validate($bundle->timeline_templates ?? [], $deliveryPeriods);

        $bundle->status = RolloutPolicyBundle::STATUS_PUBLISHED;
        $bundle->published_at = now();
        $bundle->save();

        return $bundle->fresh(['playbookVersion']);
    }

    /**
     * Idempotent dev/bootstrap: published policy with gate + email notification defaults (v1 playbook).
     */
    public function ensureDefaultPublishedBundle(
        RolloutPlaybookVersion $playbookVersion,
        ?string $code = null,
        ?string $name = null,
    ): RolloutPolicyBundle {
        $code = Str::slug($code ?? (string) config('toweros.tenant_provisioning.default_rollout_policy_code', 'towerco-default'), '-');
        if ($code === '') {
            $code = 'towerco-default';
        }

        $name = trim($name ?? 'TowerCo Default Rollout Policy');

        /** @var RolloutPolicyBundle|null $existing */
        $existing = RolloutPolicyBundle::query()->where('code', $code)->first();

        if ($existing?->status === RolloutPolicyBundle::STATUS_PUBLISHED) {
            return $existing->fresh(['playbookVersion']);
        }

        $bundle = $existing ?? $this->createDraft($playbookVersion, $code, $name);

        if ($bundle->status === RolloutPolicyBundle::STATUS_DRAFT) {
            $needsEmail = $bundle->email_notification_policies === null
                || $bundle->email_notification_policies === [];
            if ($needsEmail) {
                $bundle = $this->updateDraft($bundle, [
                    'email_notification_policies' => RolloutEmailNotificationPolicyDefaults::all(),
                ]);
            }
            $bundle = $this->publish($bundle);
        }

        return $bundle->fresh(['playbookVersion']);
    }

    /**
     * Idempotent dev/bootstrap: full gate approval on every phase (v2 playbook) + email defaults.
     */
    public function ensureFullGateApprovalPublishedBundle(
        RolloutPlaybookVersion $playbookVersion,
        string $code = 'towerco-full-gate-approval',
        string $name = 'TowerCo Full Gate Approval',
    ): RolloutPolicyBundle {
        $normalizedCode = Str::slug($code, '-');
        if ($normalizedCode === '') {
            $normalizedCode = 'towerco-full-gate-approval';
        }

        /** @var RolloutPolicyBundle|null $existing */
        $existing = RolloutPolicyBundle::query()->where('code', $normalizedCode)->first();

        if ($existing?->status === RolloutPolicyBundle::STATUS_PUBLISHED) {
            return $existing->fresh(['playbookVersion']);
        }

        $bundle = $existing ?? $this->createDraft($playbookVersion, $normalizedCode, $name);

        $timelineTemplates = is_array($playbookVersion->timeline_templates)
            ? $playbookVersion->timeline_templates
            : [];
        $deliveryPeriods = is_array($playbookVersion->delivery_periods)
            ? $playbookVersion->delivery_periods
            : [];

        $bundle = $this->updateDraft($bundle, [
            'name' => trim($name),
            'timeline_templates' => $timelineTemplates,
            'delivery_periods' => $deliveryPeriods,
            'gate_approval_policies' => RolloutGateApprovalPolicyFullCoverage::fromTimelineTemplates($timelineTemplates),
            'email_notification_policies' => RolloutEmailNotificationPolicyDefaults::all(),
            'changelog' => 'Full gate approval on all timeline phases (SAQ/PMO/CME chains by phase owner).',
        ]);

        if ($bundle->status === RolloutPolicyBundle::STATUS_DRAFT) {
            $bundle = $this->publish($bundle);
        }

        return $bundle->fresh(['playbookVersion']);
    }

    /**
     * Published policy bundle used when provisioning a new tenant (platform console / CLI).
     */
    public function resolveDefaultForProvisioning(?RolloutPlaybookVersion $playbookVersion = null): ?RolloutPolicyBundle
    {
        if (! (bool) config('toweros.tenant_provisioning.auto_assign_rollout_policy', true)) {
            return null;
        }

        $configuredCode = trim((string) config('toweros.tenant_provisioning.default_rollout_policy_code', ''));
        if ($configuredCode !== '') {
            /** @var RolloutPolicyBundle|null $byCode */
            $byCode = RolloutPolicyBundle::query()
                ->where('code', $configuredCode)
                ->where('status', RolloutPolicyBundle::STATUS_PUBLISHED)
                ->first();

            if ($byCode !== null) {
                return $byCode;
            }
        }

        if ($playbookVersion !== null) {
            /** @var RolloutPolicyBundle|null $forVersion */
            $forVersion = RolloutPolicyBundle::query()
                ->where('status', RolloutPolicyBundle::STATUS_PUBLISHED)
                ->where('playbook_version_id', $playbookVersion->id)
                ->orderByDesc('published_at')
                ->first();

            if ($forVersion !== null) {
                return $forVersion;
            }
        }

        /** @var RolloutPolicyBundle|null $latest */
        $latest = RolloutPolicyBundle::query()
            ->where('status', RolloutPolicyBundle::STATUS_PUBLISHED)
            ->orderByDesc('published_at')
            ->first();

        return $latest;
    }

    public function assignToTenant(
        Tenant $tenant,
        RolloutPolicyBundle $bundle,
        string $upgradePolicy = 'new_rollouts_only',
    ): TenantPlaybookBinding {
        if ($bundle->status !== RolloutPolicyBundle::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'rollout_policy_bundle_id' => [__('Only published rollout policies can be assigned.')],
            ]);
        }

        $version = $bundle->playbookVersion;
        if ($version === null) {
            throw ValidationException::withMessages([
                'rollout_policy_bundle_id' => [__('Policy bundle is missing a playbook version.')],
            ]);
        }

        /** @var TenantPlaybookBinding $binding */
        $binding = TenantPlaybookBinding::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'playbook_version_id' => $version->id,
                'rollout_policy_bundle_id' => $bundle->id,
                'upgrade_policy' => $upgradePolicy,
                'assigned_at' => now(),
            ],
        );

        return $binding->fresh(['playbookVersion', 'rolloutPolicyBundle']);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTenantPlaybookSnapshot(RolloutPolicyBundle $bundle, RolloutPlaybookVersion $version): array
    {
        $snapshot = $this->catalog->snapshot($version);
        $snapshot['timeline_templates'] = $this->effectiveTimelineTemplates($bundle);
        $snapshot['delivery_periods'] = $bundle->delivery_periods ?? $snapshot['delivery_periods'];
        $snapshot['policy_bundle_code'] = $bundle->code;
        $snapshot['policy_bundle_name'] = $bundle->name;
        $snapshot['milestone_cycle_targets'] = RolloutPlaybookMilestoneDeriver::deriveAllTemplates($snapshot);
        $snapshot['milestone_derived_from_timeline'] = true;

        return $snapshot;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function effectiveTimelineTemplates(RolloutPolicyBundle $bundle): array
    {
        $templates = $bundle->timeline_templates ?? [];
        $hidden = $bundle->hidden_phases ?? [];
        $result = [];

        foreach ($templates as $templateKey => $phases) {
            if (! is_array($phases)) {
                continue;
            }

            $hiddenKeys = $hidden[$templateKey] ?? [];
            $filtered = array_values(array_filter(
                $phases,
                static fn (array $phase): bool => ! in_array((string) ($phase['phase_key'] ?? ''), $hiddenKeys, true),
            ));

            $result[$templateKey] = array_map(
                static fn (array $phase, int $index): array => array_merge($phase, ['sort_order' => $index]),
                $filtered,
                array_keys($filtered),
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $templates
     * @return array<string, list<array<string, mixed>>>
     */
    private function normalizeTimelineTemplates(array $templates): array
    {
        $normalized = [];

        foreach ($templates as $templateKey => $phases) {
            if (! is_array($phases)) {
                continue;
            }

            $rows = [];
            foreach (array_values($phases) as $index => $phase) {
                if (! is_array($phase)) {
                    continue;
                }

                $row = [
                    'phase_key' => (string) ($phase['phase_key'] ?? ''),
                    'label' => (string) ($phase['label'] ?? $phase['phase_key'] ?? ''),
                    'owner_role' => isset($phase['owner_role']) ? (string) $phase['owner_role'] : null,
                    'anchor' => (string) ($phase['anchor'] ?? 'endorsement'),
                    'working_day_start' => (int) ($phase['working_day_start'] ?? 0),
                    'working_day_end' => (int) ($phase['working_day_end'] ?? 0),
                    'gate' => isset($phase['gate']) ? (string) $phase['gate'] : (isset($phase['gate_label']) ? (string) $phase['gate_label'] : null),
                    'counts_toward_sla' => array_key_exists('counts_toward_sla', $phase) ? (bool) $phase['counts_toward_sla'] : true,
                    'is_custom' => (bool) ($phase['is_custom'] ?? false),
                    'catalog_phase_id' => isset($phase['catalog_phase_id']) ? (string) $phase['catalog_phase_id'] : null,
                    'sort_order' => $index,
                ];

                if (isset($phase['label']) && trim((string) $phase['label']) !== '') {
                    $row['label'] = (string) $phase['label'];
                }

                $rows[] = $row;
            }

            $normalized[(string) $templateKey] = $rows;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(RolloutPolicyBundle $bundle): array
    {
        $deliveryPeriods = $bundle->delivery_periods ?? $bundle->playbookVersion?->delivery_periods ?? [];
        $slaSummary = [];

        foreach ($bundle->timeline_templates ?? [] as $templateKey => $phases) {
            if (! is_array($phases)) {
                continue;
            }

            $postTotal = 0;
            foreach ($phases as $phase) {
                if (($phase['anchor'] ?? '') === 'tssr_approved' && ($phase['counts_toward_sla'] ?? true)) {
                    $start = (int) ($phase['working_day_start'] ?? 0);
                    $end = (int) ($phase['working_day_end'] ?? 0);
                    $postTotal += max(0, $end - $start + 1);
                }
            }

            $slaSummary[$templateKey] = [
                'sla_working_days' => (int) ($deliveryPeriods[$templateKey]['working_days'] ?? 0),
                'post_day_one_total' => $postTotal,
                'valid' => $postTotal === (int) ($deliveryPeriods[$templateKey]['working_days'] ?? 0)
                    || $postTotal === 0
                    || (int) ($deliveryPeriods[$templateKey]['working_days'] ?? 0) === 0,
            ];
        }

        return [
            'id' => $bundle->id,
            'code' => $bundle->code,
            'name' => $bundle->name,
            'status' => $bundle->status,
            'playbook_version' => $bundle->playbookVersion?->version,
            'playbook_version_id' => $bundle->playbook_version_id,
            'timeline_templates' => $bundle->timeline_templates,
            'hidden_phases' => $bundle->hidden_phases ?? [],
            'gate_approval_policies' => $bundle->gate_approval_policies ?? [],
            'email_notification_policies' => $bundle->email_notification_policies
                ?? RolloutEmailNotificationPolicyDefaults::all(),
            'delivery_periods' => $deliveryPeriods,
            'sla_summary' => $slaSummary,
            'changelog' => $bundle->changelog,
            'published_at' => $bundle->published_at?->toIso8601String(),
            'updated_at' => $bundle->updated_at?->toIso8601String(),
        ];
    }
}
