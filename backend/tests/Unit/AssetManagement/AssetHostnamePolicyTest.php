<?php

declare(strict_types=1);

namespace Tests\Unit\AssetManagement;

use App\Modules\AssetManagement\Support\AssetHostnamePolicy;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssetHostnamePolicyTest extends TestCase
{
    public function test_builds_initials_middle_and_department_code(): void
    {
        $user = new TenantUser([
            'name' => 'Christopher Michael Villanueva',
            'given_name' => 'Christopher',
            'surname' => 'Villanueva',
        ]);

        $this->assertSame('CMVILLANUEVAIT', AssetHostnamePolicy::suggest($user, 'IT'));
    }

    public function test_chops_the_surname_before_the_department_code(): void
    {
        $user = new TenantUser([
            'name' => 'Christopher Michael Villanueva',
            'given_name' => 'Christopher',
            'surname' => 'Villanueva',
        ]);

        $hostname = AssetHostnamePolicy::suggest($user, 'QMSOPS');

        $this->assertLessThanOrEqual(15, strlen($hostname));
        $this->assertStringEndsWith('QMSOPS', $hostname);
        $this->assertStringStartsWith('CM', $hostname);
    }

    public function test_blocks_when_the_department_code_cannot_fit(): void
    {
        $user = new TenantUser([
            'name' => 'Ann Lee',
            'given_name' => 'Ann',
            'surname' => 'Lee',
        ]);

        $this->expectException(ValidationException::class);
        AssetHostnamePolicy::suggest($user, 'DEPARTMENTCODE');
    }
}
