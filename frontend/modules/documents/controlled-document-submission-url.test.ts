import { describe, expect, it } from "vitest";

import {
  controlledDocumentSubmissionUrl,
  eApprovalRequestUrlFromNewSubmissionQuery,
  submissionOpenPath,
} from "@/modules/documents/controlled-document-submission-url";

describe("controlled document submission url", () => {
  it("builds new submission entry with controlled document params", () => {
    expect(
      controlledDocumentSubmissionUrl({
        formId: "019efd72-ab9b-7100-9968-cf8a155f2cf7",
        mode: "revision",
        documentCode: "ATC-P-PMO-001",
      }),
    ).toBe(
      "/documents/controlled/new?form_id=019efd72-ab9b-7100-9968-cf8a155f2cf7&controlled_mode=revision&document_code=ATC-P-PMO-001",
    );
  });

  it("resolves request compose url from new submission query", () => {
    const params = new URLSearchParams({
      form_id: "019efd72-ab9b-7100-9968-cf8a155f2cf7",
      controlled_mode: "revision",
      document_code: "ATC-P-PMO-001",
    });

    expect(eApprovalRequestUrlFromNewSubmissionQuery(params)).toBe(
      "/documents/controlled/new?form_id=019efd72-ab9b-7100-9968-cf8a155f2cf7&controlled_mode=revision&document_code=ATC-P-PMO-001",
    );
  });

  it("opens a controlled document in EDCS and other forms in E-Forms", () => {
    expect(submissionOpenPath("sub-1", "iso_document_control", "decide")).toBe(
      "/documents/controlled/requests/sub-1?tab=decide",
    );
    expect(submissionOpenPath("sub-1", "cash_advance", "approvals")).toBe(
      "/e-approval/submissions/sub-1?tab=approvals",
    );
  });

  it("returns null when form_id is missing", () => {
    expect(eApprovalRequestUrlFromNewSubmissionQuery(new URLSearchParams())).toBeNull();
  });
});
