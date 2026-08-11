import {
  ISO_FORM_FAMILY,
  ISO_FORM_WORKSPACE_SLUG,
  type EApprovalFormWorkspaceConfig,
} from "@/modules/e-approval/form-workspace-types";

export function parseFormWorkspaceConfig(
  metadata: Record<string, unknown> | null | undefined,
): EApprovalFormWorkspaceConfig | null {
  const raw = metadata?.workspace;
  if (!raw || typeof raw !== "object") {
    return null;
  }

  const workspace = raw as Record<string, unknown>;
  const enabled = workspace.enabled === true;
  const slug = String(workspace.slug ?? "").trim();
  if (!enabled || slug === "") {
    return null;
  }

  return {
    enabled: true,
    slug,
    title: typeof workspace.title === "string" ? workspace.title : null,
    description: typeof workspace.description === "string" ? workspace.description : null,
    default_list_scope: workspace.default_list_scope === "approver" ? "approver" : "own",
    visibility:
      workspace.visibility === "workspace_all" ||
      workspace.visibility === "tenant_all" ||
      workspace.visibility === "approver"
        ? workspace.visibility
        : "own",
    nav:
      workspace.nav && typeof workspace.nav === "object"
        ? {
            show_in_sidebar: (workspace.nav as { show_in_sidebar?: boolean }).show_in_sidebar !== false,
            section: String((workspace.nav as { section?: string }).section ?? "Operate"),
          }
        : undefined,
    actions:
      workspace.actions && typeof workspace.actions === "object"
        ? {
            new_request_mode:
              (workspace.actions as { new_request_mode?: string }).new_request_mode === "standard"
                ? "standard"
                : "focused",
            show_export: (workspace.actions as { show_export?: boolean }).show_export === true,
          }
        : undefined,
  };
}

export function isoPilotWorkspaceDefaults(formName?: string): EApprovalFormWorkspaceConfig {
  return {
    enabled: true,
    slug: ISO_FORM_WORKSPACE_SLUG,
    title: formName?.trim() || "ISO Document Control",
    description: "Controlled document requests, revisions, and approval tracking.",
    default_list_scope: "own",
    visibility: "workspace_all",
    nav: {
      show_in_sidebar: true,
      section: "Operate",
    },
    actions: {
      new_request_mode: "focused",
      show_export: false,
    },
  };
}

export function isIsoDocumentControlForm(metadata: Record<string, unknown> | null | undefined): boolean {
  return String(metadata?.form_family ?? "").trim() === ISO_FORM_FAMILY;
}

export function workspaceDisplayTitle(
  workspace: EApprovalFormWorkspaceConfig,
  formName: string,
): string {
  return workspace.title?.trim() || formName;
}

export function newRequestHref(formId: string, workspace: EApprovalFormWorkspaceConfig): string {
  const mode = workspace.actions?.new_request_mode ?? "focused";
  if (mode === "standard") {
    return `/e-approval/request/${formId}`;
  }
  return `/e-approval/focus/${formId}?controlled_mode=new`;
}
