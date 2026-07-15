<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutPermit;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Support\RolloutPermitCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RolloutPermitService
{
    public function __construct(
        private readonly RolloutAuditLogger $audit,
    ) {}

    /**
     * @return list<array{
     *   id: string,
     *   permit_type: string,
     *   label: string,
     *   applied_date: string|null,
     *   secured_date: string|null,
     *   notes: string|null,
     *   sort_order: int,
     *   timeline_phase_key: string
     * }>
     */
    public function listForProgram(RolloutProgram $program): array
    {
        if ($program->status === 'batch') {
            return [];
        }

        $existing = RolloutPermit::query()
            ->where('rollout_program_id', $program->id)
            ->orderBy('sort_order')
            ->orderBy('permit_type')
            ->get()
            ->keyBy('permit_type');

        $rows = [];
        foreach (RolloutPermitCatalog::typeKeys() as $permitType) {
            /** @var RolloutPermit|null $permit */
            $permit = $existing->get($permitType);
            $rows[] = $this->presentRow($permitType, $permit);
        }

        return $rows;
    }

    /**
     * @param  list<array{permit_type: string, applied_date?: string|null, secured_date?: string|null, notes?: string|null}>  $permits
     * @return list<array<string, mixed>>
     */
    public function syncForProgram(RolloutProgram $program, array $permits): array
    {
        if ($program->status === 'batch') {
            throw ValidationException::withMessages([
                'rollout' => [__('Permits cannot be edited on a batch parent rollout.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($program, $permits): array {
            $seen = [];
            foreach ($permits as $index => $entry) {
                $permitType = (string) ($entry['permit_type'] ?? '');
                if (! RolloutPermitCatalog::isValid($permitType)) {
                    throw ValidationException::withMessages([
                        "permits.{$index}.permit_type" => [__('Invalid permit type.')],
                    ]);
                }

                if (isset($seen[$permitType])) {
                    throw ValidationException::withMessages([
                        "permits.{$index}.permit_type" => [__('Duplicate permit type in request.')],
                    ]);
                }
                $seen[$permitType] = true;

                $applied = $this->nullableDate($entry['applied_date'] ?? null);
                $secured = $this->nullableDate($entry['secured_date'] ?? null);

                if ($applied === null && $secured === null && trim((string) ($entry['notes'] ?? '')) === '') {
                    RolloutPermit::query()
                        ->where('rollout_program_id', $program->id)
                        ->where('permit_type', $permitType)
                        ->delete();

                    continue;
                }

                RolloutPermit::query()->updateOrCreate(
                    [
                        'rollout_program_id' => $program->id,
                        'permit_type' => $permitType,
                    ],
                    [
                        'applied_date' => $applied,
                        'secured_date' => $secured,
                        'notes' => $entry['notes'] ?? null,
                        'sort_order' => RolloutPermitCatalog::sortOrder($permitType),
                    ],
                );
            }

            $this->audit->log('permits_updated', $program, ['permit_count' => count($permits)]);

            return $this->listForProgram($program->fresh());
        });
    }

    /**
     * @return array<string, string|null>
     */
    public function flattenForExport(RolloutProgram $program): array
    {
        $flat = [];
        foreach ($this->listForProgram($program) as $row) {
            $type = (string) $row['permit_type'];
            $flat["permit_{$type}_applied_date"] = $row['applied_date'];
            $flat["permit_{$type}_secured_date"] = $row['secured_date'];
        }

        return $flat;
    }

    /**
     * @return array{
     *   id: string|null,
     *   permit_type: string,
     *   label: string,
     *   applied_date: string|null,
     *   secured_date: string|null,
     *   notes: string|null,
     *   sort_order: int
     * }
     */
    private function presentRow(string $permitType, ?RolloutPermit $permit): array
    {
        return [
            'id' => $permit?->id,
            'permit_type' => $permitType,
            'label' => RolloutPermitCatalog::label($permitType),
            'applied_date' => $permit?->applied_date?->toDateString(),
            'secured_date' => $permit?->secured_date?->toDateString(),
            'notes' => $permit?->notes,
            'sort_order' => RolloutPermitCatalog::sortOrder($permitType),
            'timeline_phase_key' => RolloutPermitCatalog::timelinePhaseKey($permitType),
        ];
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}
