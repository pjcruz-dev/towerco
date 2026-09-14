"use client";

import { useMemo } from "react";

import { OrgPersonCard } from "@/components/admin/admin-org-person-card";
import { resolveManager, type OrgChartIndex, type OrgChartNode } from "@/lib/admin/org-chart";

function Connector() {
  return (
    <div className="flex flex-col items-center py-1" aria-hidden>
      <div className="h-4 w-px bg-border" />
    </div>
  );
}

export function AdminOrgChartView({
  index,
  focusedId,
  onFocus,
  organizationLabel,
  onManageRoles,
  showRoles = false,
}: {
  index: OrgChartIndex;
  focusedId: string;
  onFocus: (id: string) => void;
  organizationLabel: string;
  onManageRoles?: (person: OrgChartNode) => void;
  showRoles?: boolean;
}) {
  const focused = index.byId.get(focusedId);
  const manager = useMemo(() => resolveManager(index, focused), [focused, index]);

  const reports = index.reports.get(focusedId) ?? [];

  if (!focused) {
    return <p className="text-sm text-muted-foreground">Select a person to view their organization.</p>;
  }

  return (
    <div className="flex flex-col items-center gap-1 px-2 py-6">
      {manager ? (
        <>
          <p className="mb-1 text-[11px] font-medium text-muted-foreground">Reports to</p>
          <OrgPersonCard
            person={manager}
            emphasis="manager"
            showRoles={showRoles}
            onSelect={onFocus}
            onManageRoles={onManageRoles}
          />
          <Connector />
        </>
      ) : focused.external ? (
        <p className="mb-2 max-w-md text-center text-xs text-muted-foreground">
          This manager is in Microsoft Entra but does not have a {organizationLabel} account yet.
        </p>
      ) : null}

      <OrgPersonCard
        person={focused}
        emphasis="focus"
        showRoles={showRoles}
        onSelect={onFocus}
        onManageRoles={onManageRoles}
      />

      {reports.length > 0 ? (
        <>
          <Connector />
          <p className="mb-2 text-[11px] font-medium text-muted-foreground">
            Direct reports ({reports.length})
          </p>
          <div className="grid w-full max-w-4xl grid-cols-1 justify-items-center gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {reports.map((person) => (
              <OrgPersonCard
                key={person.id}
                person={person}
                showRoles={showRoles}
                onSelect={onFocus}
                onManageRoles={onManageRoles}
              />
            ))}
          </div>
        </>
      ) : (
        <p className="mt-4 text-xs text-muted-foreground">No direct reports in {organizationLabel}.</p>
      )}
    </div>
  );
}
