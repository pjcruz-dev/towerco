"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { OnChangeFn, RowSelectionState } from "@tanstack/react-table";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Download, UserPlus } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import { AdminUserDetailDrawer } from "@/components/admin/admin-user-detail-drawer";
import { AdminUserFormSheet } from "@/components/admin/admin-user-form-sheet";
import { AdminUserImpersonateDialog } from "@/components/admin/admin-user-impersonate-dialog";
import {
  UserAuthMethodsBadges,
  UserMfaStatusBadge,
} from "@/components/admin/admin-user-security-badges";
import {
  createUsersTableColumns,
  UserRowActions,
  UserStatusBadge,
} from "@/components/admin/users-table-columns";
import { UsersBulkRolePicker } from "@/components/admin/users-bulk-role-picker";
import { FilterSelect } from "@/components/forms/filter-select";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { useAdminRoleCatalog } from "@/hooks/use-admin-role-catalog";
import { useAdminUsersIndex } from "@/hooks/use-admin-users-index";
import { useServerTableSort } from "@/hooks/use-server-table-sort";
import { formatLastActive } from "@/lib/admin/user-display";
import { getErrorMessage } from "@/lib/api/error";
import { filterRolesForEnabledModules } from "@/lib/rbac/role-groups";
import {
  ADMIN_USERS_IMPORT_TEMPLATE_CSV,
  bulkAssignRolesAdminUsers,
  bulkDeactivateAdminUsers,
  bulkResetPasswordAdminUsers,
  exportAdminUsersCsv,
  fetchAdminUsersIds,
  fetchAdminUsersSeatUsage,
  importAdminUsers,
  type AdminUserLastActiveFilter,
  type AdminUserMfaFilter,
  type AdminUserRow,
  type AdminUserStatusFilter,
} from "@/lib/api/modules/admin-users-api";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

const STATUS_OPTIONS: { value: AdminUserStatusFilter; label: string }[] = [
  { value: "all", label: "All statuses" },
  { value: "active", label: "Active only" },
  { value: "inactive", label: "Inactive only" },
];

const LAST_ACTIVE_OPTIONS: { value: AdminUserLastActiveFilter; label: string }[] = [
  { value: "all", label: "Any activity" },
  { value: "7d", label: "Active in 7 days" },
  { value: "30d", label: "Active in 30 days" },
  { value: "90d", label: "Active in 90 days" },
  { value: "never", label: "Never signed in" },
];

const MFA_OPTIONS: { value: AdminUserMfaFilter; label: string }[] = [
  { value: "all", label: "Any MFA status" },
  { value: "enrolled", label: "MFA enrolled" },
  { value: "not_enrolled", label: "MFA not enrolled" },
];

function notifyBulkResult(
  notify: ReturnType<typeof useNotificationStore.getState>["push"],
  title: string,
  result: { processed: number; skipped: number; errors: Array<{ user_id: string; message: string }> },
  successMessage: string,
) {
  const errorCount = result.errors.length;
  const detail = `${result.processed} updated${result.skipped > 0 ? `, ${result.skipped} skipped` : ""}${
    errorCount > 0 ? `, ${errorCount} failed` : ""
  }.`;

  notify({
    level: errorCount > 0 ? "warning" : "success",
    title,
    message: errorCount > 0 ? detail : successMessage.replace("{count}", String(result.processed)),
  });
}

