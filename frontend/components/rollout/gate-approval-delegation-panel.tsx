"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import {
  createGateApprovalDelegation,
  fetchGateApprovalDelegations,
  fetchRolloutAssignableUsers,
  revokeGateApprovalDelegation,
  type GateApprovalDelegation,
} from "@/lib/api/modules/rollout-api";
import { filterAssignableUsersForDelegationRole } from "@/lib/rollout/gate-approval-delegation";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

const roleOptions = [
  { value: "", label: "All my approval roles" },
  { value: "saq", label: "SAQ" },
  { value: "pmo", label: "PMO" },
  { value: "cme", label: "CME" },
  { value: "engineering", label: "Engineering" },
  { value: "mno", label: "MNO" },
] as const;

function canCreateDelegation(permissionList: string[]): boolean {
  return [
    permissions.rolloutGateApprove,
    permissions.rolloutManage,
    permissions.saqManage,
    permissions.cmeManage,
  ].some((permission) => permissionList.includes(permission));
}

export function GateApprovalDelegationPanel() {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const currentUserId = useAuthStore((state) => state.user?.id ?? null);
  const permissionList = effectivePermissions();

  const [delegateId, setDelegateId] = useState("");
  const [roleKey, setRoleKey] = useState("");
  const [notes, setNotes] = useState("");

  const mayCreate = useMemo(() => canCreateDelegation(permissionList), [permissionList]);

  const usersQuery = useQuery({
    queryKey: ["project-one", "assignable-users"],
    queryFn: fetchRolloutAssignableUsers,
    enabled: mayCreate,
  });

  const query = useQuery({
    queryKey: ["project-one", "gate-approval-delegations"],
    queryFn: fetchGateApprovalDelegations,
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createGateApprovalDelegation({
        delegate_id: delegateId.trim(),
        role_key: roleKey.trim() || undefined,
        notes: notes.trim() || undefined,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["project-one", "gate-approval-delegations"] });
      void queryClient.invalidateQueries({ queryKey: ["project-one", "gate-approvals"] });
      push({ level: "success", title: "Delegation created", message: "Acting approver can decide on your behalf." });
      setDelegateId("");
      setRoleKey("");
      setNotes("");
    },
    onError: (error) =>
      push({ level: "error", title: "Could not create delegation", message: getErrorMessage(error) }),
  });

  const revokeMutation = useMutation({
    mutationFn: (id: string) => revokeGateApprovalDelegation(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["project-one", "gate-approval-delegations"] });
      void queryClient.invalidateQueries({ queryKey: ["project-one", "gate-approvals"] });
      push({ level: "success", title: "Delegation revoked" });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not revoke delegation", message: getErrorMessage(error) }),
  });

  const rows = query.data ?? [];
  const allUsers = usersQuery.data ?? [];

  const eligibleDelegates = useMemo(
    () => filterAssignableUsersForDelegationRole(allUsers, roleKey, currentUserId),
    [allUsers, currentUserId, roleKey],
  );

  useEffect(() => {
    if (!delegateId) {
      return;
    }

    if (!eligibleDelegates.some((user) => user.id === delegateId)) {
      setDelegateId("");
    }
  }, [delegateId, eligibleDelegates]);

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium text-foreground">Acting approver delegation</h2>
      <p className="mt-1 text-sm text-muted-foreground">
        While you are out of office, a colleague can approve gate steps assigned to you on rollouts where you are the
        owner (SAQ, PMO, or CME).
      </p>

      {mayCreate ? (
        <div className="mt-4 grid gap-3 md:grid-cols-3">
          <label className="space-y-1.5 text-sm md:col-span-2">
            <span className="font-medium text-foreground">Acting approver</span>
            <select
              className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={delegateId}
              onChange={(e) => setDelegateId(e.target.value)}
            >
              <option value="">Select user…</option>
              {eligibleDelegates.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.name}
                  {user.roles.length ? ` · ${user.roles.join(", ")}` : ""}
                </option>
              ))}
            </select>
            {eligibleDelegates.length === 0 ? (
              <p className="text-[11px] text-amber-700 dark:text-amber-300">
                No users match this role scope. Try another scope or assign roles in Admin.
              </p>
            ) : null}
          </label>
          <label className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Role scope</span>
            <select
              className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={roleKey}
              onChange={(e) => setRoleKey(e.target.value)}
            >
              {roleOptions.map((option) => (
                <option key={option.value || "all"} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <p className="text-[11px] text-muted-foreground">
              {roleKey.trim()
                ? `Showing users with roles suited to ${roleKey.toUpperCase()} delegation.`
                : "Showing all active users (except you)."}
            </p>
          </label>
          <div className="md:col-span-3">
            <FormInput label="Notes (optional)" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
        </div>
      ) : (
        <p className="mt-3 text-sm text-muted-foreground">
          You need rollout gate approve, rollout manage, SAQ manage, or CME manage permission to create a delegation.
        </p>
      )}

      {mayCreate ? (
        <Button
          type="button"
          size="sm"
          className="mt-3"
          disabled={!delegateId.trim() || createMutation.isPending}
          onClick={() => createMutation.mutate()}
        >
          {createMutation.isPending ? "Saving…" : "Create delegation"}
        </Button>
      ) : null}

      <ul className="mt-4 space-y-2">
        {query.isLoading ? (
          <li className="text-sm text-muted-foreground">Loading delegations…</li>
        ) : rows.length === 0 ? (
          <li className="text-sm text-muted-foreground">No active delegations.</li>
        ) : (
          rows.map((row) => (
            <DelegationRow
              key={row.id}
              row={row}
              pending={revokeMutation.isPending}
              onRevoke={() => revokeMutation.mutate(row.id)}
            />
          ))
        )}
      </ul>
    </section>
  );
}

function DelegationRow({
  row,
  pending,
  onRevoke,
}: {
  row: GateApprovalDelegation;
  pending: boolean;
  onRevoke: () => void;
}) {
  return (
    <li className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/70 px-3 py-2 text-sm">
      <div>
        <p className="font-medium">
          {row.delegator?.name ?? "You"} → {row.delegate?.name ?? row.delegate?.id}
        </p>
        <p className="text-xs text-muted-foreground">
          {row.role_key ? `Role: ${row.role_key}` : "All roles"} · {row.valid_from}
          {row.valid_until ? ` – ${row.valid_until}` : " – open-ended"}
        </p>
      </div>
      <Button type="button" size="sm" variant="outline" disabled={pending} onClick={onRevoke}>
        Revoke
      </Button>
    </li>
  );
}
