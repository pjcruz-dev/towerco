"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  createEApprovalDelegation,
  fetchEApprovalDelegations,
  revokeEApprovalDelegation,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  approverOptions: { id: string; label: string }[];
};

export function EApprovalDelegationPanel({ approverOptions }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [delegateId, setDelegateId] = useState("");
  const [validUntil, setValidUntil] = useState("");
  const [notes, setNotes] = useState("");

  const delegationsQuery = useQuery({
    queryKey: ["e-approval", "delegations"],
    queryFn: fetchEApprovalDelegations,
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createEApprovalDelegation({
        delegate_id: delegateId,
        valid_until: validUntil || null,
        notes: notes || undefined,
      }),
    onSuccess: () => {
      setDelegateId("");
      setValidUntil("");
      setNotes("");
      queryClient.invalidateQueries({ queryKey: ["e-approval", "delegations"] });
      push({ level: "success", title: "Delegation created" });
    },
    onError: (e) => push({ level: "error", title: "Failed", message: getErrorMessage(e) }),
  });

  const revokeMutation = useMutation({
    mutationFn: (id: string) => revokeEApprovalDelegation(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "delegations"] });
      push({ level: "success", title: "Delegation revoked" });
    },
    onError: (e) => push({ level: "error", title: "Revoke failed", message: getErrorMessage(e) }),
  });

  const rows = delegationsQuery.data ?? [];

  return (
    <section className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium">Out-of-office delegation</h2>
      <p className="text-sm text-muted-foreground">
        Assign another user to act on your pending approvals for a date range.
      </p>

      <div className="flex flex-wrap items-end gap-2">
        <Label className="block space-y-1">
          <span className="text-muted-foreground">Acting approver</span>
          <Select className="min-w-[200px]" value={delegateId} onChange={(e) => setDelegateId(e.target.value)}>
            <option value="">Select user</option>
            {approverOptions.map((o) => (
              <option key={o.id} value={o.id}>
                {o.label}
              </option>
            ))}
          </Select>
        </Label>
        <label className="text-sm">
          <span className="text-muted-foreground">Valid until</span>
          <DatePicker className="mt-1 w-40" value={validUntil} onChange={setValidUntil} />
        </label>
        <Input className="max-w-xs" placeholder="Notes (optional)" value={notes} onChange={(e) => setNotes(e.target.value)} />
        <Button size="sm" onClick={() => createMutation.mutate()} disabled={!delegateId || createMutation.isPending}>
          Add delegation
        </Button>
      </div>

      <ul className="space-y-2 text-sm">
        {rows.length === 0 ? (
          <li className="text-muted-foreground">No active delegations.</li>
        ) : (
          rows.map((row) => {
            const id = String(row.id ?? "");
            const delegate = row.delegate as { name?: string } | undefined;
            const delegator = row.delegator as { name?: string } | undefined;
            return (
              <li key={id} className="flex flex-wrap items-center justify-between gap-2 border-b border-border py-2">
                <span>
                  {delegator?.name ?? "You"} → {delegate?.name ?? "—"}
                  {row.valid_until ? ` until ${String(row.valid_until)}` : ""}
                </span>
                <Button size="sm" variant="outline" onClick={() => revokeMutation.mutate(id)} disabled={revokeMutation.isPending}>
                  Revoke
                </Button>
              </li>
            );
          })
        )}
      </ul>
    </section>
  );
}
