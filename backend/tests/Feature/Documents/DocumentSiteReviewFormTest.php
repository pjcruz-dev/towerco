<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Services\DocumentSiteReviewFormProvisionerService;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentSiteReviewFormTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'e_approval', 'documents', 'sites',
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->testTenant->update(['plan_tier' => 'professional']);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_provisioner_creates_published_site_document_review_form(): void
    {
        tenancy()->initialize($this->testTenant);
        $actor = TenantUser::query()->findOrFail($this->testTenantAdmin->id);
        $form = app(DocumentSiteReviewFormProvisionerService::class)->ensure($actor);
        tenancy()->end();

        $this->assertSame('published', $form->status);
        $this->assertSame('Site document review', $form->name);
        $this->assertTrue($form->fields->contains('name', 'document_title'));
        $this->assertTrue($form->fields->contains('name', 'site_code'));

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, EApprovalForm::query()->where('status', 'published')->count());
        tenancy()->end();
    }

    public function test_provisioner_is_idempotent(): void
    {
        tenancy()->initialize($this->testTenant);
        $actor = TenantUser::query()->findOrFail($this->testTenantAdmin->id);
        $first = app(DocumentSiteReviewFormProvisionerService::class)->ensure($actor);
        $second = app(DocumentSiteReviewFormProvisionerService::class)->ensure($actor);
        tenancy()->end();

        $this->assertSame($first->id, $second->id);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, EApprovalForm::query()->count());
        tenancy()->end();
    }
}
