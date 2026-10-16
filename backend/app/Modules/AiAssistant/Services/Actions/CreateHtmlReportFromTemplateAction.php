<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services\Actions;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\DTOs\ActionExecutionResult;
use App\Modules\AiAssistant\DTOs\ActionProposalDraft;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Services\DynHtmlReportService;
use App\Modules\DynamicEntities\Support\DynHtmlReportTemplateCatalog;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Install a catalog HTML/CSS/JS template as a Manage HTML Report. Saves only after confirm.
 */
final class CreateHtmlReportFromTemplateAction implements AssistantActionInterface
{
    public function __construct(
        private readonly DynHtmlReportService $reports,
    ) {}

    public function name(): string
    {
        return 'create_html_report_from_template';
    }

    public function description(): string
    {
        $keys = array_map(
            static fn (array $definition): string => $definition['key'],
            DynHtmlReportTemplateCatalog::definitions(),
        );

        return 'Install a named HTML report template ('.implode(', ', $keys).'). Use this when the user asks to create one of those dashboards. Do not use Report Builder for them. Saves only after confirmation.';
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
            'template_key' => ['required', 'string', 'max:80'],
            'name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255'],
        ];
    }

    public function propose(TenantUser $viewer, string $question, array $args = []): ActionProposalDraft
    {
        $key = isset($args['template_key']) && is_string($args['template_key'])
            ? trim($args['template_key'])
            : '';
        $template = $key !== ''
            ? DynHtmlReportTemplateCatalog::find($key)
            : DynHtmlReportTemplateCatalog::match($question);
        if ($template === null) {
            throw ValidationException::withMessages([
                'template_key' => ['No HTML report template matches that request.'],
            ]);
        }

        $name = isset($args['name']) && is_string($args['name']) && trim($args['name']) !== ''
            ? trim($args['name'])
            : $template['name'];

        return new ActionProposalDraft(
            action: $this->name(),
            title: 'Create HTML report',
            summary: 'I will save "'.$name.'" from the '.$template['name'].' template. The HTML, CSS, and JavaScript are that example. Nothing is saved until you confirm.',
            payload: [
                'template_key' => $template['key'],
                'name' => $name,
            ],
            preview: [
                'template_key' => $template['key'],
                'name' => $name,
                'description' => $template['description'],
                'output' => $template['output'],
            ],
            editableFields: [
                ['key' => 'name', 'label' => 'Report title', 'type' => 'text', 'required' => true],
            ],
            moduleKey: 'dynamic_entities',
            confirmLabel: 'Save report',
        );
    }

    public function execute(TenantUser $viewer, array $payload): ActionExecutionResult
    {
        $key = trim((string) ($payload['template_key'] ?? ''));
        $template = DynHtmlReportTemplateCatalog::find($key);
        if ($template === null) {
            throw ValidationException::withMessages([
                'template_key' => ['Unknown HTML report template.'],
            ]);
        }

        $sources = DynHtmlReportTemplateCatalog::sources($key);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = $template['name'];
        }

        $sameTitle = Str::lower($name) === Str::lower($template['name']);
        $existing = $sameTitle
            ? DynHtmlReport::query()->where('slug', $template['slug'])->first()
            : null;

        if ($existing instanceof DynHtmlReport) {
            $saved = $this->reports->update($existing, [
                'name' => $name,
                'description' => $template['description'],
                'html_source' => $sources['html'],
                'css_source' => $sources['css'],
                'js_source' => $sources['js'],
                'builder_json' => null,
            ], $viewer);
        } else {
            $saved = $this->reports->create([
                'name' => $name,
                'slug' => $sameTitle ? $template['slug'] : null,
                'description' => $template['description'],
                'html_source' => $sources['html'],
                'css_source' => $sources['css'],
                'js_source' => $sources['js'],
                'builder_json' => null,
            ], $viewer);
        }

        $slug = (string) ($saved['slug'] ?? '');
        $id = (string) ($saved['id'] ?? '');

        return new ActionExecutionResult(
            ok: true,
            entityType: 'dyn_html_report',
            entityId: $id !== '' ? $id : $slug,
            entityLabel: $name,
            meta: [
                'slug' => $slug,
                'template_key' => $key,
            ],
            href: $slug !== '' ? '/dynamic-entities/html-reports/'.$slug : '/dynamic-entities/html-reports',
        );
    }
}
