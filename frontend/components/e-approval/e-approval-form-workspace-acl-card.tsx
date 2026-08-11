"use client";

import { useQuery } from "@tanstack/react-query";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { fetchAdminRoleCatalog } from "@/lib/api/modules/admin-roles-api";
import { fetchEApprovalFormsIndex } from "@/lib/api/modules/e-approval-api";
import {
  rolesFromEditorString,
  rolesToEditorString,
  type FormWorkspaceAclSettings,
} from "@/modules/e-approval/form-workspace-acl-config";

type Props = {
  value: FormWorkspaceAclSettings;
  onChange: (next: FormWorkspaceAclSettings) => void;
  currentFormId?: string;
  formRestrictedTo?: string | null;
  disabled?: boolean;
};

export function EApprovalFormWorkspaceAclCard({
  value,
  onChange,
  currentFormId,
  formRestrictedTo,
  disabled,
}: Props) {
  const rolesQuery = useQuery({
    queryKey: ["admin", "roles", "catalog"],
    queryFn: fetchAdminRoleCatalog,
    staleTime: 60_000,
  });

  const formsQuery = useQuery({
    queryKey: ["e-approval", "forms", "workspace-link-options"],
    queryFn: () => fetchEApprovalFormsIndex({ page: 1, per_page: 100, status: "published" }),
    staleTime: 30_000,
  });

  const roleOptions = rolesQuery.data?.roles ?? [];
  const linkableForms =
    formsQuery.data?.data.filter((form) => form.id !== currentFormId && form.status === "published") ?? [];

  return (
    <EApprovalSectionCard
      title="Access & grouping"
      description="Limit who sees this workspace, enforce the form's restricted roles, and optionally group more published forms under one dashboard."
    >
      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="ea-workspace-acl-roles">Allowed roles</Label>
          {roleOptions.length > 0 ? (
            <Select
              id="ea-workspace-acl-roles"
              multiple
              value={value.roles}
              disabled={disabled}
              onChange={(e) => {
                const selected = Array.from(e.target.selectedOptions).map((option) => option.value);
                onChange({ ...value, roles: selected });
              }}
              className="min-h-[88px]"
            >
              {roleOptions.map((role) => (
                <option key={role.id} value={role.name}>
                  {role.name}
                </option>
              ))}
            </Select>
          ) : (
            <Input
              id="ea-workspace-acl-roles"
              value={rolesToEditorString(value.roles)}
              disabled={disabled}
              onChange={(e) => onChange({ ...value, roles: rolesFromEditorString(e.target.value) })}
              placeholder="e_approval_requestor, e_approval_approver"
            />
          )}
          <p className="text-xs text-muted-foreground">
            Leave empty to allow any user with E-Approval view access. Coordinators and auditors always have access.
          </p>
        </div>

        <label className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
          <span>
            <span className="block text-sm font-medium text-foreground">Enforce form restricted roles</span>
            <span className="mt-0.5 block text-xs text-muted-foreground">
              {formRestrictedTo?.trim()
                ? `Also require: ${formRestrictedTo}`
                : "Uses the form's restricted_to field when set on Setup."}
            </span>
          </span>
          <Switch
            checked={value.enforce_form_restricted_to}
            disabled={disabled}
            onCheckedChange={(checked) => onChange({ ...value, enforce_form_restricted_to: checked })}
          />
        </label>

        <div className="space-y-2">
          <Label htmlFor="ea-workspace-linked-forms">Linked forms (multi-form workspace)</Label>
          <Select
            id="ea-workspace-linked-forms"
            multiple
            value={value.linked_form_ids}
            disabled={disabled}
            onChange={(e) => {
              const selected = Array.from(e.target.selectedOptions).map((option) => option.value);
              onChange({ ...value, linked_form_ids: selected });
            }}
            className="min-h-[96px]"
          >
            {linkableForms.map((form) => (
              <option key={form.id} value={form.id}>
                {form.name}
              </option>
            ))}
          </Select>
          <p className="text-xs text-muted-foreground">
            Linked forms must be published and cannot have their own workspace enabled.
          </p>
        </div>
      </div>
    </EApprovalSectionCard>
  );
}
