"use client";

import { useMemo, useState } from "react";

import { useQuery } from "@tanstack/react-query";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalAssignableUsers } from "@/lib/api/modules/e-approval-api";
import {
  formRequestAccessFromMetadata,
  formRequestAccessPatch,
  type FormRequestAccessMode,
} from "@/modules/e-approval/form-request-access";
import { parseFormMetadataJson } from "@/modules/e-approval/builder-layout-rows";

type Props = {
  /** Raw form metadata JSON from the setup editor. */
  metadataJson: string;
  onPatch: (patch: Record<string, unknown>) => void;
};

export function EApprovalFormRequestAccessCard({ metadataJson, onPatch }: Props) {
  const access = formRequestAccessFromMetadata(parseFormMetadataJson(metadataJson));
  const [search, setSearch] = useState("");
  const usersQuery = useQuery({
    queryKey: ["e-approval", "assignable-users", "requestors"],
    queryFn: () => fetchEApprovalAssignableUsers("requestors"),
    enabled: access.mode === "selected",
    staleTime: 120_000,
  });

  const users = usersQuery.data ?? [];
  const visibleUsers = useMemo(() => {
    const needle = search.trim().toLowerCase();
    if (!needle) return users;
    return users.filter((user) =>
      `${user.name} ${user.email}`.toLowerCase().includes(needle),
    );
  }, [search, users]);

  const setMode = (mode: FormRequestAccessMode) => {
    onPatch(formRequestAccessPatch({ mode, userIds: access.userIds }));
  };

  const toggleUser = (userId: string, checked: boolean) => {
    const userIds = checked
      ? [...new Set([...access.userIds, userId])]
      : access.userIds.filter((id) => id !== userId);
    onPatch(formRequestAccessPatch({ mode: "selected", userIds }));
  };

  return (
    <EApprovalSectionCard
      title="Who can start a request"
      description="Everyone can start this form unless you limit it to selected people. Anyone left off the list will not see it when they pick a form."
    >
      <div className="space-y-4">
        <div className="max-w-sm space-y-2">
          <Label htmlFor="ea-form-request-access">Access</Label>
          <Select
            id="ea-form-request-access"
            value={access.mode}
            onChange={(event) => setMode(event.target.value === "selected" ? "selected" : "all")}
          >
            <option value="all">Everyone</option>
            <option value="selected">Selected users only</option>
          </Select>
        </div>

        {access.mode === "selected" ? (
          <div className="space-y-3">
            <p className="text-sm text-muted-foreground">
              {access.userIds.length === 0
                ? "No one can start this form until you select at least one person."
                : `${access.userIds.length} ${access.userIds.length === 1 ? "person" : "people"} can start this form.`}
            </p>
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search name or email"
              aria-label="Search people who can start this form"
            />
            <div className="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
              {usersQuery.isLoading ? (
                <p className="px-2 py-3 text-sm text-muted-foreground">Loading people…</p>
              ) : usersQuery.isError ? (
                <p className="px-2 py-3 text-sm text-destructive">Could not load people. Try again.</p>
              ) : visibleUsers.length === 0 ? (
                <p className="px-2 py-3 text-sm text-muted-foreground">No matching people.</p>
              ) : (
                visibleUsers.map((user) => {
                  const checked = access.userIds.includes(user.id);
                  return (
                    <label
                      key={user.id}
                      className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 hover:bg-muted/60"
                    >
                      <Checkbox
                        checked={checked}
                        onCheckedChange={(value) => toggleUser(user.id, value === true)}
                        aria-label={`${user.name} can start this form`}
                      />
                      <span className="min-w-0">
                        <span className="block truncate text-sm text-foreground">{user.name}</span>
                        <span className="block truncate text-xs text-muted-foreground">{user.email}</span>
                      </span>
                    </label>
                  );
                })
              )}
            </div>
          </div>
        ) : null}
      </div>
    </EApprovalSectionCard>
  );
}
