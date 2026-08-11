"use client";

import { useMutation } from "@tanstack/react-query";
import { Check, Copy } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Separator } from "@/components/ui/separator";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import {
  createAdminUser,
  updateAdminUser,
  type AdminUserRow,
  type AdminUserSeatUsage,
} from "@/lib/api/modules/admin-users-api";
import type { AdminRoleRow } from "@/lib/api/modules/admin-roles-api";
import { groupRolesByType } from "@/lib/rbac/role-groups";
import { groupPermissionsByModule, permissionLabel } from "@/lib/rbac/permission-groups";
import { getTenantRoleGuide } from "@/lib/rbac/tenant-role-guides";
import { useNotificationStore } from "@/stores/notification-store";

export type UserFormState = {
  name: string;
  email: string;
  password: string;
  roles: string[];
};

const emptyForm = (): UserFormState => ({
  name: "",
  email: "",
  password: "",
  roles: ["viewer"],
});

function roleLabel(name: string): string {
  return name.replace(/_/g, " ");
}

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing: AdminUserRow | null;
  roleOptions: string[];
  roleCatalog?: AdminRoleRow[];
  enabledModules?: string[];
  seatUsage?: AdminUserSeatUsage | null;
  onSaved: () => void;
};

export function AdminUserFormSheet({
  open,
  onOpenChange,
  editing,
  roleOptions,
  roleCatalog = [],
  enabledModules,
  seatUsage = null,
  onSaved,
}: Props) {
  const notify = useNotificationStore((state) => state.push);
  const [form, setForm] = useState<UserFormState>(emptyForm());
  const [setPasswordManually, setSetPasswordManually] = useState(false);
  const [generatedPassword, setGeneratedPassword] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }
    if (editing) {
      setForm({
        name: editing.name,
        email: editing.email,
        password: "",
        roles: editing.roles.length > 0 ? editing.roles : ["viewer"],
      });
      setSetPasswordManually(false);
      setGeneratedPassword(null);
      setCopied(false);
      return;
    }
    setForm(emptyForm());
    setSetPasswordManually(false);
    setGeneratedPassword(null);
    setCopied(false);
  }, [open, editing]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (editing) {
        return updateAdminUser(editing.id, {
          name: form.name.trim(),
          email: form.email.trim(),
          roles: form.roles,
          password: form.password.trim() || undefined,
        });
      }
      return createAdminUser({
        name: form.name.trim(),
        email: form.email.trim(),
        roles: form.roles,
        password: setPasswordManually && form.password.trim() ? form.password.trim() : undefined,
      });
    },
    onSuccess: (result) => {
      onSaved();
      const tempPassword =
        "generated_password" in result && typeof result.generated_password === "string"
          ? result.generated_password
          : null;
      if (!editing && tempPassword) {
        setGeneratedPassword(tempPassword);
        notify({
          level: "success",
          title: "User created",
          message: `${result.email} — copy the generated password before closing.`,
        });
        return;
      }
      notify({
        level: "success",
        title: editing ? "User updated" : "User created",
        message: result.email,
      });
      onOpenChange(false);
    },
    onError: (error) => {
      notify({ level: "error", title: "Save failed", message: getErrorMessage(error) });
    },
  });

  const isCreate = !editing;
  const showCredentials = isCreate && !generatedPassword;
  const paidSeatsFull = Boolean(seatUsage?.paid_seats_full);
  const createBlockedBySeats = isCreate && paidSeatsFull;
  const canSubmit =
    form.name.trim() !== "" &&
    form.email.trim() !== "" &&
    (!setPasswordManually || form.password.trim().length >= 8) &&
    !createBlockedBySeats;

  const toggleRole = (roleName: string) => {
    if (createBlockedBySeats) {
      notify({
        level: "warning",
        title: "Paid seats full",
        message: `Seat limit reached (${seatUsage?.seat_used}/${seatUsage?.seat_limit}). Deactivate a user or raise the seat limit before adding accounts.`,
      });
      return;
    }
    setForm((prev) => {
      const has = prev.roles.includes(roleName);
      const next = has ? prev.roles.filter((r) => r !== roleName) : [...prev.roles, roleName];
      return { ...prev, roles: next.length > 0 ? next : ["viewer"] };
    });
  };

  async function copyPassword() {
    if (!generatedPassword) {
      return;
    }
    try {
      await navigator.clipboard.writeText(generatedPassword);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      notify({ level: "error", title: "Copy failed", message: "Copy the password manually." });
    }
  }

  const roleGroups = useMemo(() => {
    const catalog =
      roleCatalog.length > 0
        ? roleCatalog
        : roleOptions.map((name) => ({
            id: 0,
            name,
            is_baseline: ["tenant_admin", "billing", "viewer", "manager"].includes(name),
            permissions: [] as string[],
            user_count: 0,
          }));

    const catalogNames = new Set(catalog.map((role) => role.name));
    const assignedExtras = (editing?.roles ?? [])
      .filter((name) => !catalogNames.has(name))
      .map((name) => ({
        id: 0,
        name,
        is_baseline: ["tenant_admin", "billing", "viewer", "manager"].includes(name),
        is_system: true,
        permissions: [] as string[],
        user_count: 0,
      }));

    return groupRolesByType([...catalog, ...assignedExtras], {
      enabledModules,
      alwaysIncludeRoleNames: editing?.roles ?? form.roles,
    });
  }, [editing?.roles, enabledModules, form.roles, roleCatalog, roleOptions]);

  const effectivePermissionGroups = useMemo(
    () => groupPermissionsByModule(editing?.permissions ?? []),
    [editing?.permissions],
  );

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col p-0 sm:max-w-lg">
        <SheetHeader className="border-b border-border px-4 pb-4">
          <SheetTitle>{editing ? "Edit user" : "Add user"}</SheetTitle>
          <SheetDescription>
            {editing
              ? "Update profile, roles, or password. Deactivated users stay in the directory until permanently deleted."
              : "Create an organization account. A secure password is generated unless you set one manually."}
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 space-y-6 overflow-y-auto px-4 py-5">
          {generatedPassword ? (
            <div className="rounded-lg border border-primary/30 bg-primary/5 p-4">
              <p className="text-sm font-medium text-foreground">Temporary password</p>
              <p className="mt-1 font-mono text-sm text-muted-foreground">{generatedPassword}</p>
              <p className="mt-2 text-xs text-muted-foreground">
                Share this securely with the user. It will not be shown again after you close this panel.
              </p>
              <Button type="button" variant="outline" size="sm" className="mt-3 gap-2" onClick={() => void copyPassword()}>
                {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                {copied ? "Copied" : "Copy password"}
              </Button>
            </div>
          ) : (
            <>
              {seatUsage ? (
                <div
                  className={`rounded-lg border px-3 py-2.5 text-xs ${
                    paidSeatsFull
                      ? "border-destructive/40 bg-destructive/5 text-destructive"
                      : "border-border bg-muted/30 text-muted-foreground"
                  }`}
                >
                  Paid seats:{" "}
                  <span className="font-medium text-foreground">
                    {seatUsage.seat_used}/{seatUsage.seat_limit}
                  </span>
                  {paidSeatsFull
                    ? " — limit reached. Deactivate a user or raise the seat limit before adding accounts."
                    : ` · ${seatUsage.seats_available} available · ${seatUsage.viewer_seats_used} viewer-only (free while under limit)`}
                </div>
              ) : null}
              <section className="space-y-4">
                <h3 className="text-sm font-medium text-foreground">Profile</h3>
                <FormInput
                  label="Full name"
                  value={form.name}
                  onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                  autoComplete="name"
                  placeholder="e.g. Maria Santos"
                />
                <FormInput
                  label="Work email"
                  type="email"
                  value={form.email}
                  onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                  autoComplete="email"
                  placeholder="name@company.com"
                />
              </section>

              <Separator />

              <section className="space-y-3">
                <div>
                  <h3 className="text-sm font-medium text-foreground">Roles</h3>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Select one or more roles. At least one role is required. Only roles for modules enabled
                    on this organization are listed.
                  </p>
                </div>
                <div className="space-y-4">
                  {roleGroups.map((group) => (
                    <div key={group.id} className="space-y-2">
                      <p className="text-xs font-medium text-muted-foreground">{group.label}</p>
                      <div className="grid gap-2 sm:grid-cols-1">
                        {group.roles.map((role) => {
                          const active = form.roles.includes(role.name);
                          const guide = getTenantRoleGuide(role.name);
                          return (
                            <button
                              key={role.name}
                              type="button"
                              onClick={() => toggleRole(role.name)}
                              className={`rounded-lg border px-3 py-2.5 text-left text-sm transition-colors ${
                                active
                                  ? "border-primary bg-primary/10 text-primary"
                                  : "border-border bg-background text-muted-foreground hover:border-primary/40 hover:text-foreground"
                              }`}
                            >
                              <span className="font-medium text-foreground">{roleLabel(role.name)}</span>
                              {guide ? (
                                <span className="mt-1 block text-xs leading-snug opacity-90">{guide.summary}</span>
                              ) : null}
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  ))}
                </div>
                <p className="text-xs text-muted-foreground">
                  Gate approvals also require the user as SAQ / PMO / CME owner on each rollout (edit rollout metadata).
                  Use <span className="font-medium">manager</span> if one person covers multiple disciplines.
                </p>
                {editing && effectivePermissionGroups.length > 0 ? (
                  <div className="rounded-lg border border-border/60 bg-muted/10 p-3">
                    <p className="text-xs font-medium text-foreground">
                      Current effective permissions ({editing.permissions.length})
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Saved permissions from assigned roles. Re-save after role changes to refresh access.
                    </p>
                    <div className="mt-3 space-y-2">
                      {effectivePermissionGroups.slice(0, 3).map((group) => (
                        <div key={group.id}>
                          <p className="text-[11px] font-medium text-muted-foreground">{group.label}</p>
                          <p className="mt-1 text-xs text-muted-foreground">
                            {group.permissions.slice(0, 4).map(permissionLabel).join(" · ")}
                            {group.permissions.length > 4 ? ` · +${group.permissions.length - 4} more` : ""}
                          </p>
                        </div>
                      ))}
                      {effectivePermissionGroups.length > 3 ? (
                        <p className="text-xs text-muted-foreground">
                          +{effectivePermissionGroups.length - 3} more module groups — open user profile for full list.
                        </p>
                      ) : null}
                    </div>
                  </div>
                ) : null}
              </section>

              {showCredentials ? (
                <>
                  <Separator />
                  <section className="space-y-4">
                    <div>
                      <h3 className="text-sm font-medium text-foreground">Sign-in credentials</h3>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        Leave unchecked to auto-generate a one-time password after create.
                      </p>
                    </div>
                    <label className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border px-3 py-2.5">
                      <Checkbox
                        className="mt-0.5"
                        checked={setPasswordManually}
                        onCheckedChange={(v) => {
                          const checked = v === true;
                          setSetPasswordManually(checked);
                          if (!checked) {
                            setForm((f) => ({ ...f, password: "" }));
                          }
                        }}
                      />
                      <span className="text-sm text-foreground">Set password manually</span>
                    </label>
                    {setPasswordManually ? (
                      <FormInput
                        label="Password"
                        type="password"
                        autoComplete="new-password"
                        value={form.password}
                        onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                        placeholder="Minimum 8 characters"
                      />
                    ) : null}
                  </section>
                </>
              ) : null}

              {editing ? (
                <>
                  <Separator />
                  <section className="space-y-4">
                    <div>
                      <h3 className="text-sm font-medium text-foreground">Reset password</h3>
                      <p className="mt-0.5 text-xs text-muted-foreground">Leave blank to keep the current password.</p>
                    </div>
                    <FormInput
                      label="New password"
                      type="password"
                      autoComplete="new-password"
                      value={form.password}
                      onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
                      placeholder="Optional"
                    />
                  </section>
                </>
              ) : null}
            </>
          )}
        </div>

        <SheetFooter className="border-t border-border px-4 py-4 sm:flex-row sm:justify-end">
          {generatedPassword ? (
            <Button
              className="w-full sm:w-auto"
              onClick={() => {
                onOpenChange(false);
              }}
            >
              Done
            </Button>
          ) : (
            <>
              <Button variant="outline" className="w-full sm:w-auto" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button
                className="w-full sm:w-auto"
                disabled={saveMutation.isPending || !canSubmit}
                onClick={() => saveMutation.mutate()}
              >
                {saveMutation.isPending
                  ? "Saving…"
                  : createBlockedBySeats
                    ? "Seat limit reached"
                    : editing
                      ? "Save changes"
                      : "Create user"}
              </Button>
            </>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
