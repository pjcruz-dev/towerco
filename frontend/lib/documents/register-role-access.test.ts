import { describe, expect, it } from "vitest";

import {
  controlledDocumentAllows,
  documentRegisterNavTargetForPath,
  documentRegisterNavTargetHidden,
  documentRegisterPreviewAllowsPath,
  filterNavGroupsForDocumentRegisterPreview,
} from "@/lib/documents/register-role-access";

describe("document register role access", () => {
  it("leaves a role open until Document Register access is set", () => {
    expect(controlledDocumentAllows(undefined, "view")).toBe(true);
    expect(
      controlledDocumentAllows(
        { entities: { e_approval_submission: { view: true, create: false, edit: false, delete: false, export: false } } },
        "export",
      ),
    ).toBe(true);
  });

  it("honours a Document Register row once an admin sets one", () => {
    const matrix = {
      entities: {
        controlled_document: { view: true, create: false, edit: false, delete: false, export: false },
      },
    };
    expect(controlledDocumentAllows(matrix, "view")).toBe(true);
    expect(controlledDocumentAllows(matrix, "export")).toBe(false);
  });

  it("hides register pages the preview role is not granted", () => {
    const granted = ["documents:controlled:view"];
    expect(documentRegisterPreviewAllowsPath(granted, undefined, "/documents/controlled/register")).toBe(true);
    expect(documentRegisterPreviewAllowsPath(granted, undefined, "/documents/controlled/access")).toBe(false);
    expect(documentRegisterPreviewAllowsPath(granted, undefined, "/e-approval")).toBe(true);
  });

  it("hides one page without hiding the rest of the register", () => {
    const granted = ["documents:controlled:view"];
    const matrix = {
      fields: {
        controlled_document: { "nav:/documents/controlled/register": "hide" as const },
      },
    };
    expect(documentRegisterNavTargetHidden(matrix, "/documents/controlled/register")).toBe(true);
    expect(documentRegisterNavTargetHidden(matrix, "/documents/controlled/published/doc-1")).toBe(true);
    expect(documentRegisterNavTargetHidden(matrix, "/documents/controlled/new")).toBe(true);
    expect(documentRegisterPreviewAllowsPath(granted, matrix, "/documents/controlled/register")).toBe(false);
    expect(documentRegisterPreviewAllowsPath(granted, matrix, "/documents/controlled")).toBe(true);
    expect(documentRegisterPreviewAllowsPath(granted, matrix, "/documents/controlled/reports")).toBe(true);
  });

  it("treats a published file as Register and leaves Overview on its own url", () => {
    expect(documentRegisterNavTargetForPath("/documents/controlled/published/doc-1")?.label).toBe("Register");
    expect(documentRegisterNavTargetForPath("/documents/controlled/register")?.label).toBe("Register");
    expect(documentRegisterNavTargetForPath("/documents/controlled")?.label).toBe("Overview");
    expect(documentRegisterNavTargetForPath("/documents/controlled/reports")?.label).toBe("Reports");
    expect(documentRegisterNavTargetForPath("/documents/controlled/approvals")?.label).toBe("Approvals");
    expect(documentRegisterNavTargetForPath("/documents/controlled/requests/sub-1")?.label).toBe("Approvals");
    expect(documentRegisterNavTargetForPath("/documents/controlled/new")?.label).toBe("Register");
  });

  it("keeps the Document register group when a child link is hidden", () => {
    const groups = filterNavGroupsForDocumentRegisterPreview(
      [
        {
          group: "Operations",
          items: [
            {
              title: "EDCS",
              href: "/documents/controlled",
              items: [
                { title: "Overview", href: "/documents/controlled" },
                { title: "Reports", href: "/documents/controlled/reports" },
              ],
            },
          ],
        },
      ],
      (href) => href === "/documents/controlled/reports",
    );
    expect(groups[0]?.items[0]?.items?.map((item) => item.href)).toEqual(["/documents/controlled/reports"]);
  });

  it("leaves an already empty E-Forms group in place", () => {
    const groups = filterNavGroupsForDocumentRegisterPreview(
      [
        {
          group: "Operations",
          items: [{ title: "E-Forms", items: [] }],
        },
      ],
      () => false,
    );
    expect(groups[0]?.items.map((item) => item.title)).toEqual(["E-Forms"]);
  });
});
