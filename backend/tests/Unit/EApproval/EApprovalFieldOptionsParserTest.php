<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use PHPUnit\Framework\TestCase;

final class EApprovalFieldOptionsParserTest extends TestCase
{
    public function test_parses_legacy_pipe_delimited_option_list(): void
    {
        $choices = EApprovalFieldOptionsParser::selectChoices([
            'Manual|M',
            'Policies and Procedures|P',
        ]);

        $this->assertSame([
            ['value' => 'M', 'label' => 'Manual'],
            ['value' => 'P', 'label' => 'Policies and Procedures'],
        ], $choices);
    }

    public function test_parses_modern_choices_object(): void
    {
        $choices = EApprovalFieldOptionsParser::selectChoices([
            'choices' => [
                ['value' => 'a', 'label' => 'Option A'],
            ],
        ]);

        $this->assertSame([['value' => 'a', 'label' => 'Option A']], $choices);
    }

    public function test_parses_grid_columns_from_string_list(): void
    {
        $columns = EApprovalFieldOptionsParser::gridColumns([
            'SAQ-Site Survey',
            'CME-Materials',
        ]);

        $this->assertSame(['SAQ-Site Survey', 'CME-Materials'], $columns);
    }

    public function test_parses_grid_columns_from_typed_column_objects(): void
    {
        $columns = EApprovalFieldOptionsParser::gridColumns([
            'columns' => [
                ['label' => 'Vendor', 'type' => 'select', 'master_data_key' => 'vendors'],
                ['label' => 'Amount', 'type' => 'currency'],
            ],
        ]);

        $this->assertSame(['Vendor', 'Amount'], $columns);
    }

    public function test_resolves_choice_label_from_legacy_options(): void
    {
        $label = EApprovalFieldOptionsParser::choiceLabel(
            ['Quality Management System|QMS'],
            'QMS',
        );

        $this->assertSame('Quality Management System', $label);
    }

    public function test_parses_checkbox_choice_companion_inputs(): void
    {
        $choices = EApprovalFieldOptionsParser::selectChoices([
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
        ]);

        $this->assertSame('self_supporting', $choices[0]['value']);
        $this->assertSame([
            [
                'key' => 'height_agl',
                'type' => 'number',
                'suffix' => 'm.(AGL)',
                'required' => true,
            ],
        ], $choices[0]['inputs']);
    }

    public function test_parses_size_matrix_row_input_kinds(): void
    {
        $rows = EApprovalFieldOptionsParser::sizeMatrixRows([
            'rows' => [
                ['value' => 'roofdeck', 'label' => 'Roofdeck', 'input' => 'size'],
                ['value' => 'other', 'label' => 'Other (specify)', 'input' => 'text'],
            ],
        ]);

        $this->assertSame('size', $rows[0]['input']);
        $this->assertSame('text', $rows[1]['input']);
    }

    public function test_parses_checkbox_size_companion_input(): void
    {
        $choices = EApprovalFieldOptionsParser::selectChoices([
            'choices' => [
                [
                    'value' => 'dry_wall',
                    'label' => 'Dry Wall',
                    'inputs' => [
                        ['key' => 'size', 'type' => 'size', 'required' => true],
                    ],
                    'help' => "Check structural stability",
                ],
            ],
        ]);

        $this->assertSame('size', $choices[0]['inputs'][0]['type']);
        $this->assertSame('Check structural stability', $choices[0]['help']);
    }
}
