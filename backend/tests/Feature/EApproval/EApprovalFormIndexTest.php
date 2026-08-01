<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFormIndexTest extends TestCase
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

    public function test_submission_creator_can_list_published_forms_without_view_permission(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = TenantUser::query()->create([
            'name' => 'Requestor',
            'email' => 'requestor-only@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->syncPermissions(['e_approval:submissions:create']);

        EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Published Form',
            'description' => 'Open for submissions',
            'category' => 'general',
            'status' => 'published',
            'accepts_new_submissions' => true,
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Closed Form',
            'description' => 'Closed to new submissions',
            'category' => 'general',
            'status' => 'published',
            'accepts_new_submissions' => false,
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Draft Form',
            'description' => 'Not published',
            'category' => 'general',
            'status' => 'draft',
            'accepts_new_submissions' => true,
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        tenancy()->end();

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/forms?status=published')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Published Form');
    }

    public function test_submission_creator_cannot_list_forms_without_published_filter(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = TenantUser::query()->create([
            'name' => 'Requestor',
            'email' => 'requestor-no-view@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->syncPermissions(['e_approval:submissions:create']);

        tenancy()->end();

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/forms')
            ->assertForbidden();
    }

    public function test_submission_creator_cannot_list_draft_forms(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = TenantUser::query()->create([
            'name' => 'Requestor',
            'email' => 'requestor-no-drafts@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->syncPermissions(['e_approval:submissions:create']);

        tenancy()->end();

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/forms?status=draft')
            ->assertForbidden();
    }
}
