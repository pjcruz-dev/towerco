<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\DTOs\GlobalHelpArticle;
use InvalidArgumentException;
use RuntimeException;

/**
 * Minimal YAML-like frontmatter parser for global help markdown files.
 */
final class GlobalHelpFrontMatterParser
{
    public function parseFile(string $absolutePath, string $relativePath): GlobalHelpArticle
    {
        if (! is_file($absolutePath)) {
            throw new InvalidArgumentException("Help article not found: {$absolutePath}");
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            throw new RuntimeException("Unable to read help article: {$absolutePath}");
        }

        return $this->parse($raw, $absolutePath, $relativePath);
    }

    public function parse(string $raw, string $absolutePath, string $relativePath): GlobalHelpArticle
    {
        $normalized = str_replace("\r\n", "\n", $raw);
        if (! str_starts_with($normalized, "---\n")) {
            throw new InvalidArgumentException("Missing frontmatter in {$relativePath}");
        }

        $end = strpos($normalized, "\n---\n", 4);
        if ($end === false) {
            throw new InvalidArgumentException("Unclosed frontmatter in {$relativePath}");
        }

        $frontMatterBlock = substr($normalized, 4, $end - 4);
        $body = trim(substr($normalized, $end + 5));
        $meta = $this->parseFrontMatter($frontMatterBlock, $relativePath);

        $slug = $meta['slug'];
        $title = $meta['title'];
        $module = $meta['module'];
        $audience = $meta['audience'];
        $status = $meta['status'];
        $version = $meta['version'];

        return new GlobalHelpArticle(
            slug: $slug,
            title: $title,
            moduleKey: $module,
            audience: $audience,
            permissions: $meta['permissions'],
            status: $status,
            version: $version,
            relatedRoutes: $meta['related_routes'],
            lastReviewed: $meta['last_reviewed'],
            body: $body,
            relativePath: $relativePath,
            absolutePath: $absolutePath,
            contentChecksum: hash('sha256', $normalized),
        );
    }

    /**
     * @return array{
     *   title: string,
     *   slug: string,
     *   module: string,
     *   audience: string,
     *   permissions: list<string>,
     *   status: string,
     *   version: int,
     *   related_routes: list<string>,
     *   last_reviewed: string|null
     * }
     */
    private function parseFrontMatter(string $block, string $relativePath): array
    {
        $data = [];
        $lines = explode("\n", $block);
        $currentListKey = null;

        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $matches) === 1) {
                $key = $matches[1];
                $value = trim($matches[2]);
                $currentListKey = null;

                if ($value === '' || $value === '[]') {
                    $data[$key] = [];
                    $currentListKey = $value === '' ? $key : null;

                    continue;
                }

                $data[$key] = $this->parseScalar($value);
                continue;
            }

            if ($currentListKey !== null && preg_match('/^\s*-\s+(.*)$/', $line, $matches) === 1) {
                if (! isset($data[$currentListKey]) || ! is_array($data[$currentListKey])) {
                    $data[$currentListKey] = [];
                }
                $data[$currentListKey][] = $this->parseScalar(trim($matches[1]));
            }
        }

        $slug = isset($data['slug']) && is_string($data['slug']) ? trim($data['slug']) : '';
        $title = isset($data['title']) && is_string($data['title']) ? trim($data['title']) : '';
        $module = isset($data['module']) && is_string($data['module']) ? trim($data['module']) : '';
        $audience = isset($data['audience']) && is_string($data['audience']) ? trim($data['audience']) : 'tenant_user';
        $status = isset($data['status']) && is_string($data['status']) ? trim($data['status']) : 'draft';
        $version = isset($data['version']) && is_numeric($data['version']) ? (int) $data['version'] : 0;

        if ($slug === '' || $title === '' || $module === '' || $version < 1) {
            throw new InvalidArgumentException("Invalid frontmatter required fields in {$relativePath}");
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException("Invalid slug in {$relativePath}");
        }

        /** @var list<string> $permissions */
        $permissions = $this->stringList($data['permissions'] ?? []);
        /** @var list<string> $relatedRoutes */
        $relatedRoutes = $this->stringList($data['related_routes'] ?? []);

        $lastReviewed = isset($data['last_reviewed']) && is_string($data['last_reviewed'])
            ? trim($data['last_reviewed'])
            : null;

        return [
            'title' => $title,
            'slug' => $slug,
            'module' => $module,
            'audience' => $audience !== '' ? $audience : 'tenant_user',
            'permissions' => $permissions,
            'status' => $status !== '' ? $status : 'draft',
            'version' => $version,
            'related_routes' => $relatedRoutes,
            'last_reviewed' => $lastReviewed !== '' ? $lastReviewed : null,
        ];
    }

    private function parseScalar(string $value): string|int|bool
    {
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? $value : (int) $value;
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null', '~' => '',
            default => $value,
        };
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) || is_numeric($item)) {
                $trimmed = trim((string) $item);
                if ($trimmed !== '') {
                    $items[] = $trimmed;
                }
            }
        }

        return array_values($items);
    }
}
