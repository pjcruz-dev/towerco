<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentExpiryAlert;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Services\DocumentExpiryNotificationService;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\Notifications\Models\TenantNotification;
use App\Modules\Notifications\Support\TenantNotificationModule;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentExpiryNotificationTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'sites', 'documents',
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_expiry_notify_sends_once_per_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01'));

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();

        $site = Site::query()->create([
            'site_code' => 'EXP-001',
            'name' => 'Expiry Site',
            'status' => 'active',
        ]);

        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($site);
        $node = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('node_key', 'saq_phase_1')
            ->firstOrFail();

        Document::query()->create([
            'id' => (string) Str::uuid(),
            'site_id' => $site->id,
            'site_node_id' => $node->id,
            'title' => 'Expiring Lease',
            'original_filename' => 'lease.pdf',
            'stored_path' => 'test/lease.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'status' => DocumentStatus::FINAL,
            'version' => 1,
            'expires_at' => Carbon::parse('2026-07-01'),
            'uploaded_by_id' => $this->testTenantAdmin->id,
            'last_touched_by_id' => $this->testTenantAdmin->id,
            'last_touched_at' => now(),
        ]);

        $result = app(DocumentExpiryNotificationService::class)->run();
        tenancy()->end();

        $this->assertSame(1, $result['alerts_sent']);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, DocumentExpiryAlert::query()->count());
        $this->assertGreaterThan(
            0,
            TenantNotification::query()
                ->where('module', TenantNotificationModule::DOCUMENTS)
                ->where('type', 'document_expiring')
                ->count(),
        );

        $secondRun = app(DocumentExpiryNotificationService::class)->run();
        tenancy()->end();

        $this->assertSame(0, $secondRun['alerts_sent']);

        Carbon::setTestNow();
    }
}
