"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";

import { AssetStatusBadge } from "@/components/assets/asset-status-badge";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  completeOffboarding,
  fetchOffboarding,
  recordOffboardingItem,
  searchAssetPeople,
  startOffboarding,
} from "@/lib/api/modules/assets-api";
import { getErrorMessage } from "@/lib/api/error";
import { notify } from "@/lib/ui/notify";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import type { AssetPerson, OffboardingCase } from "@/modules/assets/types";

export function OffboardingPageClient() {
  const [query, setQuery] = useState("");
  const [employee, setEmployee] = useState<AssetPerson | null>(null);
  const [caseId, setCaseId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const people = useQuery({
    queryKey: ["asset-people", query],
    queryFn: () => searchAssetPeople(query.trim()),
    enabled: true,
  });
  const current = useQuery({
    queryKey: ["offboarding", caseId],
    queryFn: () => fetchOffboarding(caseId ?? ""),
    enabled: Boolean(caseId),
  });

  return (
    <div className={adminPageShellClass}>
      <WorkspacePageHeader
        title="Offboarding"
        description="Return the assets assigned to a workspace user. This does not deactivate their account."
      />
      <div className="max-w-lg space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
        <Label htmlFor="offboard-search">User</Label>
        <Input
          id="offboard-search"
          value={query}
          placeholder="Search users by name or email"
          onChange={(event) => setQuery(event.target.value)}
        />
        {people.isLoading ? <p className="text-sm text-muted-foreground">Loading users…</p> : null}
        {people.isError ? <p className="text-sm text-destructive">{getErrorMessage(people.error)}</p> : null}
        {employee ? (
          <p className="text-sm">
            Selected user: {employee.name}
            {employee.email ? <span className="text-muted-foreground"> · {employee.email}</span> : null}
          </p>
        ) : null}
        <ul className="max-h-56 overflow-y-auto text-sm">
          {(people.data ?? []).map((person) => (
            <li key={person.id}>
              <button
                type="button"
                className="w-full rounded-md px-2 py-2 text-left hover:bg-muted"
                onClick={() => setEmployee(person)}
              >
                {person.name}
                {person.email ? <span className="text-muted-foreground"> · {person.email}</span> : null}
              </button>
            </li>
          ))}
        </ul>
        {!people.isLoading && !people.isError && (people.data ?? []).length === 0 ? (
          <p className="text-sm text-muted-foreground">No active users match that search.</p>
        ) : null}
        <Button
          type="button"
          disabled={!employee}
          onClick={() => {
            if (!employee) return;
            setError(null);
            void startOffboarding(employee.id)
              .then((row: OffboardingCase) => {
                notify.success("Return started", row.number);
                setCaseId(row.id);
              })
              .catch((caught: unknown) => setError(getErrorMessage(caught)));
          }}
        >
          Start return
        </Button>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {current.data ? (
        <section className="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm">
              {current.data.number}{" "}
              <AssetStatusBadge status={current.data.status} />
            </p>
            <Button type="button" variant="outline" render={<Link href={`/print/assets/offboarding/${current.data.id}`} />}>
              Print sheet
            </Button>
          </div>
          {current.data.items.map((item) => (
            <form
              key={item.id}
              className="grid gap-2 border-t border-border pt-3 sm:grid-cols-[1fr_160px_160px_auto]"
              onSubmit={(event) => {
                event.preventDefault();
                const data = new FormData(event.currentTarget);
                setError(null);
                void recordOffboardingItem(current.data.id, item.id, {
                  scanned_tag: String(data.get("scanned_tag") ?? ""),
                  result: String(data.get("result") ?? ""),
                  condition: String(data.get("condition") ?? ""),
                })
                  .then(() => {
                    notify.success("Item saved", item.asset?.asset_tag);
                    return current.refetch();
                  })
                  .catch((caught: unknown) => setError(getErrorMessage(caught)));
              }}
            >
              <Input name="scanned_tag" aria-label="Scanned tag" placeholder={item.asset?.asset_tag ?? "Tag"} className="font-mono" />
              <Select name="result" defaultValue={item.result ?? "RETURNED"} aria-label="Result">
                {["RETURNED", "MISSING", "DAMAGED", "LOST", "FOR_REPAIR", "INCOMPLETE"].map((result) => (
                  <option key={result} value={result}>
                    {result}
                  </option>
                ))}
              </Select>
              <Input name="condition" aria-label="Condition" placeholder="Condition" defaultValue={item.condition ?? ""} />
              <Button type="submit">Save</Button>
            </form>
          ))}
          <Button
            type="button"
            onClick={() => {
              setError(null);
              void completeOffboarding(current.data.id)
                .then(() => {
                  notify.success("Return completed", current.data.number);
                  return current.refetch();
                })
                .catch((caught: unknown) => setError(getErrorMessage(caught)));
            }}
          >
            Complete
          </Button>
        </section>
      ) : null}
    </div>
  );
}
