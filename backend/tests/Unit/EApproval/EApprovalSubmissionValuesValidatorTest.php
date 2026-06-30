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
}
