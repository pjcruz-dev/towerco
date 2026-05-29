<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\OperationalAcronym;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OperationalAcronymService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        return OperationalAcronym::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('acronym')
            ->get()
            ->map(static fn (OperationalAcronym $row) => $row->toApiArray())
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        return OperationalAcronym::query()
            ->orderBy('sort_order')
            ->orderBy('acronym')
            ->get()
            ->map(static fn (OperationalAcronym $row) => $row->toApiArray())
            ->values()
            ->all();
    }

    /**
     * @param  array{acronym: string, definition: string, category?: string|null, sort_order?: int, is_active?: bool}  $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $acronym = $this->normalizeAcronym((string) ($input['acronym'] ?? ''));
        $definition = trim((string) ($input['definition'] ?? ''));

        if ($acronym === '' || $definition === '') {
            throw ValidationException::withMessages([
                'acronym' => [__('Acronym and definition are required.')],
            ]);
        }

        if (OperationalAcronym::query()->where('acronym', $acronym)->exists()) {
            throw ValidationException::withMessages([
                'acronym' => [__('This acronym already exists.')],
            ]);
        }

        $row = OperationalAcronym::query()->create([
            'acronym' => $acronym,
            'definition' => $definition,
            'category' => $this->normalizeCategory($input['category'] ?? null),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'is_active' => (bool) ($input['is_active'] ?? true),
        ]);

        return $row->toApiArray();
    }

    /**
     * @param  array{acronym?: string, definition?: string, category?: string|null, sort_order?: int, is_active?: bool}  $input
     * @return array<string, mixed>
     */
    public function update(OperationalAcronym $row, array $input): array
    {
        if (array_key_exists('acronym', $input)) {
            $acronym = $this->normalizeAcronym((string) $input['acronym']);
            if ($acronym === '') {
                throw ValidationException::withMessages([
                    'acronym' => [__('Acronym is required.')],
                ]);
            }

            if (
                OperationalAcronym::query()
                    ->where('acronym', $acronym)
                    ->where('id', '!=', $row->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'acronym' => [__('This acronym already exists.')],
                ]);
            }

            $row->acronym = $acronym;
        }

        if (array_key_exists('definition', $input)) {
            $definition = trim((string) $input['definition']);
            if ($definition === '') {
                throw ValidationException::withMessages([
                    'definition' => [__('Definition is required.')],
                ]);
            }
            $row->definition = $definition;
        }

        if (array_key_exists('category', $input)) {
            $row->category = $this->normalizeCategory($input['category']);
        }

        if (array_key_exists('sort_order', $input)) {
            $row->sort_order = (int) $input['sort_order'];
        }

        if (array_key_exists('is_active', $input)) {
            $row->is_active = (bool) $input['is_active'];
        }

        $row->save();

        return $row->fresh()?->toApiArray() ?? $row->toApiArray();
    }

    public function delete(OperationalAcronym $row): void
    {
        $row->delete();
    }

    /**
     * @param  list<array{acronym: string, definition: string, category?: string|null, sort_order?: int}>  $defaults
     */
    public function syncDefaults(array $defaults): int
    {
        $created = 0;

        foreach ($defaults as $index => $item) {
            $acronym = $this->normalizeAcronym((string) ($item['acronym'] ?? ''));
            $definition = trim((string) ($item['definition'] ?? ''));

            if ($acronym === '' || $definition === '') {
                continue;
            }

            OperationalAcronym::query()->updateOrCreate(
                ['acronym' => $acronym],
                [
                    'definition' => $definition,
                    'category' => $this->normalizeCategory($item['category'] ?? null),
                    'sort_order' => (int) ($item['sort_order'] ?? $index),
                    'is_active' => true,
                ],
            );

            $created++;
        }

        return $created;
    }

    private function normalizeAcronym(string $value): string
    {
        return Str::of($value)->trim()->replaceMatches('/\s+/', ' ')->toString();
    }

    private function normalizeCategory(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $category = trim((string) $value);

        return $category !== '' ? substr($category, 0, 64) : null;
    }
}
