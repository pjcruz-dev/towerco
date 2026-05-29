<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\TenantPublicHoliday;
use App\Modules\Rollout\Support\PhilippinesPublicHolidayCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class TenantPublicHolidayService
{
    public function __construct(
        private readonly RolloutSlaRecalculationService $slaRecalculation,
    ) {}

    /**
     * @return list<string> Y-m-d
     */
    public function activeHolidayDates(?int $year = null): array
    {
        if (! Schema::connection('tenant')->hasTable('tenant_public_holidays')) {
            return [];
        }

        $year = $year ?? (int) now()->format('Y');

        return TenantPublicHoliday::query()
            ->where('calendar_year', $year)
            ->orderBy('holiday_date')
            ->pluck('holiday_date')
            ->map(static fn ($date) => $date->toDateString())
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, holiday_date: string, name: string, region: string|null, calendar_year: int}>
     */
    public function listForYear(int $year): array
    {
        if (! Schema::connection('tenant')->hasTable('tenant_public_holidays')) {
            return [];
        }

        return TenantPublicHoliday::query()
            ->where('calendar_year', $year)
            ->orderBy('holiday_date')
            ->get()
            ->map(fn (TenantPublicHoliday $holiday) => $this->present($holiday))
            ->values()
            ->all();
    }

    /**
     * @param  array{holiday_date: string, name: string, region?: string|null, calendar_year?: int|null}  $data
     */
    public function create(array $data): TenantPublicHoliday
    {
        $date = Carbon::parse($data['holiday_date']);

        /** @var TenantPublicHoliday $holiday */
        $holiday = TenantPublicHoliday::query()->create([
            'holiday_date' => $date->toDateString(),
            'name' => $data['name'],
            'region' => $data['region'] ?? null,
            'calendar_year' => (int) ($data['calendar_year'] ?? $date->format('Y')),
        ]);

        $this->refreshRolloutSlas();

        return $holiday;
    }

    /**
     * @param  array{holiday_date?: string, name?: string, region?: string|null, calendar_year?: int|null}  $data
     */
    public function update(TenantPublicHoliday $holiday, array $data): TenantPublicHoliday
    {
        if (array_key_exists('holiday_date', $data) && $data['holiday_date'] !== null) {
            $date = Carbon::parse($data['holiday_date']);
            $holiday->holiday_date = $date->toDateString();
            $holiday->calendar_year = (int) ($data['calendar_year'] ?? $date->format('Y'));
        }

        if (array_key_exists('name', $data) && $data['name'] !== null) {
            $holiday->name = $data['name'];
        }

        if (array_key_exists('region', $data)) {
            $holiday->region = $data['region'];
        }

        if (array_key_exists('calendar_year', $data) && $data['calendar_year'] !== null && ! array_key_exists('holiday_date', $data)) {
            $holiday->calendar_year = (int) $data['calendar_year'];
        }

        try {
            $holiday->save();
        } catch (\Illuminate\Database\QueryException $exception) {
            if (str_contains($exception->getMessage(), 'Duplicate')) {
                throw ValidationException::withMessages([
                    'holiday_date' => [__('A holiday already exists for this date and region.')],
                ]);
            }

            throw $exception;
        }

        $this->refreshRolloutSlas();

        return $holiday->fresh();
    }

    public function delete(TenantPublicHoliday $holiday): void
    {
        $holiday->delete();
        $this->refreshRolloutSlas();
    }

    public function seedPhilippinesYear(int $year, ?string $region = null): int
    {
        $count = 0;

        foreach (PhilippinesPublicHolidayCatalog::forYear($year) as $row) {
            TenantPublicHoliday::query()->updateOrCreate(
                [
                    'holiday_date' => $row['date'],
                    'region' => $region,
                ],
                [
                    'name' => $row['name'],
                    'calendar_year' => $year,
                ],
            );
            $count++;
        }

        $this->refreshRolloutSlas();

        return $count;
    }

    /**
     * @return array{id: string, holiday_date: string, name: string, region: string|null, calendar_year: int}
     */
    private function present(TenantPublicHoliday $holiday): array
    {
        return [
            'id' => $holiday->id,
            'holiday_date' => $holiday->holiday_date->toDateString(),
            'name' => $holiday->name,
            'region' => $holiday->region,
            'calendar_year' => $holiday->calendar_year,
        ];
    }

    private function refreshRolloutSlas(): int
    {
        if (! Schema::connection('tenant')->hasTable('rollout_programs')) {
            return 0;
        }

        return $this->slaRecalculation->recalculateActivePrograms();
    }
}
