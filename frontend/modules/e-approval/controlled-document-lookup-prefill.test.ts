import { describe, expect, it } from "vitest";

import { applyControlledDocumentLookupPrefill } from "@/modules/e-approval/controlled-document-lookup-prefill";
import type { ControlledDocumentSyncMeta } from "@/modules/e-approval/controlled-document-sync";

const sync: ControlledDocumentSyncMeta = {
  enabled: true,
  autoRevision: true,
  documentCodeField: "document_code",
  revisionFieldName: "revision",
  fieldMap: {
    title: "title",
    document_type: "document_type",
    department: "department",
    effective_date: "effective_date",
    next_review_date: "next_review_date",
  },
  attachmentField: "attachments",
  composeUi: { hideSectionProgress: true, hideRegistryPicker: true },
};

describe("controlled document lookup prefill", () => {
  it("keeps an effective date the requestor already entered", () => {
    const next = applyControlledDocumentLookupPrefill(
      sync,
      [],
      { document_code: "ATC-EDD-F-002", effective_date: "2026-12-01", title: "" },
      {
        exists: true,
        document_code: "ATC-EDD-F-002",
        title: "Pre Delivery Inspection Form",
        next_revision: 3,
        current_revision: 2,
        effective_date: "2026-11-02",
      },
      { overwrite: true },
    );

    expect(next.effective_date).toBe("2026-12-01");
    expect(next.title).toBe("Pre Delivery Inspection Form");
  });

  it("fills the effective date from the register when the request is still blank", () => {
    const next = applyControlledDocumentLookupPrefill(
      sync,
      [],
      { document_code: "ATC-EDD-F-002", effective_date: "" },
      {
        exists: true,
        document_code: "ATC-EDD-F-002",
        next_revision: 3,
        effective_date: "2026-11-02",
      },
    );

    expect(next.effective_date).toBe("2026-11-02");
  });
});
