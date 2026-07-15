<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;

final class ProcurementDocumentScopeService
{
    public function canManageDocuments(TenantUser $user): bool
    {
        return $user->can('procurement_one:documents:manage');
    }

    /**
     * When null, procurement admins may list all tenant documents.
     */
    public function requestorIdForIndex(TenantUser $user, bool $mine): ?string
    {
        if ($mine || ! $this->canManageDocuments($user)) {
            return (string) $user->id;
        }

        return null;
    }

    public function assertCanView(TenantUser $user, string $documentRequestorId): void
    {
        if ($this->canManageDocuments($user)) {
            return;
        }

        if ((string) $documentRequestorId !== (string) $user->id) {
            abort(403, __('You do not have access to this document.'));
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyRequestorScope(Builder $query, TenantUser $user, string $column = 'requestor_id'): Builder
    {
        if ($this->canManageDocuments($user)) {
            return $query;
        }

        return $query->where($column, (string) $user->id);
    }
}
