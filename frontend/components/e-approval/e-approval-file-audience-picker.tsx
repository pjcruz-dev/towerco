"use client";

import { useMemo, useState } from "react";

import { useQuery } from "@tanstack/react-query";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchEApprovalAssignableUsers } from "@/lib/api/modules/e-approval-api";

export type EApprovalFileAudience = "everyone" | "specific_person" | "selected_users";

type Props = {
  audience: EApprovalFileAudience;
  recipientIds: string[];
  onAudienceChange: (audience: EApprovalFileAudience) => void;
  onRecipientIdsChange: (ids: string[]) => void;
  disabled?: boolean;
  timingNote?: string;
};

export function EApprovalFileAudiencePicker({
  audience,
  recipientIds,
  onAudienceChange,
  onRecipientIdsChange,
  disabled,
  timingNote,
}: Props) {
  const [search, setSearch] = useState("");
  const usersQuery = useQuery({
    queryKey: ["e-approval", "assignable-users", "recipients"],
    queryFn: () => fetchEApprovalAssignableUsers("recipients"),
    enabled: audience !== "everyone",
    staleTime: 120_000,
  });

  const users = usersQuery.data ?? [];
  const visibleUsers = useMemo(() => {
    const needle = search.trim().toLowerCase();
    if (!needle) return users;
    return users.filter((user) => `${user.name} ${user.email}`.toLowerCase().includes(needle));
  }, [search, users]);

  return (
    <div className="max-w-md space-y-2">
      <Label htmlFor="ea-file-audience">Who receives the files</Label>
      <Select
        id="ea-file-audience"
        value={audience}
        disabled={disabled}
        onChange={(event) => {
          const next = event.target.value;
          const audienceNext: EApprovalFileAudience =
            next === "specific_person" || next === "selected_users" ? next : "everyone";
          onAudienceChange(audienceNext);
          if (audienceNext === "everyone") {
            onRecipientIdsChange([]);
          } else if (audienceNext === "specific_person") {
            onRecipientIdsChange(recipientIds.slice(0, 1));
          }
        }}
      >
        <option value="everyone">Everyone</option>
        <option value="specific_person">Specific person</option>
        <option value="selected_users">Selected users</option>
      </Select>
      {audience === "everyone" ? (
        <p className="text-xs text-muted-foreground">
          {timingNote ??
            "Sent when this request is fully approved. Everyone with an email address receives the published files."}
        </p>
      ) : (
        <div className="space-y-2">
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search people"
            disabled={disabled}
            aria-label="Search people"
          />
          {audience === "specific_person" ? (
            <Select
              aria-label="Specific person"
              value={recipientIds[0] ?? ""}
              disabled={disabled || usersQuery.isLoading}
              onChange={(event) => onRecipientIdsChange(event.target.value ? [event.target.value] : [])}
            >
              <option value="">Select a person</option>
              {visibleUsers.map((user) => (
                <option key={user.id} value={user.id}>
                  {user.name} · {user.email}
                </option>
              ))}
            </Select>
          ) : (
            <div className="max-h-48 space-y-1 overflow-auto rounded-lg border border-border p-2">
              {visibleUsers.length === 0 ? (
                <p className="px-1 py-2 text-xs text-muted-foreground">
                  {usersQuery.isLoading ? "Loading people…" : "No people match that search."}
                </p>
              ) : (
                visibleUsers.map((user) => {
                  const checked = recipientIds.includes(user.id);
                  return (
                    <label key={user.id} className="flex cursor-pointer items-start gap-2 rounded-md px-1 py-1 text-sm hover:bg-muted/40">
                      <input
                        type="checkbox"
                        className="mt-1"
                        checked={checked}
                        disabled={disabled}
                        onChange={(event) => {
                          onRecipientIdsChange(
                            event.target.checked
                              ? [...new Set([...recipientIds, user.id])]
                              : recipientIds.filter((id) => id !== user.id),
                          );
                        }}
                      />
                      <span>
                        <span className="block">{user.name}</span>
                        <span className="block text-xs text-muted-foreground">{user.email}</span>
                      </span>
                    </label>
                  );
                })
              )}
            </div>
          )}
          <p className="text-xs text-muted-foreground">
            {audience === "specific_person"
              ? "That person receives the published files when this request is fully approved."
              : recipientIds.length === 0
                ? "Select at least one person."
                : `${recipientIds.length} ${recipientIds.length === 1 ? "person" : "people"} will receive the published files when this request is fully approved.`}
            {timingNote ? ` ${timingNote}` : ""}
          </p>
        </div>
      )}
    </div>
  );
}
