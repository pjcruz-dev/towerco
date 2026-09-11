<?php

declare(strict_types=1);

namespace Tests\Unit\AdminOne;

use App\Modules\AdminOne\Services\TenantUserDepartmentDisplay;
use PHPUnit\Framework\TestCase;

final class TenantUserDepartmentDisplayTest extends TestCase
{
    public function test_display_walks_manager_chain_until_department_found(): void
    {
        $resolver = new TenantUserDepartmentDisplay;
        $departments = [
            'demetrio' => 'Project Implementation',
            'christopher' => null,
            'arvin' => null,
            'jerry' => null,
        ];
        $managers = [
            'demetrio' => null,
            'christopher' => 'demetrio',
            'arvin' => 'christopher',
            'jerry' => 'christopher',
        ];

        $this->assertSame(
            'Project Implementation',
            $resolver->displayFromMaps('christopher', null, $departments, $managers),
        );
        $this->assertSame(
            'Project Implementation',
            $resolver->displayFromMaps('arvin', null, $departments, $managers),
        );
        $this->assertSame(
            'Project Implementation',
            $resolver->displayFromMaps('jerry', null, $departments, $managers),
        );
    }

    public function test_display_prefers_own_department_over_ancestors(): void
    {
        $resolver = new TenantUserDepartmentDisplay;
        $departments = [
            'boss' => 'Executive Office',
            'report' => 'Finance and Accounting',
        ];
        $managers = [
            'boss' => null,
            'report' => 'boss',
        ];

        $this->assertSame(
            'Finance and Accounting',
            $resolver->displayFromMaps('report', null, $departments, $managers),
        );
    }

    public function test_display_falls_back_to_entra_manager_snapshot(): void
    {
        $resolver = new TenantUserDepartmentDisplay;

        $this->assertSame(
            'Supply Chain Management',
            $resolver->displayFromMaps('orphan', 'Supply Chain Management', ['orphan' => null], ['orphan' => null]),
        );
    }
}
