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
}
