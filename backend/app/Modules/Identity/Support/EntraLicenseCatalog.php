<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

final class EntraLicenseCatalog
{
    /**
     * skuPartNumber => [full name, short card label, rank (lower is better)].
     *
     * @var array<string, array{0: string, 1: string, 2: int}>
     */
    private const PRODUCTS = [
        'SPE_E5' => ['Microsoft 365 E5', 'E5', 10],
        'SPE_E5_NOPSTNCONF' => ['Microsoft 365 E5', 'E5', 11],
        'ENTERPRISEPREMIUM' => ['Office 365 E5', 'O365 E5', 12],
        'SPE_E3' => ['Microsoft 365 E3', 'E3', 20],
        'SPE_E3_USGOV_DOD' => ['Microsoft 365 E3', 'E3', 21],
        'ENTERPRISEPACK' => ['Office 365 E3', 'O365 E3', 22],
        'SPE_E1' => ['Microsoft 365 E1', 'E1', 30],
        'STANDARDPACK' => ['Office 365 E1', 'O365 E1', 31],
        'SPB' => ['Microsoft 365 Business Premium', 'Business Premium', 40],
        'O365_BUSINESS_PREMIUM' => ['Microsoft 365 Business Premium', 'Business Premium', 41],
        'O365_BUSINESS' => ['Microsoft 365 Business Standard', 'Business Standard', 50],
        'O365_BUSINESS_ESSENTIALS' => ['Microsoft 365 Business Basic', 'Business Basic', 60],
        'SPE_F1' => ['Microsoft 365 F1', 'F1', 70],
        'SPE_F3' => ['Microsoft 365 F3', 'F3', 71],
        'EXCHANGEENTERPRISE' => ['Exchange Online Plan 2', 'Exchange P2', 80],
        'EXCHANGESTANDARD' => ['Exchange Online Plan 1', 'Exchange P1', 81],
        'Microsoft_365_Copilot' => ['Microsoft 365 Copilot', 'Copilot', 90],
        'POWER_BI_PRO' => ['Power BI Pro', 'Power BI Pro', 100],
        'POWER_BI_STANDARD' => ['Power BI Free', 'Power BI', 101],
        'PROJECTPROFESSIONAL' => ['Project Plan 3', 'Project', 110],
        'VISIOCLIENT' => ['Visio Plan 2', 'Visio', 111],
        'EMS' => ['Enterprise Mobility + Security E3', 'EMS E3', 120],
        'EMSPREMIUM' => ['Enterprise Mobility + Security E5', 'EMS E5', 121],
        'AAD_PREMIUM' => ['Entra ID P1', 'Entra P1', 130],
        'AAD_PREMIUM_P2' => ['Entra ID P2', 'Entra P2', 131],
    ];

    /** Well-known skuId => skuPartNumber when subscribedSkus is unavailable. */
    private const SKU_IDS = [
        '06ebc4ee-1bb5-47ef-8236-9a1f8d966287' => 'SPE_E5',
        '05e9a617-0261-4cee-bb44-138d3ef5d965' => 'SPE_E3',
        'cbdc14ab-d96c-4c30-b9f4-6ada7cdc1d46' => 'SPB',
        'f245ecc8-75af-4f8e-b61f-27d8114de5f3' => 'O365_BUSINESS',
        '3b555118-da6a-4418-894f-7df1e209c02c' => 'O365_BUSINESS_ESSENTIALS',
        'c7df2760-2c81-4ef7-b578-5b5392b571df' => 'ENTERPRISEPREMIUM',
        '6fd2c87f-b296-42f0-b197-1e91e994b900' => 'ENTERPRISEPACK',
        '18181a46-0d4e-45cd-891e-60aabd171b4e' => 'STANDARDPACK',
        '66b55226-6b4f-492c-910c-a3b7a3c9d993' => 'SPE_F3',
        '4b9405b0-7788-4568-add1-55668158885a' => 'EXCHANGESTANDARD',
        '19ec0d23-8335-4cbd-94ac-7790c98aba1d' => 'EXCHANGEENTERPRISE',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public static function skuIdsFromGraph(array $payload): array
    {
        $raw = $payload['assignedLicenses'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $skuId = strtolower(trim((string) ($row['skuId'] ?? '')));
            if ($skuId !== '') {
                $ids[] = $skuId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $skuIds
     * @param  array<string, string>  $skuMap  skuId => skuPartNumber
     * @return array{licensed: bool, label: string|null, names: list<string>}
     */
    public static function summarize(array $skuIds, array $skuMap = []): array
    {
        $normalized = [];
        foreach ($skuIds as $skuId) {
            $id = strtolower(trim($skuId));
            if ($id !== '') {
                $normalized[] = $id;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return ['licensed' => false, 'label' => null, 'names' => []];
        }

        $products = [];
        foreach ($normalized as $skuId) {
            $part = $skuMap[$skuId] ?? self::SKU_IDS[$skuId] ?? null;
            $products[] = self::describe($part, $skuId);
        }

        usort($products, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        $names = [];
        $seen = [];
        foreach ($products as $product) {
            if (isset($seen[$product['name']])) {
                continue;
            }
            $seen[$product['name']] = true;
            $names[] = $product['name'];
        }

        return [
            'licensed' => true,
            'label' => $products[0]['label'],
            'names' => $names,
        ];
    }

    /**
     * @return array{name: string, label: string, rank: int}
     */
    private static function describe(?string $partNumber, string $skuId): array
    {
        $part = $partNumber !== null ? trim($partNumber) : '';
        if ($part !== '' && isset(self::PRODUCTS[$part])) {
            [$name, $label, $rank] = self::PRODUCTS[$part];

            return ['name' => $name, 'label' => $label, 'rank' => $rank];
        }

        if ($part !== '') {
            $readable = str_replace('_', ' ', $part);

            return ['name' => $readable, 'label' => $readable, 'rank' => 500];
        }

        return ['name' => 'Microsoft 365 license', 'label' => 'Licensed', 'rank' => 400];
    }
}
