<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Tools;

use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\DTOs\ToolResult;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Identity\Models\TenantUser;

/**
 * Read-only lookup of an IT asset by serial or asset tag.
 * Workspace search does not include it_assets.
 */
final class FindItAssetTool implements AssistantToolInterface
{
    public function name(): string
    {
        return 'find_it_asset';
    }

    public function description(): string
    {
        return 'Find an IT asset by serial number or asset tag (for example SN-IMPORT-1002 or ATC-LAP-000002). '
            .'Returns the tag, serial, status, and edit path. Does not change the asset.';
    }

    public function requiredModule(): ?string
    {
        return 'asset_management';
    }

    public function requiredPermissions(): array
    {
        return ['assets:view'];
    }

    public function argumentRules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    public function execute(TenantUser $viewer, array $args, int $maxRows): ToolResult
    {
        unset($viewer);
        $query = trim((string) $args['query']);
        $needle = mb_strtoupper($query);
        $limit = max(1, min(5, $maxRows));

        $exact = Asset::query()
            ->where(function ($builder) use ($needle): void {
                $builder->whereRaw('UPPER(serial_number) = ?', [$needle])
                    ->orWhereRaw('UPPER(asset_tag) = ?', [$needle]);
            })
            ->limit($limit)
            ->get();

        $rows = $exact;
        if ($rows->isEmpty()) {
            $like = '%'.addcslashes($needle, '%_\\').'%';
            $rows = Asset::query()
                ->where(function ($builder) use ($like): void {
                    $builder->whereRaw('UPPER(serial_number) LIKE ?', [$like])
                        ->orWhereRaw('UPPER(asset_tag) LIKE ?', [$like]);
                })
                ->limit($limit)
                ->get();
        }

        $payload = [];
        $routes = [];
        foreach ($rows as $asset) {
            $tag = (string) $asset->asset_tag;
            $href = '/assets/'.$tag;
            $routes[] = $href;
            $current = AssetStatus::tryFrom(strtoupper((string) $asset->status));
            $allowed = $current === null
                ? []
                : array_values(array_map(
                    static fn (AssetStatus $next): string => $next->value,
                    array_filter(
                        AssetStatus::cases(),
                        static fn (AssetStatus $next): bool => $next !== $current && $current->canTransitionTo($next),
                    ),
                ));
            $payload[] = [
                'asset_tag' => $tag,
                'serial_number' => $asset->serial_number,
                'status' => $asset->status,
                'allowed_statuses' => $allowed,
                'asset_type' => $asset->asset_type,
                'manufacturer' => $asset->manufacturer,
                'product_name' => $asset->product_name,
                'hostname' => $asset->hostname,
                'href' => $href,
            ];
        }

        $count = count($payload);
        if ($count === 0) {
            $summary = sprintf('No IT asset with serial or tag "%s".', $query);
        } else {
            $first = $payload[0];
            $allowed = is_array($first['allowed_statuses'] ?? null) ? $first['allowed_statuses'] : [];
            $summary = sprintf(
                'Found %d IT asset. Tag %s, serial %s, status %s. Statuses that can be set next: %s. A status change is saved only after the confirm card.',
                $count,
                (string) $first['asset_tag'],
                (string) ($first['serial_number'] ?? ''),
                (string) ($first['status'] ?? ''),
                $allowed === [] ? 'none' : implode(', ', $allowed),
            );
        }

        return new ToolResult(
            tool: $this->name(),
            ok: true,
            data: ['assets' => $payload],
            summary: $summary,
            moduleKey: 'asset_management',
            relatedRoutes: array_values(array_unique($routes)),
            rowCount: $count,
        );
    }
}
