import { describe, expect, it } from "vitest";

import {
  buildSubmissionFormContentGroups,
  formatSubmissionCurrencyDisplay,
  shouldHideSubmissionAttachmentFieldValue,
} from "@/modules/e-approval/submission-form-content";

describe("submission form content", () => {
  it("groups values under section headings and builds checklist tables", () => {
    const groups = buildSubmissionFormContentGroups(
      [
        {
          field_id: "1",
          field_name: "payee",
          field_type: "text",
          label: "Payee",
          value: "EGUIA TRANSPORT SERVICES",
        },
        {
          field_id: "2",
          field_name: "payment_amount",
          field_type: "currency",
          label: "Payment amount",
          value: "12698.95",
        },
        {
          field_id: "3",
          field_name: "cost_application",
          field_type: "checklist_matrix",
          label: "Cost application",
          value: JSON.stringify({
            saq_permitting: {
              selected: true,
              cells: {
                project_site_no: "Site 1",
                ref_no: "R-2",
                or_no: "OR-3",
              },
            },
          }),
        },
        {
          field_id: "4",
          field_name: "service_invoice",
          field_type: "file",
          label: "Attachments",
          value: "invoice.pdf",
        },
      ],
      [
        { id: "s1", type: "section", name: "section_payee", label: "Payee & payment details" },
        { id: "f1", type: "text", name: "payee", label: "Payee" },
        { id: "f2", type: "currency", name: "payment_amount", label: "Payment amount" },
        { id: "s2", type: "section", name: "section_bank", label: "Bank & cost charge" },
        {
          id: "f3",
          type: "checklist_matrix",
          name: "cost_application",
          label: "Cost application",
          options: {
            row_select_label: "Cost Application",
            rows: [{ value: "saq_permitting", label: "SAQ-Permitting" }],
            columns: [
              { value: "project_site_no", label: "Project Site No", type: "text" },
              { value: "ref_no", label: "Ref No", type: "text" },
              { value: "or_no", label: "OR No.", type: "text" },
            ],
          },
        },
        { id: "f4", type: "file", name: "service_invoice", label: "Attachments" },
      ],
      [{ id: "a1", field_name: "service_invoice", file_name: "invoice.pdf", file_path: "x" }],
    );

    expect(groups.map((group) => group.title)).toEqual([
      "Payee & payment details",
      "Bank & cost charge",
    ]);
    expect(groups[0]?.items.map((item) => item.value.field_name)).toEqual([
      "payee",
      "payment_amount",
    ]);
    expect(groups[1]?.items).toHaveLength(1);
    expect(groups[1]?.items[0]?.table?.rows).toEqual([
      ["SAQ-Permitting", "Site 1", "R-2", "OR-3"],
    ]);
  });

  it("hides file fields when attachments panel will show them", () => {
    expect(
      shouldHideSubmissionAttachmentFieldValue(
        {
          field_name: "service_invoice",
          field_type: "file",
          label: "Service invoice",
          value: "a.pdf",
        },
        [{ field_name: "service_invoice", file_name: "a.pdf" }],
      ),
    ).toBe(true);
  });

  it("formats currency for submission display", () => {
    expect(
      formatSubmissionCurrencyDisplay({
        field_type: "currency",
        value: "12698.95",
      }),
    ).toBe("12,698.95");
  });
});