export function UsersPageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const router = useRouter();
  const searchParams = useSearchParams();
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const scopedUser = user ? { ...user, permissions: effectivePermissions() } : null;
  const canManageRoles = hasPermission(scopedUser, [permissions.roleManage]);
  const canImpersonateUsers = hasPermission(scopedUser, [permissions.userImpersonate]);
  const canManageUsers = hasPermission(scopedUser, [permissions.userManage]);
  const isImpersonating = Boolean(user?.isImpersonating);

  const initialRole = searchParams.get("role") ?? "all";
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<AdminUserStatusFilter>("all");
  const [lastActiveFilter, setLastActiveFilter] = useState<AdminUserLastActiveFilter>("all");
  const [mfaFilter, setMfaFilter] = useState<AdminUserMfaFilter>("all");
  const [roleFilter, setRoleFilter] = useState(initialRole === "all" ? "all" : initialRole);
  const { sort, sorting, onSortingChange, manualSorting } = useServerTableSort({
    defaultSort: "name:asc",
    sortableColumnIds: ["name", "email"],
  });
  const { setPage, debouncedSearch, query } = useAdminUsersIndex(
    search,
    statusFilter,
    lastActiveFilter,
    mfaFilter,
    roleFilter === "all" ? "" : roleFilter,
    sort,
  );
  const { data, isFetching, isLoading, isError } = query;
  const rows = data?.data ?? [];
  const meta = data?.meta;

  const [exporting, setExporting] = useState(false);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailUser, setDetailUser] = useState<AdminUserRow | null>(null);
  const [editing, setEditing] = useState<AdminUserRow | null>(null);
  const [impersonateTarget, setImpersonateTarget] = useState<AdminUserRow | null>(null);
  const [impersonateDialogOpen, setImpersonateDialogOpen] = useState(false);
  const [importErrors, setImportErrors] = useState<string[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [bulkRoles, setBulkRoles] = useState<string[]>(["viewer"]);
  const [selectAllMatching, setSelectAllMatching] = useState(false);
  const [selectingAllMatching, setSelectingAllMatching] = useState(false);
  const [bulkPasswordResults, setBulkPasswordResults] = useState<
    Array<{ email: string; name: string; temporary_password: string }> | null
  >(null);

  const rolesQuery = useAdminRoleCatalog();
  const seatUsageQuery = useQuery({
    queryKey: ["admin", "users", "seat-usage"],
    queryFn: fetchAdminUsersSeatUsage,
    staleTime: 30_000,
  });
  const seatUsage = seatUsageQuery.data ?? null;
  const enabledModules = rolesQuery.data?.enabled_modules;
  const visibleRoleCatalog = useMemo(
    () =>
      filterRolesForEnabledModules(rolesQuery.data?.roles ?? [], {
        enabledModules,
      }),
    [enabledModules, rolesQuery.data?.roles],
  );
  const roleOptions =
    visibleRoleCatalog.length > 0
      ? visibleRoleCatalog.map((r) => r.name)
      : ["viewer", "manager", "tenant_admin"];

  const selectedIdList = useMemo(
    () => Object.keys(rowSelection).filter((id) => rowSelection[id]),
    [rowSelection],
  );
  const selectedCount = selectedIdList.length;
  const totalMatching = meta?.total ?? 0;
  const pageRowIds = useMemo(() => rows.map((row) => row.id), [rows]);
  const allPageRowsSelected =
    pageRowIds.length > 0 && pageRowIds.every((id) => Boolean(rowSelection[id]));
  const canOfferSelectAllMatching =
    !selectAllMatching &&
    allPageRowsSelected &&
    totalMatching > selectedCount &&
    totalMatching > pageRowIds.length;

  useEffect(() => {
    const roleFromUrl = searchParams.get("role") ?? "all";
    setRoleFilter(roleFromUrl === "all" ? "all" : roleFromUrl);
  }, [searchParams]);

  useEffect(() => {
    setRowSelection({});
    setSelectAllMatching(false);
  }, [debouncedSearch, statusFilter, lastActiveFilter, mfaFilter, roleFilter]);

  const invalidateUsers = () => {
    void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
  };

  const openCreate = () => {
    setEditing(null);
    setSheetOpen(true);
  };

  const openEdit = (row: AdminUserRow) => {
    setEditing(row);
    setSheetOpen(true);
  };

  const openView = (row: AdminUserRow) => {
    setDetailUser(row);
    setDetailOpen(true);
  };

  const openImpersonate = (row: AdminUserRow) => {
    if (isImpersonating) {
      notify({
        level: "warning",
        title: "Already impersonating",
        message: "Use the banner to end the current session before starting another.",
      });
      return;
    }
    setImpersonateTarget(row);
    setImpersonateDialogOpen(true);
  };

  const columns = useMemo(
    () =>
      createUsersTableColumns({
        currentUserId: user?.id,
        canImpersonateUsers,
        onView: openView,
        onEdit: openEdit,
        onImpersonate: openImpersonate,
        onMutate: invalidateUsers,
      }),
    [user?.id, canImpersonateUsers, isImpersonating, queryClient],
  );

  const clearSelection = () => {
    setRowSelection({});
    setSelectAllMatching(false);
  };

  const toggleMobileRowSelection = (id: string) => {
    setSelectAllMatching(false);
    setRowSelection((current) => {
      const next = { ...current };
      if (next[id]) {
        delete next[id];
      } else {
        next[id] = true;
      }
      return next;
    });
  };

  const handleRowSelectionChange: OnChangeFn<RowSelectionState> = (updater) => {
    setSelectAllMatching(false);
    setRowSelection(updater);
  };

  const selectAllMatchingUsers = async () => {
    setSelectingAllMatching(true);
    try {
      const result = await fetchAdminUsersIds({
        search: debouncedSearch.trim() || undefined,
        status: statusFilter,
        last_active: lastActiveFilter,
        mfa: mfaFilter,
        role: roleFilter === "all" ? undefined : roleFilter,
        sort,
      });
      const next: RowSelectionState = {};
      for (const id of result.ids) {
        next[id] = true;
      }
      setRowSelection(next);
      setSelectAllMatching(!result.truncated);
      if (result.truncated) {
        notify({
          level: "warning",
          title: "Selection capped",
          message: `Selected ${result.ids.length} of ${result.total} matching users. Narrow filters to include the rest.`,
        });
      }
    } catch (error) {
      notify({
        level: "error",
        title: "Could not select all users",
        message: getErrorMessage(error),
      });
    } finally {
      setSelectingAllMatching(false);
    }
  };

  const bulkDeactivateMutation = useMutation({
    mutationFn: () => bulkDeactivateAdminUsers(selectedIdList),
    onSuccess: (result) => {
      invalidateUsers();
      clearSelection();
      notifyBulkResult(notify, "Bulk deactivate complete", result, "{count} users deactivated.");
    },
    onError: (error) => {
      notify({ level: "error", title: "Bulk deactivate failed", message: getErrorMessage(error) });
    },
  });

  const bulkAssignRoleMutation = useMutation({
    mutationFn: () => bulkAssignRolesAdminUsers(selectedIdList, bulkRoles),
    onSuccess: (result) => {
      invalidateUsers();
      clearSelection();
      const labels = bulkRoles.map(roleLabel).join(", ");
      notifyBulkResult(
        notify,
        "Role assignment complete",
        result,
        `Added ${labels} to {count} users.`,
      );
    },
    onError: (error) => {
      notify({ level: "error", title: "Bulk role assignment failed", message: getErrorMessage(error) });
    },
  });

  const bulkResetPasswordMutation = useMutation({
    mutationFn: () => bulkResetPasswordAdminUsers(selectedIdList),
    onSuccess: (result) => {
      invalidateUsers();
      clearSelection();
      setBulkPasswordResults(result.passwords.map(({ email, name, temporary_password }) => ({
        email,
        name,
        temporary_password,
      })));
      notifyBulkResult(
        notify,
        "Passwords reset",
        result,
        `Reset passwords for {count} users. Copy them before closing the results panel.`,
      );
    },
    onError: (error) => {
      notify({ level: "error", title: "Bulk password reset failed", message: getErrorMessage(error) });
    },
  });

  const bulkPending =
    bulkDeactivateMutation.isPending ||
    bulkAssignRoleMutation.isPending ||
    bulkResetPasswordMutation.isPending ||
    selectingAllMatching;

  const handleBulkDeactivate = () => {
    if (
      window.confirm(
        `Deactivate ${selectedCount} selected user${selectedCount === 1 ? "" : "s"}? Active accounts will not be able to sign in until reactivated. Already inactive users are skipped.`,
      )
    ) {
      bulkDeactivateMutation.mutate();
    }
  };

  const handleBulkAssignRole = () => {
    if (bulkRoles.length === 0) {
      notify({
        level: "warning",
        title: "Choose roles",
        message: "Select at least one role to add.",
      });
      return;
    }
    const labels = bulkRoles.map(roleLabel).join(", ");
    if (
      window.confirm(
        `Add ${bulkRoles.length === 1 ? `the "${labels}" role` : `these roles (${labels})`} to ${selectedCount} selected user${selectedCount === 1 ? "" : "s"}? Existing roles are kept.`,
      )
    ) {
      bulkAssignRoleMutation.mutate();
    }
  };

  const handleBulkResetPassword = () => {
    if (
      window.confirm(
        `Reset passwords for ${selectedCount} selected user${selectedCount === 1 ? "" : "s"}? Unique temporary passwords will be generated and shown once. Active sessions will be revoked.`,
      )
    ) {
      bulkResetPasswordMutation.mutate();
    }
  };

  const downloadBulkPasswordsCsv = () => {
    if (!bulkPasswordResults || bulkPasswordResults.length === 0) {
      return;
    }
    const lines = [
      "email,name,temporary_password",
      ...bulkPasswordResults.map(
        (row) =>
          `"${row.email.replaceAll('"', '""')}","${row.name.replaceAll('"', '""')}","${row.temporary_password.replaceAll('"', '""')}"`,
      ),
    ];
    const blob = new Blob([`${lines.join("\n")}\n`], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `temporary-passwords-${new Date().toISOString().slice(0, 10)}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  };

  const updateRoleFilter = (value: string) => {
    setRoleFilter(value);
    setPage(1);
    const params = new URLSearchParams(searchParams.toString());
    if (value === "all") {
      params.delete("role");
    } else {
      params.set("role", value);
    }
    const query = params.toString();
    router.replace(query ? `/users?${query}` : "/users", { scroll: false });
  };

  const importMutation = useMutation({
    mutationFn: importAdminUsers,
    onSuccess: (result) => {
      invalidateUsers();
      setImportErrors(result.errors);
      notify({
        level: result.errors.length > 0 ? "warning" : "success",
        title: "Import complete",
        message: `${result.created} created, ${result.skipped} skipped${result.errors.length ? `, ${result.errors.length} errors` : ""}.`,
      });
    },
    onError: (error) => {
      notify({ level: "error", title: "Import failed", message: getErrorMessage(error) });
    },
  });

  async function handleExport() {
    setExporting(true);
    try {
      const blob = await exportAdminUsersCsv({
        search: debouncedSearch.trim() || undefined,
        status: statusFilter,
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = `users-${new Date().toISOString().slice(0, 10)}.csv`;
      link.click();
      URL.revokeObjectURL(url);
    } catch {
      notify({ level: "error", title: "Export failed", message: "Unable to download user CSV." });
    } finally {
      setExporting(false);
    }
  }

  function downloadImportTemplate() {
    const blob = new Blob([ADMIN_USERS_IMPORT_TEMPLATE_CSV], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "users-import-template.csv";
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <PermissionGate requiredPermissions={[permissions.userManage]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">Team & Access</h1>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
              Manage users, role assignments, and bulk onboarding. Deactivate former staff instead of deleting
              when audit history matters.
            </p>
            {seatUsage ? (
              <p
                className={cn(
                  "mt-2 text-xs",
                  seatUsage.paid_seats_full ? "font-medium text-destructive" : "text-muted-foreground",
                )}
              >
                Paid seats {seatUsage.seat_used}/{seatUsage.seat_limit}
                {seatUsage.paid_seats_full
                  ? " — limit reached; new users cannot be added until seats are freed or raised."
                  : ` · ${seatUsage.seats_available} available`}
              </p>
            ) : null}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" disabled={exporting} onClick={() => void handleExport()}>
              <Download className="size-3.5" />
              {exporting ? "Exporting…" : "Export CSV"}
            </Button>
            {canManageRoles ? (
              <Link
                href="/users/roles"
                prefetch={false}
                className={cn(buttonVariants({ variant: "outline", size: "sm" }))}
              >
                Roles & permissions
              </Link>
            ) : null}
            <Button
              size="sm"
              className="gap-1.5"
              onClick={openCreate}
              disabled={Boolean(seatUsage?.paid_seats_full)}
              title={
                seatUsage?.paid_seats_full
                  ? "Paid seat limit reached — deactivate a user or raise the seat limit"
                  : undefined
              }
            >
              <UserPlus className="size-3.5" />
              Add user
            </Button>
          </div>
        </header>

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <div className="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div className="min-w-0 flex-1">
              <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="users-search">
                Filter
              </label>
              <Input
                id="users-search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Name or email"
                className="h-11 w-full text-base sm:h-9 sm:max-w-md sm:text-sm"
              />
            </div>
            <FilterSelect
              id="users-status"
              label="Status"
              value={statusFilter}
              onChange={(value) => {
                setStatusFilter(value as AdminUserStatusFilter);
                setPage(1);
              }}
              touchFriendly
              className="w-full min-w-[10rem] sm:w-auto"
            >
              {STATUS_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect
              id="users-last-active"
              label="Last active"
              value={lastActiveFilter}
              onChange={(value) => {
                setLastActiveFilter(value as AdminUserLastActiveFilter);
                setPage(1);
              }}
              touchFriendly
              className="w-full min-w-[10rem] sm:w-auto"
            >
              {LAST_ACTIVE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect
              id="users-mfa"
              label="MFA"
              value={mfaFilter}
              onChange={(value) => {
                setMfaFilter(value as AdminUserMfaFilter);
                setPage(1);
              }}
              touchFriendly
              className="w-full min-w-[10rem] sm:w-auto"
            >
              {MFA_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </FilterSelect>
            <FilterSelect
              id="users-role"
              label="Role"
              value={roleFilter}
              onChange={updateRoleFilter}
              touchFriendly
              className="w-full min-w-[10rem] sm:w-auto"
            >
              <option value="all">Any role</option>
              {roleOptions.map((role) => (
                <option key={role} value={role}>
                  {roleLabel(role)}
                </option>
              ))}
            </FilterSelect>
          </div>

          {selectedCount > 0 ? (
            <div className="space-y-2 border-b border-border bg-muted/40 px-4 py-3">
              <div className="flex flex-wrap items-center gap-3">
                <p className="text-sm font-medium text-foreground">
                  {selectAllMatching && totalMatching === selectedCount
                    ? `All ${selectedCount} matching selected`
                    : `${selectedCount} selected`}
                </p>
                <Button variant="outline" size="sm" disabled={bulkPending} onClick={handleBulkDeactivate}>
                  Deactivate
                </Button>
                <Button variant="outline" size="sm" disabled={bulkPending} onClick={handleBulkResetPassword}>
                  Reset passwords
                </Button>
                <div className="flex flex-wrap items-center gap-2">
                  <UsersBulkRolePicker
                    id="users-bulk-role"
                    roleOptions={roleOptions}
                    value={bulkRoles}
                    onChange={setBulkRoles}
                    disabled={bulkPending}
                  />
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={bulkPending || bulkRoles.length === 0}
                    onClick={handleBulkAssignRole}
                  >
                    {bulkRoles.length > 1 ? "Add roles" : "Add role"}
                  </Button>
                </div>
                <Button variant="ghost" size="sm" disabled={bulkPending} onClick={clearSelection}>
                  Clear
                </Button>
              </div>
              {canOfferSelectAllMatching ? (
                <p className="text-sm text-muted-foreground">
                  {selectedCount} on this page.{" "}
                  <button
                    type="button"
                    className="font-medium text-foreground underline-offset-2 hover:underline disabled:opacity-50"
                    disabled={selectingAllMatching}
                    onClick={() => void selectAllMatchingUsers()}
                  >
                    {selectingAllMatching ? "Selecting…" : `Select all ${totalMatching} matching filters`}
                  </button>
                </p>
              ) : null}
            </div>
          ) : null}

          <div className="md:hidden">
            {rows.length === 0 ? (
              <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                {isLoading ? "Loading…" : "No users match this filter."}
              </p>
            ) : (
              <ul className="divide-y divide-border">
                {rows.map((row) => (
                  <li key={row.id} className="space-y-2 px-4 py-4">
                    <div className="flex items-start gap-3">
                      <Checkbox
                        className="mt-1"
                        checked={!!rowSelection[row.id]}
                        onCheckedChange={() => toggleMobileRowSelection(row.id)}
                        aria-label={`Select ${row.name}`}
                      />
                      <div className="min-w-0 flex-1 space-y-2">
                        <div className="flex items-start justify-between gap-2">
                          <button type="button" className="text-left" onClick={() => openView(row)}>
                            <p className="font-medium text-foreground hover:text-primary">{row.name}</p>
                            <p className="text-sm text-muted-foreground">{row.email}</p>
                          </button>
                          <UserStatusBadge active={row.is_active} />
                        </div>
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                          <span>Last active: {formatLastActive(row.last_active_at)}</span>
                          <UserMfaStatusBadge mfaEnrolled={row.mfa_enrolled} mfaRequired={row.mfa_required} />
                        </div>
                        <UserAuthMethodsBadges methods={row.auth_methods} />
                        <div className="flex flex-wrap gap-1">
                          {row.roles.map((role) => (
                            <Badge key={role} variant="secondary">
                              {roleLabel(role)}
                            </Badge>
                          ))}
                        </div>
                        <UserRowActions
                          row={row}
                          currentUserId={user?.id}
                          canImpersonateUsers={canImpersonateUsers}
                          onView={openView}
                          onEdit={openEdit}
                          onImpersonate={openImpersonate}
                          onMutate={invalidateUsers}
                        />
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="hidden md:block">
            <RegistryDataTableView
              columns={columns}
              data={rows}
              getRowId={(r) => r.id}
              rowSelection={rowSelection}
              onRowSelectionChange={handleRowSelectionChange}
              enableRowSelection
              isLoading={isLoading && rows.length === 0}
              isEmpty={!isLoading && rows.length === 0}
              emptyMessage="No users match this filter."
              getRowClassName={(row) => (!row.original.is_active ? "opacity-75" : undefined)}
              scrollClassName="max-h-none"
              enableColumnVisibility
              columnVisibilityStorageKey="toweros.table.columns.admin.users"
              sorting={sorting}
              onSortingChange={onSortingChange}
              manualSorting={manualSorting}
            />
          </div>

          {meta ? <PaginatedListFooter meta={meta} onPageChange={setPage} isPending={isFetching} /> : null}
        </div>

        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-base font-medium text-foreground">Bulk import</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Upload a CSV with columns <span className="font-mono text-xs">email</span>,{" "}
            <span className="font-mono text-xs">name</span>, and optional{" "}
            <span className="font-mono text-xs">role</span>. Multiple roles: comma-separate in one cell (quote the cell
            in Excel), e.g.{" "}
            <span className="font-mono text-xs">e_approval_approver,e_approval_requestor,e_approval_viewer</span>.
            Passwords are generated automatically. Existing emails are skipped.
          </p>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" onClick={downloadImportTemplate}>
              Download template
            </Button>
            <Button variant="outline" size="sm" onClick={() => fileInputRef.current?.click()} disabled={importMutation.isPending}>
              Choose file
            </Button>
            <input
              ref={fileInputRef}
              type="file"
              accept=".csv,text/csv"
              className="hidden"
              onChange={(event) => {
                const file = event.target.files?.[0];
                if (file) {
                  setImportErrors([]);
                  importMutation.mutate(file);
                  event.target.value = "";
                }
              }}
            />
            {importMutation.isPending ? (
              <span className="text-sm text-muted-foreground">Importing…</span>
            ) : null}
          </div>
          {importErrors.length > 0 ? (
            <ul className="mt-3 max-h-40 space-y-1 overflow-y-auto rounded-md border border-border bg-muted/30 p-3 text-xs text-muted-foreground">
              {importErrors.map((err) => (
                <li key={err}>{err}</li>
              ))}
            </ul>
          ) : null}
        </div>

        {isError ? (
          <p className="text-sm text-destructive">Could not load users. Confirm you have the user:manage permission.</p>
        ) : null}

        <AdminUserDetailDrawer
          user={detailUser}
          open={detailOpen}
          onOpenChange={setDetailOpen}
          onEdit={openEdit}
          onImpersonate={openImpersonate}
          canImpersonate={canImpersonateUsers}
          canManageUsers={canManageUsers}
        />

        <AdminUserFormSheet
          open={sheetOpen}
          onOpenChange={setSheetOpen}
          editing={editing}
          roleOptions={roleOptions}
          roleCatalog={rolesQuery.data?.roles ?? []}
          enabledModules={enabledModules}
          seatUsage={seatUsage}
          onSaved={invalidateUsers}
        />

        {bulkPasswordResults ? (
          <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h2 className="text-base font-medium text-foreground">Temporary passwords</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Shown once. Download or copy now — closing this panel clears them from the browser.
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" onClick={downloadBulkPasswordsCsv}>
                  Download CSV
                </Button>
                <Button type="button" variant="ghost" size="sm" onClick={() => setBulkPasswordResults(null)}>
                  Close
                </Button>
              </div>
            </div>
            <ul className="mt-3 max-h-64 space-y-2 overflow-y-auto rounded-md border border-border bg-muted/20 p-3 text-xs">
              {bulkPasswordResults.map((row) => (
                <li key={row.email} className="flex flex-wrap items-center justify-between gap-2">
                  <span className="min-w-0">
                    <span className="font-medium text-foreground">{row.name}</span>{" "}
                    <span className="text-muted-foreground">{row.email}</span>
                  </span>
                  <span className="font-mono text-foreground">{row.temporary_password}</span>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <AdminUserImpersonateDialog
          user={impersonateTarget}
          open={impersonateDialogOpen}
          onOpenChange={setImpersonateDialogOpen}
        />
      </div>
    </PermissionGate>
  );
}
