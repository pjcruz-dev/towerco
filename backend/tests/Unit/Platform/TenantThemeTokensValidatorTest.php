<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Support\TenantThemeTokensValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TenantThemeTokensValidatorTest extends TestCase
{
    public function test_accepts_https_logo_url(): void
    {
        $tokens = TenantThemeTokensValidator::validate([
            'version' => 1,
            'logo_url' => 'https://cdn.example.com/logo.png',
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
        ]);

        $this->assertSame('https://cdn.example.com/logo.png', $tokens['logo_url']);
    }

    public function test_accepts_hosted_public_asset_url(): void
    {
        $tenantId = '278e0e2c-ac8a-4b83-8b0a-fed3071fdc6b';
        $url = '/api/v1/public/tenant-branding/logo?tenant='.$tenantId;

        $tokens = TenantThemeTokensValidator::validate([
            'version' => 2,
            'logo_url' => $url,
            'logo_asset' => 'platform/tenant-branding/'.$tenantId.'/logo.png',
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
        ]);

        $this->assertSame($url, $tokens['logo_url']);
        $this->assertSame('platform/tenant-branding/'.$tenantId.'/logo.png', $tokens['logo_asset']);
    }

    public function test_rejects_http_logo_url(): void
    {
        $this->expectException(ValidationException::class);

        TenantThemeTokensValidator::validate([
            'version' => 1,
            'logo_url' => 'http://cdn.example.com/logo.png',
            'light' => [],
            'dark' => [],
        ]);
    }

    public function test_sanitize_for_public_strips_asset_paths(): void
    {
        $tenantId = '278e0e2c-ac8a-4b83-8b0a-fed3071fdc6b';
        $public = TenantThemeTokensValidator::sanitizeForPublic([
            'version' => 2,
            'logo_url' => '/api/v1/public/tenant-branding/logo?tenant='.$tenantId,
            'logo_asset' => 'platform/tenant-branding/'.$tenantId.'/logo.png',
            'favicon_url' => null,
            'light' => [],
            'dark' => [],
        ]);

        $this->assertArrayNotHasKey('logo_asset', $public);
        $this->assertSame('/api/v1/public/tenant-branding/logo?tenant='.$tenantId, $public['logo_url']);
    }

    public function test_sanitize_for_public_includes_letterhead_fields(): void
    {
        $public = TenantThemeTokensValidator::sanitizeForPublic([
            'version' => 1,
            'logo_url' => null,
            'favicon_url' => null,
            'company_address' => 'Unit 1718 BGC, Taguig City',
            'company_phone' => '+63 2 1234 5678',
            'company_email' => 'contact@alliancetowers.com.ph',
            'company_tin' => '987-654-321-000',
            'light' => [],
            'dark' => [],
        ]);

        $this->assertSame('Unit 1718 BGC, Taguig City', $public['company_address']);
        $this->assertSame('contact@alliancetowers.com.ph', $public['company_email']);
        $this->assertSame('987-654-321-000', $public['company_tin']);
    }
}
