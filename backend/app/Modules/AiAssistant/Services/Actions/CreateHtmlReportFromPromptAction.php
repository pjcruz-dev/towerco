<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\DynamicEntities\Services\DynReportBuilderAiService;
use App\Modules\DynamicEntities\Services\DynReportBuilderService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Validation\ValidationException;

/**
 * Propose a Report Builder definition from NL; save DynHtmlReport only after confirm.
 */
final class CreateHtmlReportFromPromptAction implements AssistantActionInterface
{
    public function __construct(
        private readonly DynReportBuilderAiService $aiBuild,
        private readonly DynReportBuilderService $reports,
        private readonly PinHtmlReportToDashboardAction $pinToDashboard,
    ) {}

    public function name(): string
    {
        return 'create_html_report_from_prompt';
    }

    public function description(): string
    {
        return 'Propose creating an HTML report from a natural-language description (Report Builder DSL). Saves only after confirmation.';
    }

    public function requiredModule(): ?string
    {
        return 'dynamic_entities';
    }

    public function requiredDomainPermissions(): array
    {
        return ['html_reports:manage'];
    }

    public function argumentRules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'entity_slug' => ['required', 'string', 'max:160'],
            'format' => ['sometimes', 'nullable', 'string', 'in:summary,detail,matrix'],
            'group_by' => ['sometimes', 'nullable', 'string', 'max:120'],
            'chart' => ['sometimes', 'nullable', 'string', 'in:bar,pie,line,none'],
            'pin_to_dashboard' => ['sometimes', 'boolean'],
            'definition' => ['required', 'array'],
            'prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $entitySlug = isset($args['entity_slug']) && is_string($args['entity_slug'])
            ? trim($args['entity_slug'])
            : null;

        $entityHints = [];
        if (is_array($args['entity_hints'] ?? null)) {
            foreach ($args['entity_hints'] as $hint) {
                if (is_string($hint) && trim($hint) !== '') {
                    $entityHints[] = trim($hint);
                }
            }
        }

        $moduleContext = isset($args['module_context']) && is_string($args['module_context'])
            ? trim($args['module_context'])
            : null;

        $built = $this->aiBuild->build([
            'prompt' => $question,
            'entity_slug' => $entitySlug,
            'entity_hints' => $entityHints,
            'module_context' => $moduleContext,
            'preferred_model' => isset($args['preferred_model']) && is_string($args['preferred_model'])
                ? trim($args['preferred_model'])
                : null,
        ]);

        $definition = is_array($built['definition'] ?? null) ? $built['definition'] : [];
        if ($definition === [] && is_array($args['base_definition'] ?? null)) {
            $definition = $args['base_definition'];
        }
        if ($definition === []) {
            throw ValidationException::withMessages([
                'prompt' => ['Could not draft a report definition from that request.'],
            ]);
        }

        if (is_array($args['base_definition'] ?? null) && ($args['refine'] ?? false)) {
            $definition = $this->mergeRefineDefinition($args['base_definition'], $definition, $question);
        }

        if (isset($args['title']) && is_string($args['title']) && trim($args['title']) !== '') {
            $definition['title'] = trim($args['title']);
        }
        if ($entitySlug) {
            $definition['entity_slug'] = $entitySlug;
        }

        $title = trim((string) ($definition['title'] ?? 'Untitled report'));
        $entity = trim((string) ($definition['entity_slug'] ?? ''));
        $format = trim((string) ($definition['format'] ?? 'summary'));
        $groupBy = trim((string) ($definition['group_by'] ?? ''));
        $chart = trim((string) ($definition['chart'] ?? 'bar'));
        $pin = array_key_exists('pin_to_dashboard', $args)
            ? (bool) $args['pin_to_dashboard']
            : $this->wantsDashboardPin($question);

        $payload = [
            'title' => $title,
            'entity_slug' => $entity,
            'format' => $format !== '' ? $format : 'summary',
            'group_by' => $groupBy !== '' ? $groupBy : null,
            'chart' => $chart !== '' ? $chart : 'bar',
            'pin_to_dashboard' => $pin,
            'definition' => $definition,
            'prompt' => $question,
        ];

