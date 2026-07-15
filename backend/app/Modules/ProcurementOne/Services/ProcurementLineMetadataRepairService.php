<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementPrLine;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementGridValueParser;
use Illuminate\Support\Facades\DB;

final class ProcurementLineMetadataRepairService
{
    public function __construct(
        private readonly ProcurementPrValueMapper $prMapper,
        private readonly ProcurementPoValueMapper $poMapper,
        private readonly ProcurementGridValueParser $gridParser,
        private readonly ProcurementRfqPrLineSyncService $rfqPrLineSync,
    ) {}

    /**
     * @return array{
     *     pr_lines_scanned: int,
     *     pr_lines_updated: int,
     *     pr_lines_skipped: int,
     *     po_lines_scanned: int,
     *     po_lines_updated: int,
     *     po_lines_skipped: int,
     *     pr_documents: int,
     *     po_documents: int,
     *     rfqs_synced: int
     * }
     */
    public function repair(bool $dryRun = false, ?string $prId = null, ?string $poId = null): array
    {
        $result = [
            'pr_lines_scanned' => 0,
            'pr_lines_updated' => 0,
            'pr_lines_skipped' => 0,
            'po_lines_scanned' => 0,
            'po_lines_updated' => 0,
            'po_lines_skipped' => 0,
            'pr_documents' => 0,
            'po_documents' => 0,
            'rfqs_synced' => 0,
        ];

        $run = function () use ($dryRun, $prId, $poId, &$result): void {
            ProcurementPr::query()
                ->when($prId, static fn ($query) => $query->where('id', $prId))
                ->when($poId, static fn ($query) => $query->whereRaw('0 = 1'))
                ->with(['lines'])
                ->orderBy('created_at')
                ->each(function (ProcurementPr $pr) use ($dryRun, &$result): void {
                    $outcome = $this->repairPurchaseRequisition($pr, $dryRun);
                    if ($outcome['scanned'] === 0) {
                        return;
                    }

                    $result['pr_documents']++;
                    $result['pr_lines_scanned'] += $outcome['scanned'];
                    $result['pr_lines_updated'] += $outcome['updated'];
                    $result['pr_lines_skipped'] += $outcome['skipped'];

                    if (! $dryRun && $outcome['updated'] > 0) {
                        $result['rfqs_synced'] += $this->syncLinkedRfqs($pr);
                    }
                });

            ProcurementPo::query()
                ->when($poId, static fn ($query) => $query->where('id', $poId))
                ->when($prId, static fn ($query) => $query->whereRaw('0 = 1'))
                ->with(['lines'])
                ->orderBy('created_at')
                ->each(function (ProcurementPo $po) use ($dryRun, &$result): void {
                    $outcome = $this->repairPurchaseOrder($po, $dryRun);
                    if ($outcome['scanned'] === 0) {
                        return;
                    }

                    $result['po_documents']++;
                    $result['po_lines_scanned'] += $outcome['scanned'];
                    $result['po_lines_updated'] += $outcome['updated'];
                    $result['po_lines_skipped'] += $outcome['skipped'];
                });
        };

        if ($dryRun) {
            $run();

            return $result;
        }

        DB::connection('tenant')->transaction($run);

        return $result;
    }

