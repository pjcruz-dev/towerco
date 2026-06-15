<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Services\EApprovalVendorMasterDataDedupeService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EApprovalVendorMasterDataDedupeServiceTest extends TestCase
{
    private EApprovalVendorMasterDataDedupeService $dedupe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dedupe = app(EApprovalVendorMasterDataDedupeService::class);
    }

    #[DataProvider('taxIdNormalizationProvider')]
    public function test_normalize_tax_id_strips_non_alphanumeric_characters(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->dedupe->normalizeTaxId($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function taxIdNormalizationProvider(): array
    {
        return [
            'dashes' => ['123-456-789-000', '123456789000'],
            'mixed case' => ['tin-998877', 'TIN998877'],
            'spaces' => ['TIN 998877', 'TIN998877'],
        ];
    }

    #[DataProvider('companyNameNormalizationProvider')]
    public function test_normalize_company_name_collapses_legal_suffixes(string $left, string $right): void
    {
        $this->assertSame(
            $this->dedupe->normalizeCompanyName($left),
            $this->dedupe->normalizeCompanyName($right),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function companyNameNormalizationProvider(): array
    {
        return [
            'inc suffix' => ['Acme Telecom Supplies Inc.', 'ACME TELECOM SUPPLIES INC'],
            'corporation suffix' => ['Shared Services Corporation', 'Shared Services Corp.'],
            'punctuation' => ['Legacy Vendor Co.', 'Legacy Vendor Company'],
        ];
    }
}
