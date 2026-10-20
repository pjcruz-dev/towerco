<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantModule
{
    public function __construct(
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $enabled = $this->enabledModules->resolveForCurrentTenant();

        if (! in_array($module, $enabled, true)) {
            if ($this->assetFormUsesEntitySchema($module, $enabled, $request)) {
                return $next($request);
            }

            abort(Response::HTTP_FORBIDDEN, __('This module is not enabled for your workspace.'));
        }

        return $next($request);
    }

    /**
     * The asset form is the it_asset entity. Asset management can load and edit that schema
     * when the Dynamic Entities module itself is off.
     *
     * @param  list<string>  $enabled
     */
    private function assetFormUsesEntitySchema(string $module, array $enabled, Request $request): bool
    {
        if ($module !== 'dynamic_entities' || ! in_array('asset_management', $enabled, true)) {
            return false;
        }

        $route = $request->route();
        $name = $route?->getName();
        if (! is_string($name) || ! in_array($name, self::ASSET_SCHEMA_ROUTES, true)) {
            return false;
        }

        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return false;
        }

        return $this->requestTargetsItAsset($request);
    }

    private function requestTargetsItAsset(Request $request): bool
    {
        $entityKey = $request->route('entity');
        if (is_string($entityKey) && $entityKey !== '') {
            if ($entityKey === 'it_asset') {
                return true;
            }

            $entity = DynEntity::query()->whereKey($entityKey)->first();

            return $this->isItAsset($entity);
        }

        $fieldKey = $request->route('field');
        if (is_string($fieldKey) && $fieldKey !== '') {
            $field = DynField::query()->with('entity')->whereKey($fieldKey)->first();

            return $this->isItAsset($field?->entity);
        }

        $groupKey = $request->route('group');
        if (is_string($groupKey) && $groupKey !== '') {
            $group = DynFieldGroup::query()->with('entity')->whereKey($groupKey)->first();

            return $this->isItAsset($group?->entity);
        }

        return false;
    }

    private function isItAsset(?DynEntity $entity): bool
    {
        return $entity instanceof DynEntity
            && $entity->slug === 'it_asset'
            && $entity->source_linked_table === 'it_assets';
    }

    /** @var list<string> */
    private const ASSET_SCHEMA_ROUTES = [
        'api.tenant.v1.dynamic_entities.entities.show',
        'api.tenant.v1.dynamic_entities.fields.store',
        'api.tenant.v1.dynamic_entities.fields.update',
        'api.tenant.v1.dynamic_entities.fields.destroy',
        'api.tenant.v1.dynamic_entities.field_groups.store',
        'api.tenant.v1.dynamic_entities.field_groups.update',
        'api.tenant.v1.dynamic_entities.field_groups.destroy',
        'api.tenant.v1.dynamic_entities.action_history.index',
        'api.tenant.v1.dynamic_entities.action_history.undo',
        'api.tenant.v1.dynamic_entities.action_history.redo',
        'api.tenant.v1.dynamic_entities.action_history.revert',
        'api.tenant.v1.dynamic_entities.action_history.reapply',
    ];
}
