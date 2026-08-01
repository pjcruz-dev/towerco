<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Services\EApprovalFormValueDisplayService;
use PHPUnit\Framework\TestCase;

final class EApprovalFormValueDisplayServiceCheckboxTest extends TestCase
{
    public function test_resolves_checkbox_companion_display_labels(): void
    {
        $service = new EApprovalFormValueDisplayService;
        $payload = json_encode([
            'selected' => ['self_supporting'],
            'companions' => [
                'self_supporting' => ['height_agl' => '15'],
            ],
        ], JSON_THROW_ON_ERROR);

        $display = $service->resolveDisplayValue(
            'checkbox',
            $payload,
            collect(),
            [
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
        );

        $this->assertSame('Self Supporting Tower or Pole — 15 m.(AGL)', $display);
    }

    public function test_resolves_boolean_checkbox_display(): void
    {
        $service = new EApprovalFormValueDisplayService;

        $this->assertSame('Yes', $service->resolveDisplayValue('checkbox', 'true', collect()));
        $this->assertSame('No', $service->resolveDisplayValue('checkbox', 'false', collect()));
    }

    public function test_resolves_matrix_display_labels(): void
    {
        $service = new EApprovalFormValueDisplayService;

        $display = $service->resolveDisplayValue(
            'matrix',
            '{"a":"yes","b":"no"}',
            collect(),
            [
                'rows' => [
                    ['value' => 'a', 'label' => 'A. Cut and Fill'],
                    ['value' => 'b', 'label' => 'B. Slope Protection'],
                ],
                'columns' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ],
            ],
        );

        $this->assertSame('A. Cut and Fill: Yes; B. Slope Protection: No', $display);
    }

    public function test_resolves_size_matrix_display_labels(): void
    {
        $service = new EApprovalFormValueDisplayService;

        $display = $service->resolveDisplayValue(
            'size_matrix',
            '{"roofdeck":{"w":"10","h":"12"},"wall":{"na":true}}',
            collect(),
            [
                'rows' => [
                    ['value' => 'roofdeck', 'label' => 'Roofdeck'],
                    ['value' => 'wall', 'label' => 'Wall'],
                ],
            ],
        );

        $this->assertSame('Roofdeck: 10 × 12; Wall: NA', $display);
    }

    public function test_resolves_size_matrix_text_row_display(): void
    {
        $service = new EApprovalFormValueDisplayService;

        $display = $service->resolveDisplayValue(
            'size_matrix',
            '{"roofdeck":{"w":"10","h":"12"},"other":{"text":"Parapet"}}',
            collect(),
            [
                'rows' => [
                    ['value' => 'roofdeck', 'label' => 'Roofdeck', 'input' => 'size'],
                    ['value' => 'other', 'label' => 'Other (specify)', 'input' => 'text'],
                ],
            ],
        );

        $this->assertSame('Roofdeck: 10 × 12; Other (specify): Parapet', $display);
    }

    public function test_resolves_matrix_display_with_notes(): void
    {
        $service = new EApprovalFormValueDisplayService;

        $display = $service->resolveDisplayValue(
            'matrix',
            '{"answers":{"a":"yes"},"notes":{"a":"5 m"}}',
            collect(),
            [
                'rows' => [
                    ['value' => 'a', 'label' => 'A. Diff. in elev.'],
                ],
                'columns' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                ],
            ],
        );

        $this->assertSame('A. Diff. in elev.: Yes (5 m)', $display);
    }

    public function test_resolves_checklist_matrix_display_labels(): void
    {
        $service = new EApprovalFormValueDisplayService;

        $display = $service->resolveDisplayValue(
            'checklist_matrix',
            '{"others":{"selected":true,"cells":{"project_site_no":"PS-1","ref_no":"R-9","or_no":""}}}',
            collect(),
            [
                'row_select_label' => 'Cost Application',
                'rows' => [
                    ['value' => 'others', 'label' => 'Others'],
                    ['value' => 'logistics', 'label' => 'Logistics'],
                ],
                'columns' => [
                    ['value' => 'project_site_no', 'label' => 'Project Site No'],
                    ['value' => 'ref_no', 'label' => 'Ref No'],
                    ['value' => 'or_no', 'label' => 'OR No.'],
                ],
            ],
        );

        $this->assertSame('Others — Project Site No: PS-1; Ref No: R-9', $display);
    }

    public function test_resolves_currency_display_with_thousand_separators(): void
    {
        $service = new EApprovalFormValueDisplayService;

        $this->assertSame('12,698.95', $service->resolveDisplayValue('currency', '12698.95', collect()));
        $this->assertSame('1,000', $service->resolveDisplayValue('currency', '1000', collect()));
        $this->assertSame('-2,500.5', $service->resolveDisplayValue('currency', '-2500.5', collect()));
    }
}
