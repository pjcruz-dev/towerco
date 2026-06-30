<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\Identity\Models\TenantUser;

final class FormPublishService
{
    public function __construct(
        private readonly EApprovalFormRevisionService $revisions,
    ) {}

    public function publish(EApprovalForm $form, ?TenantUser $actor = null): EApprovalForm
    {
        $form->loadMissing(['fields', 'workflowTemplate.steps']);
        $form->published_snapshot = json_encode($form->toStorageSnapshot(), JSON_THROW_ON_ERROR);
        $form->status = 'published';
        $form->schema_version = max(1, (int) $form->schema_version) + 1;
        $form->save();

        if ($actor !== null) {
            $this->revisions->record($form->fresh(['fields', 'workflowTemplate.steps']), $actor, 'published');
        }

        return $form->fresh(['fields', 'workflowTemplate.steps']);
    }
}
