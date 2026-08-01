<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\DTOs;

/**
 * @phpstan-type FrontMatter array{
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
final readonly class GlobalHelpArticle
{
    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $relatedRoutes
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $moduleKey,
        public string $audience,
        public array $permissions,
        public string $status,
        public int $version,
        public array $relatedRoutes,
        public ?string $lastReviewed,
        public string $body,
        public string $relativePath,
        public string $absolutePath,
        public string $contentChecksum,
    ) {}
}
