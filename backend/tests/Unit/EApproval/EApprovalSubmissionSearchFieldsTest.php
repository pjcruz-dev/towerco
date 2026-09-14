<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalSubmissionSearchFields;
use PHPUnit\Framework\TestCase;

final class EApprovalSubmissionSearchFieldsTest extends TestCase
{
    public function test_dsl_fields_include_core_and_form_fields(): void
    {
        $fields = EApprovalSubmissionSearchFields::dslFields([], ['vendor_name', 'cost_center']);

        $this->assertArrayHasKey('status', $fields);
        $this->assertArrayHasKey('subsidiary', $fields);
        $this->assertArrayHasKey('department', $fields);
        $this->assertArrayHasKey('vendor_name', $fields);
        $this->assertArrayHasKey('cost_center', $fields);
        $this->assertArrayHasKey('handler', $fields['vendor_name']);
    }

    public function test_reserved_core_keys_are_not_overridden_by_form_fields(): void
    {
        $fields = EApprovalSubmissionSearchFields::dslFields([], ['status', 'document']);

        $this->assertSame('status', $fields['status']['column'] ?? null);
        $this->assertArrayNotHasKey('handler', $fields['status']);
    }

    public function test_invalid_field_names_are_skipped(): void
    {
        $fields = EApprovalSubmissionSearchFields::dslFields([], ['Bad-Name', '']);

        $this->assertArrayNotHasKey('bad-name', $fields);
    }
}
