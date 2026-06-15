<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Models\Tenant;
use App\Models\TenantBillingRfiCompletion;
use App\Modules\Billing\Services\TenantRfiMeterService;
use App\Modules\Rollout\Models\RolloutProgram;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantRfiMeterServiceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
        $this->ensureHybridBillingSchema();
    }

    public function test_rfi_not_blocked_before_meter_starts(): void
    {
        $service = app(TenantRfiMeterService::class);
        $program = $this->makeRolloutProgram();

        $service->assertCanRecordRfi($this->testTenant, $program, Carbon::parse('2026-06-01'));

        $this->assertSame(0, $service->billableCount($this->testTenant));
    }

    public function test_rfi_blocked_when_limit_reached_after_go_live(): void
    {
        $this->testTenant->billing_meter_starts_at = Carbon::parse('2026-06-01');
        $this->testTenant->billing_overrides = ['included_rfi_units' => 1];
        $this->testTenant->save();

        TenantBillingRfiCompletion::query()->create([
            'tenant_id' => $this->testTenant->id,
            'rollout_id' => (string) Str::uuid(),
            'rfi_at' => Carbon::parse('2026-06-02'),
        ]);

        $service = app(TenantRfiMeterService::class);
        $program = $this->makeRolloutProgram();

        $this->expectException(ValidationException::class);
        $service->assertCanRecordRfi($this->testTenant, $program, Carbon::parse('2026-06-10'));
    }

    public function test_rfi_before_go_live_does_not_count_toward_limit(): void
    {
        $this->testTenant->billing_meter_starts_at = Carbon::parse('2026-06-10');
        $this->testTenant->billing_overrides = ['included_rfi_units' => 1];
        $this->testTenant->save();

        $service = app(TenantRfiMeterService::class);
        $program = $this->makeRolloutProgram();

        $service->assertCanRecordRfi($this->testTenant, $program, Carbon::parse('2026-06-01'));
        $service->recordCompletion($this->testTenant, $program, Carbon::parse('2026-06-01'));

        $this->assertSame(0, $service->billableCount($this->testTenant));
    }

    public function test_grandfather_units_increase_effective_limit(): void
    {
        $this->testTenant->billing_meter_starts_at = Carbon::parse('2026-06-01');
        $this->testTenant->billing_overrides = [
            'included_rfi_units' => 1,
            'grandfather_rfi_units' => 2,
        ];
        $this->testTenant->save();

        $service = app(TenantRfiMeterService::class);

        $this->assertSame(3, $service->rfiLimit($this->testTenant));
    }

    private function makeRolloutProgram(): RolloutProgram
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $program */
        $program = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-METER-001',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'permitting',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 120,
        ]);

        tenancy()->end();

        return $program;
    }

    private function ensureHybridBillingSchema(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
            if (! Schema::connection('central')->hasColumn('tenants', 'billing_meter_starts_at')) {
                $table->timestamp('billing_meter_starts_at')->nullable();
            }
            if (! Schema::connection('central')->hasColumn('tenants', 'billing_interval')) {
                $table->string('billing_interval', 16)->default('monthly');
            }
            if (! Schema::connection('central')->hasColumn('tenants', 'billing_overrides')) {
                $table->json('billing_overrides')->nullable();
            }
        });

        if (! Schema::connection('central')->hasTable('platform_billing_settings')) {
            Schema::connection('central')->create('platform_billing_settings', function (Blueprint $table): void {
                $table->unsignedTinyInteger('id')->primary();
                $table->string('currency', 8)->default('USD');
                $table->decimal('default_annual_discount_percent', 5, 2)->default(20);
                $table->json('tier_overrides')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::connection('central')->hasTable('tenant_billing_rfi_completions')) {
            Schema::connection('central')->create('tenant_billing_rfi_completions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('tenant_id');
                $table->uuid('rollout_id');
                $table->uuid('site_id')->nullable();
                $table->timestamp('rfi_at');
                $table->timestamps();
                $table->unique(['tenant_id', 'rollout_id']);
            });
        }
    }
}
