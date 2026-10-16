<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use Illuminate\Contracts\Auth\Authenticatable;

final class DynSchemaAccess
{
    public static function assertCanManage(?Authenticatable $user, DynEntity $entity): void
    {
        abort_unless(self::canManage($user, $entity), 403);
    }

    public static function assertCanManageField(?Authenticatable $user, DynField $field): void
    {
        $field->loadMissing('entity');
        $entity = $field->entity;
        abort_unless($entity instanceof DynEntity, 404);
        self::assertCanManage($user, $entity);
    }

    public static function assertCanManageGroup(?Authenticatable $user, DynFieldGroup $group): void
    {
        $group->loadMissing('entity');
        $entity = $group->entity;
        abort_unless($entity instanceof DynEntity, 404);
        self::assertCanManage($user, $entity);
    }

    public static function canView(?Authenticatable $user, DynEntity $entity): bool
    {
        if ($user?->can('dynamic_entities:view') || $user?->can('role:manage')) {
            return true;
        }

        if (self::isItAsset($entity) && (
            $user?->can('assets:view')
            || $user?->can('assets:manage')
            || $user?->can('assets:fields:manage')
            || $user?->can('assets:self:view')
        )) {
            return true;
        }

        return self::isHelpdeskFamily($entity) && (
            $user?->can('ticketing:view')
            || $user?->can('ticketing:tickets:create')
            || $user?->can('ticketing:tickets:manage')
            || $user?->can('ticketing:fields:manage')
        );
    }

    public static function allowsModule(?Authenticatable $user, DynEntity $entity, string $permission): bool
    {
        if ($user?->can($permission)) {
            return true;
        }

        if (! self::isHelpdeskFamily($entity)) {
            return false;
        }

        return match ($permission) {
            'dynamic_entities:view', 'dynamic_entities:records:manage' => $user?->can('ticketing:view')
                || $user?->can('ticketing:tickets:create')
                || $user?->can('ticketing:tickets:manage'),
            default => false,
        };
    }

    public static function canManage(?Authenticatable $user, DynEntity $entity): bool
    {
        if ($user?->can('dynamic_entities:fields:manage')) {
            return true;
        }

        if (self::isItAsset($entity) && $user?->can('assets:fields:manage')) {
            return true;
        }

        return $entity->slug === 'helpdesk_ticket' && $user?->can('ticketing:fields:manage');
    }

    private static function isItAsset(DynEntity $entity): bool
    {
        return $entity->slug === 'it_asset' && $entity->source_linked_table === 'it_assets';
    }

    public static function isHelpdeskFamily(DynEntity $entity): bool
    {
        return in_array($entity->slug, [
            'helpdesk_ticket',
            'helpdesk_ticket_activities',
            'workspace_users',
        ], true);
    }
}
