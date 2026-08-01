<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Services\EApprovalSubmissionValuesValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EApprovalSubmissionValuesValidatorTest extends TestCase
{
    public function test_submit_validation_rejects_empty_required_grid(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'line_items',
                'label' => 'Line items',
                'type' => 'grid',
                'validation' => ['required' => true],
                'options' => ['columns' => [['label' => 'Description'], ['label' => 'Qty']]],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['line_items' => '{"rows":[]}'], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.line_items', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_rejects_missing_required_file_attachment(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'quotes',
                'label' => 'Vendor quotes',
                'type' => 'file',
                'validation' => ['required' => true],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, [], requireRequired: true, attachmentCountsByFieldName: []);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.quotes', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_rejects_unchecked_required_boolean_checkbox(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'terms',
                'label' => 'Terms',
                'type' => 'checkbox',
                'validation' => ['required' => true],
                'options' => [],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['terms' => 'false'], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.terms', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_accepts_checked_required_boolean_checkbox(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'terms',
                'label' => 'Terms',
                'type' => 'checkbox',
                'validation' => ['required' => true],
                'options' => [],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);
        $validator->validate($form, ['terms' => 'true'], requireRequired: true);

        $this->assertTrue(true);
    }

    public function test_submit_validation_rejects_empty_required_multi_checkbox(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'checklist',
                'label' => 'Checklist',
                'type' => 'checkbox',
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'a', 'label' => 'Option A'],
                        ['value' => 'b', 'label' => 'Option B'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['checklist' => ''], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.checklist', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_rejects_invalid_multi_checkbox_option(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'checklist',
                'label' => 'Checklist',
                'type' => 'checkbox',
                'validation' => ['required' => false],
                'options' => [
                    'choices' => [
                        ['value' => 'a', 'label' => 'Option A'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['checklist' => 'z'], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.checklist', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_accepts_multi_checkbox_selection(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'checklist',
                'label' => 'Checklist',
                'type' => 'checkbox',
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        ['value' => 'a', 'label' => 'Option A'],
                        ['value' => 'b', 'label' => 'Option B'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);
        $validator->validate($form, ['checklist' => 'a,b'], requireRequired: true);

        $this->assertTrue(true);
    }

    public function test_submit_validation_rejects_missing_required_checkbox_companion(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'structure',
                'label' => 'Type and Height',
                'type' => 'checkbox',
                'validation' => ['required' => false],
                'options' => [
                    'choices' => [
                        [
                            'value' => 'self_supporting',
                            'label' => 'Self Supporting Tower or Pole',
                            'inputs' => [
                                [
                                    'key' => 'height_agl',
                                    'type' => 'number',
                                    'suffix' => 'm.(AGL)',
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['structure' => 'self_supporting'], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.structure', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_accepts_checkbox_with_companion_values(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'structure',
                'label' => 'Type and Height',
                'type' => 'checkbox',
                'validation' => ['required' => true],
                'options' => [
                    'choices' => [
                        [
                            'value' => 'self_supporting',
                            'label' => 'Self Supporting Tower or Pole',
                            'inputs' => [
                                [
                                    'key' => 'height_agl',
                                    'type' => 'number',
                                    'suffix' => 'm.(AGL)',
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]));

        $payload = json_encode([
            'selected' => ['self_supporting'],
            'companions' => [
                'self_supporting' => ['height_agl' => '15'],
            ],
        ], JSON_THROW_ON_ERROR);

        $validator = app(EApprovalSubmissionValuesValidator::class);
        $validator->validate($form, ['structure' => $payload], requireRequired: true);

        $this->assertTrue(true);
    }

    public function test_submit_validation_rejects_incomplete_required_matrix(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'c2',
                'label' => 'C2',
                'type' => 'matrix',
                'validation' => ['required' => true],
                'options' => [
                    'rows' => [
                        ['value' => 'a', 'label' => 'A. Cut and Fill'],
                        ['value' => 'b', 'label' => 'B. Slope Protection'],
                    ],
                    'columns' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['c2' => '{"a":"yes"}'], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.c2', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_accepts_complete_matrix(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'c2',
                'label' => 'C2',
                'type' => 'matrix',
                'validation' => ['required' => true],
                'options' => [
                    'rows' => [
                        ['value' => 'a', 'label' => 'A. Cut and Fill'],
                        ['value' => 'b', 'label' => 'B. Slope Protection'],
                    ],
                    'columns' => [
                        ['value' => 'yes', 'label' => 'Yes'],
                        ['value' => 'no', 'label' => 'No'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);
        $validator->validate(
            $form,
            ['c2' => '{"a":"yes","b":"no"}'],
            requireRequired: true,
        );

        $this->assertTrue(true);
    }

    public function test_submit_validation_rejects_incomplete_required_size_matrix(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'components',
                'label' => 'Components',
                'type' => 'size_matrix',
                'validation' => ['required' => true],
                'options' => [
                    'rows' => [
                        ['value' => 'roofdeck', 'label' => 'Roofdeck'],
                        ['value' => 'wall', 'label' => 'Wall'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['components' => '{"roofdeck":{"w":"10","h":"12"}}'], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.components', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_accepts_size_matrix_with_na(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'components',
                'label' => 'Components',
                'type' => 'size_matrix',
                'validation' => ['required' => true],
                'options' => [
                    'rows' => [
                        ['value' => 'roofdeck', 'label' => 'Roofdeck'],
                        ['value' => 'wall', 'label' => 'Wall'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);
        $validator->validate(
            $form,
            ['components' => '{"roofdeck":{"w":"10","h":"12"},"wall":{"na":true}}'],
            requireRequired: true,
        );

        $this->assertTrue(true);
    }

    public function test_submit_validation_accepts_size_matrix_with_optional_text_rows(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'components',
                'label' => 'Components',
                'type' => 'size_matrix',
                'validation' => ['required' => true],
                'options' => [
                    'rows' => [
                        ['value' => 'roofdeck', 'label' => 'Roofdeck', 'input' => 'size'],
                        ['value' => 'other', 'label' => 'Other (specify)', 'input' => 'text'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);
        $validator->validate(
            $form,
            ['components' => '{"roofdeck":{"w":"10","h":"12"},"other":{"text":"Parapet"}}'],
            requireRequired: true,
        );

        $this->assertTrue(true);
    }

    public function test_submit_validation_rejects_empty_required_checklist_matrix(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'cost',
                'label' => 'Cost Application',
                'type' => 'checklist_matrix',
                'validation' => ['required' => true],
                'options' => [
                    'rows' => [
                        ['value' => 'others', 'label' => 'Others'],
                        ['value' => 'logistics', 'label' => 'Logistics'],
                    ],
                    'columns' => [
                        ['value' => 'ref_no', 'label' => 'Ref No'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);

        $this->expectException(ValidationException::class);

        try {
            $validator->validate($form, ['cost' => ''], requireRequired: true);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('values.cost', $exception->errors());

            throw $exception;
        }
    }

    public function test_submit_validation_accepts_selected_checklist_matrix_row(): void
    {
        $form = new EApprovalForm(['id' => 'form-1']);
        $form->setRelation('fields', collect([
            new EApprovalFormField([
                'name' => 'cost',
                'label' => 'Cost Application',
                'type' => 'checklist_matrix',
                'validation' => ['required' => true],
                'options' => [
                    'rows' => [
                        ['value' => 'others', 'label' => 'Others'],
                        ['value' => 'logistics', 'label' => 'Logistics'],
                    ],
                    'columns' => [
                        ['value' => 'ref_no', 'label' => 'Ref No'],
                        ['value' => 'or_no', 'label' => 'OR No.'],
                    ],
                ],
            ]),
        ]));

        $validator = app(EApprovalSubmissionValuesValidator::class);
        $validator->validate(
            $form,
            ['cost' => '{"others":{"selected":true,"cells":{"ref_no":"R-1","or_no":""}}}'],
            requireRequired: true,
        );

        $this->assertTrue(true);
    }
}
