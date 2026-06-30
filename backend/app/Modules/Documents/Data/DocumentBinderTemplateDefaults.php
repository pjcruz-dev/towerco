<?php

declare(strict_types=1);

namespace App\Modules\Documents\Data;

/**
 * Platform-default site binder template (all tenants).
 */
final class DocumentBinderTemplateDefaults
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     children?: list<array<string, mixed>>
     * }>
     */
    public static function tree(): array
    {
        return [
            [
                'key' => 'esite_binder',
                'label' => 'eSite Binder (SAQ / Legal)',
                'type' => 'binder',
                'children' => [
                    [
                        'key' => 'saq_phase_1',
                        'label' => 'SAQ Phase 1',
                        'type' => 'fixed',
                    ],
                    [
                        'key' => 'lessors',
                        'label' => 'Lessors',
                        'type' => 'repeatable_container',
                        'children' => [
                            [
                                'key' => 'lessor_documents',
                                'label' => 'Documents',
                                'type' => 'fixed',
                            ],
                        ],
                    ],
                    [
                        'key' => 'legal',
                        'label' => 'Legal',
                        'type' => 'folder',
                        'children' => [
                            ['key' => 'col', 'label' => 'COL', 'type' => 'fixed'],
                            ['key' => 'affidavit', 'label' => 'Affidavit', 'type' => 'fixed'],
                            ['key' => 'vendor_contracts', 'label' => 'Vendor contracts', 'type' => 'fixed'],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'esite_folder',
                'label' => 'eSite Folder (Engineering)',
                'type' => 'binder',
                'children' => [
                    ['key' => 'drawings', 'label' => 'Drawings', 'type' => 'fixed'],
                    ['key' => 'boq', 'label' => 'BOQ / Estimates', 'type' => 'fixed'],
                    ['key' => 'structural', 'label' => 'Structural / Design', 'type' => 'fixed'],
                    ['key' => 'as_built', 'label' => 'As-built', 'type' => 'fixed'],
                    ['key' => 'engineering_other', 'label' => 'Other', 'type' => 'fixed'],
                ],
            ],
        ];
    }
}
