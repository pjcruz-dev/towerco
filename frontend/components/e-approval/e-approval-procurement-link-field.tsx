"use client";

import { useQuery } from "@tanstack/react-query";

import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { fetchProcurementBudgetLines } from "@/lib/api/modules/procurement-one-api";
import { fetchProjectsIndex, fetchRolloutsIndex } from "@/lib/api/modules/project-one-api";
import { fetchSitesIndex } from "@/lib/api/modules/sites-api";
import type { ProcurementLinkFieldName } from "@/modules/e-approval/procurement-link-fields";
import { cn } from "@/lib/utils";

type Props = {
  fieldName: ProcurementLinkFieldName;
  label: string;
  value: string;
  onChange: (value: string) => void;
  allValues: Record<string, string>;
  disabled?: boolean;
  error?: string | null;
  helpText?: string;
  /** Public forms: skip authenticated /sites and related pickers. */
  allowRemoteLookups?: boolean;
};

export function EApprovalProcurementLinkField({
  fieldName,
  label,
  value,
  onChange,
  allValues,
  disabled,
  error,
  helpText,
  allowRemoteLookups = true,
}: Props) {
  const projectId = allValues.project_id?.trim() ?? "";
  const rolloutId = allValues.rollout_id?.trim() ?? "";
  const siteId = allValues.site_id?.trim() ?? "";

  const sitesQuery = useQuery({
    queryKey: ["sites", "picker", fieldName === "site_id" ? "all" : siteId],
    queryFn: () => fetchSitesIndex({ per_page: 100, search: undefined }),
    enabled: allowRemoteLookups && fieldName === "site_id",
    staleTime: 60_000,
  });

  const projectsQuery = useQuery({
    queryKey: ["project-one", "projects", "picker", siteId],
    queryFn: () => fetchProjectsIndex({ per_page: 100, site_id: siteId || undefined }),
    enabled: allowRemoteLookups && fieldName === "project_id",
    staleTime: 60_000,
  });

  const rolloutsQuery = useQuery({
    queryKey: ["project-one", "rollouts", "picker"],
    queryFn: () => fetchRolloutsIndex({ per_page: 100 }),
    enabled: allowRemoteLookups && fieldName === "rollout_id",
    staleTime: 60_000,
  });

  const budgetLinesQuery = useQuery({
    queryKey: ["procurement-one", "budget-lines", "picker", projectId, rolloutId],
    queryFn: () =>
      fetchProcurementBudgetLines({
        project_id: projectId || undefined,
        rollout_id: rolloutId || undefined,
      }),
    enabled: allowRemoteLookups && fieldName === "boq_line_id",
    staleTime: 60_000,
  });

  const isLoading =
    (fieldName === "site_id" && sitesQuery.isLoading)
    || (fieldName === "project_id" && projectsQuery.isLoading)
    || (fieldName === "rollout_id" && rolloutsQuery.isLoading)
    || (fieldName === "boq_line_id" && budgetLinesQuery.isLoading);

  const rollouts = (rolloutsQuery.data?.data ?? []).filter((row) => {
    if (!projectId) {
      return true;
    }

    return !row.project_id || row.project_id === projectId;
  });

  return (
    <div className="space-y-2">
      <Label>
        {label}
      </Label>
      <Select
        disabled={disabled || isLoading}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={cn(error ? "border-destructive" : undefined)}
      >
        <option value="">{isLoading ? "Loading options…" : "None (optional)"}</option>
        {fieldName === "site_id"
          ? (sitesQuery.data?.data ?? []).map((site) => (
              <option key={site.id} value={site.id}>
                {site.site_code} · {site.name}
              </option>
            ))
          : null}
        {fieldName === "project_id"
          ? (projectsQuery.data?.data ?? []).map((project) => (
              <option key={project.id} value={project.id}>
                {project.name}
                {project.site ? ` · ${project.site.site_code}` : ""}
              </option>
            ))
          : null}
        {fieldName === "rollout_id"
          ? rollouts.map((rollout) => (
              <option key={rollout.id} value={rollout.id}>
                {rollout.rollout_ref ?? rollout.id}
                {rollout.search_ring_name ? ` · ${rollout.search_ring_name}` : ""}
              </option>
            ))
          : null}
        {fieldName === "boq_line_id"
          ? (budgetLinesQuery.data ?? []).map((line) => (
              <option key={line.id} value={line.id}>
                {line.line_code ? `${line.line_code} · ` : ""}
                {line.description}
              </option>
            ))
          : null}
      </Select>
      {error ? (
        <p className="text-xs text-destructive" role="alert">
          {error}
        </p>
      ) : null}
      {helpText ? <p className="text-xs text-muted-foreground">{helpText}</p> : null}
      {fieldName === "boq_line_id" && !projectId && !rolloutId ? (
        <p className="text-xs text-muted-foreground">Select a project or rollout to filter budget lines.</p>
      ) : null}
    </div>
  );
}
