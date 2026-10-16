<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

final class ControlledDocumentImportPathMatcher
{
    /** @var list<string> */
    private const OFFICE_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'zip',
    ];

    /**
     * @param  list<string>  $availablePaths
     * @return array{status: string, path: string|null}
     */
    public function match(?string $folderPath, array $availablePaths): array
    {
        $raw = trim((string) $folderPath);
        if ($raw === '') {
            return ['status' => 'none', 'path' => null];
        }

        $needle = $this->segments($raw);
        if ($needle === []) {
            return ['status' => 'missing', 'path' => null];
        }

        $bestScore = 0;
        $best = [];

        foreach ($availablePaths as $candidate) {
            $score = $this->trailingSegmentMatch($needle, $this->segments($candidate));
            if ($score < 1) {
                continue;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$candidate];

                continue;
            }

            if ($score === $bestScore) {
                $best[] = $candidate;
            }
        }

        $unique = array_values(array_unique($best));
        if ($unique === []) {
            return ['status' => 'missing', 'path' => null];
        }

        if (count($unique) > 1) {
            return ['status' => 'ambiguous', 'path' => null];
        }

        $path = $unique[0];
        if (! $this->extensionAllowed($path)) {
            return ['status' => 'invalid_type', 'path' => $path];
        }

        return ['status' => 'matched', 'path' => $path];
    }

    public function extensionAllowed(string $path): bool
    {
        $extension = strtolower(pathinfo(str_replace('\\', '/', $path), PATHINFO_EXTENSION));
        if ($extension === '') {
            return false;
        }

        return in_array($extension, $this->allowedExtensions(), true);
    }

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        /** @var list<string> $cad */
        $cad = config('toweros.documents.cad_extensions', []);

        return array_values(array_unique(array_map(
            static fn (string $extension): string => strtolower(trim($extension)),
            array_merge(self::OFFICE_EXTENSIONS, $cad),
        )));
    }

    public function normalize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return strtolower(trim($path, '/'));
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        $normalized = $this->normalize($path);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $normalized), static fn (string $segment): bool => $segment !== '' && $segment !== '.'));
    }

    /**
     * @param  list<string>  $needle
     * @param  list<string>  $candidate
     */
    private function trailingSegmentMatch(array $needle, array $candidate): int
    {
        $score = 0;
        while ($score < count($needle) && $score < count($candidate)) {
            if ($needle[count($needle) - 1 - $score] !== $candidate[count($candidate) - 1 - $score]) {
                break;
            }
            $score++;
        }

        return $score;
    }
}
