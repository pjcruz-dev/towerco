<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalUserAttachment;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EApprovalUserProfileService
{
    public function __construct(
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalFileStorageService $storage,
        private readonly EApprovalDelegationService $delegations,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function profile(TenantUser $user): array
    {
        $savedSignature = $this->settings->getUserSignature((string) $user->id);
        $latestApprovalSignature = $savedSignature === null
            ? $this->resolveLatestApprovalSignature((string) $user->id)
            : null;

        return [
            'user_id' => (string) $user->id,
            'signature' => $savedSignature ?? $latestApprovalSignature,
            'signature_source' => $savedSignature !== null
                ? 'profile'
                : ($latestApprovalSignature !== null ? 'last_approval' : null),
            'delegations' => array_map(
                fn ($d) => $this->delegations->present($d),
                $this->delegations->listForUser($user),
            ),
            'attachments' => $this->listAttachments($user),
            'public_ui' => $this->settings->publicUiFlags(),
        ];
    }

    public function updateSignature(TenantUser $user, ?string $signature, TenantUser $actor): void
    {
        if ((string) $actor->id !== (string) $user->id && ! $actor->can('e_approval:settings:manage')) {
            throw ValidationException::withMessages([
                'signature' => [__('You can only update your own signature.')],
            ]);
        }

        $this->settings->setUserSignature((string) $user->id, $signature);
    }

    private function resolveLatestApprovalSignature(string $userId): ?string
    {
        $signature = EApprovalRequestApproval::query()
            ->where('approver_id', $userId)
            ->where('status', EApprovalApprovalStatus::APPROVED)
            ->whereNotNull('signature')
            ->where('signature', '!=', '')
            ->orderByDesc('acted_at')
            ->value('signature');

        if (! is_string($signature)) {
            return null;
        }

        $trimmed = trim($signature);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAttachments(TenantUser $user): array
    {
        return EApprovalUserAttachment::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (EApprovalUserAttachment $a) => [
                'id' => (string) $a->id,
                'file_name' => $a->file_name,
                'metadata' => $a->metadata,
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function storeAttachment(TenantUser $user, UploadedFile $file, ?array $metadata = null): EApprovalUserAttachment
    {
        $stored = $this->storage->storeUserAttachment($user, $file);

        return EApprovalUserAttachment::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'file_path' => $stored['path'],
            'file_name' => $stored['name'],
            'metadata' => $metadata,
        ]);
    }

    public function deleteAttachment(EApprovalUserAttachment $attachment, TenantUser $actor): void
    {
        if ((string) $attachment->user_id !== (string) $actor->id && ! $actor->can('e_approval:settings:manage')) {
            throw ValidationException::withMessages([
                'attachment' => [__('You cannot delete this attachment.')],
            ]);
        }

        $this->storage->deleteIfExists($attachment->file_path);
        $attachment->delete();
    }
}
