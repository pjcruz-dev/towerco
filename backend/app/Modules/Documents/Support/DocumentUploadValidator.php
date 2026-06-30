<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class DocumentUploadValidator
{
    /**
     * @return list<string>
     */
    public function cadExtensions(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('toweros.documents.cad_extensions', []);

        return array_values(array_unique(array_map(
            static fn (string $ext): string => strtolower(trim($ext)),
            $extensions,
        )));
    }

    public function isCadFilename(string $filename): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, $this->cadExtensions(), true);
    }

    public function assertAllowedFile(string $mimeType, string $filename, int $sizeBytes): void
    {
        $this->assertAllowedSize($sizeBytes);

        if ($this->mimeMatchesAllowList($mimeType)) {
            return;
        }

        if ($this->isCadFilename($filename) && $this->cadMimeAllowed($mimeType)) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => [__('File type is not allowed.')],
        ]);
    }

    public function assertAllowedUploadedFile(UploadedFile $file): void
    {
        $this->assertAllowedFile(
            (string) ($file->getMimeType() ?? 'application/octet-stream'),
            $file->getClientOriginalName(),
            (int) $file->getSize(),
        );
    }

    private function mimeMatchesAllowList(string $mime): bool
    {
        /** @var list<string> $allowed */
        $allowed = config('toweros.documents.allowed_mimes', []);

        foreach ($allowed as $pattern) {
            if ($pattern === $mime) {
                return true;
            }
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($mime, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function cadMimeAllowed(string $mime): bool
    {
        if ($mime === 'application/octet-stream') {
            return true;
        }

        /** @var list<string> $cadMimes */
        $cadMimes = config('toweros.documents.cad_mimes', []);

        return in_array($mime, $cadMimes, true);
    }

    private function assertAllowedSize(int $sizeBytes): void
    {
        $maxKb = (int) config('toweros.documents.max_size_kb', 51200);

        if ($sizeBytes > $maxKb * 1024) {
            throw ValidationException::withMessages([
                'file' => [__('File exceeds the maximum upload size.')],
            ]);
        }
    }
}
