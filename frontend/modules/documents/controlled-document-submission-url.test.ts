import { describe, expect, it } from "vitest";

import {
  controlledDocumentSubmissionUrl,
  eApprovalRequestUrlFromNewSubmissionQuery,
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
      "/e-approval/submissions/new?form_id=019efd72-ab9b-7100-9968-cf8a155f2cf7&controlled_mode=revision&document_code=ATC-P-PMO-001",
    );
  });

  it("resolves request compose url from new submission query", () => {
    const params = new URLSearchParams({
      form_id: "019efd72-ab9b-7100-9968-cf8a155f2cf7",
      controlled_mode: "revision",
      document_code: "ATC-P-PMO-001",
    });

    expect(eApprovalRequestUrlFromNewSubmissionQuery(params)).toBe(
      "/e-approval/request/019efd72-ab9b-7100-9968-cf8a155f2cf7?controlled_mode=revision&document_code=ATC-P-PMO-001",
    );
  });

  it("returns null when form_id is missing", () => {
    expect(eApprovalRequestUrlFromNewSubmissionQuery(new URLSearchParams())).toBeNull();
  });
});
