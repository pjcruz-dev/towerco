<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Notifications\EApprovalMailTestNotification;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalMailTestNotificationTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_test_email_uses_tenant_brand_not_platform_name(): void
    {
        $this->testTenant->slug = 'atc';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);

        $mail = (new EApprovalMailTestNotification('ATC'))->toMail(new TenantUser([
            'name' => 'Admin',
            'email' => 'admin@atc.localhost',
        ]));

        $this->assertSame('[ATC] E-Approval test email', $mail->subject);
        $this->assertStringNotContainsString('TowerOS', (string) $mail->subject);
        $this->assertStringContainsString('ATC', (string) $mail->greeting);
        $this->assertSame('ATC', app(TenantAppUrlResolver::class)->mailBrandLabel());

        tenancy()->end();
    }
}
