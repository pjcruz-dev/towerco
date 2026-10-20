<?php

declare(strict_types=1);

namespace Tests\Feature\AssetManagement;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssetManagementTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);
        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'asset_management',
                'dynamic_entities',
            ],
        ]);
        $this->bootInMemoryTenantApi();
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_registers_an_asset_and_blocks_a_duplicate_serial(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'pf123456',
                'asset_type' => 'laptop',
                'manufacturer' => 'Lenovo',
                'product_name' => 'ThinkPad E14',
                'status' => 'AVAILABLE',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.serial_number', 'PF123456')
            ->assertJsonPath('data.asset_tag', 'ATC-LAP-000001');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'PF123456',
                'asset_type' => 'laptop',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Serial number PF123456 is already registered as ATC-LAP-000001. Update that asset instead of creating another one.']);
    }

    public function test_assignment_creates_a_draft_and_send_requires_confirmation(): void
    {
        $assetId = $this->createAsset('SN-ASSIGN');
        tenancy()->initialize($this->testTenant);
        $employee = TenantUser::query()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@test.localhost',
            'password' => 'password',
            'is_active' => true,
            'department' => 'IT',
        ]);
        $employee->assignRole('assets_employee');
        tenancy()->end();

        $assign = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$assetId.'/assign', [
                'employee_id' => $employee->id,
                'extra_labels' => ['Charger'],
            ]);
        $assign->assertCreated();
        $accountabilityId = (string) $assign->json('data.id');
        $this->assertNotSame('', $accountabilityId);
        $this->assertSame('DRAFT', $assign->json('data.status'));

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/accountability/'.$accountabilityId.'/send', [])
            ->assertStatus(422);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/accountability/'.$accountabilityId.'/send', ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_SIGNATURE');
    }

    public function test_assigner_can_record_the_signature_and_another_employee_cannot(): void
    {
        $assetId = $this->createAsset('SN-SIGN');
        tenancy()->initialize($this->testTenant);
        $employee = TenantUser::query()->create([
            'name' => 'Denver Arquiza',
            'email' => 'denver.sign@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $employee->assignRole('assets_employee');
        $other = TenantUser::query()->create([
            'name' => 'Other Employee',
            'email' => 'other.sign@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $other->assignRole('assets_employee');
        tenancy()->end();

        $assign = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$assetId.'/assign', [
                'employee_id' => $employee->id,
            ]);
        $assign->assertCreated();
        $accountabilityId = (string) $assign->json('data.id');
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/accountability/'.$accountabilityId.'/send', ['confirm' => true])
            ->assertOk();

        $this->actingAs($other, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/accountability/'.$accountabilityId.'/sign', [
                'signature' => 'other-signature',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Only the assigned employee can sign this accountability.']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/accountability/'.$accountabilityId.'/sign', [
                'signature' => 'denver-signature',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'SIGNED');
    }

    public function test_inventory_retry_does_not_create_a_second_asset(): void
    {
        $payload = [
            'client_key' => 'INV-2026-000812',
            'serial_number' => 'SN-SCAN-1',
            'asset_type' => 'laptop',
            'manufacturer' => 'Lenovo',
            'windows_identity' => 'DOMAIN\\pjcruz',
            'network_adapters' => [
                ['adapter_name' => 'Wi-Fi', 'adapter_type' => 'wifi', 'mac_address' => 'a8:b2:c3:d4:e5:f6'],
            ],
            'applications' => [
                ['application_name' => 'Google Chrome', 'version' => '130'],
            ],
        ];
        $first = $this->actingAsTenantAdmin()->withHeaders($this->tenantApiHeaders())->postJson('/api/v1/assets/inventory', $payload);
        $first->assertOk()->assertJsonPath('data.created', true);
        $second = $this->actingAsTenantAdmin()->withHeaders($this->tenantApiHeaders())->postJson('/api/v1/assets/inventory', $payload);
        $second->assertOk()->assertJsonPath('data.idempotent', true);
        $this->assertSame($first->json('data.asset.id'), $second->json('data.asset.id'));
        $this->assertSame('A8-B2-C3-D4-E5-F6', $first->json('data.asset.network_adapters.0.mac_address'));
    }

    public function test_serial_mismatch_blocks_verification(): void
    {
        $tag = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'PF123456',
                'asset_type' => 'laptop',
            ])->json('data.asset_tag');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/inventory/verify', [
                'asset_tag' => $tag,
                'detected_serial' => 'PF987654',
            ])
            ->assertStatus(422);
    }

    public function test_repair_blocks_transfer_and_usable_flag_is_required(): void
    {
        $assetId = $this->createAsset('SN-REPAIR');
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$assetId.'/repairs', [
                'problem' => 'No display',
            ])
            ->assertStatus(422);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$assetId.'/repairs', [
                'usable' => false,
                'replacement_required' => false,
                'problem' => 'No display',
            ])
            ->assertCreated();

        tenancy()->initialize($this->testTenant);
        $employee = TenantUser::query()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.repair@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/transfers', [
                'asset_ids' => [$assetId],
                'employee_id' => $employee->id,
                'confirm' => true,
            ])
            ->assertStatus(422);
    }

    public function test_offboarding_rejects_the_wrong_asset_and_stays_incomplete(): void
    {
        $assetId = $this->createAsset('SN-OFF');
        tenancy()->initialize($this->testTenant);
        $employee = TenantUser::query()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.off@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        tenancy()->end();
        $this->actingAsTenantAdmin()->withHeaders($this->tenantApiHeaders())->postJson('/api/v1/assets/'.$assetId.'/assign', [
            'employee_id' => $employee->id,
        ])->assertCreated();

        $case = $this->actingAsTenantAdmin()->withHeaders($this->tenantApiHeaders())->postJson('/api/v1/assets/offboarding', [
            'employee_id' => $employee->id,
        ]);
        $case->assertCreated();
        $itemId = (string) $case->json('data.items.0.id');
        $offboardingId = (string) $case->json('data.id');

        $this->actingAsTenantAdmin()->withHeaders($this->tenantApiHeaders())->postJson('/api/v1/assets/offboarding/'.$offboardingId.'/items/'.$itemId, [
            'scanned_tag' => 'ATC-LAP-999999',
            'result' => 'RETURNED',
            'condition' => 'Good',
        ])->assertStatus(422);

        $this->actingAsTenantAdmin()->withHeaders($this->tenantApiHeaders())->postJson('/api/v1/assets/offboarding/'.$offboardingId.'/complete', [])
            ->assertStatus(422);
    }

    public function test_unknown_asset_is_not_found_and_a_viewer_cannot_create(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/does-not-exist')
            ->assertNotFound();

        tenancy()->initialize($this->testTenant);
        $viewer = TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('assets_viewer');
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'SN-NOPE',
                'asset_type' => 'laptop',
            ])
            ->assertForbidden();
    }

    public function test_schema_backfills_a_missing_system_field(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/schema')
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        DynField::query()->where('name', 'warranty_end')->delete();
        tenancy()->end();

        $again = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/schema')
            ->assertOk();

        $names = collect($again->json('data.fields'))->pluck('name');
        $this->assertTrue($names->contains('warranty_end'));
    }

    public function test_a_field_created_without_a_group_joins_identification(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/schema')
            ->assertOk();

        $created = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/dynamic-entities/entities/it_asset/fields', [
                'label' => 'Cost center',
                'name' => 'cost_center',
                'type' => 'text',
            ])
            ->assertCreated();

        $schema = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/schema')
            ->assertOk();

        $identification = collect($schema->json('data.groups'))->firstWhere('name', 'Identification');
        $this->assertIsArray($identification);
        $this->assertSame($identification['id'], $created->json('data.form_group_id'));
        $this->assertSame($identification['id'], $created->json('data.view_group_id'));
    }

    public function test_the_asset_form_loads_when_dynamic_entities_is_off(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
            $table->json('enabled_modules')->nullable();
        });
        $this->testTenant->enabled_modules = ['core', 'team_access', 'asset_management'];
        $this->testTenant->save();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/schema')
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dynamic-entities/entities/it_asset')
            ->assertOk()
            ->assertJsonPath('data.slug', 'it_asset');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/dynamic-entities/entities/it_asset/field-groups', [
                'name' => 'Holder notes',
                'surface' => 'assignment',
            ])
            ->assertCreated()
            ->assertJsonPath('data.surface', 'assignment');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dynamic-entities/entities')
            ->assertForbidden();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dynamic-entities/entities/it_asset/records')
            ->assertForbidden();
    }

    public function test_a_field_group_can_live_on_an_asset_tab(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/schema')
            ->assertOk();

        $created = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/dynamic-entities/entities/it_asset/field-groups', [
                'name' => 'Previous holders',
                'surface' => 'assignment',
            ])
            ->assertCreated()
            ->assertJsonPath('data.surface', 'assignment');

        $entity = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dynamic-entities/entities/it_asset')
            ->assertOk();
        $group = collect($entity->json('data.field_groups'))->firstWhere('name', 'Previous holders');
        $this->assertIsArray($group);
        $this->assertSame('assignment', $group['surface']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/dynamic-entities/field-groups/'.$created->json('data.id'), [
                'surface' => 'repair',
            ])
            ->assertOk()
            ->assertJsonPath('data.surface', 'repair');
    }

    public function test_custom_field_sorts_by_its_stored_value(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/schema/fields', [
                'label' => 'Cost center',
                'name' => 'cost_center',
                'type' => 'text',
                'show_in_table' => true,
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'SN-SORT-B',
                'asset_type' => 'laptop',
                'custom_values' => ['cost_center' => 'BBB'],
            ])
            ->assertCreated();
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => 'SN-SORT-A',
                'asset_type' => 'laptop',
                'custom_values' => ['cost_center' => 'AAA'],
            ])
            ->assertCreated();

        $asc = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets?sort=cost_center&per_page=100')
            ->assertOk();
        $serials = collect($asc->json('data'))->pluck('serial_number')->all();
        $this->assertLessThan(
            array_search('SN-SORT-B', $serials, true),
            array_search('SN-SORT-A', $serials, true),
        );

        $desc = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets?sort=-cost_center&per_page=100')
            ->assertOk();
        $descSerials = collect($desc->json('data'))->pluck('serial_number')->all();
        $this->assertLessThan(
            array_search('SN-SORT-A', $descSerials, true),
            array_search('SN-SORT-B', $descSerials, true),
        );
    }

    public function test_dashboard_cards_open_the_rows_they_count(): void
    {
        $lost = $this->createAsset('SN-LOST');
        $stolen = $this->createAsset('SN-STOLEN');
        $pending = $this->createAsset('SN-PEND');
        $draft = $this->createAsset('SN-DRAFT');
        $vendor = $this->createAsset('SN-VENDOR');
        $review = $this->createAsset('SN-REVIEW');
        $planning = $this->createAsset('SN-PLAN');
        $fresh = $this->createAsset('SN-FRESH');
        $this->createAsset('SN-PLAIN');

        tenancy()->initialize($this->testTenant);
        Asset::query()->whereKey($lost)->update(['status' => 'LOST']);
        Asset::query()->whereKey($stolen)->update(['status' => 'STOLEN']);
        Asset::query()->whereKey($review)->update(['commission_date' => now()->subMonths(38)->toDateString()]);
        Asset::query()->whereKey($planning)->update(['commission_date' => now()->subMonths(44)->toDateString()]);
        Asset::query()->whereKey($fresh)->update(['commission_date' => now()->subMonths(6)->toDateString()]);
        $employee = TenantUser::query()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.cards@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $employee->assignRole('assets_employee');
        tenancy()->end();

        $pendingForm = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$pending.'/assign', ['employee_id' => $employee->id]);
        $pendingForm->assertCreated();
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/accountability/'.$pendingForm->json('data.id').'/send', ['confirm' => true])
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$draft.'/assign', ['employee_id' => $employee->id])
            ->assertCreated();

        $repair = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$vendor.'/repairs', [
                'usable' => true,
                'problem' => 'Fan noise',
            ]);
        $repair->assertCreated();
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/assets/repairs/'.$repair->json('data.id'), ['result' => 'PENDING_VENDOR'])
            ->assertOk();

        $dashboard = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/dashboard');
        $dashboard->assertOk();
        $cards = collect($dashboard->json('data.cards'))->keyBy('key');

        $this->assertSame('/assets?status=LOST,STOLEN', $cards['lost']['href']);
        $this->assertSame('/assets?attention=accountability', $cards['accountability']['href']);
        $this->assertSame('/assets?repair=PENDING_VENDOR', $cards['vendor']['href']);
        $this->assertSame('/assets?lifecycle=LIFECYCLE_REVIEW,REPLACEMENT_PLANNING', $cards['near_eol']['href']);

        $this->assertCardList($cards['lost'], ['SN-LOST', 'SN-STOLEN'], ['SN-PLAIN', 'SN-PEND']);
        $this->assertCardList($cards['accountability'], ['SN-PEND'], ['SN-DRAFT', 'SN-PLAIN']);
        $this->assertCardList($cards['vendor'], ['SN-VENDOR'], ['SN-PLAIN', 'SN-PEND']);
        $this->assertCardList($cards['near_eol'], ['SN-REVIEW', 'SN-PLAN'], ['SN-FRESH', 'SN-PLAIN']);
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  list<string>  $include
     * @param  list<string>  $exclude
     */
    private function assertCardList(array $card, array $include, array $exclude): void
    {
        $query = parse_url((string) $card['href'], PHP_URL_QUERY);
        $this->assertIsString($query);
        $list = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets?'.$query);
        $list->assertOk()->assertJsonPath('meta.total', $card['value']);
        $serials = collect($list->json('data'))->pluck('serial_number')->all();
        foreach ($include as $serial) {
            $this->assertContains($serial, $serials);
        }
        foreach ($exclude as $serial) {
            $this->assertNotContains($serial, $serials);
        }
    }

    public function test_a_reissued_serial_updates_the_same_laptop(): void
    {
        $assetId = $this->createAsset('SN-REISSUE');
        tenancy()->initialize($this->testTenant);
        $first = TenantUser::query()->create([
            'name' => 'First Holder',
            'email' => 'first.reissue@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $second = TenantUser::query()->create([
            'name' => 'Second Holder',
            'email' => 'second.reissue@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $first->assignRole('assets_employee');
        $second->assignRole('assets_employee');
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets/'.$assetId.'/assign', ['employee_id' => $first->id])
            ->assertCreated();

        $map = json_encode([
            'Serial Number' => 'serial_number',
            'Type' => 'asset_type',
            'Status' => 'status',
            'Employee Email' => 'employee_email',
        ]);
        $reissue = "Serial Number,Type,Status,Employee Email\nSN-REISSUE,laptop,ASSIGNED,second.reissue@test.localhost\n";
        $dry = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/assets/import/run', [
                'file' => UploadedFile::fake()->createWithContent('reissue.csv', $reissue),
                'column_map' => $map,
                'dry_run' => '1',
            ]);
        $dry->assertOk()
            ->assertJsonPath('data.would_create', 0)
            ->assertJsonPath('data.would_update', 1)
            ->assertJsonPath('data.error_rows', 0);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/assets/import/run', [
                'file' => UploadedFile::fake()->createWithContent('reissue.csv', $reissue),
                'column_map' => $map,
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.error_rows', 0);

        $shown = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets/'.$assetId);
        $shown->assertOk()
            ->assertJsonPath('data.serial_number', 'SN-REISSUE')
            ->assertJsonPath('data.assignee.email', 'second.reissue@test.localhost');
        $assignments = collect($shown->json('data.assignments'));
        $this->assertCount(2, $assignments);
        $this->assertTrue($assignments->contains(fn (array $row): bool => $row['status'] === 'CLOSED' && ($row['employee']['email'] ?? null) === 'first.reissue@test.localhost'));
        $this->assertTrue($assignments->contains(fn (array $row): bool => $row['status'] === 'ACTIVE' && ($row['employee']['email'] ?? null) === 'second.reissue@test.localhost'));

        $fileRows = "Serial Number,Type,Status,Employee Email\nSN-FILE,laptop,ASSIGNED,first.reissue@test.localhost\nSN-FILE,laptop,ASSIGNED,second.reissue@test.localhost\n";
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/assets/import/run', [
                'file' => UploadedFile::fake()->createWithContent('file.csv', $fileRows),
                'column_map' => $map,
                'dry_run' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('data.would_create', 1)
            ->assertJsonPath('data.would_update', 1)
            ->assertJsonPath('data.error_rows', 0);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/assets/import/run', [
                'file' => UploadedFile::fake()->createWithContent('file.csv', $fileRows),
                'column_map' => $map,
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.error_rows', 0);

        $listed = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/assets?q=SN-FILE');
        $listed->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame('second.reissue@test.localhost', $listed->json('data.0.assignee.email'));
    }

    public function test_import_preview_flags_a_duplicate_serial(): void
    {
        $this->createAsset('SN-IMPORT');
        $csv = "Serial Number,Type,Status\nSN-IMPORT,laptop,AVAILABLE\nSN-NEW,laptop,AVAILABLE\n";
        $file = UploadedFile::fake()->createWithContent('assets.csv', $csv);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/assets/import/preview', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.errors', 1)
            ->assertJsonPath('data.valid', 1);
    }

    private function createAsset(string $serial): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assets', [
                'serial_number' => $serial,
                'asset_type' => 'laptop',
            ]);
        $response->assertCreated();

        return (string) $response->json('data.id');
    }
}
