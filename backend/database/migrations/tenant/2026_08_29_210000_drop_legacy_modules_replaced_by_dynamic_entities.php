<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop legacy module tables replaced by Dynamic Entities (ATC cutover).
 * Historical create migrations are left intact for migrate history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Disable FK checks for MySQL while dropping interdependent tables.
        Schema::disableForeignKeyConstraints();

        foreach ($this->tables() as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible — recreate via historical migrations on a fresh tenant DB.
    }

    /**
     * Children / leaf tables first, then parents. Order still matters if FK checks stay on.
     *
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            // Procurement (leaf → root)
            'procurement_rfq_bid_attachments',
            'procurement_rfq_bid_version_lines',
            'procurement_rfq_bid_versions',
            'procurement_rfq_bid_lines',
            'procurement_rfq_bids',
            'procurement_rfq_po_links',
            'procurement_rfq_vendors',
            'procurement_rfq_lines',
            'procurement_rfqs',
            'procurement_contract_documents',
            'procurement_contracts',
            'procurement_payment_requests',
            'procurement_payment_batches',
            'procurement_credit_notes',
            'procurement_ap_invoice_lines',
            'procurement_ap_invoices',
            'procurement_budget_lines',
            'procurement_cost_centers',
            'procurement_inventory_stock_movements',
            'procurement_inventory_stock_balances',
            'procurement_inventory_locations',
            'procurement_lifecycle_events',
            'procurement_grn_attachments',
            'procurement_grn_lines',
            'procurement_grns',
            'procurement_po_pr_links',
            'procurement_po_lines',
            'procurement_pos',
            'procurement_pr_attachments',
            'procurement_pr_lines',
            'procurement_prs',
            'procurement_vendor_documents',
            'procurement_vendor_accreditation_events',
            'procurement_vendors',
            'procurement_one_settings',

            // Documents / controlled register
            'controlled_document_revisions',
            'controlled_documents',
            'document_upload_intents',
            'document_expiry_alerts',
            'document_binder_templates',
            'document_activities',
            'document_versions',
            'documents',
            'document_site_nodes',
            'document_site_workspaces',

            // Project-One / Rollout
            'rollout_gate_approval_delegations',
            'rollout_gate_approval_requests',
            'rollout_permits',
            'rollout_geography_lookups',
            'tenant_rollout_files',
            'site_profitability_records',
            'cme_daily_reports',
            'site_hunting_daily_logs',
            'site_candidates',
            'rollout_timeline_phases',
            'rollout_programs',
            'tenant_rollout_playbook_config',
            'tenant_public_holidays',
            'project_approvals',
            'milestones',
            'projects',

            // Optional infra that FK sites
            'assets',
            'fiber_routes',
            'towers',

            // Sites hub
            'sites',
        ];
    }
};
