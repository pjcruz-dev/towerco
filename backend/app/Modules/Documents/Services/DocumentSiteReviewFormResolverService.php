<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Data\SiteDocumentReviewFormTemplate;
use App\Modules\EApproval\Models\EApprovalForm;

final class DocumentSiteReviewFormResolverService
{
    public const TEMPLATE_ID = SiteDocumentReviewFormTemplate::TEMPLATE_ID;

    public function resolvePublishedDefaultForm(): ?EApprovalForm
    {
        return EApprovalForm::query()
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->get()
            ->first(function (EApprovalForm $form): bool {
                $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

                if (($metadata['default_site_document_review_form'] ?? false) === true) {
                    return true;
                }

                if (($metadata['created_from_template'] ?? null) === self::TEMPLATE_ID) {
                    return true;
                }

                return ($metadata['form_family'] ?? null) === self::TEMPLATE_ID;
            });
    }
}
