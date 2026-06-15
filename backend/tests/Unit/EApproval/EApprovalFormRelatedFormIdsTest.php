<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Models\EApprovalForm;
use PHPUnit\Framework\TestCase;

final class EApprovalFormRelatedFormIdsTest extends TestCase
{
    public function test_empty_array_stores_as_null(): void
    {
        $form = new EApprovalForm;
        $form->related_form_ids = [];

        $this->assertNull($form->getAttributes()['related_form_ids']);
    }

    public function test_non_empty_array_stores_as_json_string(): void
    {
        $form = new EApprovalForm;
        $form->related_form_ids = ['019e9044-f543-72bd-9b55-d9cb0c62c46c'];

        $this->assertSame(
            '["019e9044-f543-72bd-9b55-d9cb0c62c46c"]',
            $form->getAttributes()['related_form_ids'],
        );
    }

    public function test_string_value_is_preserved(): void
    {
        $form = new EApprovalForm;
        $form->related_form_ids = '["a","b"]';

        $this->assertSame('["a","b"]', $form->getAttributes()['related_form_ids']);
    }

    public function test_sample_import_json_empty_related_form_ids(): void
    {
        $samplesDir = dirname(__DIR__, 4).'/docs/samples/e-approval-imports';

        foreach (['01-document-approval.json', '02-iso-approval.json', '03-payment-request.json'] as $file) {
            $path = $samplesDir.'/'.$file;
            $this->assertFileExists($path, $file);

            $envelope = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $related = $envelope['form']['related_form_ids'] ?? null;

            $form = new EApprovalForm;
            $form->related_form_ids = is_array($related) ? $related : null;

            $this->assertNull(
                $form->getAttributes()['related_form_ids'],
                'Expected null storage for empty related_form_ids in '.$file,
            );
        }
    }
}
