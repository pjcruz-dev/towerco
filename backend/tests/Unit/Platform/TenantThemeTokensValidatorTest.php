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
}
