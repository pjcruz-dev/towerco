import { describe, expect, it } from "vitest";

import {
  isValidWorkspaceSlug,
  mergeWorkspaceIntoMetadata,
  slugifyWorkspaceSlug,
  suggestIsoPilotWorkspaceSettings,
  workspaceEditorReadiness,
  workspaceSettingsFromMetadata,
} from "@/modules/e-approval/form-workspace-config";

describe("form workspace config", () => {
  it("slugifies form names into URL-safe slugs", () => {
    expect(slugifyWorkspaceSlug("ISO Document Control")).toBe("iso-document-control");
    expect(slugifyWorkspaceSlug("  PMO / Requests  ")).toBe("pmo-requests");
  });

  it("validates workspace slug format", () => {
    expect(isValidWorkspaceSlug("iso-approval")).toBe(true);
    expect(isValidWorkspaceSlug("a")).toBe(false);
    expect(isValidWorkspaceSlug("Bad Slug")).toBe(false);
  });

  it("parses workspace settings from metadata", () => {
    const settings = workspaceSettingsFromMetadata(
      {
        workspace: {
          enabled: true,
          slug: "iso-approval",
          title: "ISO",
          description: "Pilot",
          visibility: "workspace_all",
          nav: { show_in_sidebar: true },
          actions: { new_request_mode: "focused", show_export: false },
        },
      },
      "ISO Approval",
    );

    expect(settings.enabled).toBe(true);
    expect(settings.slug).toBe("iso-approval");
    expect(settings.visibility).toBe("workspace_all");
    expect(settings.new_request_mode).toBe("focused");
  });

  it("merges editor settings into metadata", () => {
    const merged = mergeWorkspaceIntoMetadata({}, {
      enabled: true,
      slug: "Leave Requests",
      title: "Leave",
      description: "Team leave",
      default_list_scope: "own",
      visibility: "workspace_all",
      show_in_sidebar: true,
      new_request_mode: "standard",
      show_export: true,
    });

    expect(merged.workspace).toMatchObject({
      enabled: true,
      slug: "leave-requests",
      title: "Leave",
      visibility: "workspace_all",
      actions: { new_request_mode: "standard", show_export: true },
    });
  });

  it("disables workspace without removing prior config", () => {
    const merged = mergeWorkspaceIntoMetadata(
      { workspace: { enabled: true, slug: "iso-approval" } },
      {
        enabled: false,
        slug: "iso-approval",
        title: "",
        description: "",
        default_list_scope: "own",
        visibility: "workspace_all",
        show_in_sidebar: true,
        new_request_mode: "focused",
        show_export: false,
      },
    );

    expect(merged.workspace).toMatchObject({ enabled: false, slug: "iso-approval" });
  });

  it("blocks save when enabled slug is invalid", () => {
    const readiness = workspaceEditorReadiness({
      enabled: true,
      slug: "!!",
      title: "Test",
      description: "",
      default_list_scope: "own",
      visibility: "own",
      show_in_sidebar: true,
      new_request_mode: "focused",
      show_export: false,
    });

    expect(readiness.ok).toBe(false);
    expect(readiness.message).toContain("slug");
  });

  it("suggests ISO pilot defaults", () => {
    const settings = suggestIsoPilotWorkspaceSettings("ISO Approval");
    expect(settings.enabled).toBe(true);
    expect(settings.slug).toBe("iso-approval");
    expect(settings.new_request_mode).toBe("focused");
  });
});
