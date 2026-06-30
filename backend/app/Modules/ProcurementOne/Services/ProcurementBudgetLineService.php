<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementBudgetLine;
use App\Modules\ProcurementOne\Support\ProcurementExpenseType;
use Illuminate\Validation\ValidationException;

final class ProcurementBudgetLineService
{
    public static function sumActiveBudgetForRollout(string $rolloutId): float
    {
        $totals = self::sumActiveBudgetForRollouts([$rolloutId]);

        return $totals[$rolloutId] ?? 0.0;
    }

    /**
     * @param  list<string>  $rolloutIds
     * @return array<string, float>
     */
    public static function sumActiveBudgetForRollouts(array $rolloutIds): array
    {
        if ($rolloutIds === []) {
            return [];
        }

        return ProcurementBudgetLine::query()
            ->whereIn('rollout_id', $rolloutIds)
            ->where('is_active', true)
            ->groupBy('rollout_id')
            ->selectRaw('rollout_id, round(sum(budget_amount), 2) as aggregate')
            ->pluck('aggregate', 'rollout_id')
            ->mapWithKeys(static fn ($total, $rolloutId): array => [(string) $rolloutId => (float) $total])
            ->all();
    }

    public static function sumActiveBudgetForProject(string $projectId): float
    {
        return round((float) ProcurementBudgetLine::query()
            ->where('project_id', $projectId)
            ->whereNull('rollout_id')
            ->where('is_active', true)
            ->sum('budget_amount'), 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForScope(?string $rolloutId = null, ?string $projectId = null): array
    {
        $query = ProcurementBudgetLine::query()
            ->with('costCenter:id,code,name')
            ->where('is_active', true)
            ->orderBy('line_code')
            ->orderBy('description');

        if ($rolloutId !== null && $rolloutId !== '') {
            $query->where('rollout_id', $rolloutId);
        } elseif ($projectId !== null && $projectId !== '') {
            $query->where('project_id', $projectId);
        }

        return $query->get()->map(fn (ProcurementBudgetLine $line) => $this->asPayload($line))->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): ProcurementBudgetLine
    {
        $normalized = $this->validatePayload($input);

        return ProcurementBudgetLine::query()->create($normalized);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(ProcurementBudgetLine $line, array $input): ProcurementBudgetLine
    {
        $normalized = $this->validatePayload(array_merge([
            'project_id' => $line->project_id,
            'rollout_id' => $line->rollout_id,
            'cost_center_id' => $line->cost_center_id,
            'line_code' => $line->line_code,
            'description' => $line->description,
            'expense_type' => $line->expense_type,
            'budget_amount' => $line->budget_amount,
            'is_active' => $line->is_active,
            'notes' => $line->notes,
        ], $input));

        $line->fill($normalized);
        $line->save();

        return $line->refresh()->load('costCenter');
    }

    public function delete(ProcurementBudgetLine $line): void
    {
        $line->delete();
    }

    public function find(string $id): ?ProcurementBudgetLine
    {
        return ProcurementBudgetLine::query()->with('costCenter')->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function asPayload(ProcurementBudgetLine $line): array
    {
        return [
            'id' => (string) $line->id,
            'project_id' => $line->project_id,
            'rollout_id' => $line->rollout_id,
            'cost_center_id' => $line->cost_center_id,
            'cost_center' => $line->costCenter ? [
                'id' => (string) $line->costCenter->id,
                'code' => $line->costCenter->code,
                'name' => $line->costCenter->name,
            ] : null,
            'line_code' => $line->line_code,
            'description' => $line->description,
            'expense_type' => $line->expense_type,
            'expense_type_label' => ProcurementExpenseType::label((string) $line->expense_type),
            'budget_amount' => (float) $line->budget_amount,
            'is_active' => (bool) $line->is_active,
            'notes' => $line->notes,
            'created_at' => $line->created_at?->toIso8601String(),
            'updated_at' => $line->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validatePayload(array $input): array
    {
        $projectId = $input['project_id'] ?? null;
        $rolloutId = $input['rollout_id'] ?? null;
        if ($projectId === null && $rolloutId === null) {
            throw ValidationException::withMessages([
                'scope' => [__('Budget line must be linked to a project or rollout.')],
            ]);
        }

        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            throw ValidationException::withMessages([
                'description' => [__('Budget line description is required.')],
            ]);
        }

        $expenseType = (string) ($input['expense_type'] ?? ProcurementExpenseType::CAPEX);
        if (! ProcurementExpenseType::isValid($expenseType)) {
            throw ValidationException::withMessages([
                'expense_type' => [__('Expense type must be capex or opex.')],
            ]);
        }

        $amount = (float) ($input['budget_amount'] ?? 0);
        if ($amount < 0) {
            throw ValidationException::withMessages([
                'budget_amount' => [__('Budget amount cannot be negative.')],
            ]);
        }

        return [
            'project_id' => $projectId,
            'rollout_id' => $rolloutId,
            'cost_center_id' => $input['cost_center_id'] ?? null,
            'line_code' => $input['line_code'] ?? null,
            'description' => $description,
            'expense_type' => $expenseType,
            'budget_amount' => round($amount, 2),
            'is_active' => (bool) ($input['is_active'] ?? true),
            'notes' => $input['notes'] ?? null,
        ];
    }
}
