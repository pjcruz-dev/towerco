<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Typed reader for form metadata_json.outbound (deliverables emailed to externals on approve).
 */
final class EApprovalOutboundPackageConfig
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array{email_package_on_approve: bool}
     */
    public static function fromFormMetadata(?array $metadata): array
    {
        $raw = is_array($metadata['outbound'] ?? null) ? $metadata['outbound'] : [];

        return [
            'email_package_on_approve' => (bool) ($raw['email_package_on_approve'] ?? false),
        ];
    }
}
