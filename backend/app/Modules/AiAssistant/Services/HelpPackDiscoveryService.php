<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\DTOs\GlobalHelpArticle;
use App\Modules\AiAssistant\Support\GlobalHelpFrontMatterParser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Discovers per-module "help packs" shipped by any registered module under:
 *
 *   backend/app/Modules/{Module}/Knowledge/help/*.md
 *
 * Each markdown file must carry the standard help frontmatter (module, permissions,
 * audience, status, version, related_routes, plus title/slug). Discovered packs are
 * registered as GLOBAL, module-tagged knowledge sources so a new module becomes
 * assistant-discoverable simply by shipping a help pack — no core changes required.
 */
final class HelpPackDiscoveryService
{
    /** Convention subpath, relative to a module directory. */
    public const HELP_PACK_SUBPATH = 'Knowledge/help';

    public function __construct(
        private readonly GlobalHelpFrontMatterParser $parser,
    ) {}

    public function modulesRoot(): string
    {
        return app_path('Modules');
    }

    /**
     * Every module key TowerOS knows about (required + toggleable).
     *
     * @return list<string>
     */
    public function knownModuleKeys(): array
    {
        return array_values(array_unique(array_merge(
            TenantEnabledModulesResolver::REQUIRED_MODULES,
            TenantEnabledModulesResolver::TOGGLEABLE_MODULES,
        )));
    }

    /**
     * Map a module directory name (StudlyCase) to its module key (snake_case).
     * e.g. "ProcurementOne" => "procurement_one", "EApproval" => "e_approval".
     */
    public function moduleKeyForFolder(string $folder): string
    {
        return Str::snake($folder);
    }

    /**
     * Discover module help pack articles across all module directories.
     *
     * @return Collection<int, GlobalHelpArticle>
     */
    public function discoverModuleHelpPacks(): Collection
    {
        $root = $this->modulesRoot();
        if (! is_dir($root)) {
            return collect();
        }

        $articles = collect();

        foreach (File::directories($root) as $moduleDir) {
            $moduleFolder = basename($moduleDir);
            $helpDir = $moduleDir.DIRECTORY_SEPARATOR.'Knowledge'.DIRECTORY_SEPARATOR.'help';
            if (! is_dir($helpDir)) {
                continue;
            }

            foreach (File::files($helpDir) as $file) {
                $name = strtolower($file->getFilename());
                if (! str_ends_with($name, '.md') || $name === 'readme.md') {
                    continue;
                }

                $relative = $moduleFolder.'/'.self::HELP_PACK_SUBPATH.'/'.$file->getFilename();
                $articles->push($this->parser->parseFile($file->getPathname(), $relative));
            }
        }

        return $articles
            ->sortBy(static fn (GlobalHelpArticle $article): string => $article->slug)
            ->values();
    }

    public function discoverModuleHelpPacksCount(): int
    {
        return $this->discoverModuleHelpPacks()->count();
    }

    /**
     * Validate every module help pack without throwing. Returns a flat list of issues.
     *
     * @return list<array{path: string, level: 'error'|'warning', message: string}>
     */
    public function validateModuleHelpPacks(): array
    {
        $root = $this->modulesRoot();
        if (! is_dir($root)) {
            return [];
        }

        $known = $this->knownModuleKeys();
        $issues = [];
        $slugOwners = [];

        foreach (File::directories($root) as $moduleDir) {
            $moduleFolder = basename($moduleDir);
            $expectedKey = $this->moduleKeyForFolder($moduleFolder);
            $helpDir = $moduleDir.DIRECTORY_SEPARATOR.'Knowledge'.DIRECTORY_SEPARATOR.'help';
            if (! is_dir($helpDir)) {
                continue;
            }

            foreach (File::files($helpDir) as $file) {
                $name = strtolower($file->getFilename());
                if (! str_ends_with($name, '.md') || $name === 'readme.md') {
                    continue;
                }

                $relative = $moduleFolder.'/'.self::HELP_PACK_SUBPATH.'/'.$file->getFilename();

                try {
                    $article = $this->parser->parseFile($file->getPathname(), $relative);
                } catch (Throwable $e) {
                    $issues[] = [
                        'path' => $relative,
                        'level' => 'error',
                        'message' => $e->getMessage(),
                    ];

                    continue;
                }

                if (! in_array($article->moduleKey, $known, true)) {
                    $issues[] = [
                        'path' => $relative,
                        'level' => 'error',
                        'message' => "Unknown module key '{$article->moduleKey}'. Add it to TenantEnabledModulesResolver or fix the frontmatter.",
                    ];
                } elseif ($article->moduleKey !== $expectedKey) {
                    $issues[] = [
                        'path' => $relative,
                        'level' => 'error',
                        'message' => "Module key '{$article->moduleKey}' does not match its folder (expected '{$expectedKey}').",
                    ];
                }

                if ($article->permissions === []) {
                    $issues[] = [
                        'path' => $relative,
                        'level' => 'warning',
                        'message' => 'No required permissions declared; this article will be retrievable by any user with the module enabled.',
                    ];
                }

                if (isset($slugOwners[$article->slug])) {
                    $issues[] = [
                        'path' => $relative,
                        'level' => 'error',
                        'message' => "Duplicate slug '{$article->slug}' also used by {$slugOwners[$article->slug]}.",
                    ];
                } else {
                    $slugOwners[$article->slug] = $relative;
                }
            }
        }

        return $issues;
    }
}
