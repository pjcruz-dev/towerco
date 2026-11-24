<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalSubmission;

/** Where a submission opens. Document-control requests stay in EDCS; other forms stay in E-Forms. */
final class EApprovalSubmissionLink
{
    public static function isControlledDocument(EApprovalSubmission $submission): bool
    {
        $form = $submission->relationLoaded('form') ? $submission->form : null;
        if ($form !== null && ! array_key_exists('metadata_json', $form->getAttributes())) {
            $submission->unsetRelation('form');
        }
        $submission->loadMissing('form:id,metadata_json');
        $metadata = $submission->form?->metadata_json;

        return is_array($metadata)
            && ($metadata['form_family'] ?? null) === EApprovalFormWorkspaceSupport::ISO_FORM_FAMILY;
    }

    public static function path(EApprovalSubmission $submission, ?string $tab = null): string
    {
        $base = self::isControlledDocument($submission)
            ? '/documents/controlled/requests/'.$submission->id
            : '/e-approval/submissions/'.$submission->id;

        $tab = trim((string) $tab);

        return $tab === '' ? $base : $base.'?tab='.$tab;
    }
}
