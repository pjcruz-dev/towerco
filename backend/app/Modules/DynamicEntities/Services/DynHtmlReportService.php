<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Support\DynHtmlReportCatalog;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DynHtmlReportService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $this->ensureSeeded();

        return DynHtmlReport::query()
            ->orderBy('name')
            ->get()
            ->map(fn (DynHtmlReport $r): array => $this->present($r, false))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(DynHtmlReport $report): array
    {
        return $this->present($report, true);
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, html_source?: string|null, css_source?: string|null, js_source?: string|null, builder_json?: array<string, mixed>|null}  $data
     * @return array<string, mixed>
     */
    public function create(array $data, TenantUser $actor): array
    {
        $name = trim((string) $data['name']);
        $slug = $this->normalizeSlug((string) ($data['slug'] ?? ''), $name);
        $this->assertUniqueSlug($slug);

        $report = DynHtmlReport::query()->create([
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'html_source' => (string) ($data['html_source'] ?? DynHtmlReportCatalog::starterHtml($name)),
            'css_source' => (string) ($data['css_source'] ?? DynHtmlReportCatalog::starterCss()),
            'js_source' => (string) ($data['js_source'] ?? DynHtmlReportCatalog::starterJs()),
            'builder_json' => is_array($data['builder_json'] ?? null) ? $data['builder_json'] : null,
            'is_system' => false,
            'created_by' => (string) $actor->id,
            'updated_by' => (string) $actor->id,
        ]);

        return $this->present($report, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(DynHtmlReport $report, array $data, TenantUser $actor): array
    {
        if (array_key_exists('name', $data)) {
            $report->name = trim((string) $data['name']);
        }
        if (array_key_exists('slug', $data)) {
            $slug = $this->normalizeSlug((string) $data['slug'], (string) $report->name);
            $this->assertUniqueSlug($slug, (string) $report->id);
            $report->slug = $slug;
        }
        if (array_key_exists('description', $data)) {
            $desc = trim((string) ($data['description'] ?? ''));
            $report->description = $desc !== '' ? $desc : null;
        }
        if (array_key_exists('html_source', $data)) {
            $report->html_source = (string) $data['html_source'];
        }
        if (array_key_exists('css_source', $data)) {
            $report->css_source = (string) $data['css_source'];
        }
        if (array_key_exists('js_source', $data)) {
            $report->js_source = (string) $data['js_source'];
        }
        if (array_key_exists('builder_json', $data)) {
            $report->builder_json = is_array($data['builder_json']) ? $data['builder_json'] : null;
        }

        $report->updated_by = (string) $actor->id;
        $report->save();

        return $this->present($report, true);
    }

    public function destroy(DynHtmlReport $report): void
    {
        $report->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function duplicate(DynHtmlReport $report, TenantUser $actor): array
    {
        $baseSlug = $report->slug.'-copy';
        $slug = $baseSlug;
        $i = 2;
        while (DynHtmlReport::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i;
            $i++;
        }

        $copy = DynHtmlReport::query()->create([
            'name' => $report->name.' (Copy)',
            'slug' => $slug,
            'description' => $report->description,
            'html_source' => $report->html_source,
            'css_source' => $report->css_source,
            'js_source' => $report->js_source,
            'builder_json' => $report->builder_json,
            'is_system' => false,
            'created_by' => (string) $actor->id,
            'updated_by' => (string) $actor->id,
        ]);

        return $this->present($copy, true);
    }

    public function findBySlug(string $slug): DynHtmlReport
    {
        return DynHtmlReport::query()->where('slug', $slug)->firstOrFail();
    }

    public function ensureSeeded(): void
    {
        if (DynHtmlReport::query()->exists()) {
            return;
        }

        foreach (DynHtmlReportCatalog::defaults() as $row) {
            DynHtmlReport::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'] !== '' ? $row['description'] : null,
                'html_source' => DynHtmlReportCatalog::starterHtml($row['name']),
                'css_source' => DynHtmlReportCatalog::starterCss(),
                'js_source' => DynHtmlReportCatalog::starterJs(),
                'is_system' => true,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DynHtmlReport $report, bool $withSource): array
    {
        $payload = [
            'id' => (string) $report->id,
            'name' => (string) $report->name,
            'slug' => (string) $report->slug,
            'path' => '/'.$report->slug,
            'description' => $report->description,
            'is_system' => (bool) $report->is_system,
            'has_builder' => is_array($report->builder_json) && $report->builder_json !== [],
            'created_at' => optional($report->created_at)?->toIso8601String(),
            'updated_at' => optional($report->updated_at)?->toIso8601String(),
        ];

        if ($withSource) {
            $payload['html_source'] = (string) ($report->html_source ?? '');
            $payload['css_source'] = (string) ($report->css_source ?? '');
            $payload['js_source'] = (string) ($report->js_source ?? '');
            $payload['builder_json'] = is_array($report->builder_json) ? $report->builder_json : null;
        }

        return $payload;
    }

    private function normalizeSlug(string $slug, string $fallbackName): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            $slug = $fallbackName;
        }
        $slug = Str::slug($slug);
        if ($slug === '') {
            throw ValidationException::withMessages([
                'slug' => [__('A valid URL slug is required.')],
            ]);
        }
        if (strlen($slug) > 160) {
            $slug = substr($slug, 0, 160);
        }

        return $slug;
    }

    private function assertUniqueSlug(string $slug, ?string $exceptId = null): void
    {
        $q = DynHtmlReport::query()->where('slug', $slug);
        if ($exceptId) {
            $q->where('id', '!=', $exceptId);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages([
                'slug' => [__('That report slug is already in use.')],
            ]);
        }
    }
}
