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
}
