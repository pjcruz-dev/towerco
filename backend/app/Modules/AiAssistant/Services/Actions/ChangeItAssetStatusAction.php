<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\AiAssistant\Models\AiMessage;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use App\Modules\AssetManagement\Support\AssetStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Propose an IT asset status change. The asset row changes only after confirm.
 */
final class ChangeItAssetStatusAction implements AssistantActionInterface
{
    public function __construct(
        private readonly AssetRegistryService $assets,
    ) {}

    public function name(): string
    {
        return 'change_it_asset_status';
    }

    public function description(): string
    {
        return 'Propose changing an IT asset status. Saves only after confirmation.';
    }

    public function requiredModule(): ?string
    {
        return 'asset_management';
    }

    public function requiredDomainPermissions(): array
    {
        return ['assets:manage'];
    }

    /**
     * Fields the catalog planner may fill. propose() resolves these to the execute payload.
     *
     * @return array<string, mixed>
     */
    public function plannerArgumentRules(): array
    {
        return [
            'query' => ['sometimes', 'nullable', 'string', 'max:80'],
            'asset_tag' => ['sometimes', 'nullable', 'string', 'max:40'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:80'],
            'status' => ['required', 'string', 'max:40'],
        ];
    }

    public function argumentRules(): array
    {
        return [
            'asset_tag' => ['required', 'string', 'max:40'],
            'status' => ['required', 'string', 'max:40', function (string $attribute, mixed $value, \Closure $fail): void {
                unset($attribute);
                if ($this->normalizeStatus((string) $value) === null) {
                    $fail('Status must be one of: '.implode(', ', AssetStatus::values()).'.');
                }
            }],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        unset($viewer);
        $needle = $this->argumentString($args, 'query')
            ?? $this->argumentString($args, 'asset_tag')
            ?? $this->argumentString($args, 'serial_number')
            ?? $this->argumentString($args, 'serial');
        if ($needle !== null) {
            $needle = strtoupper($needle);
        }
        if ($needle === null) {
            $needle = $this->identifier($question);
        }
        if ($needle === null) {
            $conversationId = isset($args['conversation_id']) && is_string($args['conversation_id'])
                ? $args['conversation_id']
                : null;
            $needle = $this->identifierFromConversation($conversationId);
        }

        if ($needle === null) {
            throw ValidationException::withMessages([
                'asset' => ['Include the serial or asset tag for the laptop you want to update.'],
            ]);
        }

        $asset = $this->findAsset($needle);
        if (! $asset instanceof Asset) {
            throw ValidationException::withMessages([
                'asset' => [sprintf('No IT asset matches %s.', $needle)],
            ]);
        }

        $next = $this->argumentString($args, 'status');
        $next = $next !== null ? $this->normalizeStatus($next) : null;
        if ($next === null) {
            $next = $this->targetStatus($question);
        }
        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => ['Say the status to set. From '.(string) $asset->status.' the choices are: '.implode(', ', $this->allowed($asset)).'.'],
            ]);
        }

        $current = AssetStatus::parse((string) $asset->status);
        $current->assertCanTransitionTo(AssetStatus::parse($next), (string) $asset->asset_tag);

