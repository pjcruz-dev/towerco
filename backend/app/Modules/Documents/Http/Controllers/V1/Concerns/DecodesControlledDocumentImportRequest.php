<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

trait DecodesControlledDocumentImportRequest
{
    /**
     * @return array{
     *     file: UploadedFile,
     *     column_map: array<string, string>,
     *     available_paths: list<string>,
     *     archive: UploadedFile|null,
     *     files: array<string, UploadedFile>
     * }
     */
    private function decodeControlledDocumentImport(Request $request, bool $allowFiles): array
    {
        $rules = [
            'file' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:10240'],
            'column_map' => ['required', 'json'],
            'available_paths' => ['sometimes', 'nullable', 'json'],
            'archive' => ['sometimes', 'nullable', 'file', 'mimes:zip', 'max:102400'],
        ];

        if ($allowFiles) {
            $rules['files'] = ['sometimes', 'array', 'max:30'];
            $rules['files.*'] = ['file', 'max:51200'];
            $rules['file_paths'] = ['sometimes', 'nullable', 'json'];
        }

        $data = $request->validate($rules);
        $columnMap = $this->stringMap($data['column_map'], 'column_map');
        $availablePaths = $this->stringList($data['available_paths'] ?? '[]', 'available_paths');
        $archive = $data['archive'] ?? null;
        $files = [];

        if ($allowFiles) {
            /** @var list<UploadedFile> $uploaded */
            $uploaded = $request->file('files', []);
            if (! is_array($uploaded)) {
                $uploaded = [];
            }
            $filePaths = $this->stringList($data['file_paths'] ?? '[]', 'file_paths');
            if (count($uploaded) !== count($filePaths)) {
                throw ValidationException::withMessages([
                    'file_paths' => [__('Each uploaded file needs its folder path.')],
                ]);
            }
            foreach ($uploaded as $index => $file) {
                if ($file instanceof UploadedFile) {
                    $files[$filePaths[$index]] = $file;
                }
            }
            if ($archive instanceof UploadedFile && $files !== []) {
                throw ValidationException::withMessages([
                    'archive' => [__('Upload a folder of files or a zip, not both.')],
                ]);
            }
        }

        return [
            'file' => $data['file'],
            'column_map' => $columnMap,
            'available_paths' => $availablePaths,
            'archive' => $archive instanceof UploadedFile ? $archive : null,
            'files' => $files,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(string $json, string $field): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => [__('The column map is invalid.')],
            ]);
        }

        $map = [];
        foreach ($decoded as $header => $value) {
            if (! is_string($header) || ! is_string($value)) {
                throw ValidationException::withMessages([
                    $field => [__('The column map is invalid.')],
                ]);
            }
            $map[$header] = $value;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function stringList(string $json, string $field): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => [__('The file list is invalid.')],
            ]);
        }

        $list = [];
        foreach ($decoded as $value) {
            if (! is_string($value)) {
                throw ValidationException::withMessages([
                    $field => [__('The file list is invalid.')],
                ]);
            }
            $list[] = $value;
        }

        return $list;
    }
}
