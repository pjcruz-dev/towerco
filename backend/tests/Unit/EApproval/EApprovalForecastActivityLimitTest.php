<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalForecastActivityLimit;
use App\Modules\EApproval\Support\EApprovalGridColumnRules;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
use Tests\TestCase;

final class EApprovalForecastActivityLimitTest extends TestCase
{
    public function test_a_cash_advance_forecast_longer_than_fifteen_days_is_rejected(): void
    {
        $raw = json_encode([
            'rows' => [
                ['0' => json_encode(['from' => '2026-10-01', 'to' => '2026-10-16'])],
            ],
        ], JSON_THROW_ON_ERROR);

        $error = EApprovalForecastActivityLimit::error(
            'expense_lines',
            ['From date – To date', 'Amount'],
            $raw,
        );

        $this->assertSame(
            'Forecast activities can cover at most 15 days, from the earliest start to the latest end.',
            $error,
        );
    }

    public function test_rows_on_different_dates_share_one_fifteen_day_window(): void
    {
        $inside = json_encode([
            'rows' => [
                ['0' => json_encode(['from' => '2026-10-01', 'to' => '2026-10-08'])],
                ['0' => json_encode(['from' => '2026-10-06', 'to' => '2026-10-15'])],
            ],
        ], JSON_THROW_ON_ERROR);
        $this->assertNull(EApprovalForecastActivityLimit::error(
            'expense_lines',
            ['From date – To date', 'Amount'],
            $inside,
        ));

        $outside = json_encode([
            'rows' => [
                ['0' => json_encode(['from' => '2026-10-01', 'to' => '2026-10-05'])],
                ['0' => json_encode(['from' => '2026-10-10', 'to' => '2026-10-16'])],
            ],
        ], JSON_THROW_ON_ERROR);
        $this->assertSame(
            'Forecast activities can cover at most 15 days, from the earliest start to the latest end.',
            EApprovalForecastActivityLimit::error(
                'expense_lines',
                ['From date – To date', 'Amount'],
                $outside,
            ),
        );
    }

    public function test_fifteen_days_and_liquidation_lines_stay_open(): void
    {
        $raw = json_encode([
            'rows' => [
                ['0' => json_encode(['from' => '2026-10-01', 'to' => '2026-10-15'])],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->assertNull(EApprovalForecastActivityLimit::error(
            'expense_lines',
            ['From date – To date', 'Amount'],
            $raw,
        ));

        $long = json_encode([
            'rows' => [
                ['0' => json_encode(['from' => '2026-10-01', 'to' => '2026-10-31'])],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->assertNull(EApprovalForecastActivityLimit::error(
            'expense_lines',
            ['From date – To date', 'OR No', 'Expense type', 'Amount'],
            $long,
        ));
        $this->assertNull(EApprovalForecastActivityLimit::error(
            'activity_lines',
            ['From date – To date'],
            $long,
        ));
    }

    public function test_a_required_column_is_enforced_on_a_filled_line(): void
    {
        $options = [
            'columns' => [
                ['label' => 'Amount', 'type' => 'currency'],
                ['label' => 'Nature of Activity', 'type' => 'text', 'required' => true],
            ],
        ];
        $missing = json_encode(['rows' => [['0' => '1000', '1' => '']]], JSON_THROW_ON_ERROR);
        $this->assertSame(
            'Nature of Activity is required on each line.',
            EApprovalGridColumnRules::requiredError($options, $missing),
        );
        $filled = json_encode(['rows' => [['0' => '1000', '1' => 'Site survey']]], JSON_THROW_ON_ERROR);
        $this->assertNull(EApprovalGridColumnRules::requiredError($options, $filled));
        $hidden = $options;
        $hidden['columns'][1]['hidden'] = true;
        $this->assertNull(EApprovalGridColumnRules::requiredError($hidden, $missing));
    }

    public function test_proof_of_payment_is_stored_on_the_step_and_cleared_for_review(): void
    {
        $stored = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'user',
            'proof_of_payment' => true,
        ], 'user');

        $this->assertIsArray($stored);
        $this->assertTrue($stored['proof_of_payment']);

        $normalized = EApprovalWorkflowStepDefinitionSupport::normalizePayloadStep([
            'type' => 'user',
            'proof_of_payment' => true,
        ], 1);
        $this->assertTrue($normalized['proof_of_payment']);

        $review = EApprovalWorkflowStepDefinitionSupport::buildStoredCondition([
            'type' => 'user',
            'duty' => 'review',
            'proof_of_payment' => true,
        ], 'user');
        $this->assertIsArray($review);
        $this->assertArrayNotHasKey('proof_of_payment', $review);
    }
}
