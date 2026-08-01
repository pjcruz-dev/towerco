<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\AiAssistant\Services\HelpPackDiscoveryService;
use App\Modules\AiAssistant\Services\KnowledgeCatalogService;
use Illuminate\Console\Command;
use Throwable;

/**
 * CI-friendly validation for module help packs and core global articles.
 * Fails (non-zero) when frontmatter is missing/invalid or module keys are wrong.
 */
final class AiAssistantValidateHelpPacksCommand extends Command
{
    protected $signature = 'ai-assistant:validate-help-packs
                            {--strict : Treat warnings as failures}';

    protected $description = 'Validate module help packs and global help articles (frontmatter, module keys, slugs)';

    public function handle(HelpPackDiscoveryService $discovery, KnowledgeCatalogService $catalog): int
    {
        $strict = (bool) $this->option('strict');

        $errors = 0;
        $warnings = 0;

        // 1. Core global articles must all parse.
        try {
            $core = $catalog->discoverGlobalArticles();
            $this->info('Core global articles: '.$core->count().' parsed OK.');
        } catch (Throwable $e) {
            $this->error('Core global articles failed to parse: '.$e->getMessage());

            return self::FAILURE;
        }

        // 2. Module help packs.
        $issues = $discovery->validateModuleHelpPacks();
        $packCount = $discovery->discoverModuleHelpPacksCount();

        $this->info('Module help packs discovered: '.$packCount);

        foreach ($issues as $issue) {
            if ($issue['level'] === 'error') {
                $errors++;
                $this->error(sprintf('[error] %s — %s', $issue['path'], $issue['message']));
            } else {
                $warnings++;
                $this->warn(sprintf('[warn]  %s — %s', $issue['path'], $issue['message']));
            }
        }

        if ($errors === 0 && $warnings === 0) {
            $this->info('All help packs valid.');
        } else {
            $this->line(sprintf('Summary: %d error(s), %d warning(s).', $errors, $warnings));
        }

        if ($errors > 0) {
            return self::FAILURE;
        }

        if ($strict && $warnings > 0) {
            $this->error('Strict mode: warnings present.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
