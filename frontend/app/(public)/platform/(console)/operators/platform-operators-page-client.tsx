"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import { createPlatformOperatorsTableColumns } from "@/components/platform/platform-operators-table-columns";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormInput } from "@/components/forms/form-input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformCreateOperator,
  platformDeleteOperator,
  platformFetchRoleCatalog,
  platformListOperators,
  platformUpdateOperator,
  type PlatformOperatorRow,
} from "@/lib/api/modules/platform-api";
import { platformHasPermission, platformRoleLabel, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

export function PlatformOperatorsPageClient() {
  const user = usePlatformAuthStore((s) => s.user);
  const notify = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const canManage = platformHasPermission(user, PLATFORM_PERMS.operatorsManage);

  const [sheetOpen, setSheetOpen] = useState(false);
  const [editing, setEditing] = useState<PlatformOperatorRow | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState("support");

  const operatorsQuery = useQuery({
    queryKey: ["platform", "operators"],
    queryFn: platformListOperators,
  });

  const rolesQuery = useQuery({
    queryKey: ["platform", "roles", "catalog"],
    queryFn: platformFetchRoleCatalog,
  });

  const roles = rolesQuery.data?.roles ?? ["superadmin", "billing", "support", "viewer"];
  const operators = operatorsQuery.data ?? [];

  const resetForm = () => {
    setEditing(null);
    setName("");
    setEmail("");
    setPassword("");
    setRole("support");
  };

  const openCreate = () => {
    resetForm();
    setSheetOpen(true);
  };

  const openEdit = (row: PlatformOperatorRow) => {
    setEditing(row);
    setName(row.name);
    setEmail(row.email);
    setPassword("");
    setRole(row.platform_role);
    setSheetOpen(true);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editing) {
        return platformUpdateOperator(editing.id, {
          name,
          email,
          platform_role: role,
          ...(password ? { password } : {}),
        });
      }
      return platformCreateOperator({ name, email, password, platform_role: role });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "operators"] });
      setSheetOpen(false);
      resetForm();
      notify({ level: "success", title: "Operator saved", message: "Platform operator updated." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not save operator", message: getErrorMessage(error) }),
  });

  const deleteMutation = useMutation({
    mutationFn: (operatorId: string) => platformDeleteOperator(operatorId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "operators"] });
      notify({ level: "success", title: "Operator removed", message: "Platform operator deleted." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not remove operator", message: getErrorMessage(error) }),
  });

  const columns = useMemo(
    () =>
      createPlatformOperatorsTableColumns({
        canManage,
        currentUserId: user?.id,
        onEdit: openEdit,
        onDelete: (id) => deleteMutation.mutate(id),
        deletePending: deleteMutation.isPending,
      }),
    [canManage, user?.id, deleteMutation],
  );

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">Platform operators</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Central superadmin accounts, roles, and Microsoft SSO provisioning.
          </p>
        </div>
        {canManage ? (
          <Button type="button" onClick={openCreate}>
            Add operator
          </Button>
        ) : null}
      </header>

      <Card className="rounded-xl shadow-sm">
        <CardHeader>
          <CardTitle className="text-base font-medium">Operators</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <RegistryDataTableView
            columns={columns}
            data={operators}
            getRowId={(row) => row.id}
            isLoading={operatorsQuery.isLoading || (operatorsQuery.isFetching && operators.length === 0)}
            isEmpty={!operatorsQuery.isLoading && operators.length === 0}
            emptyMessage="No platform operators found."
            enableColumnVisibility
            columnVisibilityStorageKey="toweros.table.columns.platform.operators"
            manualSorting={false}
          />
        </CardContent>
      </Card>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className="sm:max-w-md">
          <SheetHeader>
            <SheetTitle>{editing ? "Edit operator" : "Add operator"}</SheetTitle>
          </SheetHeader>
          <div className="space-y-4 px-4 py-2">
            <FormInput label="Name" value={name} onChange={(e) => setName(e.target.value)} />
            <FormInput label="Email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            <FormInput
              label={editing ? "New password (optional)" : "Password"}
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
            <div className="space-y-2">
              <Label htmlFor="operator-role">Role</Label>
              <Select id="operator-role" className="h-9" value={role} onChange={(e) => setRole(e.target.value)}>
                {roles.map((item) => (
                  <option key={item} value={item}>
                    {platformRoleLabel(item)}
                  </option>
                ))}
              </Select>
            </div>
          </div>
          <SheetFooter>
            <Button type="button" variant="outline" onClick={() => setSheetOpen(false)}>
              Cancel
            </Button>
            <Button
              type="button"
              disabled={saveMutation.isPending || !name || !email || (!editing && password.length < 8)}
              onClick={() => saveMutation.mutate()}
            >
              {saveMutation.isPending ? "Saving…" : "Save"}
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </div>
  );
}