        $notes = is_string($built['notes'] ?? null) ? $built['notes'] : null;
        $source = (string) ($built['source'] ?? 'ai');

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Create HTML report',
            summary: 'I drafted a Report Builder definition from your request. Nothing is saved until you confirm.'
                .($notes ? ' '.$notes : '')
                .' Source: '.$source.'.',
            payload: $payload,
            preview: [
                'title' => $payload['title'],
                'entity_slug' => $payload['entity_slug'],
                'format' => $payload['format'],
                'group_by' => $payload['group_by'],
                'chart' => $payload['chart'],
                'pin_to_dashboard' => $payload['pin_to_dashboard'],
                'metric' => $definition['metric'] ?? 'count',
            ],
            editableFields: [
                ['key' => 'title', 'label' => 'Report title', 'type' => 'text', 'required' => true],
                ['key' => 'entity_slug', 'label' => 'Entity slug', 'type' => 'text', 'required' => true],
                ['key' => 'format', 'label' => 'Format (summary|detail|matrix)', 'type' => 'text', 'required' => false],
                ['key' => 'group_by', 'label' => 'Group by field', 'type' => 'text', 'required' => false],
                ['key' => 'chart', 'label' => 'Chart (bar|pie|line|none)', 'type' => 'text', 'required' => false],
                ['key' => 'pin_to_dashboard', 'label' => 'Also pin to home dashboard (1/0)', 'type' => 'text', 'required' => false],
            ],
            moduleKey: 'dynamic_entities',
            confirmLabel: 'Save report',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $definition = is_array($payload['definition'] ?? null) ? $payload['definition'] : [];
        $definition['title'] = (string) ($payload['title'] ?? $definition['title'] ?? '');
        $definition['entity_slug'] = (string) ($payload['entity_slug'] ?? $definition['entity_slug'] ?? '');
        if (isset($payload['format']) && is_string($payload['format']) && $payload['format'] !== '') {
            $definition['format'] = $payload['format'];
        }
        if (array_key_exists('group_by', $payload)) {
            $definition['group_by'] = $payload['group_by'] !== null && $payload['group_by'] !== ''
                ? (string) $payload['group_by']
                : null;
        }
        if (isset($payload['chart']) && is_string($payload['chart']) && $payload['chart'] !== '') {
            $definition['chart'] = $payload['chart'];
        }

        $report = $this->reports->saveAsHtmlReport($definition, $viewer);
        $slug = (string) ($report['slug'] ?? '');
        $id = (string) ($report['id'] ?? '');
        $name = (string) ($report['name'] ?? $definition['title'] ?? 'Report');

        $meta = [
            'slug' => $slug,
            'name' => $name,
            'entity_slug' => $definition['entity_slug'] ?? null,
        ];

        $pinRequested = filter_var($payload['pin_to_dashboard'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($pinRequested && $slug !== '') {
            $pinResult = $this->pinToDashboard->execute($viewer, [
                'report_slug' => $slug,
                'title' => $name,
            ]);
            $meta['pinned'] = $pinResult->ok;
            $meta['dashboard_href'] = $pinResult->href;
        }

        return new ActionExecutionResult(
            ok: true,
            entityType: 'dyn_html_report',
            entityId: $id !== '' ? $id : $slug,
            entityLabel: $name,
            meta: $meta,
            href: $slug !== '' ? '/dynamic-entities/html-reports/'.$slug : '/dynamic-entities/html-reports',
        );
    }

    private function wantsDashboardPin(string $question): bool
    {
        $q = mb_strtolower($question);

        return str_contains($q, 'dashboard')
            || str_contains($q, 'pin to home')
            || str_contains($q, 'add to dashboard')
            || str_contains($q, 'workspace dashboard');
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $fresh
     * @return array<string, mixed>
     */
    private function mergeRefineDefinition(array $base, array $fresh, string $question): array
    {
        $merged = $base;
        foreach (['format', 'group_by', 'group_by_2', 'matrix_column', 'chart', 'metric', 'metric_field', 'title', 'caption', 'row_limit', 'filters'] as $key) {
            if (array_key_exists($key, $fresh) && $fresh[$key] !== null && $fresh[$key] !== '') {
                $merged[$key] = $fresh[$key];
            }
        }

        $q = mb_strtolower($question);
        if (preg_match('/\bbar\s+chart\b|\bwith\s+bar\b|\bconfirm\s+with\s+bar\b/', $q) === 1) {
            $merged['chart'] = 'bar';
            if (($merged['format'] ?? '') === 'detail' && trim((string) ($merged['group_by'] ?? '')) === '') {
                $merged['format'] = 'summary';
                $merged['group_by'] = 'status';
            }
        } elseif (preg_match('/\bpie\s+chart\b/', $q) === 1) {
            $merged['chart'] = 'pie';
        } elseif (preg_match('/\bline\s+chart\b/', $q) === 1) {
            $merged['chart'] = 'line';
        }

        if (is_array($fresh['filters'] ?? null) && $fresh['filters'] !== []) {
            $merged['filters'] = $fresh['filters'];
        }

        return $merged;
    }
}
