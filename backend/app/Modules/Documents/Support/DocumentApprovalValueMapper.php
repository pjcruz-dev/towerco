<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

use App\Modules\Documents\Models\Document;
use App\Modules\EApproval\Models\EApprovalForm;

/**
 * Maps TowerOS document context onto E-Approval form field names when present.
 */
final class DocumentApprovalValueMapper
{
    /**
     * @param  array<string, mixed>  $extraValues
     * @return array<string, mixed>
     */
    public function map(EApprovalForm $form, Document $document, array $extraValues = []): array
    {
        $document->loadMissing(['site:id,site_code,name', 'siteNode:id,label']);
        $site = $document->site;
        $folderLabel = $document->siteNode?->label;

        $context = [
            'toweros_document_id' => (string) $document->id,
            'document_id' => (string) $document->id,
            'document_title' => $document->title,
            'title' => $document->title,
            'document_name' => $document->title,
            'original_filename' => $document->original_filename,
            'site_code' => $site?->site_code,
            'site' => $site ? trim($site->site_code.' · '.$site->name) : null,
            'site_name' => $site?->name,
            'folder' => $folderLabel,
            'folder_path' => $folderLabel,
            'binder_folder' => $folderLabel,
        ];

        $values = $extraValues;
        foreach ($form->fields as $field) {
            $name = (string) $field->name;
            if ($name === '' || array_key_exists($name, $values)) {
                continue;
            }
            if (array_key_exists($name, $context) && $context[$name] !== null && $context[$name] !== '') {
                $values[$name] = $context[$name];
            }
        }

        return $values;
    }
}
