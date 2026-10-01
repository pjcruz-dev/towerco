<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalFormRequestAccessSupport;
use App\Modules\Identity\Models\TenantUser;
use PHPUnit\Framework\TestCase;

final class EApprovalFormRequestAccessSupportTest extends TestCase
{
    public function test_missing_config_allows_everyone(): void
    {
        $viewer = new TenantUser;
        $viewer->id = '019e9044-f543-72bd-9b55-d9cb0c62c46c';

        $this->assertTrue(EApprovalFormRequestAccessSupport::viewerCanStart($viewer, null));
        $this->assertTrue(EApprovalFormRequestAccessSupport::viewerCanStart($viewer, []));
        $this->assertSame(
            ['mode' => 'all', 'user_ids' => []],
            EApprovalFormRequestAccessSupport::normalize([]),
        );
    }

    public function test_selected_mode_allows_only_listed_users(): void
    {
        $allowed = new TenantUser;
        $allowed->id = '019e9044-f543-72bd-9b55-d9cb0c62c46c';
        $blocked = new TenantUser;
        $blocked->id = '019e9044-f543-72bd-9b55-d9cb0c62c46d';

        $metadata = [
            'request_access' => [
                'mode' => 'selected',
                'user_ids' => ['019e9044-f543-72bd-9b55-d9cb0c62c46c', 'not-a-uuid'],
            ],
        ];

        $this->assertTrue(EApprovalFormRequestAccessSupport::viewerCanStart($allowed, $metadata));
        $this->assertFalse(EApprovalFormRequestAccessSupport::viewerCanStart($blocked, $metadata));
        $this->assertSame(
            ['mode' => 'selected', 'user_ids' => ['019e9044-f543-72bd-9b55-d9cb0c62c46c']],
            EApprovalFormRequestAccessSupport::normalize($metadata),
        );
    }
}
