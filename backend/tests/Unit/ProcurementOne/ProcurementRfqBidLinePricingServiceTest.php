<?php

declare(strict_types=1);

namespace Tests\Unit\ProcurementOne;

use App\Modules\ProcurementOne\Models\ProcurementRfqLine;
use App\Modules\ProcurementOne\Services\ProcurementRfqBidLinePricingService;
use App\Modules\ProcurementOne\Support\ProcurementQuoteBasis;
use Tests\TestCase;

final class ProcurementRfqBidLinePricingServiceTest extends TestCase
{
    private ProcurementRfqBidLinePricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProcurementRfqBidLinePricingService;
    }

    public function test_one_time_line_uses_unit_price_for_totals(): void
    {
        $line = $this->rfqLine(['quote_basis' => ProcurementQuoteBasis::ONE_TIME]);

        $normalized = $this->service->normalizeLine(
            ['quantity' => 2, 'unit_price' => 500],
            $line,
        );

        $this->assertSame(1000.0, $normalized['amount']);
        $this->assertSame(1000.0, $normalized['normalized_annual_amount']);
        $this->assertNull($normalized['amount_monthly']);
        $this->assertNull($normalized['amount_yearly']);
    }

    public function test_monthly_line_annualizes_for_comparison(): void
    {
        $line = $this->rfqLine(['quote_basis' => ProcurementQuoteBasis::MONTHLY]);

        $normalized = $this->service->normalizeLine(
            ['quantity' => 3, 'monthly_unit_price' => 100],
            $line,
        );

        $this->assertSame(300.0, $normalized['amount_monthly']);
        $this->assertSame(3600.0, $normalized['normalized_annual_amount']);
        $summary = $this->service->summarizeBid([$normalized]);
        $this->assertSame(300.0, $summary['total_amount_monthly']);
        $this->assertSame(3600.0, $summary['normalized_annual_amount']);
    }

    public function test_monthly_yearly_line_prefers_yearly_for_normalized_amount(): void
    {
        $line = $this->rfqLine(['quote_basis' => ProcurementQuoteBasis::MONTHLY_YEARLY]);

        $normalized = $this->service->normalizeLine(
            [
                'quantity' => 2,
                'monthly_unit_price' => 50,
                'yearly_unit_price' => 500,
            ],
            $line,
        );

        $this->assertSame(100.0, $normalized['amount_monthly']);
        $this->assertSame(1000.0, $normalized['amount_yearly']);
        $this->assertSame(1000.0, $normalized['normalized_annual_amount']);
    }

    private function rfqLine(array $metadata): ProcurementRfqLine
    {
        $line = new ProcurementRfqLine;
        $line->id = '01900000-0000-7000-8000-000000000001';
        $line->description = 'Cloud subscription';
        $line->quantity = 1;
        $line->metadata_json = $metadata;

        return $line;
    }
}
