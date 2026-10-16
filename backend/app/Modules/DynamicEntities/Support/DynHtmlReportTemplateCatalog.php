<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use InvalidArgumentException;

/**
 * Hand-authored HTML report templates the assistant can install.
 * Add one by placing {key}.html, {key}.css, and {key}.js in Support/html-reports
 * and appending a definition here. Each entry is a full example output.
 */
final class DynHtmlReportTemplateCatalog
{
    /**
     * @return list<array{key: string, name: string, slug: string, description: string, phrases: list<string>, output: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'procurement-approval-pipeline',
                'name' => 'Procurement & Approval Pipeline',
                'slug' => 'procurement-approval-pipeline',
                'description' => 'Pending approvals by level and process stage across requisitions, orders, SCM sourcing, and disbursements.',
                'phrases' => [
                    'procurement dashboard',
                    'procurement approval',
                    'procurement pipeline',
                    'approval pipeline',
                    'purchase requisition dashboard',
                ],
                'output' => 'Pipeline dashboard fragment: print header, filter toolbar, KPI stage cards, stacked bar chart, SLA doughnut, tabbed queue with CSV export. JavaScript reads with AppFramework.ExecuteSQL.',
            ],
            [
                'key' => 'ticketing-dashboard',
                'name' => 'Ticketing Dashboard',
                'slug' => 'ticketing-dashboard',
                'description' => 'IT ticket queue: open work, assignment, priority, and SLA ageing.',
                'phrases' => [
                    'ticketing html report',
                    'ticketing dashboard',
                    'ticketing report',
                    'ticket dashboard',
                    'helpdesk dashboard',
                    'helpdesk report',
                ],
                'output' => 'IT ticket dashboard fragment: filters, KPI cards, status bar chart, SLA doughnut, ticket table. JavaScript reads ticketing_tickets with AppFramework.ExecuteSQL.',
            ],
        ];
    }

    /**
     * @return array{key: string, name: string, slug: string, description: string, phrases: list<string>, output: string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['key'] === $key) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * A create/build request that names one template.
     *
     * @return array{key: string, name: string, slug: string, description: string, phrases: list<string>, output: string}|null
     */
    public static function match(string $question): ?array
    {
        $q = mb_strtolower(trim($question));
        if ($q === '' || ! self::asksToCreate($q)) {
            return null;
        }

        $best = null;
        $bestLength = 0;
        foreach (self::definitions() as $definition) {
            $needles = $definition['phrases'];
            $needles[] = $definition['key'];
            foreach ($needles as $needle) {
                if ($needle !== '' && str_contains($q, $needle) && strlen($needle) > $bestLength) {
                    $best = $definition;
                    $bestLength = strlen($needle);
                }
            }
        }

        return $best;
    }

    /**
     * @return array{html: string, css: string, js: string}
     */
    public static function sources(string $key): array
    {
        if (self::find($key) === null) {
            throw new InvalidArgumentException("Unknown HTML report template [{$key}].");
        }

        $base = app_path('Modules/DynamicEntities/Support/html-reports/'.$key);
        $html = self::read($base.'.html');
        $css = self::read($base.'.css');
        $js = self::read($base.'.js');
        if ($html === '' || $js === '') {
            throw new InvalidArgumentException("HTML report template [{$key}] is missing source.");
        }

        return ['html' => $html, 'css' => $css, 'js' => $js];
    }

    public static function promptSection(): string
    {
        $lines = [];
        foreach (self::definitions() as $definition) {
            $lines[] = '- `'.$definition['key'].'` — '.$definition['name'].'. '.$definition['description'].' Output: '.$definition['output'];
        }
        $list = implode("\n", $lines);

        return <<<MD
## HTML report templates (example outputs)
These are finished HTML/CSS/JS reports. When the user asks to create one of them, propose `create_html_report_from_template` with that key. The saved report is the template itself. Do not rewrite it as Report Builder JSON, and do not paste the source into chat.

The page already loads Bootstrap, Font Awesome, jQuery, and Chart.js. Template JavaScript calls `AppFramework.ExecuteSQL(sql)` for one SELECT. `html_source` stays a fragment (a div), not a full document.

{$list}

A request that does not name one of these templates stays on Report Builder (`create_html_report_from_prompt`).
MD;
    }

    private static function asksToCreate(string $question): bool
    {
        foreach (['create', 'build', 'make', 'generate', 'install', 'set up', 'setup', 'add the', 'use the', 'from template', 'from the template'] as $verb) {
            if (str_contains($question, $verb)) {
                return true;
            }
        }

        return false;
    }

    private static function read(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }
}
