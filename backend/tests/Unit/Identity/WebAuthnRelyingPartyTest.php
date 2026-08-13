<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Support\WebAuthnRelyingParty;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class WebAuthnRelyingPartyTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_rp_id_uses_tenant_hostname(): void
    {
        tenancy()->initialize($this->testTenant);

        $rp = app(WebAuthnRelyingParty::class);

        $this->assertSame('test.localhost', $rp->rpId());
        $this->assertContains('http://test.localhost', $rp->allowedOrigins());

        tenancy()->end();
    }
}
