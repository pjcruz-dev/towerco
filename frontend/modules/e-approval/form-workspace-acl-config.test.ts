import { describe, expect, it } from "vitest";

import {
  aclSettingsFromMetadata,
  mergeAclIntoWorkspaceMetadata,
  rolesFromEditorString,
  rolesToEditorString,
} from "@/modules/e-approval/form-workspace-acl-config";

describe("form workspace acl config", () => {
  it("parses acl and linked forms from metadata", () => {
    const settings = aclSettingsFromMetadata({
      workspace: {
        enabled: true,
        slug: "pmo-hub",
        acl: {
          roles: ["e_approval_approver", "e_approval_requestor"],
          enforce_form_restricted_to: false,
        },
        forms: {
          mode: "multi",
          linked_form_ids: ["form-a", "form-b"],
        },
      },
    });

    expect(settings.roles).toEqual(["e_approval_approver", "e_approval_requestor"]);
    expect(settings.enforce_form_restricted_to).toBe(false);
    expect(settings.linked_form_ids).toEqual(["form-a", "form-b"]);
  });

  it("merges acl settings into workspace metadata", () => {
    const merged = mergeAclIntoWorkspaceMetadata(
      { workspace: { enabled: true, slug: "pmo-hub" } },
      {
        roles: ["e_approval_approver"],
        enforce_form_restricted_to: true,
        linked_form_ids: ["linked-1"],
      },
    );

    const workspace = merged.workspace as Record<string, unknown>;
    expect(workspace.acl).toEqual({
      roles: ["e_approval_approver"],
      enforce_form_restricted_to: true,
    });
    expect(workspace.forms).toEqual({
      mode: "multi",
      linked_form_ids: ["linked-1"],
    });
  });

  it("normalizes role editor strings", () => {
    expect(rolesFromEditorString(" Approver, REQUESTOR , ")).toEqual(["approver", "requestor"]);
    expect(rolesToEditorString(["e_approval_approver", "e_approval_requestor"])).toBe(
      "e_approval_approver, e_approval_requestor",
    );
  });
});
