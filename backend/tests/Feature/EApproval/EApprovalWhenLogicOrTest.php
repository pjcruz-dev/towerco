<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalWhenLogicOrTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        Mail::fake();
        Notification::fake();

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        $this->approver = TenantUser::query()->create([
            'name' => 'OR Approver',
            'email' => 'or-approver@test.localhost',
            'password' => 'password',
        ]);
        $this->approver->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_or_when_logic_activates_step_when_any_condition_matches(): void
    {
        $formId = $this->createOrForm();

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'urgent' => 'no',
                    'amount' => '9000',
                ],
            ]);

        $subRes->assertCreated();
        $this->assertSame(
            1,
            EApprovalRequestApproval::query()
                ->where('submission_id', $subRes->json('data.id'))
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->count(),
        );
    }

    public function test_or_when_logic_skips_step_when_no_condition_matches(): void
    {
        $formId = $this->createOrForm();

        $preview = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/workflow-preview", [
                'values' => [
                    'urgent' => 'no',
                    'amount' => '10',
                ],
            ]);

        $preview->assertOk();
        $this->assertSame([], $preview->json('data.resolved_steps'));
        $this->assertNotEmpty($preview->json('data.skipped_steps'));
    }

    public function test_form_detail_returns_when_logic_or(): void
    {
        $formId = $this->createOrForm();

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/forms/{$formId}");

        $detail->assertOk();
        $step = $detail->json('data.steps.0');
        $this->assertSame('or', $step['when_logic'] ?? null);
        $this->assertCount(2, $step['when'] ?? []);
    }

    private function createOrForm(): string
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'OR Logic Form',
                'description' => 'Match any condition',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'urgent', 'label' => 'Urgent'],
                    ['type' => 'number', 'name' => 'amount', 'label' => 'Amount'],
                ],
                'steps' => [
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->approver->id,
                        'step_order' => 1,
                        'when_logic' => 'or',
                        'when' => [
                            ['field' => 'urgent', 'operator' => 'equals', 'value' => 'yes'],
                            ['field' => 'amount', 'operator' => 'gt', 'value' => '5000'],
                        ],
                    ],
                ],
            ]);

        $formRes->assertCreated();

        return (string) $formRes->json('data.form.id');
    }
}
