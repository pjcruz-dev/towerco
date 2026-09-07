<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalDepartmentDocCode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EApprovalDepartmentDocCodeTest extends TestCase
{
    #[Test]
    public function it_builds_initials_from_multi_word_departments(): void
    {
        $this->assertSame('EDD', EApprovalDepartmentDocCode::fromLabel('Engineering & Design Department'));
        $this->assertSame('ED', EApprovalDepartmentDocCode::fromLabel('Engineering & Design'));
        $this->assertSame('BD', EApprovalDepartmentDocCode::fromLabel('Business Development'));
        $this->assertSame('TQG', EApprovalDepartmentDocCode::fromLabel('Technology and Quality Governance'));
        $this->assertSame('FA', EApprovalDepartmentDocCode::fromLabel('Finance and Accounting'));
    }

    #[Test]
    public function it_keeps_short_codes_and_single_words(): void
    {
        $this->assertSame('QMS', EApprovalDepartmentDocCode::fromLabel('QMS'));
        $this->assertSame('EDD', EApprovalDepartmentDocCode::fromLabel('EDD'));
        $this->assertSame('FINANCE', EApprovalDepartmentDocCode::fromLabel('Finance'));
    }
}
