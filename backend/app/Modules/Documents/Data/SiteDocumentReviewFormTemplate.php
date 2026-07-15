<?php

declare(strict_types=1);

namespace App\Modules\Documents\Data;

/**
 * Platform definition for the Site document review E-Approval form.
 */
final class SiteDocumentReviewFormTemplate
{
    public const TEMPLATE_ID = 'site_document_review';

    /**
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        return [
            'name' => 'Site document review',
            'description' => 'Review and approve site binder documents. Fields auto-fill from the Site binder when you request approval.',
            'category' => 'documents',
            'doc_type_code' => 'SDR',
            'metadata_json' => [
                'form_family' => self::TEMPLATE_ID,
                'default_site_document_review_form' => true,
            ],
            'fields' => [
                [
                    'type' => 'section',
                    'name' => 'section_document',
                    'label' => 'Document',
                    'step_order' => 1,
                ],
                [
                    'type' => 'text',
                    'name' => 'document_title',
                    'label' => 'Document title',
                    'step_order' => 2,
                    'validation' => ['required' => true],
                ],
                [
                    'type' => 'text',
                    'name' => 'site_code',
                    'label' => 'Site code',
                    'step_order' => 3,
                    'validation' => ['required' => true],
                    'options' => ['layout' => ['width' => 'half', 'row_id' => 'site_row', 'slot' => 0]],
                ],
                [
                    'type' => 'text',
                    'name' => 'site',
                    'label' => 'Site',
                    'step_order' => 4,
                    'options' => ['layout' => ['width' => 'half', 'row_id' => 'site_row', 'slot' => 1]],
                ],
                [
                    'type' => 'text',
                    'name' => 'binder_folder',
                    'label' => 'Binder folder',
                    'step_order' => 5,
                ],
                [
                    'type' => 'textarea',
                    'name' => 'review_notes',
                    'label' => 'Notes for approver',
                    'step_order' => 6,
                    'validation' => ['placeholder' => 'Optional context for the reviewer'],
                ],
            ],
            'steps' => [
                ['type' => 'role', 'step_order' => 1, 'approverId' => 'tenant_admin'],
            ],
        ];
    }
}
