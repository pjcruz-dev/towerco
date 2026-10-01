<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\UserUiPreferenceService;
use Illuminate\Validation\ValidationException;

/**
 * Pin a saved HTML report onto the tenant workspace home dashboard layout prefs.
 */
final class PinHtmlReportToDashboardAction implements AssistantActionInterface
{
    public const LAYOUT_STORAGE_KEY = 'toweros.workspace.dashboard.layout';

    public const CATALOG_WIDGET_ID = 'dyn_html_report';

    public function __construct(
        private readonly UserUiPreferenceService $preferences,
    ) {}

    public function name(): string
    {
        return 'pin_html_report_to_dashboard';
    }

    public function description(): string
    {
        return 'Pin a saved Dynamic Entities HTML report widget onto the home dashboard.';
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
            'report_slug' => ['required', 'string', 'max:160'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $slug = isset($args['report_slug']) && is_string($args['report_slug'])
            ? trim($args['report_slug'])
            : $this->extractSlug($question);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'report_slug' => ['Provide the HTML report slug to pin.'],
            ]);
        }

        $report = DynHtmlReport::query()->where('slug', $slug)->first();
        if ($report === null) {
            throw ValidationException::withMessages([
                'report_slug' => ['HTML report “'.$slug.'” was not found.'],
            ]);
        }

        $title = isset($args['title']) && is_string($args['title']) && trim($args['title']) !== ''
            ? trim($args['title'])
            : (string) $report->name;

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Pin report to dashboard',
            summary: 'Add “'.$title.'” as a widget on your home dashboard. Nothing changes until you confirm.',
            payload: [
                'report_slug' => $slug,
                'title' => $title,
            ],
            preview: [
                'report_slug' => $slug,
                'title' => $title,
                'href' => '/dynamic-entities/html-reports/'.$slug,
            ],
            editableFields: [
                ['key' => 'report_slug', 'label' => 'Report slug', 'type' => 'text', 'required' => true],
                ['key' => 'title', 'label' => 'Widget title', 'type' => 'text', 'required' => false],
            ],
            moduleKey: 'dynamic_entities',
            confirmLabel: 'Pin to dashboard',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $slug = trim((string) ($payload['report_slug'] ?? ''));
        if ($slug === '') {
            throw ValidationException::withMessages([
                'report_slug' => ['Report slug is required.'],
            ]);
        }

        $report = DynHtmlReport::query()->where('slug', $slug)->first();
        if ($report === null) {
            throw ValidationException::withMessages([
                'report_slug' => ['HTML report “'.$slug.'” was not found.'],
            ]);
        }

        $title = isset($payload['title']) && is_string($payload['title']) && trim($payload['title']) !== ''
            ? trim($payload['title'])
            : (string) $report->name;

        $prefKey = 'dashboard-layout.'.self::LAYOUT_STORAGE_KEY;
        $current = $this->preferences->get($viewer, $prefKey) ?? [];
        $next = $this->mergeLayout($current, $slug, $title);
        $this->preferences->put($viewer, $prefKey, $next);

        return new ActionExecutionResult(
            ok: true,
            entityType: 'dashboard_widget',
            entityId: self::CATALOG_WIDGET_ID.'~'.$slug,
            entityLabel: $title,
            meta: [
                'report_slug' => $slug,
                'widget_id' => self::CATALOG_WIDGET_ID.'~'.$slug,
                'layout_key' => self::LAYOUT_STORAGE_KEY,
            ],
            href: '/dashboard',
        );
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    public function mergeLayout(array $layout, string $slug, string $title): array
    {
        $widgetId = self::CATALOG_WIDGET_ID.'~'.$slug;

        $enabled = array_values(array_filter(
            is_array($layout['enabledWidgetIds'] ?? null) ? $layout['enabledWidgetIds'] : [],
            static fn ($id): bool => is_string($id) && $id !== '',
        ));
        $order = array_values(array_filter(
            is_array($layout['widgetOrder'] ?? null) ? $layout['widgetOrder'] : [],
            static fn ($id): bool => is_string($id) && $id !== '',
        ));
        $hidden = array_values(array_filter(
            is_array($layout['hiddenWidgetIds'] ?? null) ? $layout['hiddenWidgetIds'] : [],
            static fn ($id): bool => is_string($id) && $id !== '',
        ));
        $spans = is_array($layout['spans'] ?? null) ? $layout['spans'] : [];
        $options = is_array($layout['widgetOptions'] ?? null) ? $layout['widgetOptions'] : [];

        if (! in_array($widgetId, $enabled, true)) {
            $enabled[] = $widgetId;
        }
        if (! in_array($widgetId, $order, true)) {
            array_unshift($order, $widgetId);
        }
        $hidden = array_values(array_filter($hidden, static fn (string $id): bool => $id !== $widgetId));

        $spans[$widgetId] = 'half';
        $options[$widgetId] = [
            'title' => $title,
            'span' => 'half',
            'settings' => [
                'reportSlug' => $slug,
                'reportHref' => '/dynamic-entities/html-reports/'.$slug,
                'showDescription' => true,
            ],
        ];

        return [
            'widgetOrder' => $order,
            'hiddenWidgetIds' => $hidden,
            'enabledWidgetIds' => $enabled,
            'spans' => $spans,
            'widgetOptions' => $options,
            'pageChrome' => is_array($layout['pageChrome'] ?? null) ? $layout['pageChrome'] : [],
        ];
    }

    private function extractSlug(string $question): string
    {
        if (preg_match('/\b([a-z0-9][a-z0-9_-]{1,120})\b/i', $question, $m) === 1) {
            $candidate = strtolower($m[1]);
            if (! in_array($candidate, ['pin', 'report', 'dashboard', 'html', 'the', 'to', 'a'], true)) {
                return $candidate;
            }
        }

        return '';
    }
}
