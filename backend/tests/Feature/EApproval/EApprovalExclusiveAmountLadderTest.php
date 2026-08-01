<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

/**
 * Exclusive Low / Mid / High amount ladder + gap compaction + boundary cases.
 */
final class EApprovalExclusiveAmountLadderTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $low;

    private TenantUser $mid;

    private TenantUser $high;

    private TenantUser $sharedA;

    private TenantUser $sharedB;

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
        $this->low = $this->makeApprover('Low', 'ladder.low@test.localhost');
        $this->mid = $this->makeApprover('Mid', 'ladder.mid@test.localhost');
        $this->high = $this->makeApprover('High', 'ladder.high@test.localhost');
        $this->sharedA = $this->makeApprover('Shared A', 'ladder.a@test.localhost');
        $this->sharedB = $this->makeApprover('Shared B', 'ladder.b@test.localhost');
        tenancy()->end();
    }

    public function test_compact_step_orders_closes_editor_gaps_preserving_parallel_ties(): void
    {
        $compacted = EApprovalWorkflowStepDefinitionSupport::compactStepOrdersPreservingTies([
            ['type' => 'user', 'approverId' => 'a', 'step_order' => 1],
            ['type' => 'user', 'approverId' => 'b', 'step_order' => 2],
            ['type' => 'user', 'approverId' => 'c', 'step_order' => 6],
            ['type' => 'user', 'approverId' => 'd', 'step_order' => 6],
            ['type' => 'user', 'approverId' => 'e', 'step_order' => 9],
        ]);

        $this->assertSame([1, 2, 3, 3, 4], array_column($compacted, 'step_order'));
    }

    public function test_form_save_compacts_gapped_step_orders(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Gap compact form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->low->id, 'step_order' => 1],
                    ['type' => 'user', 'approverId' => (string) $this->mid->id, 'step_order' => 6],
                    ['type' => 'user', 'approverId' => (string) $this->sharedA->id, 'step_order' => 6],
                    ['type' => 'user', 'approverId' => (string) $this->high->id, 'step_order' => 9],
                ],
            ]);

        $create->assertCreated();
        $formId = (string) $create->json('data.form.id');

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/forms/{$formId}");

        $show->assertOk();
        $orders = collect($show->json('data.steps'))->pluck('step_order')->all();
        $this->assertSame([1, 2, 2, 3], array_map('intval', $orders));
    }

    #[DataProvider('amountBandProvider')]
    public function test_exclusive_bands_activate_expected_first_gates(
        string $amount,
        array $expectedFirstApproverEmails,
        int $expectedCompiledCount,
    ): void {
        $formId = $this->createExclusiveLadderForm(withGap: true);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => "Amount {$amount}",
                    'non_po' => $amount,
                    'approver_list' => json_encode([
                        (string) $this->sharedA->id,
                        (string) $this->sharedB->id,
                    ], JSON_THROW_ON_ERROR),
                ],
            ]);

        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $compiled = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();
        $this->assertCount($expectedCompiledCount, $compiled);

        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with(['step', 'approver'])
            ->get();

        $this->assertTrue($pending->every(static fn ($row) => (int) $row->step?->step_order === 1));
        $this->assertEqualsCanonicalizing(
            $expectedFirstApproverEmails,
            $pending->pluck('approver.email')->map(static fn ($e) => (string) $e)->all(),
        );

        // Drive through remaining bands so the request never sticks.
        $guard = 0;
        while (
            EApprovalSubmission::query()->findOrFail($submissionId)->status === 'pending'
            && $guard < 20
        ) {
            $next = EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->with('approver')
                ->first();
            $this->assertNotNull($next, 'Workflow stuck with no pending approver');
            $this->actingAs($next->approver, 'sanctum')
                ->withHeaders($this->tenantApiHeaders())
                ->postJson("/api/v1/e-approval/approvals/{$next->id}/decide", [
                    'decision' => 'approved',
                ])
                ->assertOk();
            $guard++;
        }

        $this->assertSame('approved', EApprovalSubmission::query()->findOrFail($submissionId)->status);
        $this->assertSame(
            0,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->whereHas('step', static fn ($q) => $q->whereNull('compiled_for_submission_id'))
                ->count(),
        );
    }

    public static function amountBandProvider(): array
    {
        return [
            'low boundary 5000' => ['5000', ['ladder.low@test.localhost'], 6],
            'mid just over 5000' => ['5001', ['ladder.mid@test.localhost'], 6],
            'mid boundary 20000' => ['20000', ['ladder.mid@test.localhost'], 6],
            'high just over 20000' => ['20001', ['ladder.high@test.localhost'], 6],
            'empty amount starts on shared parallel' => [
                '',
                ['ladder.a@test.localhost', 'ladder.b@test.localhost'],
                5,
            ],
        ];
    }

    public function test_empty_amount_skips_exclusive_gates_and_starts_on_shared_parallel(): void
    {
        $formId = $this->createExclusiveLadderForm(withGap: false);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => 'No amount',
                    'non_po' => '',
                    'approver_list' => json_encode([
                        (string) $this->sharedA->id,
                    ], JSON_THROW_ON_ERROR),
                ],
            ]);

        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with(['step', 'approver'])
            ->get();

        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(static fn ($row) => (int) $row->step?->step_order === 1));
        $this->assertEqualsCanonicalizing(
            ['ladder.a@test.localhost', 'ladder.b@test.localhost'],
            $pending->pluck('approver.email')->map(static fn ($e) => (string) $e)->all(),
        );
    }

    private function makeApprover(string $name, string $email): TenantUser
    {
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('e_approval_approver');

        return $user;
    }

    private function createExclusiveLadderForm(bool $withGap): string
    {
        // Intentional editor gap (6 then 9) when withGap=true — sync must compact.
        $sharedOrder = $withGap ? 6 : 4;
        $listOrder = $withGap ? 8 : 5;
        $finalOrder = $withGap ? 9 : 6;

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Exclusive amount ladder',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'number', 'name' => 'non_po', 'label' => 'Non-PO'],
                    ['type' => 'approver_list', 'name' => 'approver_list', 'label' => 'Approver list'],
                ],
                'steps' => [
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->low->id,
                        'step_order' => 1,
                        'when' => [['field' => 'non_po', 'operator' => 'lte', 'value' => '5000']],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->mid->id,
                        'step_order' => 2,
                        'when' => [
                            ['field' => 'non_po', 'operator' => 'gt', 'value' => '5000'],
                            ['field' => 'non_po', 'operator' => 'lte', 'value' => '20000'],
                        ],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->high->id,
                        'step_order' => 3,
                        'when' => [['field' => 'non_po', 'operator' => 'gt', 'value' => '20000']],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->sharedA->id,
                        'step_order' => $sharedOrder,
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->sharedB->id,
                        'step_order' => $sharedOrder,
                    ],
                    [
                        'type' => 'user_list',
                        'approverId' => 'approver_list',
                        'step_order' => $listOrder,
                        'parallel_mode' => 'any',
                        'fallback_approver_id' => (string) $this->low->id,
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->low->id,
                        'step_order' => $finalOrder,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }
}
