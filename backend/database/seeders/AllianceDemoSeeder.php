<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\AdminOne\Models\AdminSettings;
use App\Modules\AssetOne\Models\Asset;
use App\Modules\FiberOne\Models\FiberRoute;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProjectOne\Models\Milestone;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\ProjectOne\Models\ProjectApproval;
use App\Modules\Rollout\Models\CmeDailyReport;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Models\SiteHuntingDailyLog;
use App\Modules\Rollout\Models\SiteProfitabilityRecord;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Services\RolloutPhaseGateLabelBackfillService;
use App\Modules\Rollout\Services\RolloutProgramService;
use App\Modules\Rollout\Services\TenantPublicHolidayService;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\TowerOne\Models\Tower;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent demo dataset for the Alliance tenant (alliance.localhost).
 */
class AllianceDemoSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantRbacBaselineService::class)->ensure();

        $users = $this->seedUsers();
        $sites = $this->seedSites();
        $this->seedProjects($sites, $users);
        $this->seedTowers($sites);
        $this->seedFiberRoutes($sites);
        $this->seedAssets($sites);
        $this->seedAdminSettings();
        $this->seedRolloutDemo($users);

        if ($this->command !== null) {
            $this->command->info('Alliance demo data seeded (idempotent).');
        }
    }

    /**
     * @return array<string, TenantUser>
     */
    private function seedUsers(): array
    {
        $password = Hash::make('password');

        $definitions = [
            'manager' => [
                'name' => 'Operations Manager',
                'email' => 'manager@alliance.localhost',
                'roles' => ['manager'],
            ],
            'viewer' => [
                'name' => 'NOC Viewer',
                'email' => 'ops.viewer@alliance.localhost',
                'roles' => ['viewer'],
            ],
            'pm' => [
                'name' => 'Project Lead',
                'email' => 'project.lead@alliance.localhost',
                'roles' => ['manager'],
            ],
            'finance' => [
                'name' => 'Finance Analyst',
                'email' => 'finance@alliance.localhost',
                'roles' => ['finance'],
            ],
        ];

        $users = [];
        foreach ($definitions as $key => $row) {
            /** @var TenantUser $user */
            $user = TenantUser::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => $password,
                ],
            );
            $user->syncRoles($row['roles']);
            $users[$key] = $user->fresh(['roles']);
        }

        return $users;
    }

    /**
     * @return array<string, Site>
     */
    private function seedSites(): array
    {
        $definitions = [
            'mnl' => [
                'site_code' => 'ALL-MNL-001',
                'name' => 'Makati Exchange',
                'latitude' => 14.554729,
                'longitude' => 121.024445,
                'type' => 'macro',
                'status' => 'active',
            ],
            'bgc' => [
                'site_code' => 'ALL-BGC-002',
                'name' => 'BGC Hub Tower',
                'latitude' => 14.551547,
                'longitude' => 121.046622,
                'type' => 'rooftop',
                'status' => 'active',
            ],
            'qc' => [
                'site_code' => 'ALL-QC-003',
                'name' => 'Quezon Central',
                'latitude' => 14.676041,
                'longitude' => 121.043701,
                'type' => 'macro',
                'status' => 'under_construction',
            ],
            'psg' => [
                'site_code' => 'ALL-PSG-004',
                'name' => 'Pasig Riverside',
                'latitude' => 14.576377,
                'longitude' => 121.085117,
                'type' => 'rooftop',
                'status' => 'active',
            ],
            'tag' => [
                'site_code' => 'ALL-TAG-005',
                'name' => 'Taguig Logistics Yard',
                'latitude' => 14.517635,
                'longitude' => 121.050895,
                'type' => 'warehouse',
                'status' => 'active',
            ],
            'ceb' => [
                'site_code' => 'ALL-CEB-006',
                'name' => 'Cebu North Ring',
                'latitude' => 10.315699,
                'longitude' => 123.885437,
                'type' => 'macro',
                'status' => 'active',
            ],
        ];

        $sites = [];
        foreach ($definitions as $key => $row) {
            $sites[$key] = Site::query()->updateOrCreate(
                ['site_code' => $row['site_code']],
                $row,
            );
        }

        return $sites;
    }

    /**
     * @param  array<string, Site>  $sites
     * @param  array<string, TenantUser>  $users
     */
    private function seedProjects(array $sites, array $users): void
    {
        $today = Carbon::today();

        $projectDefs = [
            'bgc_rollout' => [
                'site_id' => $sites['bgc']->id,
                'name' => 'BGC 5G Rooftop Rollout',
                'project_manager_id' => $users['pm']->id,
                'status' => 'active',
                'start_date' => $today->copy()->subMonths(2),
                'end_date' => $today->copy()->addMonths(4),
            ],
            'qc_upgrade' => [
                'site_id' => $sites['qc']->id,
                'name' => 'Quezon Structural Upgrade',
                'project_manager_id' => $users['pm']->id,
                'status' => 'planning',
                'start_date' => $today->copy()->subWeeks(2),
                'end_date' => $today->copy()->addMonths(6),
            ],
            'ceb_backhaul' => [
                'site_id' => $sites['ceb']->id,
                'name' => 'Cebu Backhaul Expansion',
                'project_manager_id' => $users['manager']->id,
                'status' => 'on_hold',
                'start_date' => $today->copy()->subMonth(),
                'end_date' => $today->copy()->addMonths(8),
            ],
        ];

        $projects = [];
        foreach ($projectDefs as $key => $row) {
            $projects[$key] = Project::query()->updateOrCreate(
                ['name' => $row['name'], 'site_id' => $row['site_id']],
                $row,
            );
        }

        $milestones = [
            ['project_key' => 'bgc_rollout', 'name' => 'Tenant LOA signed', 'due_date' => $today->copy()->subWeeks(3), 'status' => 'completed', 'order_index' => 1],
            ['project_key' => 'bgc_rollout', 'name' => 'Civil works complete', 'due_date' => $today->copy()->addWeeks(2), 'status' => 'in_progress', 'order_index' => 2],
            ['project_key' => 'bgc_rollout', 'name' => 'RF integration', 'due_date' => $today->copy()->subDays(2), 'status' => 'overdue', 'order_index' => 3],
            ['project_key' => 'bgc_rollout', 'name' => 'Acceptance testing', 'due_date' => $today->copy()->addWeeks(5), 'status' => 'pending', 'order_index' => 4],
            ['project_key' => 'qc_upgrade', 'name' => 'Structural survey', 'due_date' => $today->copy()->addDays(5), 'status' => 'in_progress', 'order_index' => 1],
            ['project_key' => 'qc_upgrade', 'name' => 'Permit submission', 'due_date' => $today->copy()->addWeeks(3), 'status' => 'pending', 'order_index' => 2],
            ['project_key' => 'ceb_backhaul', 'name' => 'Route survey', 'due_date' => $today->copy()->subWeeks(1), 'status' => 'completed', 'order_index' => 1],
            ['project_key' => 'ceb_backhaul', 'name' => 'Vendor contract', 'due_date' => $today->copy()->addWeeks(1), 'status' => 'pending', 'order_index' => 2],
        ];

        foreach ($milestones as $row) {
            $projectId = $projects[$row['project_key']]->id;
            Milestone::query()->updateOrCreate(
                ['project_id' => $projectId, 'order_index' => $row['order_index']],
                [
                    'name' => $row['name'],
                    'due_date' => $row['due_date'],
                    'status' => $row['status'],
                ],
            );
        }

        $approvals = [
            [
                'project_key' => 'bgc_rollout',
                'approval_type' => 'civil',
                'title' => 'BGC rooftop load certificate',
                'requester' => 'Project Lead',
                'submitted_at' => Carbon::now()->subDays(3),
                'sla_risk' => 'high',
                'status' => 'pending',
            ],
            [
                'project_key' => 'qc_upgrade',
                'approval_type' => 'regulatory',
                'title' => 'Quezon building permit extension',
                'requester' => 'Operations Manager',
                'submitted_at' => Carbon::now()->subDays(1),
                'sla_risk' => 'medium',
                'status' => 'pending',
            ],
            [
                'project_key' => 'ceb_backhaul',
                'approval_type' => 'commercial',
                'title' => 'Cebu vendor SOW approval',
                'requester' => 'Project Lead',
                'submitted_at' => Carbon::now()->subHours(6),
                'sla_risk' => 'low',
                'status' => 'pending',
            ],
            [
                'project_key' => 'bgc_rollout',
                'approval_type' => 'technical',
                'title' => 'Antenna mount specification',
                'requester' => 'Project Lead',
                'submitted_at' => Carbon::now()->subWeeks(2),
                'sla_risk' => 'low',
                'status' => 'approved',
                'resolution_notes' => 'Approved with minor RF revision.',
                'resolved_at' => Carbon::now()->subWeek(),
                'resolved_by_id' => $users['manager']->id,
            ],
        ];

        foreach ($approvals as $row) {
            $projectKey = $row['project_key'];
            unset($row['project_key']);
            ProjectApproval::query()->updateOrCreate(
                ['project_id' => $projects[$projectKey]->id, 'title' => $row['title']],
                array_merge($row, ['project_id' => $projects[$projectKey]->id]),
            );
        }
    }

    /**
     * @param  array<string, Site>  $sites
     */
    private function seedTowers(array $sites): void
    {
        $towers = [
            ['site_key' => 'mnl', 'tower_type' => 'monopole', 'height_m' => 45, 'capacity_kg' => 1200, 'max_tenants' => 4, 'status' => 'operational'],
            ['site_key' => 'bgc', 'tower_type' => 'rooftop', 'height_m' => 18, 'capacity_kg' => 600, 'max_tenants' => 2, 'status' => 'operational'],
            ['site_key' => 'qc', 'tower_type' => 'lattice', 'height_m' => 60, 'capacity_kg' => 1800, 'max_tenants' => 5, 'status' => 'under_maintenance'],
            ['site_key' => 'psg', 'tower_type' => 'rooftop', 'height_m' => 22, 'capacity_kg' => 750, 'max_tenants' => 3, 'status' => 'operational'],
            ['site_key' => 'ceb', 'tower_type' => 'monopole', 'height_m' => 52, 'capacity_kg' => 1500, 'max_tenants' => 4, 'status' => 'operational'],
        ];

        foreach ($towers as $row) {
            $siteId = $sites[$row['site_key']]->id;
            Tower::query()->updateOrCreate(
                ['site_id' => $siteId, 'tower_type' => $row['tower_type']],
                [
                    'height_m' => $row['height_m'],
                    'capacity_kg' => $row['capacity_kg'],
                    'max_tenants' => $row['max_tenants'],
                    'status' => $row['status'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Site>  $sites
     */
    private function seedFiberRoutes(array $sites): void
    {
        $routes = [
            ['name' => 'Makati ↔ BGC Metro Ring', 'status' => 'active', 'from' => 'mnl', 'to' => 'bgc', 'length_km' => 8.4],
            ['name' => 'BGC ↔ Pasig East Span', 'status' => 'active', 'from' => 'bgc', 'to' => 'psg', 'length_km' => 6.2],
            ['name' => 'Quezon ↔ Taguig South Link', 'status' => 'planned', 'from' => 'qc', 'to' => 'tag', 'length_km' => 11.8],
            ['name' => 'Manila ↔ Cebu Subsea Landing', 'status' => 'planned', 'from' => 'mnl', 'to' => 'ceb', 'length_km' => 582.0],
        ];

        foreach ($routes as $row) {
            FiberRoute::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'status' => $row['status'],
                    'from_site_id' => $sites[$row['from']]->id,
                    'to_site_id' => $sites[$row['to']]->id,
                    'length_km' => $row['length_km'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Site>  $sites
     */
    private function seedAssets(array $sites): void
    {
        $today = Carbon::today();
        $assets = [
            ['asset_code' => 'AST-RRU-001', 'name' => 'Ericsson AIR 6449', 'category' => 'radio', 'status' => 'deployed', 'rfid_tag' => 'RFID-10001', 'location_type' => 'site', 'location_id' => $sites['bgc']->id, 'warranty_expiry' => $today->copy()->addYears(2), 'purchase_value' => 185000],
            ['asset_code' => 'AST-RRU-002', 'name' => 'Huawei AAU5636', 'category' => 'radio', 'status' => 'in_transit', 'rfid_tag' => 'RFID-10002', 'location_type' => 'site', 'location_id' => $sites['tag']->id, 'warranty_expiry' => $today->copy()->addYears(3), 'purchase_value' => 142000],
            ['asset_code' => 'AST-GEN-001', 'name' => 'Cummins 50kVA Genset', 'category' => 'power', 'status' => 'deployed', 'rfid_tag' => 'RFID-20001', 'location_type' => 'site', 'location_id' => $sites['mnl']->id, 'warranty_expiry' => $today->copy()->addMonths(18), 'purchase_value' => 920000],
            ['asset_code' => 'AST-BAT-001', 'name' => 'Li-ion battery bank 48V', 'category' => 'power', 'status' => 'in_warehouse', 'rfid_tag' => 'RFID-20002', 'location_type' => 'warehouse', 'location_id' => $sites['tag']->id, 'warranty_expiry' => $today->copy()->addYears(5), 'purchase_value' => 78000],
            ['asset_code' => 'AST-FBR-001', 'name' => '144-core fiber drum', 'category' => 'fiber', 'status' => 'in_warehouse', 'rfid_tag' => 'RFID-30001', 'location_type' => 'warehouse', 'location_id' => $sites['tag']->id, 'warranty_expiry' => null, 'purchase_value' => 56000],
            ['asset_code' => 'AST-FBR-002', 'name' => 'OTDR test kit', 'category' => 'test', 'status' => 'deployed', 'rfid_tag' => 'RFID-30002', 'location_type' => 'site', 'location_id' => $sites['psg']->id, 'warranty_expiry' => $today->copy()->addYear(), 'purchase_value' => 210000],
            ['asset_code' => 'AST-SFT-001', 'name' => 'Tower climb harness set', 'category' => 'safety', 'status' => 'deployed', 'rfid_tag' => 'RFID-40001', 'location_type' => 'site', 'location_id' => $sites['qc']->id, 'warranty_expiry' => $today->copy()->addMonths(6), 'purchase_value' => 12000],
            ['asset_code' => 'AST-SFT-002', 'name' => 'RF safety monitor', 'category' => 'safety', 'status' => 'in_transit', 'rfid_tag' => 'RFID-40002', 'location_type' => 'site', 'location_id' => $sites['ceb']->id, 'warranty_expiry' => $today->copy()->addYears(2), 'purchase_value' => 45000],
            ['asset_code' => 'AST-CAB-001', 'name' => '7/8 coax jumpers (lot)', 'category' => 'cabling', 'status' => 'in_warehouse', 'rfid_tag' => 'RFID-50001', 'location_type' => 'warehouse', 'location_id' => $sites['tag']->id, 'warranty_expiry' => null, 'purchase_value' => 8500],
            ['asset_code' => 'AST-CAB-002', 'name' => 'Hybrid power cable 25m', 'category' => 'cabling', 'status' => 'deployed', 'rfid_tag' => 'RFID-50002', 'location_type' => 'site', 'location_id' => $sites['bgc']->id, 'warranty_expiry' => null, 'purchase_value' => 6200],
        ];

        foreach ($assets as $row) {
            Asset::query()->updateOrCreate(
                ['asset_code' => $row['asset_code']],
                $row,
            );
        }
    }

    private function seedAdminSettings(): void
    {
        $payload = [
            'kpi_config' => [
                'targets' => [
                    ['key' => 'site_uptime_pct', 'label' => 'Site uptime', 'target' => 99.5, 'unit' => '%'],
                    ['key' => 'wo_sla_pct', 'label' => 'Work order SLA', 'target' => 95, 'unit' => '%'],
                    ['key' => 'project_on_time_pct', 'label' => 'Projects on time', 'target' => 88, 'unit' => '%'],
                ],
            ],
            'sla_config' => [
                'policies' => [
                    ['severity' => 'critical', 'response_minutes' => 15, 'resolve_hours' => 4],
                    ['severity' => 'high', 'response_minutes' => 60, 'resolve_hours' => 24],
                    ['severity' => 'medium', 'response_minutes' => 240, 'resolve_hours' => 72],
                ],
            ],
            'workflow_templates' => [
                [
                    'key' => 'site_acceptance',
                    'label' => 'Site acceptance',
                    'steps' => [
                        ['order' => 1, 'role' => 'manager', 'action' => 'Civil sign-off'],
                        ['order' => 2, 'role' => 'tenant_admin', 'action' => 'Commercial approval'],
                        ['order' => 3, 'role' => 'manager', 'action' => 'RF on-air checklist'],
                    ],
                ],
                [
                    'key' => 'change_request',
                    'label' => 'Network change request',
                    'steps' => [
                        ['order' => 1, 'role' => 'manager', 'action' => 'Impact assessment'],
                        ['order' => 2, 'role' => 'tenant_admin', 'action' => 'Change advisory approval'],
                    ],
                ],
            ],
        ];

        $existing = AdminSettings::query()->first();
        if ($existing !== null) {
            $existing->update($payload);

            return;
        }

        AdminSettings::query()->create($payload);
    }

    /**
     * @param  array<string, TenantUser>  $users
     */
    private function seedRolloutDemo(array $users): void
    {
        if (TenantRolloutPlaybookConfig::query()->doesntExist()) {
            return;
        }

        /** @var RolloutProgramService $rolloutService */
        $rolloutService = app(RolloutProgramService::class);
        $today = Carbon::today();

        $linkedProject = Project::query()->where('name', 'Quezon Structural Upgrade')->first();

        $program = RolloutProgram::query()->where('rollout_ref', 'RP-2026-GLO-DEMO')->first();
        if ($program === null) {
            $program = $rolloutService->create([
                'mno' => 'globe',
                'project_type' => 'bts',
                'endorsement_ref' => 'GLO-END-2026-0142',
                'endorsement_date' => $today->copy()->subWeeks(2)->toDateString(),
                'search_ring_name' => 'Quezon North Ring A',
                'region' => 'ncr',
                'territory' => 'quezon',
                'rollout_ref' => 'RP-2026-GLO-DEMO',
                'saq_owner_id' => $users['pm']->id,
                'cme_pm_id' => $users['manager']->id,
                'project_id' => $linkedProject?->id,
            ]);
        } elseif ($linkedProject !== null && $program->project_id === null) {
            $program->update(['project_id' => $linkedProject->id]);
        }

        $candidateDefs = [
            [
                'candidate_number' => 1,
                'label' => 'QC Scout — EDSA corner',
                'latitude' => 14.6768,
                'longitude' => 121.0437,
                'lessor_name' => 'Metro Properties Inc.',
                'status' => 'shortlisted',
            ],
            [
                'candidate_number' => 2,
                'label' => 'QC Scout — Commonwealth Ave',
                'latitude' => 14.6779,
                'longitude' => 121.0482,
                'lessor_name' => 'Commonwealth Holdings',
                'status' => 'scouted',
            ],
            [
                'candidate_number' => 3,
                'label' => 'QC Scout — Regalado Highway',
                'latitude' => 14.6841,
                'longitude' => 121.0564,
                'lessor_name' => 'Regalado Land Corp.',
                'status' => 'rejected',
                'rejection_reason_code' => 'power_unavailable',
                'rejection_notes' => 'No 3-phase within 200m; ROW blocked by barangay.',
            ],
        ];

        foreach ($candidateDefs as $row) {
            SiteCandidate::query()->updateOrCreate(
                [
                    'rollout_program_id' => $program->id,
                    'candidate_number' => $row['candidate_number'],
                ],
                array_merge($row, ['rollout_program_id' => $program->id]),
            );
        }

        SiteHuntingDailyLog::query()->updateOrCreate(
            [
                'rollout_program_id' => $program->id,
                'log_date' => $today->copy()->subDays(3)->toDateString(),
            ],
            [
                'summary' => 'Field team covered 4 barangays; 3 candidates logged, 1 rejected on power ROW.',
                'candidates_identified_count' => 3,
                'photo_links' => ['https://example.local/demo/hunt-qc-01.jpg'],
            ],
        );

        CmeDailyReport::query()->updateOrCreate(
            [
                'rollout_program_id' => $program->id,
                'report_date' => $today->copy()->subDay()->toDateString(),
            ],
            [
                'day_number' => 12,
                'construction_working_days_total' => 44,
                'physical_progress_pct' => 18.5,
                'physical_progress_plan_pct' => 20,
                'workforce_count' => 14,
                'activities_completed' => 'Foundation rebar inspection passed; anchor bolt setting.',
                'activities_planned_tomorrow' => 'Tower leg assembly prep; material staging.',
                'toolbox_meeting_held' => true,
            ],
        );

        SiteProfitabilityRecord::query()->updateOrCreate(
            ['rollout_program_id' => $program->id],
            [
                'baseline' => [
                    'saq' => 450000,
                    'engineering' => 320000,
                    'permitting' => 180000,
                    'cme' => 2100000,
                    'tower_material' => 980000,
                    'dc_plant' => 420000,
                    'power' => 650000,
                ],
                'actual' => [
                    'saq' => 380000,
                    'engineering' => 310000,
                    'permitting' => 195000,
                    'cme' => 890000,
                    'tower_material' => 0,
                    'dc_plant' => 0,
                    'power' => 0,
                ],
                'profitability_status' => 'on_track',
                'anchor_tenant_lease_fee_php' => 85000,
            ],
        );

        if ($program->tssr_approved_date === null) {
            $rolloutService->setTssrApproved($program, $today->copy()->subWeek());
        }

        app(RolloutPhaseGateLabelBackfillService::class)->backfillProgram($program);

        $this->seedRolloutBatchDemo($rolloutService, $users, $today);
        app(TenantPublicHolidayService::class)->seedPhilippinesYear((int) $today->format('Y'));
    }

    /**
     * @param  array<string, TenantUser>  $users
     */
    private function seedRolloutBatchDemo(RolloutProgramService $rolloutService, array $users, Carbon $today): void
    {
        if (RolloutProgram::query()->where('rollout_ref', 'BATCH-2026-GLO-DEMO')->exists()) {
            return;
        }

        $rolloutService->createBatch(
            [
                'mno' => 'smart',
                'project_type' => 'bts',
                'batch_label' => 'Metro Manila Q2 Batch',
                'endorsement_ref' => 'SMT-BATCH-2026-002',
                'endorsement_date' => $today->copy()->subWeeks(3)->toDateString(),
                'rollout_ref' => 'BATCH-2026-GLO-DEMO',
                'region' => 'ncr',
            ],
            [
                [
                    'search_ring_name' => 'Makati South Ring',
                    'region' => 'ncr',
                    'territory' => 'makati',
                    'saq_owner_id' => $users['pm']->id,
                ],
                [
                    'search_ring_name' => 'Pasig East Ring',
                    'region' => 'ncr',
                    'territory' => 'pasig',
                    'saq_owner_id' => $users['pm']->id,
                ],
            ],
        );
    }
}
