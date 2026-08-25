"use client";

import { useMutation } from "@tanstack/react-query";
import type { ColumnDef } from "@tanstack/react-table";

import {
  UserAuthMethodsBadges,
  UserMfaStatusBadge,
} from "@/components/admin/admin-user-security-badges";
import { Badge } from "@/components/ui/badge";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { createRowSelectionColumn } from "@/components/ui/data-table-row-selection";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import { entraLicenseChipLabel } from "@/lib/admin/entra-license";
import { formatLastActive } from "@/lib/admin/user-display";
import { getErrorMessage } from "@/lib/api/error";
import {
  deactivateAdminUser,
  deleteAdminUser,
  reactivateAdminUser,
  type AdminUserRow,
} from "@/lib/api/modules/admin-users-api";
import { useNotificationStore } from "@/stores/notification-store";

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

export function UserStatusBadge({ active }: { active: boolean }) {
  return active ? (
    <Badge variant="secondary" className="border-success/30 bg-success/10 text-success">
      Active
    </Badge>
  ) : (
    <Badge variant="secondary" className="text-muted-foreground">
      Inactive
    </Badge>
  );
}

export function UserRowActions({
  row,
  currentUserId,
  canImpersonateUsers,
  onEdit,
  onView,
  onImpersonate,
  onMutate,
}: {
  row: AdminUserRow;
  currentUserId: string | undefined;
  canImpersonateUsers: boolean;
  onEdit: (row: AdminUserRow) => void;
  onView: (row: AdminUserRow) => void;
  onImpersonate: (row: AdminUserRow) => void;
  onMutate: () => void;
}) {
  const notify = useNotificationStore((state) => state.push);
  const isSelf = row.id === currentUserId;

  const deactivateMutation = useMutation({
    mutationFn: () => deactivateAdminUser(row.id),
    onSuccess: () => {
      onMutate();
      notify({ level: "success", title: "User deactivated", message: `${row.email} can no longer sign in.` });
    },
    onError: (error) => {
      notify({ level: "error", title: "Deactivate failed", message: getErrorMessage(error) });
    },
  });

  const reactivateMutation = useMutation({
    mutationFn: () => reactivateAdminUser(row.id),
    onSuccess: () => {
      onMutate();
      notify({ level: "success", title: "User reactivated", message: row.email });
    },
    onError: (error) => {
      notify({ level: "error", title: "Reactivate failed", message: getErrorMessage(error) });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAdminUser(row.id),
    onSuccess: () => {
      onMutate();
      notify({ level: "success", title: "User deleted", message: "Record removed permanently." });
    },
    onError: (error) => {
      notify({ level: "error", title: "Delete failed", message: getErrorMessage(error) });
    },
  });

  const pending =
    deactivateMutation.isPending || reactivateMutation.isPending || deleteMutation.isPending;

  const showImpersonate = canImpersonateUsers && row.can_impersonate;

  return (
    <RowActionsMenu
      disabled={pending}
      items={[
        {
          key: "view",
          label: "View details",
          onSelect: () => onView(row),
        },
        {
          key: "impersonate",
          label: "View as user",
          hidden: !showImpersonate,
          onSelect: () => onImpersonate(row),
        },
        {
          key: "edit",
          label: "Edit",
          onSelect: () => onEdit(row),
        },
        {
          key: "deactivate",
          label: "Deactivate",
          hidden: !row.is_active,
          disabled: isSelf || pending,
          onSelect: () => {
            if (window.confirm(`Deactivate ${row.email}? They will not be able to sign in until reactivated.`)) {
              deactivateMutation.mutate();
            }
          },
        },
        {
          key: "reactivate",
          label: "Reactivate",
          hidden: row.is_active,
          disabled: pending,
          onSelect: () => reactivateMutation.mutate(),
        },
        { type: "separator", key: "sep", hidden: row.is_active },
        {
          key: "delete",
          label: "Delete",
          hidden: row.is_active,
          destructive: true,
          disabled: isSelf || pending,
          onSelect: () => {
            if (
              window.confirm(
                `Permanently delete ${row.email}? This cannot be undone. Prefer deactivation for former staff.`,
              )
            ) {
              deleteMutation.mutate();
            }
          },
        },
      ]}
    />
  );
}

export function createUsersTableColumns(options: {
  currentUserId: string | undefined;
  canImpersonateUsers: boolean;
  organizationLabel: string;
  onView: (row: AdminUserRow) => void;
  onEdit: (row: AdminUserRow) => void;
  onImpersonate: (row: AdminUserRow) => void;
  onMutate: () => void;
}): ColumnDef<AdminUserRow>[] {
  return [
    createRowSelectionColumn<AdminUserRow>(),
    {
      accessorKey: "name",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
      cell: ({ row }) => (
        <button
          type="button"
          className="text-left font-medium text-foreground hover:text-primary"
          onClick={() => options.onView(row.original)}
        >
          {row.original.name}
        </button>
      ),
    },
    {
      accessorKey: "email",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Email" />,
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.email}</span>,
    },
    {
      id: "department",
      header: "Department",
      cell: ({ row }) => {
        const department = row.original.department?.trim();
        if (!department) {
          return <span className="text-sm text-muted-foreground">—</span>;
        }
        return <span className="text-sm text-foreground">{department}</span>;
      },
    },
    {
      id: "reports_to",
      header: "Reports to",
      cell: ({ row }) => {
        const manager = row.original.manager;
        const entraName = row.original.entra_manager_name ?? row.original.entra_manager_email;
        if (manager) {
          return <span className="text-sm text-foreground">{manager.name}</span>;
        }
        if (entraName) {
          return (
            <span
              className="text-sm text-muted-foreground"
              title={`In Microsoft Entra, not a ${options.organizationLabel} user yet`}
            >
              {entraName}
            </span>
          );
        }
        return <span className="text-sm text-muted-foreground">—</span>;
      },
    },
    {
      id: "license",
      header: "License",
      cell: ({ row }) => {
        const label = entraLicenseChipLabel(row.original.entra_license_label, row.original.entra_license_names);
        if (!label) {
          return <span className="text-sm text-muted-foreground">—</span>;
        }
        return (
          <Badge
            variant="outline"
            className="max-w-[11rem] truncate text-[11px] font-medium"
            title={row.original.entra_license_names?.join(", ") || label}
          >
            {label}
          </Badge>
        );
      },
    },
    {
      id: "status",
      header: "Status",
      cell: ({ row }) => <UserStatusBadge active={row.original.is_active} />,
    },
    {
      id: "last_active",
      header: "Last active",
      cell: ({ row }) => (
        <span className="text-sm text-muted-foreground">{formatLastActive(row.original.last_active_at)}</span>
      ),
    },
    {
      id: "sign_in",
      header: "Sign-in",
      cell: ({ row }) => <UserAuthMethodsBadges methods={row.original.auth_methods} />,
    },
    {
      id: "mfa",
      header: "MFA",
      cell: ({ row }) => (
        <UserMfaStatusBadge mfaEnrolled={row.original.mfa_enrolled} mfaRequired={row.original.mfa_required} />
      ),
    },
    {
      id: "roles",
      header: "Roles",
      cell: ({ row }) => (
        <div className="flex flex-wrap gap-1">
          {row.original.roles.slice(0, 2).map((role) => (
            <Badge key={role} variant="secondary">
              {roleLabel(role)}
            </Badge>
          ))}
          {row.original.roles.length > 2 ? (
            <Badge variant="ghost">+{row.original.roles.length - 2}</Badge>
          ) : null}
        </div>
      ),
    },
    {
      id: "actions",
      header: () => <span className="block w-full text-right">Actions</span>,
      cell: ({ row }) => (
        <UserRowActions
          row={row.original}
          currentUserId={options.currentUserId}
          canImpersonateUsers={options.canImpersonateUsers}
          onView={options.onView}
          onEdit={options.onEdit}
          onImpersonate={options.onImpersonate}
          onMutate={options.onMutate}
        />
      ),
      meta: { className: "w-[72px] text-right" },
    },
  ];
}
