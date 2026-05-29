<?php

declare(strict_types=1);

namespace Tests\Unit\Rollout;

use App\Modules\Rollout\Data\RolloutGateApprovalChainRoles;
use PHPUnit\Framework\TestCase;

final class RolloutGateApprovalChainRolesTest extends TestCase
{
    public function test_sanitize_removes_unknown_and_duplicate_roles(): void
    {
        $result = RolloutGateApprovalChainRoles::sanitize([
            'saq',
            'typo_role',
            'pmo',
            'SAQ',
            'cme',
        ]);

        $this->assertSame(['saq', 'pmo', 'cme'], $result);
    }
}
