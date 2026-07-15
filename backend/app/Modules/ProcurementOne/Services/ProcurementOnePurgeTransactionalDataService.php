<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProcurementOnePurgeTransactionalDataService
{
    /** @var list<string> */
    private const PROCUREMENT_TABLES = [
        'procurement_payment_requests',
        'procurement_payment_batches',
        'procurement_credit_notes',
        'procurement_ap_invoice_lines',
        'procurement_ap_invoices',
        'procurement_inventory_stock_movements',
        'procurement_inventory_stock_balances',
        'procurement_grn_attachments',
        'procurement_grn_lines',
        'procurement_grns',
        'procurement_rfq_bid_attachments',
        'procurement_rfq_bid_version_lines',
        'procurement_rfq_bid_versions',
        'procurement_rfq_bid_lines',
        'procurement_rfq_bids',
        'procurement_rfq_vendors',
        'procurement_rfq_po_links',
        'procurement_rfq_lines',
        'procurement_rfqs',
        'procurement_po_pr_links',
        'procurement_po_lines',
        'procurement_pos',
        'procurement_pr_attachments',
        'procurement_pr_lines',
        'procurement_prs',
        'procurement_contract_documents',
        'procurement_contracts',
        'procurement_lifecycle_events',
    ];

    /** @var list<string> */
    private const VENDOR_TABLES = [
        'procurement_vendor_documents',
        'procurement_vendor_accreditation_events',
        'procurement_vendors',
    ];

    /** @var list<string> */
    private const BUDGET_TABLES = [
        'procurement_budget_lines',
        'procurement_cost_centers',
    ];

    /** @var list<string> */
    private const INVENTORY_LOCATION_TABLES = [
        'procurement_inventory_locations',
    ];

    /** @var list<string> */
    private const DEFAULT_E_APPROVAL_FORM_FAMILIES = [
        'purchase_requisition',
        'purchase_order',
        'ap_invoice',
    ];

    /**
     * @return array<string, int>
     */
    public function purge(
        bool $dryRun,
        bool $purgeEApprovalSubmissions = true,
        bool $purgeVendors = false,
        bool $purgeBudget = false,
        bool $purgeInventoryLocations = false,
        bool $includeVendorRegistrationSubmissions = false,
        bool $resetNumbering = true,
    ): array {
        $counts = [];

        $run = function () use (
            $dryRun,
            $purgeEApprovalSubmissions,
            $purgeVendors,
            $purgeBudget,
            $purgeInventoryLocations,
            $includeVendorRegistrationSubmissions,
            $resetNumbering,
            &$counts,
        ): void {
            foreach (self::PROCUREMENT_TABLES as $table) {
                $counts[$table] = $this->clearTable($table, $dryRun);
            }

            if ($purgeVendors) {
                foreach (self::VENDOR_TABLES as $table) {
                    $counts[$table] = $this->clearTable($table, $dryRun);
                }
            }

            if ($purgeBudget) {
                foreach (self::BUDGET_TABLES as $table) {
                    $counts[$table] = $this->clearTable($table, $dryRun);
                }
            }

            if ($purgeInventoryLocations) {
                foreach (self::INVENTORY_LOCATION_TABLES as $table) {
                    $counts[$table] = $this->clearTable($table, $dryRun);
                }
            }

            if ($purgeEApprovalSubmissions) {
                $families = self::DEFAULT_E_APPROVAL_FORM_FAMILIES;
                if ($includeVendorRegistrationSubmissions) {
                    $families[] = 'vendor_registration';
                }

                $submissionCounts = $this->purgeEApprovalSubmissions($dryRun, $families);
                foreach ($submissionCounts as $key => $count) {
                    $counts[$key] = $count;
                }
            }

            if ($resetNumbering) {
                $counts['procurement_numbering_reset'] = $this->resetProcurementNumbering($dryRun);
                $counts['e_approval_document_sequences_reset'] = $this->resetEApprovalDocumentSequences(
                    $dryRun,
                    $purgeEApprovalSubmissions
                        ? array_values(array_unique(array_merge(
                            self::DEFAULT_E_APPROVAL_FORM_FAMILIES,
                            $includeVendorRegistrationSubmissions ? ['vendor_registration'] : [],
                        )))
                        : [],
                );
            }
        };

        if ($dryRun) {
            $run();

            return $counts;
        }

        DB::connection('tenant')->transaction(function () use ($run): void {
            DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $run();
            } finally {
                DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        return $counts;
    }

    /**
     * @param  list<string>  $formFamilies
     * @return array<string, int>
     */
    private function purgeEApprovalSubmissions(bool $dryRun, array $formFamilies): array
    {
        $counts = [
            'e_approval_submissions' => 0,
            'e_approval_request_approvals' => 0,
            'e_approval_form_values' => 0,
            'e_approval_attachments' => 0,
            'e_approval_comments' => 0,
            'e_approval_submission_followups' => 0,
            'e_approval_document_links' => 0,
            'e_approval_notifications' => 0,
            'e_approval_workflow_steps_compiled' => 0,
            'documents_e_approval_unlinked' => 0,
        ];

        $submissionIds = $this->resolveSubmissionIds($formFamilies);
        if ($submissionIds === []) {
            return $counts;
        }

        $counts['e_approval_submissions'] = count($submissionIds);

        if ($dryRun) {
            $counts['e_approval_request_approvals'] = $this->countWhereIn('e_approval_request_approvals', 'submission_id', $submissionIds);
            $counts['e_approval_form_values'] = $this->countWhereIn('e_approval_form_values', 'submission_id', $submissionIds);
            $counts['e_approval_attachments'] = $this->countWhereIn('e_approval_attachments', 'submission_id', $submissionIds);
            $counts['e_approval_comments'] = $this->countWhereIn('e_approval_comments', 'submission_id', $submissionIds);
            $counts['e_approval_submission_followups'] = $this->countWhereIn('e_approval_submission_followups', 'submission_id', $submissionIds);
            $counts['e_approval_document_links'] = $this->countDocumentLinks($submissionIds);
            $counts['e_approval_notifications'] = $this->countWhereIn('e_approval_notifications', 'submission_id', $submissionIds);
            $counts['e_approval_workflow_steps_compiled'] = $this->countWhereIn('e_approval_workflow_steps', 'compiled_for_submission_id', $submissionIds);
            $counts['documents_e_approval_unlinked'] = $this->countWhereIn('documents', 'e_approval_submission_id', $submissionIds);

            return $counts;
        }

        if (Schema::hasTable('documents')) {
            $counts['documents_e_approval_unlinked'] = DB::connection('tenant')->table('documents')
                ->whereIn('e_approval_submission_id', $submissionIds)
                ->update([
                    'e_approval_submission_id' => null,
                    'approval_status' => 'none',
                ]);
        }

        if (Schema::hasTable('e_approval_document_links')) {
            $counts['e_approval_document_links'] = DB::connection('tenant')->table('e_approval_document_links')
                ->where(function ($query) use ($submissionIds): void {
                    $query->whereIn('source_submission_id', $submissionIds)
                        ->orWhereIn('target_submission_id', $submissionIds);
                })
                ->delete();
        }

        if (Schema::hasTable('e_approval_notifications')) {
            $counts['e_approval_notifications'] = DB::connection('tenant')->table('e_approval_notifications')
                ->whereIn('submission_id', $submissionIds)
                ->delete();
        }

        if (Schema::hasTable('e_approval_submission_followups')) {
            $counts['e_approval_submission_followups'] = DB::connection('tenant')->table('e_approval_submission_followups')
                ->whereIn('submission_id', $submissionIds)
                ->delete();
        }

        if (Schema::hasTable('e_approval_comments')) {
            $counts['e_approval_comments'] = DB::connection('tenant')->table('e_approval_comments')
                ->whereIn('submission_id', $submissionIds)
                ->delete();
        }

        if (Schema::hasTable('e_approval_request_approvals')) {
            $counts['e_approval_request_approvals'] = DB::connection('tenant')->table('e_approval_request_approvals')
                ->whereIn('submission_id', $submissionIds)
                ->delete();
        }

        if (Schema::hasTable('e_approval_form_values')) {
            $counts['e_approval_form_values'] = DB::connection('tenant')->table('e_approval_form_values')
                ->whereIn('submission_id', $submissionIds)
                ->delete();
        }

        if (Schema::hasTable('e_approval_attachments')) {
            $counts['e_approval_attachments'] = DB::connection('tenant')->table('e_approval_attachments')
                ->whereIn('submission_id', $submissionIds)
                ->delete();
        }

        if (Schema::hasTable('e_approval_workflow_steps')) {
            $counts['e_approval_workflow_steps_compiled'] = DB::connection('tenant')->table('e_approval_workflow_steps')
                ->whereIn('compiled_for_submission_id', $submissionIds)
                ->delete();
        }

        if (Schema::hasTable('e_approval_submissions')) {
            DB::connection('tenant')->table('e_approval_submissions')
                ->whereIn('parent_submission_id', $submissionIds)
                ->update(['parent_submission_id' => null]);

            DB::connection('tenant')->table('e_approval_submissions')
                ->whereIn('id', $submissionIds)
                ->delete();
        }

        return $counts;
    }

    /**
     * @param  list<string>  $formFamilies
     * @return list<string>
     */
    private function resolveSubmissionIds(array $formFamilies): array
    {
        if (! Schema::hasTable('e_approval_submissions') || ! Schema::hasTable('e_approval_forms')) {
            return [];
        }

        $formIds = EApprovalForm::query()
            ->get(['id', 'metadata_json'])
            ->filter(static function (EApprovalForm $form) use ($formFamilies): bool {
                $family = EApprovalFormPolicySupport::documentFamily($form);

                return $family !== null && in_array($family, $formFamilies, true);
            })
            ->pluck('id')
            ->all();

        if ($formIds === []) {
            return [];
        }

        return DB::connection('tenant')->table('e_approval_submissions')
            ->whereIn('form_id', $formIds)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * @param  list<string>  $submissionIds
     */
    private function countDocumentLinks(array $submissionIds): int
    {
        if (! Schema::hasTable('e_approval_document_links')) {
            return 0;
        }

        return (int) DB::connection('tenant')->table('e_approval_document_links')
            ->where(function ($query) use ($submissionIds): void {
                $query->whereIn('source_submission_id', $submissionIds)
                    ->orWhereIn('target_submission_id', $submissionIds);
            })
            ->count();
    }

    /**
     * @param  list<string>  $values
     */
    private function countWhereIn(string $table, string $column, array $values): int
    {
        if (! Schema::hasTable($table) || $values === []) {
            return 0;
        }

        return (int) DB::connection('tenant')->table($table)->whereIn($column, $values)->count();
    }

    private function clearTable(string $table, bool $dryRun): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $count = (int) DB::connection('tenant')->table($table)->count();
        if (! $dryRun && $count > 0) {
            DB::connection('tenant')->table($table)->delete();
        }

        return $count;
    }

    private function resetProcurementNumbering(bool $dryRun): int
    {
        if (! Schema::hasTable('procurement_one_settings')) {
            return 0;
        }

        $row = DB::connection('tenant')->table('procurement_one_settings')
            ->where('key', ProcurementOneSettingsService::NUMBERING_SERIES)
            ->first();

        if ($row === null || $row->value === null || trim((string) $row->value) === '') {
            return 0;
        }

        try {
            $decoded = json_decode((string) $row->value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 0;
        }

        if (! is_array($decoded)) {
            return 0;
        }

        $changed = 0;
        foreach ($decoded as $documentType => $series) {
            if (! is_array($series)) {
                continue;
            }

            if ((int) ($series['next_sequence'] ?? 1) !== 1) {
                $changed++;
            }

            $decoded[$documentType] = array_merge($series, ['next_sequence' => 1]);
        }

        if (! $dryRun && $changed > 0) {
            DB::connection('tenant')->table('procurement_one_settings')
                ->where('key', ProcurementOneSettingsService::NUMBERING_SERIES)
                ->update([
                    'value' => json_encode($decoded, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
        }

        return $changed;
    }

    /**
     * @param  list<string>  $formFamilies
     */
    private function resetEApprovalDocumentSequences(bool $dryRun, array $formFamilies): int
    {
        if (! Schema::hasTable('e_approval_document_sequences') || $formFamilies === []) {
            return 0;
        }

        $prefixes = EApprovalForm::query()
            ->get(['metadata_json'])
            ->filter(static function (EApprovalForm $form) use ($formFamilies): bool {
                $family = EApprovalFormPolicySupport::documentFamily($form);

                return $family !== null && in_array($family, $formFamilies, true);
            })
            ->map(static function (EApprovalForm $form): ?string {
                $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
                $prefix = trim((string) ($metadata['document_prefix'] ?? ''));

                return $prefix !== '' ? $prefix : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($prefixes === []) {
            return 0;
        }

        $count = (int) DB::connection('tenant')->table('e_approval_document_sequences')
            ->whereIn('prefix', $prefixes)
            ->count();

        if (! $dryRun && $count > 0) {
            DB::connection('tenant')->table('e_approval_document_sequences')
                ->whereIn('prefix', $prefixes)
                ->update(['next_no' => 1]);
        }

        return $count;
    }
}
