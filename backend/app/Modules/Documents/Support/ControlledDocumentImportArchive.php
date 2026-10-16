<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

final class ControlledDocumentImportArchive
{
    public const MAX_ENTRIES = 2000;

    /**
     * @return list<string>
     */
    public function listPaths(string $realPath): array
    {
        $archive = $this->open($realPath);
        $paths = [];

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if (! is_string($name) || $this->isSkippedEntry($name)) {
                    continue;
                }

                $paths[] = str_replace('\\', '/', $name);
                if (count($paths) > self::MAX_ENTRIES) {
                    throw ValidationException::withMessages([
                        'archive' => [__('The zip has too many files. Choose the library folder instead.')],
                    ]);
                }
            }
        } finally {
            $archive->close();
        }

        return $paths;
    }

    /**
     * @param  list<string>  $entryNames
     * @return array<string, UploadedFile>
     */
    public function extract(string $realPath, array $entryNames): array
    {
        $wanted = [];
        foreach ($entryNames as $name) {
            $wanted[str_replace('\\', '/', $name)] = true;
        }

        if ($wanted === []) {
            return [];
        }

        $archive = $this->open($realPath);
        $files = [];

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                if (! is_string($name)) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $name);
                if (! isset($wanted[$normalized]) || $this->isSkippedEntry($normalized)) {
                    continue;
                }

                $contents = $archive->getFromIndex($index);
                if ($contents === false) {
                    continue;
                }

                $files[$normalized] = $this->toUploadedFile($normalized, $contents);
            }
        } finally {
            $archive->close();
        }

        return $files;
    }

    private function open(string $realPath): ZipArchive
    {
        $archive = new ZipArchive;
        if ($archive->open($realPath) !== true) {
            throw ValidationException::withMessages([
                'archive' => [__('Could not read the zip file.')],
            ]);
        }

        return $archive;
    }

    private function isSkippedEntry(string $name): bool
    {
        if ($name === '' || str_ends_with($name, '/')) {
            return true;
        }

        $normalized = str_replace('\\', '/', $name);

        return str_contains($normalized, '../')
            || str_starts_with($normalized, '/')
            || str_contains($normalized, ':');
    }

    private function toUploadedFile(string $entryName, string $contents): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cdimp');
        if ($tmp === false) {
            throw ValidationException::withMessages([
                'archive' => [__('Could not stage a file from the zip.')],
            ]);
        }

        file_put_contents($tmp, $contents);
        $filename = basename(str_replace('\\', '/', $entryName));
        $mime = mime_content_type($tmp) ?: null;

        return new UploadedFile($tmp, $filename !== '' ? $filename : 'document', $mime, null, true);
    }
}
