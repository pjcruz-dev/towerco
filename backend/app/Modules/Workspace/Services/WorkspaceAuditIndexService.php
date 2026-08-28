<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Models\TenantActivityLog;
use App\Modules\Workspace\Support\WorkspaceAuditActionLabel;
use App\Modules\Workspace\Support\WorkspaceAuditChanges;
use App\Modules\Workspace\Support\WorkspaceAuditTaxonomy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class WorkspaceAuditIndexService
{
    private const EXPORT_FETCH_CAP = 5000;

    private const SORTABLE = [
        'created_at',
        'module',
        'action',
        'category',
        'severity',
    ];

    /**
     * @param  array{
     *   module?: ?string,
     *   search?: ?string,
     *   from?: ?string,
     *   to?: ?string,
     *   sort?: ?string,
     *   actor?: ?string,
     *   category?: ?string,
     *   severity?: ?string,
     *   action_family?: ?string,
     *   entity_type?: ?string,
     *   entity_id?: ?string
     * }  $filters
     */
    public function paginate(
        TenantUser $viewer,
        int $page,
        int $perPage,
        array $filters = [],
    ): LengthAwarePaginator {
        unset($viewer);

        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        [$fromBound, $toBound] = $this->normalizeDateBounds($filters['from'] ?? null, $filters['to'] ?? null);

        $query = TenantActivityLog::query()->with('actor:id,name,email');
        $this->applyWorkspaceFilters($query, $filters, $fromBound, $toBound);

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($filters['sort'] ?? 'created_at:desc'),
            self::SORTABLE,
            'created_at',
            'desc',
        );
        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (TenantActivityLog $log): array => $this->mapWorkspaceLog($log));
    }

    /**
     * @param  array{
     *   module?: ?string,
     *   search?: ?string,
     *   from?: ?string,
     *   to?: ?string,
     *   actor?: ?string,
     *   category?: ?string,
     *   severity?: ?string,
     *   action_family?: ?string,
     *   entity_type?: ?string,
     *   entity_id?: ?string
     * }  $filters
     * @return list<array<string, mixed>>
     */
    public function forExport(TenantUser $viewer, array $filters = []): array
    {
        unset($viewer);
        [$fromBound, $toBound] = $this->normalizeDateBounds($filters['from'] ?? null, $filters['to'] ?? null);

        $query = TenantActivityLog::query()
            ->with('actor:id,name,email')
            ->orderByDesc('created_at')
            ->limit(self::EXPORT_FETCH_CAP);

        $this->applyWorkspaceFilters($query, $filters, $fromBound, $toBound);

        return $query->get()
            ->map(fn (TenantActivityLog $log): array => $this->mapWorkspaceLog($log))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forEntity(string $entityType, string $entityId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        return TenantActivityLog::query()
            ->with('actor:id,name,email')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (TenantActivityLog $log): array => $this->mapWorkspaceLog($log))
            ->all();
    }

    public function pruneOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        return TenantActivityLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()
            ->map(static fn (array $row): array => $row)
            ->values()
            ->all();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function normalizeDateBounds(?string $from, ?string $to): array
    {
        $fromBound = null;
        $toBound = null;

        if ($from !== null && $from !== '') {
            $fromBound = Carbon::parse($from)->startOfDay()->toDateTimeString();
        }

        if ($to !== null && $to !== '') {
            $toBound = Carbon::parse($to)->endOfDay()->toDateTimeString();
        }

        return [$fromBound, $toBound];
    }

    /**
     * @param  Builder<TenantActivityLog>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyWorkspaceFilters(
        Builder $query,
        array $filters,
        ?string $from,
        ?string $to,
    ): void {
        $module = isset($filters['module']) ? (string) $filters['module'] : null;
        $search = isset($filters['search']) ? (string) $filters['search'] : null;
        $actor = isset($filters['actor']) ? (string) $filters['actor'] : null;
        $category = WorkspaceAuditTaxonomy::normalizeCategory(
            isset($filters['category']) ? (string) $filters['category'] : null,
        );
        $severity = WorkspaceAuditTaxonomy::normalizeSeverity(
            isset($filters['severity']) ? (string) $filters['severity'] : null,
        );
        $actionFamily = isset($filters['action_family']) ? trim((string) $filters['action_family']) : '';
        $entityType = isset($filters['entity_type']) ? trim((string) $filters['entity_type']) : '';
        $entityId = isset($filters['entity_id']) ? trim((string) $filters['entity_id']) : '';

        if ($module !== null && $module !== '' && $module !== 'all') {
            $query->where('module', $module);
        }

        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', $to);
        }

        if ($category !== null) {
            $query->where('category', $category);
        }

        if ($severity !== null) {
            $query->where('severity', $severity);
        }

        if ($actionFamily !== '' && $actionFamily !== 'all') {
            $family = addcslashes($actionFamily, '%_\\');
            $query->where(static function ($inner) use ($family, $actionFamily): void {
                $inner->where('action', 'like', $family.'.%')
                    ->orWhere('action', 'like', $family.'_%')
                    ->orWhere('action', '=', $actionFamily);
            });
        }

        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        if ($entityId !== '') {
            $query->where('entity_id', $entityId);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($inner) use ($like): void {
                $inner->where('action', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('reason', 'like', $like)
                    ->orWhere('entity_label', 'like', $like)
                    ->orWhere('entity_id', 'like', $like)
                    ->orWhereHas('actor', static fn ($actorQuery) => $actorQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like));
            });
        }

        if ($actor !== null && $actor !== '') {
            $like = '%'.addcslashes($actor, '%_\\').'%';
            $query->whereHas('actor', static fn ($actorQuery) => $actorQuery
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapWorkspaceLog(TenantActivityLog $log): array
    {
        $classified = WorkspaceAuditTaxonomy::classify((string) $log->module, (string) $log->action);

        return $this->enrichRow([
            'id' => (string) $log->id,
            'source' => 'workspace',
            'module' => $log->module,
            'action' => $log->action,
            'category' => $log->category ?: $classified['category'],
            'severity' => $log->severity ?: $classified['severity'],
            'action_family' => WorkspaceAuditTaxonomy::actionFamily((string) $log->action),
            'summary' => $log->summary,
            'reason' => $log->reason,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'entity_label' => $log->entity_label,
            'actor' => $log->actor ? [
                'id' => (string) $log->actor->id,
                'name' => $log->actor->name,
                'email' => $log->actor->email,
            ] : null,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent ?? null,
            'metadata' => $log->metadata_json,
            'created_at' => $log->created_at?->toIso8601String(),
            'href' => $this->hrefFor($log->module, $log->entity_type, $log->entity_id),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichRow(array $row): array
    {
        $action = (string) ($row['action'] ?? '');
        $row['action_label'] = WorkspaceAuditActionLabel::label($action);

        $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : null;
        $changes = WorkspaceAuditChanges::extractFromMetadata($metadata);
        $row['changes'] = $changes;

        if ($changes !== null && is_array($metadata)) {
            $metadataWithoutChanges = $metadata;
            unset($metadataWithoutChanges['changes']);
            $row['metadata'] = $metadataWithoutChanges === [] ? null : $metadataWithoutChanges;
        }

        return $row;
    }

    private function hrefFor(?string $module, ?string $entityType, ?string $entityId): ?string
    {
        if ($entityId === null || $entityId === '') {
            return null;
        }

        return match ($module) {
            'e_approval' => match ($entityType) {
                'form' => '/e-approval/forms/'.$entityId,
                default => '/e-approval/submissions/'.$entityId,
            },
            'documents' => match ($entityType) {
                'controlled_document' => '/documents/controlled?document='.$entityId,
                'e_approval_form' => '/e-approval/forms/'.$entityId,
                default => '/documents',
            },
            'procurement_one' => match ($entityType) {
                'pr', 'purchase_requisition' => '/procurement/prs/'.$entityId,
                'po', 'purchase_order' => '/procurement/pos/'.$entityId,
                'request_for_quotation' => '/procurement/rfqs/'.$entityId,
                'vendor_contract' => '/procurement/contracts/'.$entityId,
                'ap_invoice' => '/procurement/ap-invoices/'.$entityId,
                'goods_receipt' => '/procurement/grns/'.$entityId,
                'payment_request' => '/procurement/payment-requests/'.$entityId,
                'payment_batch' => '/procurement/payment-batches/'.$entityId,
                default => '/procurement',
            },
            'project_one' => match ($entityType) {
                'rollout' => '/project-one/rollouts/'.$entityId,
                default => '/project-one/rollouts',
            },
            'ticketing' => match ($entityType) {
                'ticket' => '/ticketing/tickets/'.$entityId,
                default => '/ticketing/tickets',
            },
            'team_access' => match ($entityType) {
                'user' => '/users',
                'role' => '/roles',
                default => '/users',
            },
            default => null,
        };
    }
}
