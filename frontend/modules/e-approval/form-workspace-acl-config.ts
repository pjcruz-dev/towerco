export type FormWorkspaceAclSettings = {
  roles: string[];
  enforce_form_restricted_to: boolean;
  linked_form_ids: string[];
};

export const DEFAULT_FORM_WORKSPACE_ACL: FormWorkspaceAclSettings = {
  roles: [],
  enforce_form_restricted_to: true,
  linked_form_ids: [],
};

function normalizeRoleList(value: unknown): string[] {
  if (!Array.isArray(value)) {
    if (typeof value === "string") {
      return value
        .split(",")
        .map((role) => role.trim().toLowerCase())
        .filter(Boolean);
    }
    return [];
  }

  return value
    .map((role) => String(role).trim().toLowerCase())
    .filter(Boolean);
}

export function aclSettingsFromMetadata(
  metadata: Record<string, unknown> | null | undefined,
): FormWorkspaceAclSettings {
  const workspace =
    metadata?.workspace && typeof metadata.workspace === "object"
      ? (metadata.workspace as Record<string, unknown>)
      : null;
  const acl =
    workspace?.acl && typeof workspace.acl === "object"
      ? (workspace.acl as Record<string, unknown>)
      : null;
  const forms =
    workspace?.forms && typeof workspace.forms === "object"
      ? (workspace.forms as Record<string, unknown>)
      : null;

  return {
    roles: normalizeRoleList(acl?.roles),
    enforce_form_restricted_to: acl?.enforce_form_restricted_to !== false,
    linked_form_ids: Array.isArray(forms?.linked_form_ids)
      ? forms.linked_form_ids.map((id) => String(id)).filter(Boolean)
      : [],
  };
}

export function mergeAclIntoWorkspaceMetadata(
  metadata: Record<string, unknown>,
  settings: FormWorkspaceAclSettings,
): Record<string, unknown> {
  const next = { ...metadata };
  const workspace =
    next.workspace && typeof next.workspace === "object"
      ? { ...(next.workspace as Record<string, unknown>) }
      : {};

  workspace.acl = {
    roles: settings.roles,
    enforce_form_restricted_to: settings.enforce_form_restricted_to,
  };
  workspace.forms = {
    mode: settings.linked_form_ids.length > 0 ? "multi" : "single",
    linked_form_ids: settings.linked_form_ids,
  };

  next.workspace = workspace;
  return next;
}

export function rolesToEditorString(roles: string[]): string {
  return roles.join(", ");
}

export function rolesFromEditorString(value: string): string[] {
  return normalizeRoleList(value.split(","));
}
