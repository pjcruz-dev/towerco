<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Carbon;

/**
 * Creates sample IT assets through the registry, then walks each row to a legal status.
 */
final class SeedSampleItAssetsAction implements AssistantActionInterface
{
    private const MAX_COUNT = 50;

    /**
     * @var list<array{type: string, manufacturer: string, product_name: string, statuses: list<string>, age_months: int}>
     */
    private const SCENARIOS = [
        ['type' => 'laptop', 'manufacturer' => 'Dell', 'product_name' => 'Latitude 5540', 'statuses' => ['NEW'], 'age_months' => 1],
        ['type' => 'laptop', 'manufacturer' => 'Lenovo', 'product_name' => 'ThinkPad T14', 'statuses' => ['AVAILABLE'], 'age_months' => 8],
        ['type' => 'desktop', 'manufacturer' => 'Dell', 'product_name' => 'OptiPlex 7010', 'statuses' => ['AVAILABLE', 'ASSIGNED'], 'age_months' => 14],
        ['type' => 'laptop', 'manufacturer' => 'HP', 'product_name' => 'EliteBook 840', 'statuses' => ['AVAILABLE', 'UNDER_REPAIR'], 'age_months' => 20],
        ['type' => 'monitor', 'manufacturer' => 'Dell', 'product_name' => 'P2422H', 'statuses' => ['AVAILABLE'], 'age_months' => 28],
        ['type' => 'phone', 'manufacturer' => 'Apple', 'product_name' => 'iPhone 13', 'statuses' => ['AVAILABLE', 'LOST'], 'age_months' => 36],
        ['type' => 'desktop', 'manufacturer' => 'HP', 'product_name' => 'ProDesk 400', 'statuses' => ['AVAILABLE', 'RETIRED'], 'age_months' => 40],
        ['type' => 'laptop', 'manufacturer' => 'Lenovo', 'product_name' => 'ThinkPad X1', 'statuses' => ['AVAILABLE', 'ASSIGNED', 'STOLEN'], 'age_months' => 18],
        ['type' => 'dock', 'manufacturer' => 'Dell', 'product_name' => 'WD19S', 'statuses' => ['AVAILABLE', 'RETIRED', 'E_WASTE'], 'age_months' => 44],
        ['type' => 'peripheral', 'manufacturer' => 'Logitech', 'product_name' => 'MX Keys', 'statuses' => ['AVAILABLE', 'RETIRED', 'DISPOSED'], 'age_months' => 52],
        ['type' => 'laptop', 'manufacturer' => 'Dell', 'product_name' => 'Latitude 5420', 'statuses' => ['AVAILABLE', 'ASSIGNED', 'REPLACEMENT_PENDING'], 'age_months' => 42],
        ['type' => 'monitor', 'manufacturer' => 'LG', 'product_name' => '27UL500', 'statuses' => ['AVAILABLE', 'E_WASTE'], 'age_months' => 48],
    ];

    public function __construct(
        private readonly AssetRegistryService $assets,
    ) {}

    public function name(): string
    {
        return 'seed_sample_it_assets';
    }

    public function description(): string
    {
        return 'Create sample IT asset rows. Use when they ask for dummy, sample, seed, or demo assets. count is 1 to 50. Each row gets a real tag and a different lifecycle status. Saves now.';
    }

    public function requiredModule(): ?string
    {
        return 'asset_management';
    }

    public function requiredDomainPermissions(): array
    {
        return ['assets:manage'];
    }

    public function plannerArgumentRules(): array
    {
        return [
            'count' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_COUNT],
        ];
    }

    public function argumentRules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:'.self::MAX_COUNT],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($viewer);
        $count = self::countFrom($question, $args);

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Create sample assets',
            summary: sprintf('Create %d sample IT assets with different types and statuses.', $count),
            payload: ['count' => $count],
            preview: ['count' => $count],
            moduleKey: 'asset_management',
            confirmLabel: 'Create sample assets',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $count = self::clamp((int) ($payload['count'] ?? 0));
        $batch = strtoupper(bin2hex(random_bytes(3)));
        $tags = [];
        $byStatus = [];

        for ($index = 1; $index <= $count; $index++) {
            $scenario = self::SCENARIOS[($index - 1) % count(self::SCENARIOS)];
            $final = $scenario['statuses'][array_key_last($scenario['statuses'])];
            $asset = $this->assets->create([
                'serial_number' => sprintf('SN-SEED-%s-%02d', $batch, $index),
                'asset_type' => $scenario['type'],
                'status' => $scenario['statuses'][0] === 'NEW' ? 'NEW' : 'AVAILABLE',
                'manufacturer' => $scenario['manufacturer'],
                'product_name' => $scenario['product_name'],
                'purchase_date' => Carbon::now()->subMonths($scenario['age_months'])->toDateString(),
                'notes' => 'Sample asset. Scenario: '.$final.'.',
            ], $viewer);

            foreach (array_slice($scenario['statuses'], 1) as $status) {
                $asset = $this->assets->update($asset, ['status' => $status], $viewer);
            }

            $tags[] = (string) $asset->asset_tag;
            $status = (string) $asset->status;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        ksort($byStatus);
        $mix = [];
        foreach ($byStatus as $status => $total) {
            $mix[] = $status.' '.$total;
        }

        $first = $tags[0] ?? '';
        $last = $tags[array_key_last($tags)] ?? $first;

        return new ActionExecutionResult(
            ok: true,
            entityType: 'it_asset',
            entityId: ($id = Asset::query()->where('asset_tag', $first)->value('id')) !== null ? (string) $id : null,
            entityLabel: sprintf(
                'Created %d sample assets, %s to %s. Status mix: %s.',
                $count,
                $first,
                $last,
                implode(', ', $mix),
            ),
            meta: [
                'count' => $count,
                'asset_tags' => $tags,
                'statuses' => $byStatus,
            ],
            href: '/assets',
        );
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public static function countFrom(string $question, array $args): int
    {
        $raw = $args['count'] ?? null;
        if (is_numeric($raw)) {
            return self::clamp((int) $raw);
        }

        if (preg_match('/\b(\d{1,4})\b/', $question, $match) === 1) {
            return self::clamp((int) $match[1]);
        }

        return 10;
    }

    private static function clamp(int $count): int
    {
        return max(1, min(self::MAX_COUNT, $count));
    }
}
