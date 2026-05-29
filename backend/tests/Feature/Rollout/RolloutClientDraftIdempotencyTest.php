<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteCandidate;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutClientDraftIdempotencyTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_duplicate_client_draft_id_returns_existing_candidate(): void
    {
        tenancy()->initialize($this->testTenant);
        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-DRAFT-ID',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);
        tenancy()->end();

        $draftId = (string) Str::uuid();
        $body = [
            'client_draft_id' => $draftId,
            'label' => 'Field candidate',
            'latitude' => 14.676,
            'longitude' => 121.0437,
        ];

        $first = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/candidates', $body);

        $first->assertCreated();

        $candidateId = $first->json('data.id');

        $second = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/candidates', array_merge($body, [
                'label' => 'Different label should be ignored',
            ]));

        $second->assertOk()
            ->assertJsonPath('data.id', $candidateId);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, SiteCandidate::query()->where('rollout_program_id', $rollout->id)->count());
        tenancy()->end();
    }

    public function test_duplicate_client_draft_id_returns_existing_hunting_log(): void
    {
        tenancy()->initialize($this->testTenant);
        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-DRAFT-LOG',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);
        tenancy()->end();

        $draftId = (string) Str::uuid();
        $body = [
            'client_draft_id' => $draftId,
            'log_date' => '2026-05-19',
            'summary' => 'Scouted two rooftops',
        ];

        $first = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/hunting-logs', $body);

        $first->assertCreated();
        $logId = $first->json('data.id');

        $second = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/hunting-logs', array_merge($body, [
                'summary' => 'Updated summary ignored on retry',
            ]));

        $second->assertOk()
            ->assertJsonPath('data.id', $logId);
    }

    public function test_duplicate_client_draft_id_returns_existing_cme_report(): void
    {
        tenancy()->initialize($this->testTenant);
        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-DRAFT-CME',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'construction',
            'sla_working_days' => 115,
        ]);
        tenancy()->end();

        $draftId = (string) Str::uuid();
        $body = [
            'client_draft_id' => $draftId,
            'report_date' => '2026-05-19',
            'physical_progress_pct' => 42,
        ];

        $first = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/cme-reports', $body);

        $first->assertCreated();
        $reportId = $first->json('data.id');

        $second = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/cme-reports', array_merge($body, [
                'physical_progress_pct' => 99,
            ]));

        $second->assertOk()
            ->assertJsonPath('data.id', $reportId);
    }
}
