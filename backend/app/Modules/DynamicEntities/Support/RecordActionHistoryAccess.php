<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\Request;

final class RecordActionHistoryAccess
{
    public const ASSET_SLUG = 'it_asset';

    /** Tickets stored as dynamic records. */
    public const TICKETING_SLUGS = ['helpdesk_ticket', 'helpdesk_ticket_activities', 'site_tickets'];

    public static function slug(string $entity): string
    {
        $model = DynEntity::query()
            ->where(function ($query) use ($entity): void {
                $query->where('id', $entity)->orWhere('slug', $entity);
            })
            ->first();

        return $model instanceof DynEntity ? (string) $model->slug : $entity;
    }

    public static function assertView(Request $request, string $slug): void
    {
        $user = $request->user();
        if ($slug === self::ASSET_SLUG) {
            abort_unless($user?->can('assets:view') || $user?->can('dynamic_entities:view'), 403);

            return;
        }
        abort_unless($user?->can('dynamic_entities:view'), 403);
    }

    public static function assertRevert(Request $request, string $slug): void
    {
        $user = $request->user();
        if ($slug === self::ASSET_SLUG) {
            abort_unless($user?->can('assets:manage'), 403);
        } else {
            abort_unless($user?->can('dynamic_entities:records:manage'), 403);
        }
        if ($user instanceof TenantUser) {
            $allowed = RoleAccessMatrix::entityAllows(
                app(DynRoleAccessService::class)->matrixFor($user),
                $slug,
                'undo',
            );
            abort_unless($allowed ?? true, 403, __('Your role does not allow undo for this entity.'));
        }
    }

    public static function actor(Request $request): TenantUser
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser, 403);

        return $user;
    }
}
