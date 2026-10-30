<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Services\EApprovalFieldVisibilityEvaluator;
use App\Modules\EApproval\Support\EApprovalDateBounds;
use DateTimeImmutable;
use Tests\TestCase;

class EApprovalDateBoundsTest extends TestCase
{
    public function test_lead_days_reject_an_earlier_date(): void
    {
        $error = EApprovalDateBounds::valueError(
            ['date_min' => 'lead', 'lead_days' => 3],
            '2026-11-01',
            [],
            'Funds needed by',
            new DateTimeImmutable('2026-10-30'),
        );

        $this->assertSame('Funds needed by must be at least 3 days from today.', $error);
    }

    public function test_grid_range_must_start_on_or_after_the_linked_date(): void
    {
        $error = EApprovalDateBounds::gridError(
            [
                'columns' => [
                    [
                        'label' => 'From date – To date',
                        'type' => 'date_range',
                        'date_min' => 'field',
                        'date_min_field' => 'needed_by',
                    ],
                ],
            ],
            json_encode([
                'rows' => [
                    ['0' => json_encode(['from' => '2026-11-01', 'to' => '2026-11-03'])],
                ],
            ], JSON_THROW_ON_ERROR),
            ['needed_by' => '2026-11-02'],
        );

        $this->assertSame('From date – To date must be on or after the linked date.', $error);
    }

    public function test_forecast_section_stays_hidden_until_all_three_fields_are_filled(): void
    {
        $section = new EApprovalFormField;
        $section->options = [
            'visibility' => [
                'mode' => 'show_when',
                'operator' => 'all_filled',
                'field' => 'needed_by',
                'fields' => ['needed_by', 'subsidiary', 'purpose'],
            ],
        ];
        $evaluator = new EApprovalFieldVisibilityEvaluator;

        $this->assertFalse($evaluator->isVisible($section, [
            'needed_by' => '2026-11-02',
            'subsidiary' => 'ATC',
            'purpose' => '',
        ]));
        $this->assertTrue($evaluator->isVisible($section, [
            'needed_by' => '2026-11-02',
            'subsidiary' => 'ATC',
            'purpose' => 'Site visit',
        ]));
    }
}
