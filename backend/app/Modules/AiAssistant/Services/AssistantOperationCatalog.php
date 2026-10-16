<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Services;

use App\Modules\AiAssistant\Contracts\AssistantActionInterface;
use App\Modules\AiAssistant\Contracts\AssistantToolInterface;
use App\Modules\AiAssistant\Services\Actions\AssistantActionRegistry;
use App\Modules\AiAssistant\Services\Tools\AssistantToolRegistry;
use App\Modules\AiAssistant\Support\AssistantRolePolicy;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;

/**
 * Operations this admin can run on this tenant. Built at ask time from the registries.
 */
final class AssistantOperationCatalog
{
    public function __construct(
        private readonly AssistantToolRegistry $tools,
        private readonly AssistantActionRegistry $actions,
        private readonly TenantEnabledModulesResolver $enabledModules,
        private readonly AssistantRolePolicy $rolePolicy,
    ) {}

    /**
     * @return list<array{kind: string, name: string, description: string, module: ?string, arguments: list<array{name: string, required: bool}>}>
     */
    public function forViewer(TenantUser $viewer): array
    {
        if (! $viewer->can('ai_assistant:tools:use') || $this->rolePolicy->blocksAssistant($viewer)) {
            return [];
        }

        $enabled = $this->enabledModules->resolveForCurrentTenant();
        $entries = [];

        foreach ($this->tools->all() as $tool) {
            $entry = $this->toolEntry($viewer, $tool, $enabled);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        foreach ($this->actions->all() as $action) {
            $entry = $this->actionEntry($viewer, $action, $enabled);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $enabled
     * @return array{kind: string, name: string, description: string, module: ?string, arguments: list<array{name: string, required: bool}>}|null
     */
    private function toolEntry(TenantUser $viewer, AssistantToolInterface $tool, array $enabled): ?array
    {
        if (! $this->moduleAllowed($tool->requiredModule(), $enabled)) {
            return null;
        }

        foreach ($tool->requiredPermissions() as $permission) {
            if (! $viewer->can($permission)) {
                return null;
            }
        }

        if (! $this->rolePolicy->allowsTool($viewer, $tool->name())) {
            return null;
        }

        return [
            'kind' => 'read',
            'name' => $tool->name(),
            'description' => $tool->description(),
            'module' => $tool->requiredModule(),
            'arguments' => $this->argumentSchema($tool->argumentRules()),
        ];
    }

    /**
     * @param  list<string>  $enabled
     * @return array{kind: string, name: string, description: string, module: ?string, arguments: list<array{name: string, required: bool}>}|null
     */
    private function actionEntry(TenantUser $viewer, AssistantActionInterface $action, array $enabled): ?array
    {
        if (! $this->moduleAllowed($action->requiredModule(), $enabled)) {
            return null;
        }

        foreach ($action->requiredDomainPermissions() as $permission) {
            if (! $viewer->can($permission)) {
                return null;
            }
        }

        if (! $this->rolePolicy->allowsAction($viewer, $action->name())) {
            return null;
        }

        $rules = method_exists($action, 'plannerArgumentRules')
            ? $action->plannerArgumentRules()
            : $action->argumentRules();

        return [
            'kind' => 'write',
            'name' => $action->name(),
            'description' => $action->description(),
            'module' => $action->requiredModule(),
            'arguments' => $this->argumentSchema(is_array($rules) ? $rules : []),
        ];
    }

    /**
     * @param  list<string>  $enabled
     */
    private function moduleAllowed(?string $module, array $enabled): bool
    {
        if ($module === null || $module === '') {
            return true;
        }

        return in_array($module, $enabled, true);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return list<array{name: string, required: bool}>
     */
    private function argumentSchema(array $rules): array
    {
        $fields = [];
        foreach ($rules as $name => $rule) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $flat = is_array($rule)
                ? implode('|', array_values(array_filter($rule, 'is_string')))
                : (string) $rule;

            $fields[] = [
                'name' => $name,
                'required' => preg_match('/(?:^|\|)required(?:\||$)/', $flat) === 1,
            ];
        }

        return $fields;
    }
}