    /**
     * @return array{scanned: int, updated: int, skipped: int}
     */
    private function repairPurchaseRequisition(ProcurementPr $pr, bool $dryRun): array
    {
        $mappedLines = $this->mappedPrLines($pr);
        if ($mappedLines === []) {
            return ['scanned' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $scanned = 0;
        $updated = 0;
        $skipped = 0;

        $dbLines = $pr->lines->sortBy('line_order')->values();

        foreach ($dbLines as $index => $line) {
            if (! $line instanceof ProcurementPrLine) {
                continue;
            }

            $scanned++;
            $incoming = is_array($mappedLines[$index]['metadata_json'] ?? null)
                ? $mappedLines[$index]['metadata_json']
                : [];

            if ($incoming === []) {
                $skipped++;

                continue;
            }

            $merged = $this->mergeMetadata(is_array($line->metadata_json) ? $line->metadata_json : [], $incoming);
            if (! $this->metadataNeedsRepair(is_array($line->metadata_json) ? $line->metadata_json : [], $merged)) {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                $line->metadata_json = $merged !== [] ? $merged : null;
                $line->save();
            }

            $updated++;
        }

        return compact('scanned', 'updated', 'skipped');
    }

    /**
     * @return array{scanned: int, updated: int, skipped: int}
     */
    private function repairPurchaseOrder(ProcurementPo $po, bool $dryRun): array
    {
        $mappedLines = $this->mappedPoLines($po);
        if ($mappedLines === []) {
            return ['scanned' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $scanned = 0;
        $updated = 0;
        $skipped = 0;

        $dbLines = $po->lines->sortBy('line_order')->values();

        foreach ($dbLines as $index => $line) {
            if (! $line instanceof ProcurementPoLine) {
                continue;
            }

            $scanned++;
            $incoming = is_array($mappedLines[$index]['metadata_json'] ?? null)
                ? $mappedLines[$index]['metadata_json']
                : [];

            if ($incoming === []) {
                $skipped++;

                continue;
            }

            $merged = $this->mergeMetadata(is_array($line->metadata_json) ? $line->metadata_json : [], $incoming);
            if (! $this->metadataNeedsRepair(is_array($line->metadata_json) ? $line->metadata_json : [], $merged)) {
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                $line->metadata_json = $merged !== [] ? $merged : null;

                $itemCode = trim((string) ($merged['item_code'] ?? ''));
                if ($itemCode !== '' && ($line->item === null || trim((string) $line->item) === '')) {
                    $line->item = $itemCode;
                }

                $uom = trim((string) ($merged['uom'] ?? ''));
                if ($uom !== '' && ($line->uom === null || trim((string) $line->uom) === '' || $line->uom === 'EA')) {
                    $line->uom = $uom;
                }

                $line->save();
            }

            $updated++;
        }

        return compact('scanned', 'updated', 'skipped');
    }

    /**
     * @return list<array{metadata_json?: array<string, string>|null}>
     */
    private function mappedPrLines(ProcurementPr $pr): array
    {
        $gridRaw = $this->resolveLineItemsGridRaw(
            $pr->e_approval_submission_id,
            is_array($pr->metadata_json) ? $pr->metadata_json : null,
        );

        if ($gridRaw === null) {
            return [];
        }

        return $this->prMapper->linesFromGridValue($gridRaw);
    }

    /**
     * @return list<array{metadata_json?: array<string, string>|null, item?: ?string}>
     */
    private function mappedPoLines(ProcurementPo $po): array
    {
        $gridRaw = $this->resolveLineItemsGridRaw(
            $po->e_approval_submission_id,
            is_array($po->metadata_json) ? $po->metadata_json : null,
        );

        if ($gridRaw === null) {
            return [];
        }

        return $this->poMapper->linesFromGridValue($gridRaw);
    }

    /**
     * @param  array<string, mixed>|null  $recordMetadata
     */
    private function resolveLineItemsGridRaw(?string $submissionId, ?array $recordMetadata): mixed
    {
        if ($submissionId !== null && $submissionId !== '') {
            $submission = EApprovalSubmission::query()
                ->with(['values.field'])
                ->find($submissionId);

            if ($submission instanceof EApprovalSubmission) {
                foreach ($submission->values as $formValue) {
                    if ((string) ($formValue->field?->name ?? '') !== 'line_items') {
                        continue;
                    }

                    $raw = $formValue->value;
                    if ($this->gridHasRepairableContent($raw)) {
                        return $raw;
                    }
                }
            }
        }

        $compose = ProcurementComposeMetadata::composeFormValues($recordMetadata);
        $raw = $compose['line_items'] ?? null;

        return $this->gridHasRepairableContent($raw) ? $raw : null;
    }

    private function gridHasRepairableContent(mixed $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        if (is_string($raw) && trim($raw) === '') {
            return false;
        }

        $rows = $this->gridParser->extractRows($raw);

        return $rows !== [];
    }

    /**
     * @param  array<string, string>  $existing
     * @param  array<string, string>  $incoming
     */
    private function metadataNeedsRepair(array $existing, array $incoming): bool
    {
        foreach (['site_id', 'item_code', 'department', 'uom', 'quote_basis'] as $key) {
            if (trim((string) ($existing[$key] ?? '')) === '' && trim((string) ($incoming[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $existing
     * @param  array<string, string>  $incoming
     * @return array<string, string>
     */
    private function mergeMetadata(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            if (trim((string) ($existing[$key] ?? '')) === '') {
                $existing[$key] = $value;
            }
        }

        return array_filter($existing, static fn (string $value): bool => trim($value) !== '');
    }

    private function syncLinkedRfqs(ProcurementPr $pr): int
    {
        $synced = 0;

        ProcurementRfq::query()
            ->where('pr_id', $pr->id)
            ->orderBy('created_at')
            ->each(function (ProcurementRfq $rfq) use (&$synced): void {
                $this->rfqPrLineSync->syncIfApplicable($rfq);
                $synced++;
            });

        return $synced;
    }
}
