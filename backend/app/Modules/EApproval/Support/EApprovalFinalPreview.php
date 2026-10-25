<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Step 3 is the only final approver. Step 4 exists on a request only when
 * that person sends it for one preview. The preview returns to step 3.
 */
final class EApprovalFinalPreview
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{enabled: bool, step_order: int}
     */
    public static function fromMetadata(?array $metadata): array
    {
        $raw = is_array($metadata['final_preview'] ?? null) ? $metadata['final_preview'] : [];

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'step_order' => 3,
        ];
    }
}
