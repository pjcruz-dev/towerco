"use client";

import { Shield } from "lucide-react";

import { AdminOrgAvatar } from "@/components/admin/admin-org-avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { entraLicenseChipLabel } from "@/lib/admin/entra-license";
import { cn } from "@/lib/utils";
import type { OrgChartNode } from "@/lib/admin/org-chart";

export function OrgPersonCard({
  person,
  emphasis = "default",
  compact = false,
  onSelect,
  onManageRoles,
}: {
  person: OrgChartNode;
  emphasis?: "manager" | "focus" | "default";
  compact?: boolean;
  onSelect: (id: string) => void;
  onManageRoles?: (person: OrgChartNode) => void;
}) {
  const focused = emphasis === "focus";
  const license = entraLicenseChipLabel(person.license_label, person.license_names);
  const roles = (person.roles ?? []).slice(0, compact ? 1 : 3);
  const extraRoles = Math.max(0, (person.roles ?? []).length - roles.length);

  return (
    <div
      className={cn(
        "rounded-xl border bg-card text-left shadow-sm transition-colors",
        compact ? "w-[200px]" : "w-full max-w-[280px]",
        focused
          ? "border-foreground/30 ring-2 ring-foreground/15"
          : "border-border hover:border-foreground/25",
      )}
      data-org-no-pan=""
    >
      <button
        type="button"
        onClick={() => onSelect(person.id)}
        className={cn(
          "w-full text-left hover:bg-muted/30",
          compact ? "px-3 py-2.5" : "px-4 py-3",
          onManageRoles && !person.external ? "rounded-t-xl" : "rounded-xl",
        )}
      >
        <div className="flex items-start gap-3">
          <AdminOrgAvatar
            name={person.name}
            photoUrl={person.photo_url}
            size={focused && !compact ? "lg" : compact ? "sm" : "default"}
            className="mt-0.5 shrink-0"
          />
          <div className="min-w-0 flex-1">
            <p className={cn("truncate text-sm text-foreground", focused ? "font-semibold" : "font-medium")}>
              {person.name}
            </p>
            {person.job_title ? (
              <p className="truncate text-xs text-muted-foreground">{person.job_title}</p>
            ) : null}
            {person.department ? (
              <p className="truncate text-xs text-muted-foreground">{person.department}</p>
            ) : null}
            {license ? (
              <Badge
                variant="outline"
                className="mt-1 max-w-full truncate text-[10px] font-medium"
                title={person.license_names?.join(", ") || license}
              >
                {license}
              </Badge>
            ) : null}
            {roles.length > 0 ? (
              <div className="mt-1 flex flex-wrap gap-1">
                {roles.map((role) => (
                  <Badge
                    key={role}
                    variant="secondary"
                    className="max-w-full truncate text-[10px] font-medium"
                    title={role}
                  >
                    {role}
                  </Badge>
                ))}
                {extraRoles > 0 ? (
                  <Badge variant="secondary" className="text-[10px] font-medium">
                    +{extraRoles}
                  </Badge>
                ) : null}
              </div>
            ) : null}
            {person.email ? <p className="truncate text-xs text-muted-foreground">{person.email}</p> : null}
            {person.external ? (
              <p className="mt-1 text-xs text-muted-foreground">In Microsoft Entra only</p>
            ) : person.direct_report_count > 0 ? (
              <p className="mt-1 text-xs text-muted-foreground">
                {person.direct_report_count} direct report{person.direct_report_count === 1 ? "" : "s"}
              </p>
            ) : null}
          </div>
        </div>
      </button>
      {onManageRoles && !person.external ? (
        <div className="border-t border-border px-2 py-1.5">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-7 w-full justify-start gap-1.5 px-2 text-xs text-muted-foreground"
            onClick={(event) => {
              event.stopPropagation();
              onManageRoles(person);
            }}
          >
            <Shield className="size-3.5" />
            Assign roles
          </Button>
        </div>
      ) : null}
    </div>
  );
}
