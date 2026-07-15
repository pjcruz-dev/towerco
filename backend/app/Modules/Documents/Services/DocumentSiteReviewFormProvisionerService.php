<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Data\SiteDocumentReviewFormTemplate;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalFormService;
use App\Modules\EApproval\Services\EApprovalFormSyncService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;

final class DocumentSiteReviewFormProvisionerService
{
    public function __construct(
        private readonly DocumentSiteReviewFormResolverService $resolver,
        private readonly EApprovalFormService $forms,
        private readonly EApprovalFormSyncService $sync,
    ) {}

    public function ensure(TenantUser $actor): EApprovalForm
    {
        $existing = $this->resolver->resolvePublishedDefaultForm();
        if ($existing instanceof EApprovalForm) {
            $this->repairWorkflow($existing);

            return $existing->fresh(['fields', 'workflowTemplate.steps']);
        }

        return DB::connection('tenant')->transaction(function () use ($actor): EApprovalForm {
            $existing = $this->resolver->resolvePublishedDefaultForm();
            if ($existing instanceof EApprovalForm) {
                $this->repairWorkflow($existing);

                return $existing->fresh(['fields', 'workflowTemplate.steps']);
            }

            $definition = SiteDocumentReviewFormTemplate::definition();
            $metadata = is_array($definition['metadata_json'] ?? null) ? $definition['metadata_json'] : [];
            $metadata['created_from_template'] = SiteDocumentReviewFormTemplate::TEMPLATE_ID;
            $metadata['default_site_document_review_form'] = true;
            $metadata['form_family'] = SiteDocumentReviewFormTemplate::TEMPLATE_ID;

            $result = $this->forms->create([
                'name' => (string) $definition['name'],
                'description' => $definition['description'] ?? null,
                'category' => $definition['category'] ?? 'documents',
                'doc_type_code' => $definition['doc_type_code'] ?? 'SDR',
                'status' => 'published',
                'fields' => $definition['fields'] ?? [],
                'steps' => $definition['steps'] ?? [],
                'metadata_json' => $metadata,
            ], $actor);

            return $result['form'];
        });
    }

    public function repairWorkflow(EApprovalForm $form): void
    {
        $definition = SiteDocumentReviewFormTemplate::definition();
        $this->sync->sync($form, [
            'fields' => $definition['fields'],
            'steps' => $definition['steps'],
        ]);
    }
}
