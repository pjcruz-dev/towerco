<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Change identity fields on one IT asset. Status stays on change_it_asset_status.
 */
final class UpdateItAssetAction implements AssistantActionInterface
{
    /** @var list<string> */
    private const FIELDS = [
        'manufacturer',
        'product_name',
        'product_version',
        'hostname',
        'notes',
        'processor',
        'installed_ram',
        'operating_system',
    ];

    public function __construct(
        private readonly AssetRegistryService $assets,
    ) {}

    public function name(): string
    {
        return 'update_it_asset';
    }

    public function description(): string
    {
        return 'Update one IT asset field such as notes, manufacturer, product name, or hostname. Does not change status.';
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
            'asset_tag' => ['sometimes', 'nullable', 'string', 'max:40'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:80'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_version' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hostname' => ['sometimes', 'nullable', 'string', 'max:15'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'processor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'installed_ram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'operating_system' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function argumentRules(): array
    {
        return [
            'asset_tag' => ['required', 'string', 'max:40'],
            'manufacturer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'product_version' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hostname' => ['sometimes', 'nullable', 'string', 'max:15'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'processor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'installed_ram' => ['sometimes', 'nullable', 'string', 'max:255'],
            'operating_system' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($viewer);
        $needle = $this->stringFrom($args, 'asset_tag')
            ?? $this->stringFrom($args, 'serial_number')
            ?? $this->identifier($question);
        if ($needle === null) {
            throw ValidationException::withMessages([
                'asset' => ['Include the serial or asset tag for the asset you want to update.'],
            ]);
        }

        $asset = $this->find($needle);
        $changes = $this->changes($args, $question);
        if ($changes === []) {
            throw ValidationException::withMessages([
                'asset' => ['Say which field to change, such as notes, manufacturer, or hostname.'],
            ]);
        }

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Update IT asset',
            summary: sprintf('%s will be updated.', (string) $asset->asset_tag),
            payload: array_merge(['asset_tag' => (string) $asset->asset_tag], $changes),
            preview: $changes,
            moduleKey: 'asset_management',
            confirmLabel: 'Update asset',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $tag = strtoupper(trim((string) ($payload['asset_tag'] ?? '')));
        $asset = Asset::query()->whereRaw('UPPER(asset_tag) = ?', [$tag])->first();
        if (! $asset instanceof Asset) {
            throw ValidationException::withMessages([
                'asset_tag' => [sprintf('No IT asset matches %s.', $tag)],
            ]);
        }

        $data = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $payload[$field];
            }
        }
        $updated = $this->assets->update($asset, $data, $viewer);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'it_asset',
            entityId: (string) $updated->id,
            entityLabel: sprintf('%s was updated.', (string) $updated->asset_tag),
            meta: [
                'asset_tag' => (string) $updated->asset_tag,
                'notes' => $updated->notes,
            ],
            href: '/assets/'.rawurlencode((string) $updated->asset_tag),
        );
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, string>
     */
    private function changes(array $args, string $question): array
    {
        $changes = [];
        foreach (self::FIELDS as $field) {
            $value = $this->stringFrom($args, $field);
            if ($value !== null) {
                $changes[$field] = $value;
            }
        }
        if ($changes !== []) {
            return $changes;
        }

        if (preg_match('/\bnotes\b.+\bto\s+(.+)$/iu', $question, $match) === 1) {
            $changes['notes'] = trim($match[1]);
        } elseif (preg_match('/\bmanufacturer\b.+\bto\s+(.+)$/iu', $question, $match) === 1) {
            $changes['manufacturer'] = trim($match[1]);
        } elseif (preg_match('/\bhostname\b.+\bto\s+(\S+)/iu', $question, $match) === 1) {
            $changes['hostname'] = trim($match[1]);
        }

        return array_filter($changes, static fn (string $value): bool => $value !== '');
    }

    private function find(string $needle): Asset
    {
        $needle = strtoupper(trim($needle));
        $asset = Asset::query()
            ->whereRaw('UPPER(asset_tag) = ?', [$needle])
            ->orWhereRaw('UPPER(serial_number) = ?', [$needle])
            ->first();
        if (! $asset instanceof Asset) {
            throw ValidationException::withMessages([
                'asset' => [sprintf('No IT asset matches %s.', $needle)],
            ]);
        }

        return $asset;
    }

    private function identifier(string $question): ?string
    {
        if (preg_match('/\b(SN-[A-Z0-9]+(?:-[A-Z0-9]+)*|ATC-(?:LAP|DSK|MON|DOC|PHN|PER)-\d+)\b/i', $question, $match) !== 1) {
            return null;
        }

        return strtoupper($match[1]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function stringFrom(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
