<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRouter;
use App\Modules\AiAssistant\Services\AssistantOperationCatalog;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Str;
use Tests\Support\AiAssistant\BindsDeterministicAssistantLlm;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class AssistantRejectEApprovalActionTest extends TestCase
{
    use BindsDeterministicAssistantLlm;
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
                'ai_assistant',
                'e_approval',
            ],
            'ai_assistant.enabled' => true,
            'ai_assistant.embedding_provider' => 'local',
            'ai_assistant.vector_store' => 'database',
            'ai_assistant.llm_provider' => 'cursor',
            'ai_assistant.tools.enabled' => true,
            'ai_assistant.actions.enabled' => true,
        ]);

        $this->bindDeterministicAssistantLlm();
        $this->bootInMemoryTenantApi();
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_reject_command_matches_only_when_a_document_number_is_present(): void
    {
        $router = app(AssistantActionRouter::class);

        $this->assertSame(
            'reject_eapproval_submission',
            $router->match('ATC-DOC-EAPPROVAL--X-004 Can you reject the request?')['action'] ?? null,
        );
        $this->assertSame(
            'cancel_eapproval_submission',
            $router->match('ATC-DOC-EAPPROVAL--X-008 Cancel this request')['action'] ?? null,
        );
        $this->assertNull($router->match('How do I reject a request?'));

        tenancy()->initialize($this->testTenant);
        $this->testTenant->enabled_modules = ['core', 'team_access', 'ai_assistant', 'e_approval'];
        $names = array_map(
            static fn (array $entry): string => $entry['kind'].':'.$entry['name'],
            app(AssistantOperationCatalog::class)->forViewer($this->testTenantAdmin),
        );
        tenancy()->end();

        $this->assertContains('write:reject_eapproval_submission', $names);
        $this->assertContains('write:cancel_eapproval_submission', $names);
        $this->assertNotContains('write:change_it_asset_status', $names);
    }

    public function test_confirm_rejects_the_pending_submission(): void
    {
        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Approval draft test',
            'status' => 'published',
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
            'document_no' => 'ATC-DOC-EAPPROVAL--X-004',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);
        EApprovalRequestApproval::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'step_id' => $step->id,
            'approver_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
        ]);
        tenancy()->end();

        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'ATC-DOC-EAPPROVAL--X-004 Can you reject the request?',
                'module_context' => 'e_approval',
                'page_path' => '/e-approval/submissions',
            ]);

        $ask->assertOk()
            ->assertJsonPath('data.answer', 'ATC-DOC-EAPPROVAL--X-004 is now rejected')
            ->assertJsonPath('data.proposed_action', null);

        tenancy()->initialize($this->testTenant);
        $this->assertSame('rejected', EApprovalSubmission::query()->where('document_no', 'ATC-DOC-EAPPROVAL--X-004')->value('status'));
        tenancy()->end();
    }

    public function test_cancel_command_proposes_cancel_and_confirm_cancels(): void
    {
        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Approval draft test',
            'status' => 'published',
        ]);
        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'ATC-DOC-EAPPROVAL--X-008',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);
        tenancy()->end();

        $ask = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/assistant/ask', [
                'question' => 'ATC-DOC-EAPPROVAL--X-008 Cancel this request',
                'module_context' => 'e_approval',
                'page_path' => '/e-approval/submissions',
            ]);

        $ask->assertOk()
            ->assertJsonPath('data.answer', 'ATC-DOC-EAPPROVAL--X-008 is now cancelled')
            ->assertJsonPath('data.proposed_action', null);

        tenancy()->initialize($this->testTenant);
        $this->assertSame('cancelled', EApprovalSubmission::query()->find($submission->id)?->status);
        tenancy()->end();
    }
}
