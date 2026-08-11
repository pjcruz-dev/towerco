"use client";

import Link from "next/link";
import { useState } from "react";

import { ListEmptyState } from "@/components/project-one/list-empty-state";
import { projectOneProjectsTableColumns } from "@/components/project-one/project-one-projects-table-columns";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { RegistryListToolbar } from "@/components/registry/registry-list-toolbar";
import { PermissionGate } from "@/components/layout/permission-gate";
import { buttonVariants } from "@/components/ui/button";
import { useProjectOneProjectsIndex } from "@/hooks/use-project-one-projects-index";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";

const DEFAULT_SORT = "updated_at:desc";

export function ProjectsRegistryPageClient() {
  const canManage = usePermission([permissions.projectOneManage]);
  const [search, setSearch] = useState("");
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: DEFAULT_SORT,
    sortableColumnIds: ["name", "status"],
  });
  const { setPage, query } = useProjectOneProjectsIndex(search, sort);
  const { data, isFetching, isError } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;
  const isEmpty = !isFetching && rows.length === 0;

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneView]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Projects</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Programs and rollouts anchored to sites, with project manager assignment.
            </p>
          </div>
          {canManage ? (
            <Link href="/project-one/projects/new" className={buttonVariants({ size: "sm" })}>
              New project
            </Link>
          ) : null}
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <RegistryListToolbar label="Filter" value={search} onChange={setSearch} placeholder="Name, status, site" />
          {isEmpty ? (
            <ListEmptyState
              title={search.trim() ? "No matching projects" : "No projects yet"}
              description={
                search.trim()
                  ? "Try a different filter, or create a program to anchor rollouts and milestones."
                  : "Create a project to group rollouts, milestones, and approvals for a site or program."
              }
              actionHref={canManage ? "/project-one/projects/new" : undefined}
              actionLabel={canManage ? "Create project" : undefined}
              secondaryHref={canManage ? "/project-one/rollouts/new" : "/project-one"}
              secondaryLabel={canManage ? "Create rollout" : "Overview"}
            />
          ) : (
            <RegistryDataTableView
              columns={projectOneProjectsTableColumns}
              data={rows}
              getRowId={(row) => row.id}
              isLoading={isFetching && rows.length === 0}
              isEmpty={false}
              enableColumnVisibility
              columnVisibilityStorageKey="toweros.table.columns.project-one.projects"
              sorting={sorting}
              onSortingChange={onSortingChange}
              manualSorting={manualSorting}
            />
          )}
          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        {isError ? <p className="text-sm text-destructive">Could not load projects.</p> : null}
      </div>
    </PermissionGate>
  );
}
