<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Seed catalog for Manage HTML Reports (Metacoresoft parity names).
 *
 * @return list<array{name: string, slug: string, description: string}>
 */
final class DynHtmlReportCatalog
{
    public static function defaults(): array
    {
        return [
            ['name' => 'Aging Invoice & AR Adjustments Report', 'slug' => 'aging-invoice-adjustments', 'description' => 'Inspect aging buckets and post AR adjustments (opens workbench).'],
            ['name' => 'Materials Issued per Tower', 'slug' => 'materials-per-tower', 'description' => 'Warehouse material consumed by each tower.'],
            ['name' => 'Stock on Hand & Warehouse Position', 'slug' => 'inventory-stock-on-hand', 'description' => 'Current warehouse stock positions.'],
            ['name' => 'LIVE Tower Build Tracker & Forecasts', 'slug' => 'tower-build-tracker', 'description' => 'Construction progress and forecasts.'],
            ['name' => 'Monthly Management Accounts', 'slug' => 'monthly-management-accounts', 'description' => ''],
            ['name' => 'Cash Flow Statement', 'slug' => 'cash-flow-statement', 'description' => ''],
            ['name' => 'Trial Balance Report', 'slug' => 'trial-balance', 'description' => ''],
            ['name' => 'Bank Reconciliation Report', 'slug' => 'bank-reconciliation-report', 'description' => ''],
            ['name' => 'AR & AP Aging', 'slug' => 'ar-ap-aging', 'description' => ''],
            ['name' => 'General Ledger', 'slug' => 'general-ledger', 'description' => ''],
            ['name' => 'Balance Sheet', 'slug' => 'balance-sheet', 'description' => ''],
            ['name' => 'Income Statement', 'slug' => 'income-statement', 'description' => ''],
            ['name' => 'Capex & Opex per Site', 'slug' => 'capex-opex-per-site', 'description' => ''],
            ['name' => 'Permit Status & Compliance', 'slug' => 'permit-status-compliance', 'description' => ''],
            ['name' => 'SAQ Milestone Aging', 'slug' => 'saq-milestone-aging', 'description' => ''],
            ['name' => 'RFTI Pipeline & SLA', 'slug' => 'rfti-pipeline-sla', 'description' => ''],
            ['name' => 'Tax Compliance & BIR Reports', 'slug' => 'tax-compliance-bir', 'description' => ''],
        ];
    }

    public static function starterHtml(string $name): string
    {
        $safe = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<div class="report-shell">
  <header class="report-header">
    <div class="company">{{system.company_name}}</div>
    <div class="date">{{current_date}}</div>
  </header>
  <h1>{$safe}</h1>
  <p class="muted">Customize this HTML report in Manage HTML Reports. Use tokens and the integration API from JavaScript.</p>
  <div class="toolbar">
    <button type="button" onclick="window.print()">Print PDF</button>
    <button type="button" id="btn-refresh">Refresh Data</button>
  </div>
  <div id="report-body" class="card">
    <p>Connect data via <code>fetch('/api/v1/integration/...')</code> or session APIs.</p>
  </div>
</div>
HTML;
    }

    public static function starterCss(): string
    {
        return <<<'CSS'
.report-shell { font-family: Inter, system-ui, sans-serif; color: #0f172a; max-width: 1100px; margin: 0 auto; padding: 24px; }
.report-header { display: flex; justify-content: space-between; color: #64748b; font-size: 12px; margin-bottom: 12px; }
h1 { font-size: 22px; font-weight: 600; margin: 0 0 8px; }
.muted { color: #64748b; font-size: 14px; }
.toolbar { display: flex; gap: 8px; margin: 16px 0; }
.toolbar button { border: 1px solid #e2e8f0; background: #fff; border-radius: 8px; padding: 8px 12px; cursor: pointer; font-size: 13px; }
.card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; background: #fff; }
@media print { .toolbar { display: none; } }
CSS;
    }

    public static function starterJs(): string
    {
        return <<<'JS'
document.getElementById('btn-refresh')?.addEventListener('click', () => {
  console.info('Wire refresh to TowerOS Dynamic Entities / integration APIs.');
});
JS;
    }
}
