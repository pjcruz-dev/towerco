<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\AiAssistant\Services\FeedbackGapReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Lists thumbs-down assistant feedback as knowledge / routing gap candidates.
 */
final class AiAssistantFeedbackGapsCommand extends Command
{
    protected $signature = 'ai-assistant:feedback-gaps
                            {--tenant= : Tenant UUID}
                            {--all : Run for every tenant}
                            {--days=30 : Look back this many days}
                            {--limit=50 : Max gap rows per tenant}
                            {--json : Emit JSON instead of tables}';

    protected $description = 'Report AI Assistant thumbs-down feedback gaps (question, answer, module context)';

    public function handle(FeedbackGapReportService $gaps): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $since = Carbon::now()->subDays($days);
        $tenantId = $this->option('tenant');
        $all = (bool) $this->option('all');
        $asJson = (bool) $this->option('json');

        if (! $tenantId && ! $all && ! tenant()) {
            $this->error('Pass --tenant={uuid}, --all, or run inside an initialized tenant.');

            return self::FAILURE;
        }

        $tenants = [];
        if ($all) {
            $tenants = Tenant::query()->orderBy('id')->get()->all();
        } elseif (is_string($tenantId) && $tenantId !== '') {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                $this->error('Tenant not found: '.$tenantId);

                return self::FAILURE;
            }
            $tenants = [$tenant];
        } else {
            $tenants = [tenant()];
        }

        $payload = [];
        foreach ($tenants as $tenant) {
            if ($tenant === null) {
                continue;
            }

            $initializedHere = false;
            if ((string) tenant()?->getTenantKey() !== (string) $tenant->getTenantKey()) {
                tenancy()->initialize($tenant);
                $initializedHere = true;
            }

            try {
                $rows = $gaps->gaps($since, $limit);
                $summary = $gaps->summarize($rows);
                $payload[] = [
                    'tenant_id' => (string) $tenant->getTenantKey(),
                    'since' => $since->toIso8601String(),
                    'summary' => $summary,
                    'gaps' => $rows,
                ];

                if (! $asJson) {
                    $this->renderTenantReport((string) $tenant->getTenantKey(), $since, $summary, $rows);
                }
            } finally {
                if ($initializedHere) {
                    tenancy()->end();
                }
            }
        }

        if ($asJson) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{total: int, by_module: array<string, int>, sample_questions: list<string>}  $summary
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderTenantReport(string $tenantKey, Carbon $since, array $summary, array $rows): void
    {
        $this->newLine();
        $this->info(sprintf(
            'Tenant %s — %d thumbs-down gap(s) since %s',
            $tenantKey,
            $summary['total'],
            $since->toDateString(),
        ));

        if ($summary['by_module'] !== []) {
            $this->info('By module_context:');
            foreach ($summary['by_module'] as $module => $count) {
                $this->line(sprintf('  %s: %d', $module, $count));
            }
        }

        if ($rows === []) {
            $this->comment('No thumbs-down feedback in the selected window.');

            return;
        }

        $this->table(
            ['Module', 'Question', 'Comment', 'When'],
            array_map(static function (array $row): array {
                return [
                    $row['module_context'] ?? '—',
                    $row['question'] ?? '—',
                    $row['comment'] ?? '—',
                    $row['created_at'] ?? '—',
                ];
            }, $rows),
        );

        $this->comment('Use these questions to add help packs, fix tool routing, or publish tenant SOPs.');
    }
}
