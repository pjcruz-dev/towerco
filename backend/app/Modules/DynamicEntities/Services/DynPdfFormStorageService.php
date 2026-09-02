<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynPdfForm;
use App\Modules\DynamicEntities\Support\DynPdfFormCatalog;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DynPdfFormStorageService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listMerged(): array
    {
        /** @var \Illuminate\Support\Collection<string, DynPdfForm> $byCode */
        $byCode = DynPdfForm::query()->orderBy('code')->get()->keyBy(
            static fn (DynPdfForm $row): string => DynPdfFormCatalog::normalizeCode($row->code),
        );

        $rows = [];
        $seen = [];

        foreach (DynPdfFormCatalog::entries() as $entry) {
            $code = DynPdfFormCatalog::normalizeCode($entry['code']);
            $seen[$code] = true;
            $uploaded = $byCode->get($code);

            if ($uploaded instanceof DynPdfForm) {
                $rows[] = $this->presentUploaded($uploaded, $entry);
                continue;
            }

            $rows[] = [
                'id' => null,
                'code' => $entry['code'],
                'name' => $entry['name'],
                'file_name' => $entry['code'].'.pdf',
                'path' => $entry['path'],
                'size_bytes' => null,
                'size_label' => '—',
                'available' => (bool) $entry['bundled'],
                'source' => $entry['bundled'] ? 'bundled' : 'missing',
                'preview_url' => $entry['preview_path'],
                'can_rename' => false,
                'can_delete' => false,
                'updated_at' => null,
            ];
        }

        foreach ($byCode as $code => $uploaded) {
            if (isset($seen[$code])) {
                continue;
            }
            $rows[] = $this->presentUploaded($uploaded, null);
        }

        return $rows;
    }

    public function store(
        UploadedFile $file,
        ?TenantUser $actor,
        ?string $code = null,
        ?string $name = null,
    ): DynPdfForm {
        $this->assertPdf($file);

        $resolvedCode = DynPdfFormCatalog::normalizeCode(
            $code ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'FORM',
        );
        if ($resolvedCode === '') {
            throw ValidationException::withMessages([
                'code' => [__('A form code is required.')],
            ]);
        }

        $catalog = DynPdfFormCatalog::find($resolvedCode);
        $displayName = trim((string) ($name ?: ($catalog['name'] ?? $file->getClientOriginalName())));
        if ($displayName === '') {
            $displayName = $resolvedCode;
        }

        $existing = DynPdfForm::query()
            ->whereRaw('UPPER(code) = ?', [$resolvedCode])
            ->first();

        $filename = Str::uuid()->toString().'.pdf';
        $storedPath = sprintf(
            '%s/dynamic-entities/pdf-forms/%s',
            $this->tenantStoragePrefix(),
            $filename,
        );

        $stored = Storage::disk($this->disk())->putFileAs(
            dirname($storedPath),
            $file,
            basename($storedPath),
        );

        if ($stored === false) {
            throw ValidationException::withMessages([
                'file' => [__('File could not be stored. Check storage configuration and try again.')],
            ]);
        }

        if ($existing instanceof DynPdfForm) {
            $this->deleteIfExists((string) $existing->file_path);
            $existing->fill([
                'code' => $catalog['code'] ?? $resolvedCode,
                'name' => $displayName,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size_bytes' => (int) $file->getSize(),
                'catalog_code' => $catalog['code'] ?? null,
                'uploaded_by' => $actor?->id,
            ]);
            $existing->save();

            return $existing->fresh() ?? $existing;
        }

        return DynPdfForm::query()->create([
            'id' => (string) Str::uuid(),
            'code' => $catalog['code'] ?? $resolvedCode,
            'name' => $displayName,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'mime_type' => $file->getMimeType() ?: 'application/pdf',
            'size_bytes' => (int) $file->getSize(),
            'catalog_code' => $catalog['code'] ?? null,
            'uploaded_by' => $actor?->id,
        ]);
    }

    public function rename(DynPdfForm $form, string $name, ?string $code = null): DynPdfForm
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => [__('Name is required.')],
            ]);
        }

        $form->name = $name;

        if ($code !== null && trim($code) !== '') {
            $resolved = DynPdfFormCatalog::normalizeCode($code);
            $conflict = DynPdfForm::query()
                ->whereRaw('UPPER(code) = ?', [$resolved])
                ->where('id', '!=', $form->id)
                ->exists();
            if ($conflict) {
                throw ValidationException::withMessages([
                    'code' => [__('Another PDF form already uses this code.')],
                ]);
            }
            $catalog = DynPdfFormCatalog::find($resolved);
            $form->code = $catalog['code'] ?? $resolved;
            $form->catalog_code = $catalog['code'] ?? null;
        }

        $form->save();

        return $form->fresh() ?? $form;
    }

    public function destroy(DynPdfForm $form): void
    {
        $this->deleteIfExists((string) $form->file_path);
        $form->delete();
    }

    public function download(DynPdfForm $form): StreamedResponse
    {
        $disk = Storage::disk($this->disk());
        if (! $disk->exists($form->file_path)) {
            abort(404, 'PDF form file not found.');
        }

        return $disk->response($form->file_path, $form->file_name, [
            'Content-Type' => $form->mime_type ?: 'application/pdf',
        ]);
    }

    /**
     * @param  array{code: string, name: string, path: string, bundled: bool, preview_path: string|null}|null  $catalog
     * @return array<string, mixed>
     */
    private function presentUploaded(DynPdfForm $form, ?array $catalog): array
    {
        return [
            'id' => $form->id,
            'code' => $form->code,
            'name' => $form->name,
            'file_name' => $form->file_name,
            'path' => $catalog['path'] ?? $form->file_path,
            'size_bytes' => $form->size_bytes,
            'size_label' => $this->formatBytes((int) $form->size_bytes),
            'available' => true,
            'source' => 'uploaded',
            'preview_url' => '/api/v1/dynamic-entities/pdf-forms/'.$form->id.'/file',
            'can_rename' => true,
            'can_delete' => true,
            'updated_at' => optional($form->updated_at)?->toIso8601String(),
        ];
    }

    private function assertPdf(UploadedFile $file): void
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $okExt = $ext === 'pdf';
        $okMime = in_array($mime, ['application/pdf', 'application/x-pdf', 'application/octet-stream'], true);

        if (! $okExt || ! $okMime) {
            throw ValidationException::withMessages([
                'file' => [__('Only PDF files are allowed.')],
            ]);
        }

        $maxKb = max(1, (int) config('toweros.tenant_files.max_size_kb', 10240));
        if ((int) $file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => [__('File exceeds the maximum upload size of :kb KB.', ['kb' => $maxKb])],
            ]);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    private function deleteIfExists(string $path): void
    {
        if ($path === '') {
            return;
        }
        $disk = Storage::disk($this->disk());
        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function tenantStoragePrefix(): string
    {
        return (string) (tenant()?->getTenantKey() ?? 'tenant');
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }
}
