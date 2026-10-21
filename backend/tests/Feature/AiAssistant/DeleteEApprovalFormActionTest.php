<?php

declare(strict_types=1);

namespace Tests\Feature\AiAssistant;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AiAssistant\Services\Actions\DeleteEApprovalFormAction;
use App\Modules\AiAssistant\Services\Actions\RevertLastAdminChangeAction;
use App\Modules\AiAssistant\Services\AssistantCatalogRunner;
use App\Modules\AiAssistant\Services\AssistantOperationCatalog;
use App\Modules\AiAssistant\Support\AssistantRolePolicy;
use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DeleteEApprovalFormActionTest extends TestCase
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
                'ai_assistant',
                'e_approval',
            ],
            'toweros.tenant_files.disk' => 'local',
        ]);

        $this->bootInMemoryTenantApi();
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_tenant_admin_can_delete_a_named_form_with_its_submissions_and_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('e-approval/test-file.pdf', 'pdf');
        Storage::disk('local')->put('e-approval/cash-advance.pdf', 'pdf');

        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Document Control Form (TEST)',
            'status' => 'published',
        ]);
        $other = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Cash Advance',
            'status' => 'published',
        ]);
        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'DCF-TEST-0001',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);
        $otherSubmission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'CA-TEST-0001',
            'form_id' => $other->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);
        EApprovalAttachment::query()->create([
            'submission_id' => $submission->id,
            'field_name' => 'attachment',
            'file_path' => 'e-approval/test-file.pdf',
            'file_name' => 'test-file.pdf',
        ]);
        EApprovalAttachment::query()->create([
            'submission_id' => $otherSubmission->id,
            'field_name' => 'attachment',
            'file_path' => 'e-approval/cash-advance.pdf',
            'file_name' => 'cash-advance.pdf',
        ]);

        $draft = app(DeleteEApprovalFormAction::class)->propose(
            $this->testTenantAdmin,
            'Remove Document Control Form (TEST) and Cash Advance, with submissions and attachments',
            ['form_name' => 'Document Control Form (TEST) | Cash Advance'],
        );
        $this->assertSame(2, EApprovalForm::query()->count());
        $this->assertContains('Form: Document Control Form (TEST)', $draft->preview['records']);
        $this->assertContains('Submission DCF-TEST-0001 · pending · admin@test.localhost', $draft->preview['records']);
        $this->assertContains('Attachment test-file.pdf', $draft->preview['records']);
        $this->assertContains('Form: Cash Advance', $draft->preview['records']);
        $this->assertContains('Attachment cash-advance.pdf', $draft->preview['records']);

        $result = app(DeleteEApprovalFormAction::class)->execute($this->testTenantAdmin, $draft->payload);

        $this->assertTrue($result->ok);
        $this->assertStringContainsString('Document Control Form (TEST)', (string) $result->entityLabel);
        $this->assertStringContainsString('Cash Advance', (string) $result->entityLabel);
        $this->assertSame(0, EApprovalForm::query()->whereKey($form->id)->count());
        $this->assertSame(0, EApprovalForm::query()->whereKey($other->id)->count());
        $this->assertSame(0, EApprovalSubmission::query()->whereKey($submission->id)->count());
        $this->assertSame(0, EApprovalSubmission::query()->whereKey($otherSubmission->id)->count());
        $this->assertSame(0, EApprovalAttachment::query()->where('submission_id', $submission->id)->count());
        Storage::disk('local')->assertMissing('e-approval/test-file.pdf');
        Storage::disk('local')->assertMissing('e-approval/cash-advance.pdf');

        $revert = app(RevertLastAdminChangeAction::class)->propose(
            $this->testTenantAdmin,
            'revert that deletion',
            [],
        );
        $this->assertContains('Form: Document Control Form (TEST)', $revert->preview['records']);
        $this->assertContains('Form: Cash Advance', $revert->preview['records']);
        $restored = app(RevertLastAdminChangeAction::class)->execute($this->testTenantAdmin, $revert->payload);
        $this->assertTrue($restored->ok);
        $this->assertSame(1, EApprovalForm::query()->whereKey($form->id)->count());
        $this->assertSame(1, EApprovalSubmission::query()->whereKey($submission->id)->count());
        $this->assertSame(1, EApprovalAttachment::query()->where('submission_id', $submission->id)->count());

        $names = array_map(
            static fn (array $entry): string => $entry['kind'].':'.$entry['name'],
            app(AssistantOperationCatalog::class)->forViewer($this->testTenantAdmin),
        );
        $this->assertContains('write:delete_eapproval_form', $names);
        $this->assertTrue(app(AssistantRolePolicy::class)->allowsTool(
            $this->testTenantAdmin,
            'execute_sql',
            ['sql' => "DELETE FROM e_approval_forms WHERE name = 'Document Control Form (TEST)'"],
        ));
        tenancy()->end();
    }

    public function test_other_roles_cannot_delete_the_form_or_run_delete_sql(): void
    {
        tenancy()->initialize($this->testTenant);
        $staff = TenantUser::query()->create([
            'name' => 'Forms Admin',
            'email' => 'forms-admin@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $staff->assignRole('e_approval_admin');

        $names = array_map(
            static fn (array $entry): string => $entry['kind'].':'.$entry['name'],
            app(AssistantOperationCatalog::class)->forViewer($staff),
        );
        $this->assertNotContains('write:delete_eapproval_form', $names);
        $this->assertFalse(app(AssistantRolePolicy::class)->allowsTool(
            $staff,
            'execute_sql',
            ['sql' => 'DELETE FROM e_approval_forms WHERE id = 1'],
        ));
        $this->assertFalse(app(AssistantCatalogRunner::class)->writeMatchesQuestion(
            'What is Document Control Form (TEST)?',
            'delete_eapproval_form',
        ));
        $this->assertTrue(app(AssistantCatalogRunner::class)->writeMatchesQuestion(
            'Remove Document Control Form (TEST) and the attachments',
            'delete_eapproval_form',
        ));

        try {
            app(DeleteEApprovalFormAction::class)->propose($staff, 'Remove Document Control Form (TEST)', [
                'form_name' => 'Document Control Form (TEST)',
            ]);
            $this->fail('A non-owner role was allowed to propose the delete.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('tenant administrator', (string) collect($e->errors())->flatten()->first());
        }
        tenancy()->end();
    }
}
