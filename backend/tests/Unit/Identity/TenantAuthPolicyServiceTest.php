<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Services\TenantAuthPolicyService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TenantAuthPolicyServiceTest extends TestCase
{
    private TenantAuthPolicyService $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = app(TenantAuthPolicyService::class);
    }

    #[DataProvider('domainNormalizationProvider')]
    public function test_normalize_domain(string $input, string $expected): void
    {
        $this->assertSame($expected, TenantAuthPolicyService::normalizeDomain($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function domainNormalizationProvider(): array
    {
        return [
            'strips at sign' => ['@Atc.COM', 'atc.com'],
            'lowercase' => ['Example.COM', 'example.com'],
            'invalid chars' => ['not a domain!', ''],
            'empty' => ['', ''],
        ];
    }

    public function test_normalize_domain_list_deduplicates(): void
    {
        $this->assertSame(
            ['atc.com', 'alliancetowers.com'],
            $this->policy->normalizeDomainList(['@atc.com', 'ATC.COM', 'alliancetowers.com']),
        );
    }

    public function test_email_domain_extraction(): void
    {
        $this->assertSame('atc.com', TenantAuthPolicyService::emailDomain('user@atc.com'));
        $this->assertSame('', TenantAuthPolicyService::emailDomain('invalid'));
    }
}