        $allowed = $this->allowed($asset);

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Change asset status',
            summary: sprintf(
                'I can change %s (serial %s) from %s to %s. Choices from %s: %s.',
                (string) $asset->asset_tag,
                (string) $asset->serial_number,
                (string) $asset->status,
                $next,
                (string) $asset->status,
                $allowed === [] ? 'none' : implode(', ', $allowed),
            ),
            payload: [
                'asset_tag' => (string) $asset->asset_tag,
                'status' => $next,
            ],
            preview: [
                'asset_tag' => (string) $asset->asset_tag,
                'serial' => (string) $asset->serial_number,
                'from' => (string) $asset->status,
                'to' => $next,
                'allowed' => implode(', ', $allowed),
            ],
            editableFields: [
                ['key' => 'status', 'label' => 'New status', 'type' => 'text', 'required' => true],
            ],
            moduleKey: 'asset_management',
            confirmLabel: 'Change status',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $tag = strtoupper(trim((string) ($payload['asset_tag'] ?? '')));
        $next = $this->normalizeStatus((string) ($payload['status'] ?? ''));
        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => ['Status must be one of: '.implode(', ', AssetStatus::values()).'.'],
            ]);
        }

        $asset = Asset::query()->whereRaw('UPPER(asset_tag) = ?', [$tag])->first();
        if (! $asset instanceof Asset) {
            throw ValidationException::withMessages([
                'asset_tag' => [sprintf('No IT asset matches %s.', $tag)],
            ]);
        }

        $updated = $this->assets->update($asset, ['status' => $next], $viewer);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'it_asset',
            entityId: (string) $updated->id,
            entityLabel: sprintf('%s is now %s', (string) $updated->asset_tag, (string) $updated->status),
            meta: [
                'asset_tag' => (string) $updated->asset_tag,
                'serial_number' => (string) $updated->serial_number,
                'status' => (string) $updated->status,
            ],
            href: '/assets/'.rawurlencode((string) $updated->asset_tag),
        );
    }

    /**
     * @return list<string>
     */
    private function allowed(Asset $asset): array
    {
        $current = AssetStatus::tryFrom(strtoupper((string) $asset->status));
        if ($current === null) {
            return [];
        }

        $next = [];
        foreach (AssetStatus::cases() as $status) {
            if ($status !== $current && $current->canTransitionTo($status)) {
                $next[] = $status->value;
            }
        }

        return $next;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function argumentString(array $args, string $key): ?string
    {
        $value = $args[$key] ?? null;
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function findAsset(string $needle): ?Asset
    {
        $needle = strtoupper(trim($needle));

        return Asset::query()
            ->where(function ($builder) use ($needle): void {
                $builder->whereRaw('UPPER(serial_number) = ?', [$needle])
                    ->orWhereRaw('UPPER(asset_tag) = ?', [$needle]);
            })
            ->first();
    }

    private function identifier(string $text): ?string
    {
        if (preg_match('/\b(ATC-(?:LAP|DSK|MON|DOC|PHN|PER)-\d+)\b/i', $text, $match) === 1) {
            return strtoupper($match[1]);
        }

        if (preg_match('/\b(SN-[A-Z0-9]+(?:-[A-Z0-9]+)*)\b/i', $text, $match) === 1) {
            return strtoupper($match[1]);
        }

        return null;
    }

    private function identifierFromConversation(?string $conversationId): ?string
    {
        if ($conversationId === null || $conversationId === '') {
            return null;
        }

        $messages = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['content']);

        foreach ($messages as $message) {
            $found = $this->identifier((string) $message->content);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function targetStatus(string $question): ?string
    {
        if (preg_match('/\bto\s+(.+)$/iu', trim($question), $match) === 1) {
            $fromTail = $this->normalizeStatus($match[1]);
            if ($fromTail !== null) {
                return $fromTail;
            }
        }

        if (preg_match('/\b(?:mark|set)\b.{0,30}\b([a-z][a-z0-9 _-]{2,40})/iu', $question, $match) === 1) {
            return $this->normalizeStatus($match[1]);
        }

        return null;
    }

    private function normalizeStatus(string $value): ?string
    {
        $compact = strtoupper(trim($value));
        $compact = preg_replace('/[^A-Z0-9]+/', '_', $compact) ?? '';
        $compact = trim($compact, '_');
        if ($compact === '') {
            return null;
        }

        $phrases = [
            'UNDER_REPAIR' => AssetStatus::UnderRepair->value,
            'REPLACEMENT_PENDING' => AssetStatus::ReplacementPending->value,
            'E_WASTE' => AssetStatus::EWaste->value,
            'EWASTE' => AssetStatus::EWaste->value,
        ];
        foreach ($phrases as $label => $status) {
            if ($compact === $label || str_starts_with($compact, $label.'_')) {
                return $status;
            }
        }

        foreach (AssetStatus::values() as $status) {
            if ($compact === $status || str_starts_with($compact, $status.'_')) {
                return $status;
            }
        }

        return null;
    }
}
