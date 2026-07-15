<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Services\ControlledDocumentEApprovalValuesService;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalDocumentSequenceService;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ControlledDocumentTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'documents', 'document_register', 'e_approval',
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        Storage::fake('tenant_files');
        config(['toweros.tenant_files.disk' => 'tenant_files']);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_csv_import_creates_published_master_record(): void
    {
        $csv = <<<CSV
document_code,title,document_type,department,revision_number,effective_date
ATC-QMS-P-001,Quality Policy,Policies and Procedures,QMS,0,2025-05-20
CSV;

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import', [
                'file' => UploadedFile::fake()->createWithContent('import.csv', $csv),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 1);

        tenancy()->initialize($this->testTenant);
        $document = ControlledDocument::query()->where('document_code', 'ATC-QMS-P-001')->first();
        $this->assertNotNull($document);
        $this->assertSame('Quality Policy', $document->title);
        $this->assertSame(0, $document->current_revision);
        $this->assertSame(ControlledDocumentStatus::PUBLISHED, $document->status);
        tenancy()->end();
    }

    public function test_approved_submission_with_sync_metadata_publishes_to_registry(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Document Control',
            'status' => 'published',
            'metadata_json' => json_encode([
                'controlledDocumentSync' => ['enabled' => true],
            ], JSON_THROW_ON_ERROR),
        ]);

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'ATC-QMS-P-002',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => EApprovalSubmissionStatus::APPROVED,
            'current_step' => 1,
        ]);

        tenancy()->end();

        tenancy()->initialize($this->testTenant);
        app(\App\Modules\Documents\Services\ControlledDocumentEApprovalHookService::class)
            ->afterSubmissionMutation($submission->fresh(['form', 'values.field', 'attachments']), $this->testTenantAdmin);
        $document = ControlledDocument::query()->where('document_code', 'ATC-QMS-P-002')->first();
        $this->assertNotNull($document);
        $this->assertSame(0, $document->current_revision);
        $this->assertCount(1, $document->revisions);
        tenancy()->end();
    }

    public function test_controlled_index_requires_permission(): void
    {
        $viewer = $this->createViewerUser();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled')
            ->assertForbidden();
    }

    public function test_lookup_returns_next_revision_for_existing_document(): void
    {
        tenancy()->initialize($this->testTenant);
        ControlledDocument::query()->create([
            'id' => (string) Str::uuid(),
            'document_code' => 'ATC-QMS-P-010',
            'title' => 'Quality Manual',
            'document_type' => 'P',
            'department' => 'QMS',
            'current_revision' => 2,
            'status' => ControlledDocumentStatus::PUBLISHED,
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled/lookup?document_code=ATC-QMS-P-010')
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.current_revision', 2)
            ->assertJsonPath('data.next_revision', 3);
    }

    public function test_auto_revision_assigns_next_number_when_field_blank(): void
    {
        tenancy()->initialize($this->testTenant);

        ControlledDocument::query()->create([
            'id' => (string) Str::uuid(),
            'document_code' => 'ATC-QMS-P-011',
            'title' => 'Policy',
            'current_revision' => 1,
            'status' => ControlledDocumentStatus::PUBLISHED,
        ]);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'status' => 'published',
            'metadata_json' => json_encode([
                'controlledDocumentSync' => [
                    'enabled' => true,
                    'autoRevision' => true,
                    'documentCodeField' => 'document_code',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        $service = app(ControlledDocumentEApprovalValuesService::class);
        $prepared = $service->prepareForSubmit($form, [
            'document_code' => 'ATC-QMS-P-011',
            'title' => 'Policy',
        ], fn (): string => 'ATC-QMS-P-999');

        $this->assertSame('ATC-QMS-P-011-R001', $prepared['document_no']);
        $this->assertSame('ATC-QMS-P-011', $prepared['values']['document_code']);
        $this->assertSame('2', $prepared['values']['revision_number']);

        $newDoc = $service->prepareForSubmit($form, [
            'title' => 'New policy',
        ], fn (): string => 'ATC-QMS-P-020');

        $this->assertSame('ATC-QMS-P-020', $newDoc['document_no']);
        $this->assertSame('ATC-QMS-P-020', $newDoc['values']['document_code']);
        $this->assertSame('0', $newDoc['values']['revision_number']);

        tenancy()->end();
    }

    public function test_document_number_template_uses_department_and_document_type(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'status' => 'published',
            'doc_no_custom_enabled' => true,
            'doc_no_template' => 'ATC-{department}-{documentType}-{seq:3}',
        ]);

        $number = app(EApprovalDocumentSequenceService::class)->nextDocumentNumber($form, [
            'department' => 'QMS',
            'document_type' => 'P',
        ]);

        $this->assertSame('ATC-QMS-P-001', $number);

        tenancy()->end();
    }

    public function test_register_access_can_be_read_and_updated_by_manager(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Document Control',
            'status' => 'published',
            'metadata_json' => [
                'form_family' => 'iso_document_control',
                'controlledDocumentSync' => [
                    'enabled' => true,
                    'accessPolicy' => [
                        'viewerRoles' => ['member'],
                        'fullAccessRoles' => ['document_controller'],
                        'roleDepartmentMap' => ['member' => ['QMS']],
                    ],
                ],
            ],
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled/register-access')
            ->assertOk()
            ->assertJsonPath('data.form_id', $form->id)
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.access_policy.viewer_roles.0', 'member')
            ->assertJsonPath('data.access_policy.role_department_map.member.0', 'QMS');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/documents/controlled/register-access', [
                'viewer_roles' => ['viewer'],
                'full_access_roles' => ['quality_manager'],
                'role_department_map' => ['viewer' => ['PMO', 'QMS']],
            ])
            ->assertOk()
            ->assertJsonPath('data.access_policy.viewer_roles.0', 'viewer')
            ->assertJsonPath('data.access_policy.full_access_roles.0', 'quality_manager')
            ->assertJsonPath('data.access_policy.role_department_map.viewer.1', 'QMS');

        tenancy()->initialize($this->testTenant);
        $form->refresh();
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        if ($metadata === [] && is_string($form->metadata_json)) {
            $metadata = json_decode($form->metadata_json, true) ?: [];
        }
        $policy = $metadata['controlledDocumentSync']['accessPolicy'] ?? [];
        $this->assertSame(['viewer'], $policy['viewerRoles'] ?? null);
        $this->assertSame(['PMO', 'QMS'], $policy['roleDepartmentMap']['viewer'] ?? null);
        tenancy()->end();
    }

    public function test_controlled_document_index_requires_document_register_module(): void
    {
        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'documents', 'e_approval',
            ],
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled')
            ->assertForbidden();
    }

    private function createViewerUser(): TenantUser
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => 'Docs Viewer',
            'email' => 'docs.viewer@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('viewer');
        tenancy()->end();

        return $user;
    }
}
