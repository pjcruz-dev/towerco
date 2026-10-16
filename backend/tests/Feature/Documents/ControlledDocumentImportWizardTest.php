<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;
use ZipArchive;

final class ControlledDocumentImportWizardTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

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
    }

    public function test_analyze_suggests_folder_path_and_template_lists_columns(): void
    {
        $csv = "document_code,title,Folder Path\nATC-OMD-P-002,Leasing,C:\\docs\\file.pdf\n";

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/analyze', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
            ])
            ->assertOk()
            ->assertJsonPath('data.row_count', 1)
            ->assertJsonPath('data.suggested_map.Folder Path', 'folder_path')
            ->assertJsonPath('data.suggested_map.document_code', 'document_code');

        $template = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/documents/controlled/import/template');

        $template->assertOk();
        $this->assertStringContainsString('folder_path', $template->streamedContent());
    }

    public function test_preview_matches_a_windows_path_and_does_not_write(): void
    {
        $relative = 'Approved/1. POLICIES/OMD/ATC-OMD-P-002 Leasing.pdf';
        $csv = "document_code,title,Folder Path\nATC-OMD-P-002,Leasing,\"C:\\Users\\PJ\\Downloads\\Approved\\{$relative}\"\n";

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/preview', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
                'column_map' => json_encode([
                    'document_code' => 'document_code',
                    'title' => 'title',
                    'Folder Path' => 'folder_path',
                ]),
                'available_paths' => json_encode([$relative]),
            ])
            ->assertOk()
            ->assertJsonPath('data.written', false)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.rows.0.file_status', 'matched')
            ->assertJsonPath('data.rows.0.matched_path', $relative);

        tenancy()->initialize($this->testTenant);
        $this->assertNull(ControlledDocument::query()->where('document_code', 'ATC-OMD-P-002')->first());
        tenancy()->end();
    }

    public function test_run_attaches_the_matched_file_and_skips_a_repeat(): void
    {
        $relative = 'Approved/1. POLICIES/OMD/ATC-OMD-P-002 Leasing.pdf';
        $csv = "document_code,title,Folder Path\nATC-OMD-P-002,Leasing,\"C:\\Users\\PJ\\Downloads\\{$relative}\"\n";
        $pdf = UploadedFile::fake()->createWithContent('ATC-OMD-P-002 Leasing.pdf', self::PDF);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/run', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
                'column_map' => json_encode([
                    'document_code' => 'document_code',
                    'title' => 'title',
                    'Folder Path' => 'folder_path',
                ]),
                'available_paths' => json_encode([$relative]),
                'files' => [$pdf],
                'file_paths' => json_encode([$relative]),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.files_attached', 1);

        tenancy()->initialize($this->testTenant);
        $document = ControlledDocument::query()->where('document_code', 'ATC-OMD-P-002')->first();
        $this->assertNotNull($document);
        $revision = $document->revisions()->first();
        $this->assertNotNull($revision);
        $this->assertNotSame('', (string) $revision->stored_path);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/run', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
                'column_map' => json_encode([
                    'document_code' => 'document_code',
                    'title' => 'title',
                    'Folder Path' => 'folder_path',
                ]),
                'available_paths' => json_encode([$relative]),
                'files' => [UploadedFile::fake()->createWithContent('ATC-OMD-P-002 Leasing.pdf', self::PDF)],
                'file_paths' => json_encode([$relative]),
            ])
            ->assertOk()
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.created', 0);
    }

    public function test_missing_file_does_not_create_the_row(): void
    {
        $csv = "document_code,title,folder_path\nATC-QMS-P-009,Quality,C:\\missing\\policy.pdf\n";

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/run', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
                'column_map' => json_encode([
                    'document_code' => 'document_code',
                    'title' => 'title',
                    'folder_path' => 'folder_path',
                ]),
                'available_paths' => json_encode([]),
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.rows.0.file_status', 'missing');

        tenancy()->initialize($this->testTenant);
        $this->assertNull(ControlledDocument::query()->where('document_code', 'ATC-QMS-P-009')->first());
        tenancy()->end();
    }

    public function test_zip_import_attaches_the_entry_named_by_the_path(): void
    {
        $relative = 'OMD/ATC-OMD-P-003 Leasing.pdf';
        $csv = "document_code,title,folder_path\nATC-OMD-P-003,Leasing policy,\"C:\\Approved\\{$relative}\"\n";
        $zipPath = tempnam(sys_get_temp_dir(), 'cdzip');
        $this->assertNotFalse($zipPath);
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString($relative, self::PDF);
        $zip->close();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/run', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
                'column_map' => json_encode([
                    'document_code' => 'document_code',
                    'title' => 'title',
                    'folder_path' => 'folder_path',
                ]),
                'archive' => new UploadedFile($zipPath, 'library.zip', 'application/zip', null, true),
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.files_attached', 1);

        tenancy()->initialize($this->testTenant);
        $document = ControlledDocument::query()->where('document_code', 'ATC-OMD-P-003')->first();
        $this->assertNotNull($document?->revisions()->first()?->stored_path);
        tenancy()->end();
    }

    public function test_import_wizard_requires_permission(): void
    {
        tenancy()->initialize($this->testTenant);
        $viewer = TenantUser::query()->create([
            'name' => 'Docs Viewer',
            'email' => 'import.viewer@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/analyze', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', "document_code,title\nA,B\n"),
            ])
            ->assertForbidden();
    }

    public function test_rev_n_header_maps_to_revision_number(): void
    {
        $csv = "document_code,title,Rev. N\nATC-EDD-F-002,Pre Delivery,1\n";

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/analyze', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
            ]);

        $response->assertOk();
        $this->assertSame('revision_number', $response->json('data.suggested_map')['Rev. N'] ?? null);
    }

    public function test_follow_up_file_lands_on_revision_one_and_supersedes_revision_zero(): void
    {
        $csv = "document_code,title,revision_number\nATC-EDD-F-002,Pre Delivery,1\n";

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/run', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $csv),
                'column_map' => json_encode([
                    'document_code' => 'document_code',
                    'title' => 'title',
                    'revision_number' => 'revision_number',
                ]),
                'available_paths' => json_encode([]),
            ])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $relative = 'ATC-EDD-F-002_Pre Delivery.pdf';
        $followUp = "document_code,title,folder_path\nATC-EDD-F-002,Pre Delivery,\"C:\\docs\\{$relative}\"\n";

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post('/api/v1/documents/controlled/import/run', [
                'file' => UploadedFile::fake()->createWithContent('register.csv', $followUp),
                'column_map' => json_encode([
                    'document_code' => 'document_code',
                    'title' => 'title',
                    'folder_path' => 'folder_path',
                ]),
                'available_paths' => json_encode([$relative]),
                'files' => [UploadedFile::fake()->createWithContent('ATC-EDD-F-002_Pre Delivery.pdf', self::PDF)],
                'file_paths' => json_encode([$relative]),
            ])
            ->assertOk()
            ->assertJsonPath('data.files_attached', 1);

        tenancy()->initialize($this->testTenant);
        $document = ControlledDocument::query()->where('document_code', 'ATC-EDD-F-002')->first();
        $this->assertNotNull($document);
        $this->assertSame(1, (int) $document->current_revision);
        $current = $document->revisions()->where('revision_number', 1)->first();
        $zero = $document->revisions()->where('revision_number', 0)->first();
        $this->assertNotNull($current);
        $this->assertNotSame('', (string) $current->stored_path);
        $this->assertNotNull($zero);
        $this->assertSame('superseded', $zero->status);
        $this->assertTrue($zero->stored_path === null || $zero->stored_path === '');
        tenancy()->end();
    }
}
