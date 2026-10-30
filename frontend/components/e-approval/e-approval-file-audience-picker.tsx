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
  const chosen = useMemo(
    () =>
      recipientIds
        .map((id) => users.find((user) => user.id === id))
        .filter((user): user is (typeof users)[number] => user != null),
    [recipientIds, users],
  );
  const visibleUsers = useMemo(() => {
    const needle = search.trim().toLowerCase();
    const matched = needle
      ? users.filter((user) => `${user.name} ${user.email}`.toLowerCase().includes(needle))
      : users;
    const picked = chosen.filter((user) => !matched.some((row) => row.id === user.id));
    return [...picked, ...matched];
  }, [chosen, search, users]);

  const confirmation =
    audience === "everyone"
      ? "Everyone with an email address receives the published files."
      : chosen.length === 0
        ? audience === "specific_person"
          ? "Choose one person. Their name stays here so you can see who will receive the files."
          : "Choose at least one person. Their names stay here so you can see who will receive the files."
        : chosen.length === 1
          ? `${chosen[0]!.name} (${chosen[0]!.email}) receives the published files.`
          : `${chosen.map((user) => user.name).join(", ")} receive the published files.`;

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
      {audience === "everyone" ? null : (
        <div className="space-y-2">
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search people"
            disabled={disabled}
            aria-label="Search people"
          />
          {usersQuery.isLoading ? (
            <p className="px-1 py-2 text-xs text-muted-foreground">Loading people…</p>
          ) : visibleUsers.length === 0 ? (
            <p className="px-1 py-2 text-xs text-muted-foreground">No people match that search.</p>
          ) : (
            <div className="max-h-48 space-y-1 overflow-auto rounded-lg border border-border p-2">
              {visibleUsers.map((user) => {
                const checked = recipientIds.includes(user.id);
                return (
                  <label key={user.id} className="flex cursor-pointer items-start gap-2 rounded-md px-1 py-1 text-sm hover:bg-muted/40">
                    <input
                      type={audience === "specific_person" ? "radio" : "checkbox"}
                      name={audience === "specific_person" ? "ea-file-specific-person" : undefined}
                      className="mt-1"
                      checked={checked}
                      disabled={disabled}
                      onChange={(event) => {
                        if (audience === "specific_person") {
                          onRecipientIdsChange([user.id]);
                          return;
                        }
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
              })}
            </div>
          )}
        </div>
      )}
      <div className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs text-foreground">
        <p className="font-medium">Files will be sent to</p>
        <p className="mt-1 text-muted-foreground">{confirmation}</p>
        {chosen.length > 1 ? (
          <ul className="mt-2 space-y-1 text-muted-foreground">
            {chosen.map((user) => (
              <li key={user.id}>
                {user.name} · {user.email}
              </li>
            ))}
          </ul>
        ) : null}
        {timingNote ? <p className="mt-2 text-muted-foreground">{timingNote}</p> : null}
      </div>
    </div>
  );
}
