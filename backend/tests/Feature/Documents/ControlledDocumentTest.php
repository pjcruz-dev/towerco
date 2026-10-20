<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Services\ControlledDocumentDcfNumberService;
use App\Modules\Documents\Services\ControlledDocumentEApprovalHookService;
use App\Modules\Documents\Services\ControlledDocumentEApprovalValuesService;
use App\Modules\Documents\Services\ControlledDocumentPendingImportService;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\EApproval\Services\EApprovalDocumentSequenceService;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
        $csv = <<<'CSV'
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
        app(ControlledDocumentEApprovalHookService::class)
            ->afterSubmissionMutation($submission->fresh(['form', 'values.field', 'attachments']), $this->testTenantAdmin);
        $document = ControlledDocument::query()->where('document_code', 'ATC-QMS-P-002')->first();
        $this->assertNotNull($document);
        $this->assertSame(0, $document->current_revision);
        $this->assertCount(1, $document->revisions);
        tenancy()->end();
    }

    public function test_pending_submission_is_listed_until_it_is_approved_or_withdrawn(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'status' => 'published',
            'metadata_json' => json_encode([
                'controlledDocumentSync' => ['enabled' => true],
            ], JSON_THROW_ON_ERROR),
        ]);

        $hook = app(ControlledDocumentEApprovalHookService::class);
        $pending = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'ATC-QMS-P-020',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        $hook->afterSubmissionOpened($pending->fresh(['form']), $this->testTenantAdmin);
        $document = ControlledDocument::query()->where('document_code', 'ATC-QMS-P-020')->first();
        $this->assertNotNull($document);
        $this->assertSame(ControlledDocumentStatus::PENDING, $document->status);
        $this->assertNull($document->published_at);

        $sourcePath = 'tenant/e-approval/'.$pending->id.'/sample.pdf';
        Storage::disk('tenant_files')->put($sourcePath, 'pending-file');
        EApprovalAttachment::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $pending->id,
            'field_name' => 'attachments',
            'file_path' => $sourcePath,
            'file_name' => 'Sample.pdf',
        ]);
        $hook->afterAttachmentStored($pending->fresh(['form', 'attachments']));
        $pendingRevision = $document->revisions()->first();
        $this->assertNotNull($pendingRevision);
        $this->assertSame('Sample.pdf', $pendingRevision->original_filename);
        $this->assertNotSame('', (string) $pendingRevision->stored_path);

        $published = ControlledDocument::query()->create([
            'id' => (string) Str::uuid(),
            'document_code' => 'ATC-QMS-P-001',
            'title' => 'Quality Policy',
            'current_revision' => 0,
            'status' => ControlledDocumentStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        $this->assertSame(ControlledDocumentStatus::PUBLISHED, $published->status);
        $revisionRequest = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'ATC-QMS-P-001-R001',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);
        $hook->afterSubmissionOpened($revisionRequest->fresh(['form']), $this->testTenantAdmin);
        $published->refresh();
        $this->assertSame(ControlledDocumentStatus::PUBLISHED, $published->status);
        $this->assertNull(ControlledDocument::query()->where('document_code', 'ATC-QMS-P-001-R001')->first());

        $pending->status = EApprovalSubmissionStatus::APPROVED;
        $pending->save();
        $hook->afterSubmissionMutation($pending->fresh(['form', 'values.field', 'attachments']), $this->testTenantAdmin);
        $document->refresh();
        $this->assertSame(ControlledDocumentStatus::PUBLISHED, $document->status);
        $this->assertNotNull($document->published_at);

        $withdrawn = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'ATC-QMS-P-021',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);
        $hook->afterSubmissionOpened($withdrawn->fresh(['form']), $this->testTenantAdmin);
        $this->assertNotNull(ControlledDocument::query()->where('document_code', 'ATC-QMS-P-021')->first());
        $withdrawn->status = EApprovalSubmissionStatus::CANCELLED;
        $withdrawn->save();
        $hook->afterSubmissionClosed($withdrawn);
        $this->assertNull(ControlledDocument::query()->where('document_code', 'ATC-QMS-P-021')->first());

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

    public function test_index_filters_by_review_file_effective_date_and_keeps_status_counts(): void
    {
        tenancy()->initialize($this->testTenant);

        $overdue = $this->makeControlledDocument('ATC-QMS-P-100', 'Quality Policy', [
            'department' => 'QMS',
            'document_type' => 'Policy',
            'status' => ControlledDocumentStatus::PUBLISHED,
            'effective_date' => '2024-01-15',
            'next_review_date' => now()->subDay()->toDateString(),
        ]);
        $this->makeRevision($overdue, storedPath: null);

        $dueSoon = $this->makeControlledDocument('ATC-OMD-P-100', 'Leasing Procedure', [
            'department' => 'OMD',
            'document_type' => 'Procedure',
            'status' => ControlledDocumentStatus::PUBLISHED,
            'effective_date' => '2026-03-01',
            'next_review_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->makeRevision($dueSoon, storedPath: 'controlled/leasing.docx');

        $obsolete = $this->makeControlledDocument('ATC-QMS-P-101', 'Retired Manual', [
            'department' => 'QMS',
            'document_type' => 'Manual',
            'status' => ControlledDocumentStatus::OBSOLETE,
            'effective_date' => '2020-06-01',
            'next_review_date' => null,
        ]);
        $this->makeRevision($obsolete, storedPath: null);

        tenancy()->end();

        $headers = $this->tenantApiHeaders();

        $this->actingAsTenantAdmin()
            ->withHeaders($headers)
            ->getJson('/api/v1/documents/controlled?review=overdue')
            ->assertOk()
            ->assertJsonPath('data.documents.total', 1)
            ->assertJsonPath('data.documents.data.0.document_code', 'ATC-QMS-P-100')
            ->assertJsonPath('data.documents.data.0.has_file', false);

        $this->actingAsTenantAdmin()
            ->withHeaders($headers)
            ->getJson('/api/v1/documents/controlled?file=has')
            ->assertOk()
            ->assertJsonPath('data.documents.total', 1)
            ->assertJsonPath('data.documents.data.0.document_code', 'ATC-OMD-P-100')
            ->assertJsonPath('data.documents.data.0.has_file', true);

        $this->actingAsTenantAdmin()
            ->withHeaders($headers)
            ->getJson('/api/v1/documents/controlled?file=missing&department=QMS')
            ->assertOk()
            ->assertJsonPath('data.documents.total', 2)
            ->assertJsonPath('data.kpis.total', 2)
            ->assertJsonPath('data.kpis.published', 1)
            ->assertJsonPath('data.kpis.obsolete', 1);

        $this->actingAsTenantAdmin()
            ->withHeaders($headers)
            ->getJson('/api/v1/documents/controlled?department=QMS&status=published')
            ->assertOk()
            ->assertJsonPath('data.documents.total', 1)
            ->assertJsonPath('data.kpis.published', 1)
            ->assertJsonPath('data.kpis.obsolete', 1)
            ->assertJsonPath('data.facets.departments.0', 'OMD');

        $this->actingAsTenantAdmin()
            ->withHeaders($headers)
            ->getJson('/api/v1/documents/controlled?effective_from=2026-01-01&effective_to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.documents.total', 1)
            ->assertJsonPath('data.documents.data.0.document_code', 'ATC-OMD-P-100');

        $this->actingAsTenantAdmin()
            ->withHeaders($headers)
            ->getJson('/api/v1/documents/controlled?review=nope')
            ->assertUnprocessable();
    }

    public function test_incoming_filter_lists_published_rows_with_an_open_revision(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'status' => 'published',
        ]);
        $incoming = $this->makeControlledDocument('ATC-QMS-P-200', 'Incoming Policy', [
            'status' => ControlledDocumentStatus::PUBLISHED,
        ]);
        $this->makeControlledDocument('ATC-QMS-P-201', 'Quiet Policy', [
            'status' => ControlledDocumentStatus::PUBLISHED,
        ]);
        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => 'ATC-QMS-P-200-R001',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => EApprovalSubmissionStatus::PENDING,
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled?status=incoming')
            ->assertOk()
            ->assertJsonPath('data.kpis.incoming', 1)
            ->assertJsonPath('data.kpis.published', 2)
            ->assertJsonPath('data.documents.total', 1)
            ->assertJsonPath('data.documents.data.0.document_code', 'ATC-QMS-P-200')
            ->assertJsonPath('data.documents.data.0.status', ControlledDocumentStatus::PUBLISHED)
            ->assertJsonPath('data.documents.data.0.incoming_revision', 1)
            ->assertJsonPath('data.documents.data.0.id', (string) $incoming->id);
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeControlledDocument(string $code, string $title, array $attributes): ControlledDocument
    {
        return ControlledDocument::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'document_code' => $code,
            'title' => $title,
            'current_revision' => 0,
            'status' => ControlledDocumentStatus::PUBLISHED,
        ], $attributes));
    }

    private function makeRevision(ControlledDocument $document, ?string $storedPath): void
    {
        ControlledDocumentRevision::query()->create([
            'id' => (string) Str::uuid(),
            'controlled_document_id' => $document->id,
            'revision_number' => (int) $document->current_revision,
            'status' => 'published',
            'stored_path' => $storedPath,
            'original_filename' => $storedPath !== null ? 'file.docx' : null,
        ]);
    }

    public function test_pending_import_opens_an_iso_approval_for_the_approver_email(): void
    {
        tenancy()->initialize($this->testTenant);

        $approver = TenantUser::query()->create([
            'name' => 'Pending Approver',
            'email' => 'pending.approver@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'status' => 'published',
            'metadata_json' => [
                'default_controlled_document_form' => true,
                'controlledDocumentSync' => [
                    'enabled' => true,
                    'documentCodeField' => 'document_code',
                    'fieldMap' => ['revision_number' => 'revision'],
                ],
            ],
        ]);
        $template = EApprovalWorkflowTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
        ]);
        EApprovalWorkflowStep::query()->create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_id' => (string) $this->testTenantAdmin->id,
        ]);

        ControlledDocument::query()->create([
            'id' => (string) Str::uuid(),
            'document_code' => 'ATC-QMS-P-030',
            'title' => 'Published Policy',
            'current_revision' => 0,
            'status' => ControlledDocumentStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        $service = app(ControlledDocumentPendingImportService::class);
        $created = $service->open([
            'document_code' => 'ATC-QMS-P-031',
            'title' => 'Waiting Policy',
            'status' => 'pending',
            'approver_email' => 'pending.approver@test.localhost',
            'pending_since' => '2026-10-01',
            'revision_number' => '0',
        ], $this->testTenantAdmin, null);

        $this->assertSame('created', $created);
        $document = ControlledDocument::query()->where('document_code', 'ATC-QMS-P-031')->first();
        $this->assertSame(ControlledDocumentStatus::PENDING, $document?->status);
        $submission = EApprovalSubmission::query()->where('document_no', 'ATC-QMS-P-031')->first();
        $this->assertNotNull($submission);
        $approval = EApprovalRequestApproval::query()->where('submission_id', $submission->id)->first();
        $this->assertSame((string) $approver->id, (string) $approval?->approver_id);

        $revised = $service->open([
            'document_code' => 'ATC-QMS-P-030',
            'title' => 'Published Policy',
            'status' => 'pending',
            'approver_email' => 'pending.approver@test.localhost',
        ], $this->testTenantAdmin, null);

        $this->assertSame('updated', $revised);
        $published = ControlledDocument::query()->where('document_code', 'ATC-QMS-P-030')->first();
        $this->assertSame(ControlledDocumentStatus::PUBLISHED, $published?->status);
        $this->assertNotNull(
            EApprovalSubmission::query()->where('document_no', 'like', 'ATC-QMS-P-030-R%')->first(),
        );

        tenancy()->end();
    }

    public function test_publish_stamps_a_monthly_dcf_number(): void
    {
        Carbon::setTestNow('2026-10-20 08:00:00');

        try {
            tenancy()->initialize($this->testTenant);
            $form = $this->controlledForm();
            $hook = app(ControlledDocumentEApprovalHookService::class);

            $first = $this->pendingSubmission($form, 'ATC-BDD-P-010');
            $hook->afterSubmissionOpened($first->fresh(['form']), $this->testTenantAdmin);
            $pending = ControlledDocument::query()->where('document_code', 'ATC-BDD-P-010')->first();
            $this->assertNotNull($pending);
            $this->assertNull($pending->dcf_number);

            $first->status = EApprovalSubmissionStatus::APPROVED;
            $first->save();
            $hook->afterSubmissionMutation($first->fresh(['form', 'values.field', 'attachments']), $this->testTenantAdmin);
            $pending->refresh();
            $this->assertSame('2026-10-001', $pending->dcf_number);
            $this->assertSame('2026-10-001', $pending->revisions()->first()?->dcf_number);

            $second = $this->approvedSubmission($form, 'ATC-BDD-P-011');
            $hook->afterSubmissionMutation($second->fresh(['form', 'values.field', 'attachments']), $this->testTenantAdmin);
            $this->assertSame(
                '2026-10-002',
                ControlledDocument::query()->where('document_code', 'ATC-BDD-P-011')->value('dcf_number'),
            );

            Carbon::setTestNow('2026-11-03 08:00:00');
            $third = $this->approvedSubmission($form, 'ATC-BDD-P-012');
            $hook->afterSubmissionMutation($third->fresh(['form', 'values.field', 'attachments']), $this->testTenantAdmin);
            $this->assertSame(
                '2026-11-001',
                ControlledDocument::query()->where('document_code', 'ATC-BDD-P-012')->value('dcf_number'),
            );

            $direct = app(ControlledDocumentDcfNumberService::class)->next();
            $this->assertSame('2026-11-002', $direct);
            tenancy()->end();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cancelled_controlled_request_reuses_its_document_code(): void
    {
        tenancy()->initialize($this->testTenant);
        $form = $this->controlledForm('ATC-{department}-{documentType}-{seq:3}');
        $numbers = app(EApprovalDocumentSequenceService::class);

        $first = $numbers->nextDocumentNumber($form, [
            'department' => 'BDD',
            'document_type' => 'P',
        ]);
        $this->assertSame('ATC-BDD-P-001', $first);

        $second = $numbers->nextDocumentNumber($form, [
            'department' => 'BDD',
            'document_type' => 'P',
        ]);
        $this->assertSame('ATC-BDD-P-002', $second);

        $cancelled = $this->submission($form, $first, EApprovalSubmissionStatus::CANCELLED);
        app(ControlledDocumentEApprovalHookService::class)->afterSubmissionClosed($cancelled->fresh('form'));
        $cancelled->refresh();
        $this->assertSame('ATC-BDD-P-001-CANCELLED', $cancelled->document_no);

        $reused = $numbers->nextDocumentNumber($form, [
            'department' => 'BDD',
            'document_type' => 'P',
        ]);
        $this->assertSame('ATC-BDD-P-001', $reused);

        $next = $numbers->nextDocumentNumber($form, [
            'department' => 'BDD',
            'document_type' => 'P',
        ]);
        $this->assertSame('ATC-BDD-P-003', $next);

        $revisionNo = $numbers->nextRevisionNumber('ATC-BDD-P-002');
        $revision = $this->submission($form, $revisionNo, EApprovalSubmissionStatus::CANCELLED);
        app(ControlledDocumentEApprovalHookService::class)->afterSubmissionClosed($revision->fresh('form'));
        $revision->refresh();
        $this->assertSame($revisionNo, $revision->document_no);

        $afterRevisionCancel = $numbers->nextDocumentNumber($form, [
            'department' => 'BDD',
            'document_type' => 'P',
        ]);
        $this->assertSame('ATC-BDD-P-004', $afterRevisionCancel);

        $rejected = $this->submission($form, 'ATC-BDD-P-004', EApprovalSubmissionStatus::REJECTED);
        app(ControlledDocumentEApprovalHookService::class)->afterSubmissionClosed($rejected->fresh('form'));
        $rejected->refresh();
        $this->assertSame('ATC-BDD-P-004', $rejected->document_no);

        $hook = app(ControlledDocumentEApprovalHookService::class);
        $openedCode = $numbers->nextDocumentNumber($form, [
            'department' => 'BDD',
            'document_type' => 'P',
        ]);
        $opened = $this->pendingSubmission($form, $openedCode);
        $hook->afterSubmissionOpened($opened->fresh(['form']), $this->testTenantAdmin);
        $this->assertNotNull(ControlledDocument::query()->where('document_code', $openedCode)->first());
        $opened->status = EApprovalSubmissionStatus::CANCELLED;
        $opened->save();
        $hook->afterSubmissionClosed($opened->fresh('form'));
        $opened->refresh();
        $this->assertNull(ControlledDocument::query()->where('document_code', $openedCode)->first());
        $this->assertSame($openedCode.'-CANCELLED', $opened->document_no);
        $this->assertSame($openedCode, $numbers->nextDocumentNumber($form, [
            'department' => 'BDD',
            'document_type' => 'P',
        ]));

        tenancy()->end();
    }

    public function test_imported_register_row_exposes_a_revision_form(): void
    {
        tenancy()->initialize($this->testTenant);
        $form = $this->controlledForm();
        ControlledDocument::query()->create([
            'id' => (string) Str::uuid(),
            'document_code' => 'ATC-BDD-P-050',
            'title' => 'Imported policy',
            'current_revision' => 0,
            'status' => ControlledDocumentStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/controlled?search=ATC-BDD-P-050')
            ->assertOk()
            ->assertJsonPath('data.default_form_id', $form->id)
            ->assertJsonPath('data.documents.data.0.revision_form_id', $form->id)
            ->assertJsonPath('data.documents.data.0.e_approval_form_id', null)
            ->assertJsonPath('data.documents.data.0.dcf_number', null);
    }

    private function controlledForm(?string $template = null): EApprovalForm
    {
        return EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Document Control',
            'status' => 'published',
            'doc_no_custom_enabled' => $template !== null,
            'doc_no_template' => $template,
            'metadata_json' => [
                'controlledDocumentSync' => ['enabled' => true],
            ],
        ]);
    }

    private function pendingSubmission(EApprovalForm $form, string $documentNo): EApprovalSubmission
    {
        return $this->submission($form, $documentNo, EApprovalSubmissionStatus::PENDING);
    }

    private function approvedSubmission(EApprovalForm $form, string $documentNo): EApprovalSubmission
    {
        return $this->submission($form, $documentNo, EApprovalSubmissionStatus::APPROVED);
    }

    private function submission(EApprovalForm $form, string $documentNo, string $status): EApprovalSubmission
    {
        return EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'document_no' => $documentNo,
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => $status,
            'current_step' => 1,
        ]);
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
