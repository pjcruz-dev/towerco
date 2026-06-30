<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SQL pre-filter for gate approval inboxes ("awaiting me").
 *
 * Mirrors {@see RolloutGateApproverResolver::canActOnStep()} without loading every open request.
 */
final class RolloutGateApprovalInboxScope
{
    public function viewerMayBypassInboxFilter(TenantUser $viewer): bool
    {
        return $viewer->can('project_one:rollout:gate:approve');
    }

    /**
     * @param  Builder<RolloutGateApprovalRequest>  $query
     */
    public function constrainAwaitingActor(Builder $query, TenantUser $viewer): void
    {
        if ($this->viewerMayBypassInboxFilter($viewer)) {
            return;
        }

        $table = $query->getModel()->getTable();
        $roleSql = $this->currentChainRoleExpression($table);
        $viewerId = (string) $viewer->id;
        $userModelType = TenantUser::class;

        $query->where(function (Builder $outer) use ($viewer, $viewerId, $roleSql, $table, $userModelType): void {
            $this->appendPermissionRoleMatches($outer, $viewer, $roleSql);
            $this->appendProgramOwnerMatches($outer, $viewerId, $roleSql, $table);
            $this->appendDelegationMatches($outer, $viewerId, $roleSql, $table, $userModelType);
        });
    }

    private function currentChainRoleExpression(string $table): string
    {
        $driver = DB::connection('tenant')->getDriverName();

        if ($driver === 'sqlite') {
            return "json_extract({$table}.approval_chain, '$[' || {$table}.current_step || ']')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$table}.approval_chain, CONCAT('$[', {$table}.current_step, ']')))";
    }

    /**
     * @param  Builder<RolloutGateApprovalRequest>  $outer
     */
    private function appendPermissionRoleMatches(Builder $outer, TenantUser $viewer, string $roleSql): void
    {
        $map = [
            'project_one:saq:manage' => ['saq', 'saq_engineering'],
            'project_one:cme:manage' => ['cme', 'cme_power'],
            'project_one:rollout:manage' => ['engineering', 'pmo', 'mno'],
        ];

        foreach ($map as $permission => $roles) {
            if (! $viewer->can($permission)) {
                continue;
            }

            $quoted = implode("','", $roles);
            $outer->orWhereRaw("{$roleSql} IN ('{$quoted}')");
        }

        if ($viewer->hasRole('tenant_admin')) {
            $outer->orWhereRaw("{$roleSql} = 'tenant_admin'");
        }

        if ($viewer->hasRole('manager') && ! $viewer->can('project_one:rollout:manage')) {
            $outer->orWhereRaw("{$roleSql} = 'mno'");
        }
    }

    /**
     * @param  Builder<RolloutGateApprovalRequest>  $outer
     */
    private function appendProgramOwnerMatches(Builder $outer, string $viewerId, string $roleSql, string $table): void
    {
        $outer->orWhereExists(function ($sub) use ($viewerId, $roleSql, $table): void {
            $sub->from('rollout_programs as inbox_owner_rp')
                ->whereColumn('inbox_owner_rp.id', "{$table}.rollout_program_id")
                ->where(function ($owner) use ($viewerId, $roleSql): void {
                    $owner->orWhere(function ($match) use ($viewerId, $roleSql): void {
                        $match->where('inbox_owner_rp.saq_owner_id', $viewerId)
                            ->whereRaw("{$roleSql} IN ('saq', 'saq_engineering')");
                    })->orWhere(function ($match) use ($viewerId, $roleSql): void {
                        $match->where('inbox_owner_rp.pmo_owner_id', $viewerId)
                            ->whereRaw("{$roleSql} = 'pmo'");
                    })->orWhere(function ($match) use ($viewerId, $roleSql): void {
                        $match->where('inbox_owner_rp.cme_pm_id', $viewerId)
                            ->whereRaw("{$roleSql} IN ('cme', 'cme_power')");
                    });
                });
        });
    }

    /**
     * @param  Builder<RolloutGateApprovalRequest>  $outer
     */
    private function appendDelegationMatches(
        Builder $outer,
        string $viewerId,
        string $roleSql,
        string $table,
        string $userModelType,
    ): void {
        $outer->orWhereExists(function ($sub) use ($viewerId, $roleSql, $table, $userModelType): void {
            $sub->from('rollout_gate_approval_delegations as inbox_del')
                ->where('inbox_del.delegate_id', $viewerId)
                ->where('inbox_del.is_active', true)
                ->whereDate('inbox_del.valid_from', '<=', now())
                ->where(function ($dates): void {
                    $dates->whereNull('inbox_del.valid_until')
                        ->orWhereDate('inbox_del.valid_until', '>=', now());
                })
                ->where(function ($roleKey) use ($roleSql): void {
                    $roleKey->whereNull('inbox_del.role_key')
                        ->orWhereRaw('inbox_del.role_key = '.$roleSql);
                })
                ->where(function ($delegator) use ($roleSql, $table, $userModelType): void {
                    $delegator->orWhereExists(function ($ownerSub) use ($roleSql, $table): void {
                        $ownerSub->from('rollout_programs as inbox_del_rp')
                            ->whereColumn('inbox_del_rp.id', "{$table}.rollout_program_id")
                            ->whereColumn('inbox_del.delegator_id', 'inbox_del_rp.saq_owner_id')
                            ->whereRaw("{$roleSql} IN ('saq', 'saq_engineering')");
                    })->orWhereExists(function ($ownerSub) use ($roleSql, $table): void {
                        $ownerSub->from('rollout_programs as inbox_del_rp')
                            ->whereColumn('inbox_del_rp.id', "{$table}.rollout_program_id")
                            ->whereColumn('inbox_del.delegator_id', 'inbox_del_rp.pmo_owner_id')
                            ->whereRaw("{$roleSql} = 'pmo'");
                    })->orWhereExists(function ($ownerSub) use ($roleSql, $table): void {
                        $ownerSub->from('rollout_programs as inbox_del_rp')
                            ->whereColumn('inbox_del_rp.id', "{$table}.rollout_program_id")
                            ->whereColumn('inbox_del.delegator_id', 'inbox_del_rp.cme_pm_id')
                            ->whereRaw("{$roleSql} IN ('cme', 'cme_power')");
                    })->orWhereRaw($this->delegatorPermissionMatchSql($roleSql, $userModelType));
                });
        });
    }

    private function delegatorPermissionMatchSql(string $roleSql, string $userModelType): string
    {
        $escapedType = str_replace("'", "''", $userModelType);

        return "EXISTS (
            SELECT 1
            FROM model_has_permissions mhp
            INNER JOIN permissions p ON p.id = mhp.permission_id
            WHERE mhp.model_id = inbox_del.delegator_id
              AND mhp.model_type = '{$escapedType}'
              AND (
                ({$roleSql} IN ('saq', 'saq_engineering') AND p.name = 'project_one:saq:manage')
                OR ({$roleSql} IN ('cme', 'cme_power') AND p.name = 'project_one:cme:manage')
                OR ({$roleSql} IN ('engineering', 'pmo', 'mno') AND p.name = 'project_one:rollout:manage')
              )
        ) OR EXISTS (
            SELECT 1
            FROM model_has_roles mhr
            INNER JOIN roles r ON r.id = mhr.role_id
            WHERE mhr.model_id = inbox_del.delegator_id
              AND mhr.model_type = '{$escapedType}'
              AND (
                ({$roleSql} = 'tenant_admin' AND r.name = 'tenant_admin')
                OR ({$roleSql} = 'mno' AND r.name = 'manager')
              )
        )";
    }
}
