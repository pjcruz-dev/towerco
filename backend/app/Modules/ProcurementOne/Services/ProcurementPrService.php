<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementComposeMetadata;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementPrService
{
    public function __construct(
        private readonly ProcurementPrFormResolverService $formResolver,
        private readonly ProcurementPrValueMapper $mapper,
        private readonly ProcurementPrSubmissionBridgeService $bridge,
        private readonly ProcurementPrRegistryService $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     */
    public function create(array $input, TenantUser $actor, ?array $composeFormValues = null): ProcurementPr
    {
        $form = $this->formResolver->resolvePublishedFormOrFail();
        $lines = $this->normalizeLines($input['lines'] ?? []);
        $total = $this->mapper->recalculateTotal($lines);

        return DB::connection('tenant')->transaction(function () use ($input, $actor, $form, $lines, $total, $composeFormValues): ProcurementPr {
            $pr = ProcurementPr::query()->create([
                'status' => ProcurementPrStatus::DRAFT,
                'e_approval_form_id' => (string) $form->id,
                'requestor_id' => (string) $actor->id,
                'title' => trim((string) ($input['title'] ?? '')),
                'department' => $input['department'] ?? null,
                'urgency' => $input['urgency'] ?? null,
                'justification' => $input['justification'] ?? null,
                'estimated_total' => $total,
                'currency' => (string) ($input['currency'] ?? 'PHP'),
                'project_id' => $input['project_id'] ?? null,
                'rollout_id' => $input['rollout_id'] ?? null,
                'site_id' => $input['site_id'] ?? null,
                'boq_line_id' => $input['boq_line_id'] ?? null,
            ]);

            $this->mapper->syncLines($pr, $lines);
            $this->persistComposeFormValues($pr, $composeFormValues);
            $this->bridge->ensureDraftSubmission($pr->refresh()->load('lines'), $actor);

            return $this->registry->find((string) $pr->id) ?? $pr;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $composeFormValues
     */
    public function update(ProcurementPr $pr, array $input, TenantUser $actor, ?array $composeFormValues = null): ProcurementPr
    {
        if (! ProcurementPrStatus::isEditable((string) $pr->status)) {
            throw ValidationException::withMessages([
                'status' => [__('Only draft purchase requisitions can be edited.')],
            ]);
        }

        if ((string) $pr->requestor_id !== (string) $actor->id && ! $actor->can('procurement_one:documents:manage')) {
            throw ValidationException::withMessages([
                'pr' => [__('You cannot edit this purchase requisition.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($pr, $input, $actor, $composeFormValues): ProcurementPr {
            $lines = array_key_exists('lines', $input) ? $this->normalizeLines($input['lines']) : null;

            $pr->fill(array_filter([
                'title' => array_key_exists('title', $input) ? trim((string) $input['title']) : null,
                'department' => $input['department'] ?? null,
                'urgency' => $input['urgency'] ?? null,
                'justification' => $input['justification'] ?? null,
                'currency' => $input['currency'] ?? null,
                'project_id' => $input['project_id'] ?? null,
                'rollout_id' => $input['rollout_id'] ?? null,
                'site_id' => $input['site_id'] ?? null,
                'boq_line_id' => $input['boq_line_id'] ?? null,
            ], static fn ($value) => $value !== null));

            if ($lines !== null) {
                $pr->estimated_total = $this->mapper->recalculateTotal($lines);
                $this->mapper->syncLines($pr, $lines);
            }

            $this->persistComposeFormValues($pr, $composeFormValues);

            if ($pr->isDirty()) {
                $pr->save();
            }

            $this->bridge->syncDraft($pr->refresh()->load('lines'), $actor);

            return $this->registry->find((string) $pr->id) ?? $pr;
        });
    }

    /**
     * @param  array<string, mixed>|null  $composeFormValues
     */
    private function persistComposeFormValues(ProcurementPr $pr, ?array $composeFormValues): void
    {
        if ($composeFormValues === null) {
            return;
        }

        $metadata = is_array($pr->metadata_json) ? $pr->metadata_json : [];
        $metadata[ProcurementComposeMetadata::COMPOSE_FORM_VALUES_KEY] = $composeFormValues;
        $pr->metadata_json = $metadata;
        $pr->save();
    }

    /**
     * @param  list<mixed>  $lines
     * @return list<array{description: string, quantity: float, unit_price: float, amount: float}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $description = trim((string) ($line['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $quantity = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $normalized[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'amount' => round($quantity * $unitPrice, 2),
                'line_order' => $index,
            ];
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => [__('At least one line item is required.')],
            ]);
        }

        return $normalized;
    }
}
