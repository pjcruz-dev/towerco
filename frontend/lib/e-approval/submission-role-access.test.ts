import { describe, expect, it } from "vitest";

import {
  eApprovalEntityAllows,
  eApprovalFieldKey,
  eApprovalNavTargetForPath,
  eApprovalPageListState,
  eApprovalNavTargetHidden,
  eApprovalPreviewAllowsPath,
  filterNavGroupsForEApprovalPreview,
} from "@/lib/e-approval/submission-role-access";

describe("e-approval role access", () => {
  it("leaves a role open until E-Forms access is set", () => {
    expect(eApprovalEntityAllows(undefined, "view")).toBe(true);
    expect(
      eApprovalEntityAllows(
        { entities: { it_asset: { view: true, create: false, edit: false, delete: false, export: false } } },
        "create",
      ),
    ).toBe(true);
  });

  it("honours an E-Forms row once an admin sets one", () => {
    const matrix = {
      entities: {
        e_approval_submission: {
          view: true,
          create: false,
          edit: true,
          delete: false,
          export: false,
        },
      },
    };
    expect(eApprovalEntityAllows(matrix, "view")).toBe(true);
    expect(eApprovalEntityAllows(matrix, "create")).toBe(false);
    expect(eApprovalEntityAllows(matrix, "export")).toBe(false);
  });

  it("maps requestor export columns onto the requestor field", () => {
    expect(eApprovalFieldKey("requestor_name")).toBe("requestor");
    expect(eApprovalFieldKey("document_no")).toBe("document_no");
  });

  it("hides E-Forms pages the preview role is not granted", () => {
    const granted = ["e_approval:view", "e_approval:submissions:view"];
    expect(eApprovalPreviewAllowsPath(granted, undefined, "/e-approval/submissions")).toBe(true);
    expect(eApprovalPreviewAllowsPath(granted, undefined, "/e-approval/forms")).toBe(false);
    expect(eApprovalPreviewAllowsPath(granted, undefined, "/ticketing")).toBe(true);
    expect(
      eApprovalPreviewAllowsPath(
        granted,
        {
          entities: {
            e_approval_submission: { view: false, create: false, edit: false, delete: false, export: false },
          },
        },
        "/e-approval/submissions",
      ),
    ).toBe(false);
  });

  it("hides a page the admin marked hidden for this role, including its records", () => {
    const granted = ["e_approval:view", "e_approval:submissions:view", "e_approval:submissions:create"];
    const matrix = {
      fields: {
        e_approval_submission: { "nav:/e-approval/submissions": "hide" as const },
      },
    };
    expect(eApprovalNavTargetHidden(matrix, "/e-approval/submissions")).toBe(true);
    expect(eApprovalNavTargetHidden(matrix, "/e-approval/submissions/abc?tab=decide")).toBe(true);
    expect(eApprovalPreviewAllowsPath(granted, matrix, "/e-approval/submissions")).toBe(false);
    expect(eApprovalPreviewAllowsPath(granted, matrix, "/e-approval/submissions/new")).toBe(true);
    expect(eApprovalPreviewAllowsPath(granted, matrix, "/e-approval")).toBe(true);
  });

  it("does not call Approvals shown when the role cannot approve", () => {
    const granted = ["e_approval:view", "e_approval:submissions:view", "e_approval:submissions:create"];
    expect(eApprovalPageListState(granted, undefined, "/e-approval/approvals")).toBe("no-access");
    expect(eApprovalPageListState(granted, undefined, "/e-approval/submissions")).toBe("shown");
    expect(eApprovalPageListState(granted, undefined, "/e-approval/reports")).toBe("shown");
  });

  it("treats a record as the Submissions page and leaves a new request unmatched", () => {
    expect(eApprovalNavTargetForPath("/e-approval/submissions/abc")?.label).toBe("Submissions");
    expect(eApprovalNavTargetForPath("/e-approval/submissions")?.href).toBe("/e-approval/submissions");
    expect(eApprovalNavTargetForPath("/e-approval/submissions/new")).toBeNull();
    expect(eApprovalNavTargetForPath("/e-approval")?.label).toBe("Overview");
    expect(eApprovalNavTargetForPath("/e-approval/forms")?.label).toBe("Forms");
  });

  it("keeps the E-Forms group when every child link is hidden", () => {
    const groups = filterNavGroupsForEApprovalPreview(
      [
        {
          group: "Operations",
          items: [
            {
              title: "E-Forms",
              href: "/e-approval",
              items: [
                { title: "Forms", href: "/e-approval/forms" },
                { title: "Submissions", href: "/e-approval/submissions" },
              ],
            },
          ],
        },
      ],
      (href) => href === "/e-approval/submissions",
    );
    expect(groups[0]?.items[0]?.items?.map((item) => item.href)).toEqual(["/e-approval/submissions"]);
  });
});
