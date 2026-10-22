import { describe, expect, it } from "vitest";

import {
  controlledDocumentSubmissionIsRegisterRevision,
  filterFieldsForControlledDocumentMode,
} from "@/modules/e-approval/controlled-document-compose";
import type { ControlledDocumentSyncMeta } from "@/modules/e-approval/controlled-document-sync";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

const sync: ControlledDocumentSyncMeta = {
  enabled: true,
  autoRevision: true,
  documentCodeField: "document_code",
  revisionFieldName: "revision",
  fieldMap: {
    title: "title",
    document_type: "document_type",
    department: "department",
    revision_number: "revision",
    effective_date: "effective_date",
    next_review_date: "next_review_date",
    change_summary: "change_summary",
  },
  attachmentField: "attachments",
  composeUi: { hideSectionProgress: true, hideRegistryPicker: true },
};

const fields: EApprovalFormFieldInput[] = [
  { type: "section", name: "iso", label: "ISO" },
  { type: "text", name: "title", label: "Document title" },
  { type: "section", name: "section_revision", label: "Document identification" },
  { type: "number", name: "previous_revision", label: "Previous revision" },
  { type: "number", name: "revision", label: "Current revision (auto)" },
  { type: "text", name: "document_code", label: "Document number" },
  { type: "section", name: "section_change", label: "Details of change" },
  { type: "textarea", name: "change_summary", label: "Details of change" },
  { type: "textarea", name: "reason_for_change", label: "Reason for change" },
  { type: "section", name: "section_approval", label: "Process Owner" },
  { type: "approver", name: "head_approval", label: "Review Authorities" },
];

describe("controlled document compose mode", () => {
  it("hides document identification and details of change on a new request", () => {
    const labels = filterFieldsForControlledDocumentMode(fields, sync, "new").map((field) => field.label);

    expect(labels).toEqual(["ISO", "Document title", "Process Owner", "Review Authorities"]);
  });

  it("keeps document identification and details of change on a register revision", () => {
    const labels = filterFieldsForControlledDocumentMode(fields, sync, "revision").map((field) => field.label);

    expect(labels).toContain("Document identification");
    expect(labels).toContain("Previous revision");
    expect(labels).toContain("Current revision (auto)");
    expect(labels).toContain("Details of change");
    expect(labels).toContain("Reason for change");
    expect(labels).not.toContain("Document number");
  });

  it("does not treat an issued document code as a register revision", () => {
    expect(
      controlledDocumentSubmissionIsRegisterRevision(sync, {
        document_code: "ATC-TQGO-P-002",
        revision: "0",
        previous_revision: "",
        change_summary: "null",
        reason_for_change: "null",
      }),
    ).toBe(false);
  });

  it("treats a prefilled previous revision as a register revision", () => {
    expect(
      controlledDocumentSubmissionIsRegisterRevision(sync, {
        document_code: "ATC-TQGO-P-002",
        revision: "1",
        previous_revision: "0",
      }),
    ).toBe(true);
  });
});
