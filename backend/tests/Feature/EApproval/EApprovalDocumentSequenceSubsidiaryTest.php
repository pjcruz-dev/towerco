<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalDocumentSequenceService;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalDocumentSequenceSubsidiaryTest extends TestCase
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

    public function test_template_uses_subsidiary_from_submission_values(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Cash advance',
            'category' => 'finance',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
            'doc_no_custom_enabled' => true,
            'doc_no_template' => '{subsidiary}-{department}-{docTypeCode}-{seq:3}',
        ]);

        $service = app(EApprovalDocumentSequenceService::class);

        $atc = $service->nextDocumentNumber($form, [
            'subsidiary' => 'ATC',
            'department' => 'Finance',
        ]);
        $adic = $service->nextDocumentNumber($form, [
            'subsidiary' => 'ADIC',
            'department' => 'Finance',
        ]);

        $this->assertSame('ATC-FINANCE-F-001', $atc);
        $this->assertSame('ADIC-FINANCE-F-001', $adic);

        tenancy()->end();
    }

    public function test_template_department_inherits_from_manager_chain_when_form_blank(): void
    {
        tenancy()->initialize($this->testTenant);

        if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'department')
            || ! \Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn('users', 'manager_id')) {
            tenancy()->end();
            $this->markTestSkipped('Org department columns not present in test tenant schema.');
        }

        $lead = \App\Modules\Identity\Models\TenantUser::query()->create([
            'name' => 'Doc Lead',
            'email' => 'doc.lead@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => 'Project Implementation',
        ]);
        $lead->assignRole('viewer');

        $mid = \App\Modules\Identity\Models\TenantUser::query()->create([
            'name' => 'Doc Mid',
            'email' => 'doc.mid@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => null,
            'manager_id' => $lead->id,
        ]);
        $mid->assignRole('viewer');

        $submitter = \App\Modules\Identity\Models\TenantUser::query()->create([
            'name' => 'Doc Submitter',
            'email' => 'doc.submitter@towerone.test',
            'password' => 'password',
            'is_active' => true,
            'department' => null,
            'manager_id' => $mid->id,
        ]);
        $submitter->assignRole('viewer');

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Document approval',
            'category' => 'qms',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'ATC',
            'doc_type_code' => 'P',
            'doc_no_custom_enabled' => true,
            'doc_no_template' => '{ownerCode}-{docTypeCode}-{department}-{seq:3}',
        ]);

        $service = app(EApprovalDocumentSequenceService::class);
        $number = $service->nextDocumentNumber($form, [
            'subsidiary' => 'ATC',
        ], $submitter);

        // Project Implementation → PI via EApprovalDepartmentDocCode.
        $this->assertSame('ATC-P-PI-001', $number);

        tenancy()->end();
    }
}
