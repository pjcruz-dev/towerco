<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalAnalyticsTest extends TestCase
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

    public function test_analytics_requires_audit_permission_shape(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Analytics Form',
            'category' => 'ops',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'OPS',
            'doc_type_code' => 'AN',
        ]);

        $template = EApprovalWorkflowTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
        ]);

        $step = EApprovalWorkflowStep::query()->create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_id' => (string) $this->testTenantAdmin->id,
        ]);

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'AN-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        EApprovalRequestApproval::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'step_id' => $step->id,
            'approver_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
        ]);

        $approved = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'AN-002',
            'status' => EApprovalSubmissionStatus::APPROVED,
            'current_step' => 1,
        ]);
        $approved->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ])->save();

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/reports/analytics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period' => ['from', 'to', 'days'],
                    'kpis',
                    'submissions_over_time',
                    'by_status',
                    'top_forms',
                    'cycle_times',
                    'bottlenecks',
                    'approver_load',
                    'aging',
                    'rejection_reasons',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.kpis'));
        $this->assertGreaterThanOrEqual(1, count($response->json('data.top_forms')));
        $this->assertSame('Analytics Form', $response->json('data.top_forms.0.label'));
        $this->assertGreaterThanOrEqual(1, count($response->json('data.bottlenecks')));
        $this->assertGreaterThanOrEqual(1, count($response->json('data.approver_load')));
    }

    public function test_analytics_respects_date_range(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Range Form',
            'category' => 'ops',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'OPS',
            'doc_type_code' => 'RG',
        ]);

        $old = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'RG-OLD',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);
        $old->forceFill([
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ])->save();

        $new = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'RG-NEW',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);
        $new->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->save();

        tenancy()->end();

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/reports/analytics?from='.$from.'&to='.$to);

        $response->assertOk()
            ->assertJsonPath('data.period.from', $from)
            ->assertJsonPath('data.period.to', $to);

        $kpi = collect($response->json('data.kpis'))->firstWhere('key', 'submissions_period');
        $this->assertSame('1', $kpi['value'] ?? null);
    }
}
