"use client";

import { Plus, X } from "lucide-react";
import { useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { TicketingUserPicker } from "@/components/ticketing/ticketing-user-picker";
import type { TicketingUserRef } from "@/modules/ticketing/types";

type Props = {
  directoryUsers: TicketingUserRef[];
  selectedIds: string[];
  onChange: (ids: string[]) => void;
  poolConfigured: boolean;
  loading?: boolean;
  loadError?: string | null;
};

export function TicketingItAssigneePoolEditor({
  directoryUsers,
  selectedIds,
  onChange,
  poolConfigured,
  loading = false,
  loadError = null,
}: Props) {
  const [pendingId, setPendingId] = useState("");

  const selected = useMemo(() => {
    const byId = new Map(directoryUsers.map((u) => [u.id, u]));
    return selectedIds
      .map((id) => byId.get(id))
      .filter((u): u is TicketingUserRef => u != null);
  }, [directoryUsers, selectedIds]);

  const available = useMemo(
    () => directoryUsers.filter((u) => !selectedIds.includes(u.id)),
    [directoryUsers, selectedIds],
  );

  function addPending() {
    if (!pendingId || selectedIds.includes(pendingId)) return;
    onChange([...selectedIds, pendingId]);
    setPendingId("");
  }

  return (
    <div className="space-y-4">
      <div>
        <h2 className="text-sm font-medium text-foreground">IT assignees</h2>
        <p className="mt-1 text-xs text-muted-foreground">
          Users listed here appear in the ticket Assignee dropdown. Save this list to restrict
          assignment to IT only.
          {!poolConfigured ? (
            <span className="mt-1 block text-amber-700 dark:text-amber-400">
              Not configured yet — any active user can currently be assigned.
            </span>
          ) : null}
        </p>
        {loadError ? <p className="mt-2 text-xs text-destructive">{loadError}</p> : null}
      </div>

      <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
        <div className="min-w-0 flex-1 space-y-2">
          <Label htmlFor="it-assignee-add">Add IT user</Label>
          <TicketingUserPicker
            id="it-assignee-add"
            users={available}
            value={pendingId}
            onChange={setPendingId}
            disabled={loading || Boolean(loadError)}
            placeholder={loading ? "Loading users…" : "Search users to add…"}
            emptyLabel={
              loading
                ? "Loading users…"
                : directoryUsers.length === 0
                  ? "No users available."
                  : "No more users to add."
            }
          />
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="h-10"
          onClick={addPending}
          disabled={!pendingId || loading}
        >
          <Plus className="mr-1.5 h-4 w-4" aria-hidden />
          Add
        </Button>
      </div>

      {selected.length === 0 ? (
        <p className="rounded-lg border border-dashed border-border px-3 py-4 text-center text-xs text-muted-foreground">
          No IT users in the pool yet.
        </p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {selected.map((user) => (
            <li key={user.id} className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
              <div className="min-w-0">
                <p className="truncate font-medium text-foreground">{user.name}</p>
                {user.email ? <p className="truncate text-xs text-muted-foreground">{user.email}</p> : null}
              </div>
              <button
                type="button"
                className="text-muted-foreground hover:text-foreground"
                aria-label={`Remove ${user.name}`}
                onClick={() => onChange(selectedIds.filter((id) => id !== user.id))}
              >
                <X className="h-4 w-4" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
