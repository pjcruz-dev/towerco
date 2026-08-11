import type { EApprovalFormWorkspaceVisibility } from "@/modules/e-approval/form-workspace-types";
import {
  ISO_FORM_FAMILY,
  ISO_FORM_WORKSPACE_SLUG,
  type EApprovalFormWorkspaceConfig,
} from "@/modules/e-approval/form-workspace-types";
import { isoPilotWorkspaceDefaults, parseFormWorkspaceConfig } from "@/modules/e-approval/form-workspace";

export type FormWorkspaceEditorSettings = {
  enabled: boolean;
  slug: string;
  title: string;
  description: string;
  default_list_scope: "own" | "approver";
  visibility: EApprovalFormWorkspaceVisibility;
  show_in_sidebar: boolean;
  new_request_mode: "focused" | "standard";
  show_export: boolean;
};

export const DEFAULT_FORM_WORKSPACE_EDITOR: FormWorkspaceEditorSettings = {
  enabled: false,
  slug: "",
  title: "",
  description: "",
  default_list_scope: "own",
  visibility: "workspace_all",
  show_in_sidebar: true,
  new_request_mode: "focused",
  show_export: false,
};

const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

export function slugifyWorkspaceSlug(input: string): string {
  return input
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 64);
}

export function isValidWorkspaceSlug(slug: string): boolean {
  const trimmed = slug.trim();
  return trimmed.length >= 2 && trimmed.length <= 64 && SLUG_PATTERN.test(trimmed);
}

export function workspaceSettingsFromMetadata(
  metadata: Record<string, unknown> | null | undefined,
  formName = "",
): FormWorkspaceEditorSettings {
  const parsed = parseFormWorkspaceConfig(metadata);
  const raw =
    metadata?.workspace && typeof metadata.workspace === "object"
      ? (metadata.workspace as Record<string, unknown>)
      : null;

  if (!raw) {
    return {
      ...DEFAULT_FORM_WORKSPACE_EDITOR,
      title: formName.trim(),
      slug: slugifyWorkspaceSlug(formName) || "",
    };
  }

  const enabled = raw.enabled === true;
  const slug = String(raw.slug ?? "").trim() || slugifyWorkspaceSlug(formName);

  return {
    enabled,
    slug,
    title: String(raw.title ?? formName ?? "").trim(),
    description: String(raw.description ?? "").trim(),
    default_list_scope: raw.default_list_scope === "approver" ? "approver" : "own",
    visibility:
      raw.visibility === "workspace_all" ||
      raw.visibility === "tenant_all" ||
      raw.visibility === "approver" ||
      raw.visibility === "own"
        ? (raw.visibility as EApprovalFormWorkspaceVisibility)
        : parsed?.visibility ?? "workspace_all",
    show_in_sidebar: (raw.nav as { show_in_sidebar?: boolean } | undefined)?.show_in_sidebar !== false,
    new_request_mode:
      (raw.actions as { new_request_mode?: string } | undefined)?.new_request_mode === "standard"
        ? "standard"
        : "focused",
    show_export: (raw.actions as { show_export?: boolean } | undefined)?.show_export === true,
  };
}

export function mergeWorkspaceIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormWorkspaceEditorSettings,
): Record<string, unknown> {
  const next = { ...metadata };

  if (!settings.enabled) {
    if (next.workspace && typeof next.workspace === "object") {
      next.workspace = { ...(next.workspace as Record<string, unknown>), enabled: false };
    } else {
      delete next.workspace;
    }
    return next;
  }

  const slug = slugifyWorkspaceSlug(settings.slug);
  const existingWorkspace =
    next.workspace && typeof next.workspace === "object"
      ? (next.workspace as Record<string, unknown>)
      : {};
  const workspace: EApprovalFormWorkspaceConfig & Record<string, unknown> = {
    enabled: true,
    slug,
    title: settings.title.trim() || null,
    description: settings.description.trim() || null,
    default_list_scope: settings.default_list_scope,
    visibility: settings.visibility,
    nav: {
      show_in_sidebar: settings.show_in_sidebar,
      section: "Operate",
    },
    actions: {
      new_request_mode: settings.new_request_mode,
      show_export: settings.show_export,
    },
  };

  if (existingWorkspace.dashboard) {
    workspace.dashboard = existingWorkspace.dashboard as EApprovalFormWorkspaceConfig["dashboard"];
  }
  if (existingWorkspace.acl) {
    workspace.acl = existingWorkspace.acl as EApprovalFormWorkspaceConfig["acl"];
  }
  if (existingWorkspace.forms) {
    workspace.forms = existingWorkspace.forms as EApprovalFormWorkspaceConfig["forms"];
  }

  next.workspace = workspace;
  return next;
}

export function workspaceEditorReadiness(
  settings: FormWorkspaceEditorSettings,
): { ok: boolean; message?: string } {
  if (!settings.enabled) {
    return { ok: true };
  }

  const slug = slugifyWorkspaceSlug(settings.slug);
  if (!isValidWorkspaceSlug(slug)) {
    return {
      ok: false,
      message: "Workspace URL slug must be 2–64 characters: lowercase letters, numbers, and hyphens.",
    };
  }

  return { ok: true };
}

export function suggestIsoPilotWorkspaceSettings(formName: string): FormWorkspaceEditorSettings {
  const defaults = isoPilotWorkspaceDefaults(formName);
  return {
    enabled: true,
    slug: defaults.slug,
    title: defaults.title ?? formName,
    description: defaults.description ?? "",
    default_list_scope: defaults.default_list_scope ?? "own",
    visibility: defaults.visibility ?? "workspace_all",
    show_in_sidebar: defaults.nav?.show_in_sidebar !== false,
    new_request_mode: defaults.actions?.new_request_mode ?? "focused",
    show_export: defaults.actions?.show_export === true,
  };
}

export function isIsoDocumentControlMetadata(metadata: Record<string, unknown> | null | undefined): boolean {
  return String(metadata?.form_family ?? "").trim() === ISO_FORM_FAMILY;
}

export function workspacePreviewHref(slug: string): string {
  return `/e-approval/w/${encodeURIComponent(slug.trim() || ISO_FORM_WORKSPACE_SLUG)}`;
}
