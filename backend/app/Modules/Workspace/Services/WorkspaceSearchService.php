<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Services;

use App\Models\TicketingTicket;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tenant-wide command-palette entity search.
 *
 * Uses lean LIMIT queries (no COUNT / LengthAwarePaginator) so each keystroke
 * does not scan every registry with a full pagination count.
 */
final class WorkspaceSearchService
{
    private const MIN_QUERY_LENGTH = 2;

    private const MAX_TOTAL_RESULTS = 30;

    private const DEFAULT_LIMIT_PER_TYPE = 4;

    public function __construct(
        private readonly TenantEnabledModulesResolver $enabledModules,
    ) {}

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   status_label: string|null,
     *   current_step: int|null,
     *   waiting_on: string|null,
     *   href: string
     * }>
     */
    public function search(TenantUser $viewer, string $query, int $limitPerType = self::DEFAULT_LIMIT_PER_TYPE): array
    {
        $search = trim($query);
        if (mb_strlen($search) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        $limitPerType = max(1, min(10, $limitPerType));
        $enabled = $this->enabledModules->resolveForCurrentTenant();
        $like = $this->like($search);
        $results = [];

        /** @var list<callable(): list<array<string, mixed>>> $providers */
        $providers = [];

        if ($this->canSearchModule($enabled, $viewer, 'e_approval', 'e_approval:submissions:view')) {
            $providers[] = fn (): array => $this->searchEApprovalSubmissions($viewer, $search, $like, $limitPerType);
        }

        if ($this->canSearchEApprovalForms($enabled, $viewer)) {
            $providers[] = fn (): array => $this->searchEApprovalForms($viewer, $like, $limitPerType);
        }
        if ($this->canSearchModule($enabled, $viewer, 'ticketing', 'ticketing:view')) {
            $providers[] = fn (): array => $this->searchTicketingTickets($viewer, $search, $like, $limitPerType);
        }

        if ($this->canSearchModule($enabled, $viewer, 'dynamic_entities', 'dynamic_entities:view')) {
            $providers[] = fn (): array => $this->searchDynamicRecords($like, $limitPerType);
        }

        if ($this->canSearchModule($enabled, $viewer, 'team_access', 'user:manage')) {
            $providers[] = fn (): array => $this->searchUsers($like, $limitPerType);
        }

        foreach ($providers as $provider) {
            $remaining = self::MAX_TOTAL_RESULTS - count($results);
            if ($remaining <= 0) {
                break;
            }

            $chunk = $provider();
            if ($chunk === []) {
                continue;
            }

            $results = array_merge($results, array_slice($chunk, 0, min($limitPerType, $remaining)));
        }

        return $results;
    }

    /**
     * @param  list<string>  $enabled
     */
    private function canSearchModule(array $enabled, TenantUser $viewer, string $module, string $permission): bool
    {
        if (! in_array($module, $enabled, true)) {
            return false;
        }

        return $viewer->can($permission);
    }

    /**
     * @param  list<string>  $enabled
     */
    private function canSearchEApprovalForms(array $enabled, TenantUser $viewer): bool
    {
        if (! in_array('e_approval', $enabled, true)) {
            return false;
        }

        return $viewer->can('e_approval:forms:manage')
            || $viewer->can('e_approval:submissions:create')
            || $viewer->can('e_approval:view');
    }

    private function like(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   status_label: string|null,
     *   current_step: int|null,
     *   waiting_on: string|null,
     *   href: string
     * }>
     */
    private function searchEApprovalSubmissions(
        TenantUser $viewer,
        string $search,
        string $like,
        int $limit,
    ): array {
        $canViewAll = $viewer->can('e_approval:forms:manage');
        $statusMatches = EApprovalSubmissionStatus::statusesMatching($search);

        $query = EApprovalSubmission::query()
            ->select(['id', 'document_no', 'status', 'form_id', 'requestor_id', 'current_step', 'approval_cycle'])
            ->with([
                'form:id,name',
                'requestor:id,name,email',
                'approvals' => static function ($approvals): void {
                    $approvals
                        ->select(['id', 'submission_id', 'step_id', 'approver_id', 'status', 'approval_cycle'])
                        ->where('status', EApprovalApprovalStatus::PENDING)
                        ->with([
                            'approver:id,name',
                            'step:id,step_order',
                        ]);
                },
            ]);

        if (! $canViewAll) {
            $query->where(static function (Builder $q) use ($viewer): void {
                $q->where('requestor_id', $viewer->id)
                    ->orWhereIn('id', EApprovalRequestApproval::query()
                        ->where('approver_id', $viewer->id)
                        ->select('submission_id'));
            });
        }

        $query->where(static function (Builder $q) use ($like, $statusMatches): void {
            $q->where('document_no', 'like', $like)
                ->orWhereIn('form_id', EApprovalForm::query()
                    ->select('id')
                    ->where('name', 'like', $like))
                ->orWhereIn('requestor_id', TenantUser::query()
                    ->select('id')
                    ->where(static function (Builder $userQuery) use ($like): void {
                        $userQuery->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    }));

            if ($statusMatches !== []) {
                $q->orWhereIn('status', $statusMatches);
            }
        });

        return $this->mapRows(
            $query->orderByDesc('created_at')->limit($limit)->get(),
            function (EApprovalSubmission $submission): array {
                return $this->mapEApprovalSubmissionSearchResult($submission);
            },
        );
    }

    /**
     * @return array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   status_label: string|null,
     *   current_step: int|null,
     *   waiting_on: string|null,
     *   href: string
     * }
     */
    private function mapEApprovalSubmissionSearchResult(EApprovalSubmission $submission): array
    {
        $formName = $submission->form?->name;
        $requestorName = $submission->requestor?->name;
        $rawStatus = is_string($submission->status) ? $submission->status : null;
        $currentStep = max(0, (int) ($submission->current_step ?: 0));
        $waitingOn = $this->eApprovalWaitingOnLabel($submission);

        $subtitleParts = array_values(array_filter([
            is_string($formName) && $formName !== '' ? $formName : null,
            is_string($requestorName) && $requestorName !== '' ? $requestorName : null,
            $currentStep > 0 ? 'Step '.$currentStep : null,
            $waitingOn !== null ? 'Waiting on '.$waitingOn : null,
        ]));

        return [
            'module' => 'e_approval',
            'entity_type' => 'submission',
            'id' => (string) $submission->id,
            'title' => (string) $submission->document_no,
            'subtitle' => $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null,
            'status' => $rawStatus,
            'status_label' => $rawStatus !== null ? EApprovalSubmissionStatus::label($rawStatus) : null,
            'current_step' => $currentStep > 0 ? $currentStep : null,
            'waiting_on' => $waitingOn,
            'href' => '/e-approval/submissions/'.$submission->id,
        ];
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   status_label: string|null,
     *   current_step: int|null,
     *   waiting_on: string|null,
     *   href: string
     * }>
     */
    private function searchEApprovalForms(TenantUser $viewer, string $like, int $limit): array
    {
        $canManage = $viewer->can('e_approval:forms:manage');

        $query = EApprovalForm::query()
            ->select(['id', 'name', 'description', 'category', 'status', 'accepts_new_submissions'])
            ->where('status', 'published')
            ->where(static function (Builder $q): void {
                $q->where('accepts_new_submissions', true)->orWhereNull('accepts_new_submissions');
            })
            ->where(static function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit);

        return $this->mapRows(
            $query->get(),
            static function (EApprovalForm $form) use ($canManage): array {
                $category = is_string($form->category) ? trim($form->category) : '';
                $description = is_string($form->description) ? trim($form->description) : '';
                if (mb_strlen($description) > 80) {
                    $description = rtrim(mb_substr($description, 0, 77)).'…';
                }

                $subtitleParts = array_values(array_filter([
                    $category !== '' ? $category : null,
                    $description !== '' ? $description : null,
                    $canManage ? 'Open form designer' : 'Start a request',
                ]));

                $href = $canManage
                    ? '/e-approval/forms/'.$form->id
                    : '/e-approval/request/'.$form->id;

                return [
                    'module' => 'e_approval',
                    'entity_type' => 'form',
                    'id' => (string) $form->id,
                    'title' => (string) $form->name,
                    'subtitle' => $subtitleParts !== [] ? implode(' · ', $subtitleParts) : null,
                    'status' => 'published',
                    'status_label' => 'Published',
                    'current_step' => null,
                    'waiting_on' => null,
                    'href' => $href,
                ];
            },
        );
    }

    private function eApprovalWaitingOnLabel(EApprovalSubmission $submission): ?string
    {
        $status = strtolower(trim((string) $submission->status));

        if ($status === EApprovalSubmissionStatus::AWAITING_DCF) {
            return 'document control';
        }

        if ($status !== EApprovalSubmissionStatus::PENDING) {
            return null;
        }

        $currentStep = max(0, (int) ($submission->current_step ?: 0));
        $cycle = max(1, (int) ($submission->approval_cycle ?: 1));

        $names = $submission->approvals
            ->filter(static function (EApprovalRequestApproval $approval) use ($currentStep, $cycle): bool {
                if ((string) $approval->status !== EApprovalApprovalStatus::PENDING) {
                    return false;
                }

                $approvalCycle = (int) ($approval->approval_cycle ?: 1);
                if ($approvalCycle !== $cycle) {
                    return false;
                }

                if ($currentStep <= 0) {
                    return true;
                }

                return (int) ($approval->step?->step_order ?: 0) === $currentStep;
            })
            ->map(static function (EApprovalRequestApproval $approval): string {
                return trim((string) ($approval->approver?->name ?? ''));
            })
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        $maxNamed = 3;
        if ($names->count() <= $maxNamed) {
            return $names->implode(', ');
        }

        $shown = $names->take($maxNamed)->implode(', ');
        $extra = $names->count() - $maxNamed;

        return $shown.' +'.$extra.' more';
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchTicketingTickets(TenantUser $viewer, string $search, string $like, int $limit): array
    {
        $canManage = $viewer->can('ticketing:tickets:manage');
        $ticketNeedle = ltrim($search, 'TKT-tkt-');

        $query = TicketingTicket::query()
            ->select(['id', 'ticket_number', 'title', 'status', 'assignee_id', 'requester_id'])
            ->with(['assignee:id,name']);

        if (! $canManage) {
            $query->where('requester_id', $viewer->id);
        }

        $query->where(static function (Builder $inner) use ($like, $ticketNeedle): void {
            $inner->where('title', 'like', $like)
                ->orWhere('ticket_number', 'like', '%'.addcslashes($ticketNeedle, '%_\\').'%');
        });

        return $this->mapRows(
            $query->orderByDesc('updated_at')->limit($limit)->get(),
            static function (TicketingTicket $ticket): array {
                return [
                    'module' => 'ticketing',
                    'entity_type' => 'ticket',
                    'id' => (string) $ticket->id,
                    'title' => trim($ticket->displayNumber().' · '.$ticket->title),
                    'subtitle' => $ticket->assignee?->name ? 'Assignee: '.$ticket->assignee->name : null,
                    'status' => (string) $ticket->status,
                    'href' => '/ticketing/tickets/'.$ticket->id,
                ];
            },
        );
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchSites(string $like, int $limit): array
    {
        return [];
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchTowers(string $like, int $limit): array
    {
        return [];
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchAssets(string $like, int $limit): array
    {
        return [];
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchFiberRoutes(string $like, int $limit): array
    {
        return [];
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchProjects(string $like, int $limit): array
    {
        return [];
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchRollouts(string $like, int $limit): array
    {
        return [];
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchDynamicRecords(string $like, int $limit): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('dyn_records')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        $rows = \App\Modules\DynamicEntities\Models\DynRecord::query()
            ->with(['entity:id,slug,name'])
            ->where('is_deleted', false)
            ->where(static function (Builder $q) use ($like): void {
                $q->where('title', 'like', $like)
                    ->orWhere('source_external_id', 'like', $like)
                    ->orWhere('status', 'like', $like);
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $record) {
            $entity = $record->entity;
            $slug = $entity ? (string) $entity->slug : '';
            $entityName = $entity ? (string) $entity->name : 'Record';
            $title = trim((string) ($record->title ?? ''));
            if ($title === '') {
                $title = (string) $record->id;
            }
            $out[] = [
                'module' => 'dynamic_entities',
                'entity_type' => 'record',
                'id' => (string) $record->id,
                'title' => $title,
                'subtitle' => $entityName.($record->status ? ' · '.(string) $record->status : ''),
                'status' => $record->status ? (string) $record->status : null,
                'href' => '/dynamic-entities/records/'.rawurlencode((string) $record->id),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function searchUsers(string $like, int $limit): array
    {
        return $this->mapRows(
            TenantUser::query()
                ->select(['id', 'name', 'email', 'is_active'])
                ->where(static function (Builder $q) use ($like): void {
                    $q->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->orderBy('name')
                ->limit($limit)
                ->get(),
            static function (TenantUser $user): array {
                return [
                    'module' => 'team_access',
                    'entity_type' => 'user',
                    'id' => (string) $user->id,
                    'title' => (string) $user->name,
                    'subtitle' => (string) $user->email,
                    'status' => $user->isActive() ? 'active' : 'inactive',
                    'href' => '/users?search='.rawurlencode((string) $user->email),
                ];
            },
        );
    }

    /**
     * @template TModel of object
     *
     * @param  Collection<int, TModel>  $rows
     * @param  callable(TModel): array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }  $mapper
     * @return list<array{
     *   module: string,
     *   entity_type: string,
     *   id: string,
     *   title: string,
     *   subtitle: string|null,
     *   status: string|null,
     *   href: string
     * }>
     */
    private function mapRows(Collection $rows, callable $mapper): array
    {
        return $rows
            ->map(static fn ($model) => $mapper($model))
            ->values()
            ->all();
    }
}
