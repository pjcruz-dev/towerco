<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Support\ControlledDocumentAccessPolicy;
use App\Modules\Documents\Support\ControlledDocumentSyncConfig;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormRequestAccessSupport;
use App\Modules\Identity\Models\TenantUser;

final class ControlledDocumentFormResolverService
{
    public function resolvePublishedDefaultForm(): ?EApprovalForm
    {
        return EApprovalForm::query()
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->get()
            ->first(function (EApprovalForm $form): bool {
                $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

                if (($metadata['default_controlled_document_form'] ?? false) === true) {
                    return true;
                }

                return ($metadata['form_family'] ?? null) === 'iso_document_control';
            });
    }

    public function resolvePublishedDefaultFormFor(TenantUser $actor): ?EApprovalForm
    {
        return EApprovalForm::query()
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->get()
            ->first(function (EApprovalForm $form) use ($actor): bool {
                $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
                $isControlled = ($metadata['default_controlled_document_form'] ?? false) === true
                    || ($metadata['form_family'] ?? null) === 'iso_document_control';
                if (! $isControlled || ControlledDocumentSyncConfig::parse($metadata) === null) {
                    return false;
                }

                return EApprovalFormRequestAccessSupport::viewerCanStart($actor, $metadata);
            });
    }

    public function resolveSyncConfig(): ?ControlledDocumentSyncConfig
    {
        $form = $this->resolvePublishedDefaultForm();
        if (! $form instanceof EApprovalForm) {
            return null;
        }

        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];

        return ControlledDocumentSyncConfig::parse($metadata);
    }

    public function resolveAccessPolicy(): ControlledDocumentAccessPolicy
    {
        $form = $this->resolvePublishedDefaultForm();
        if (! $form instanceof EApprovalForm) {
            return ControlledDocumentAccessPolicy::defaults();
        }

        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $sync = $metadata['controlledDocumentSync'] ?? $metadata['controlled_document_sync'] ?? null;
        if (! is_array($sync)) {
            return ControlledDocumentAccessPolicy::defaults();
        }

        $raw = $sync['accessPolicy'] ?? $sync['access_policy'] ?? null;

        return ControlledDocumentAccessPolicy::parse(is_array($raw) ? $raw : null);
    }
}
