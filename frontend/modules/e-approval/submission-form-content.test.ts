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

  it("places forecast totals under the matching line columns", () => {
    const groups = buildSubmissionFormContentGroups(
      [
        {
          field_id: "g1",
          field_name: "expense_lines",
          field_type: "grid",
          label: "Forecast lines",
          value: JSON.stringify({
            rows: [{ "0": "Land", "1": "6000.00", "2": "VATable", "3": "642.86" }],
          }),
        },
        {
          field_id: "t1",
          field_name: "requested_amount",
          field_type: "currency",
          label: "Requested amount",
          value: "6000.00",
        },
        {
          field_id: "t2",
          field_name: "vat_total",
          field_type: "currency",
          label: "VAT",
          value: "642.86",
        },
        {
          field_id: "t3",
          field_name: "non_vatable_total",
          field_type: "currency",
          label: "Non-VATable",
          value: "0.00",
        },
        {
          field_id: "t4",
          field_name: "vatable_net",
          field_type: "currency",
          label: "VATable, net of VAT",
          value: "5357.14",
        },
      ],
      [
        {
          id: "g1",
          type: "grid",
          name: "expense_lines",
          label: "Forecast lines",
          options: {
            columns: [
              { label: "Expense type", type: "text" },
              { label: "Amount", type: "currency" },
              { label: "VAT treatment", type: "select" },
              { label: "VAT", type: "currency" },
            ],
          },
        },
        { id: "t1", type: "currency", name: "requested_amount", label: "Requested amount" },
        { id: "t2", type: "currency", name: "vat_total", label: "VAT" },
        { id: "t3", type: "currency", name: "non_vatable_total", label: "Non-VATable" },
        { id: "t4", type: "currency", name: "vatable_net", label: "VATable, net of VAT" },
      ],
      [],
    );

    const items = groups.flatMap((group) => group.items);
    expect(items.map((item) => item.value.field_name)).toEqual(["expense_lines"]);
    expect(items[0]?.totals).toEqual([
      { label: "Requested amount", value: "6,000.00" },
      { label: "VAT", value: "642.86" },
      { label: "Non-VATable", value: "0.00" },
      { label: "VATable, net of VAT", value: "5,357.14" },
    ]);
    expect(items[0]?.table?.headers).toEqual([
      "Expense type",
      "Amount",
      "VAT treatment",
      "VAT",
      "Non-VATable",
      "VATable, net of VAT",
    ]);
  });

  it("drops stored VAT totals when those columns are not on the table", () => {
    const groups = buildSubmissionFormContentGroups(
      [
        {
          field_id: "g1",
          field_name: "expense_lines",
          field_type: "grid",
          label: "Forecast lines",
          value: JSON.stringify({
            rows: [{ "0": "2026-11-16", "1": "Car Rental", "2": "3500", "3": "500" }],
          }),
        },
        {
          field_id: "t1",
          field_name: "requested_amount",
          field_type: "currency",
          label: "Requested amount",
          value: "3500.00",
        },
        {
          field_id: "t2",
          field_name: "vat_total",
          field_type: "currency",
          label: "VAT",
          value: "375.00",
        },
        {
          field_id: "t3",
          field_name: "non_vatable_total",
          field_type: "currency",
          label: "Non-VATable",
          value: "0.00",
        },
        {
          field_id: "t4",
          field_name: "vatable_net",
          field_type: "currency",
          label: "VATable, net of VAT",
          value: "3125.00",
        },
      ],
      [
        {
          id: "g1",
          type: "grid",
          name: "expense_lines",
          label: "Forecast lines",
          options: {
            columns: [
              { label: "From date – To date", type: "date" },
              { label: "Expense type", type: "select" },
              { label: "Amount", type: "currency", show_total: true },
              { label: "Nature of Activity", type: "text" },
              { label: "VAT", type: "currency", hidden: true, show_total: true },
              { label: "Non-VATable", type: "currency", hidden: true, show_total: true },
              { label: "VATable, net of VAT", type: "currency", hidden: true, show_total: true },
            ],
          },
        },
        { id: "t1", type: "currency", name: "requested_amount", label: "Requested amount" },
        { id: "t2", type: "currency", name: "vat_total", label: "VAT" },
        { id: "t3", type: "currency", name: "non_vatable_total", label: "Non-VATable" },
        { id: "t4", type: "currency", name: "vatable_net", label: "VATable, net of VAT" },
      ],
      [],
    );

    const items = groups.flatMap((group) => group.items);
    expect(items.map((item) => item.value.field_name)).toEqual(["expense_lines"]);
    expect(items[0]?.table?.headers).toEqual([
      "From date – To date",
      "Expense type",
      "Amount",
      "Nature of Activity",
    ]);
    expect(items[0]?.totals).toEqual([{ label: "Requested amount", value: "3,500.00" }]);
  });

  it("keeps a file field in place so every form can review that file", () => {
    const groups = buildSubmissionFormContentGroups(
      [
        {
          field_id: "file",
          field_name: "supporting_documents",
          field_type: "file",
          label: "Supporting documents",
          value: "a.pdf",
        },
        {
          field_id: "notes",
          field_name: "notes",
          field_type: "textarea",
          label: "Notes",
          value: "asdasda",
        },
      ],
      [
        { id: "file", type: "file", name: "supporting_documents", label: "Supporting documents" },
        { id: "notes", type: "textarea", name: "notes", label: "Notes" },
      ],
      [{ id: "file-1", field_name: "supporting_documents", file_name: "a.pdf", file_path: "a.pdf" }],
      { keepFileFields: true },
    );

    const items = groups.flatMap((group) => group.items);
    expect(items.map((item) => item.value.field_name)).toEqual(["supporting_documents", "notes"]);
    expect(items[0]?.fileReview).toBe(true);
    expect(items[1]?.fileReview).toBeUndefined();
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

  it("hides a blank DCF number until publish fills it", () => {
    const groups = buildSubmissionFormContentGroups(
      [
        {
          field_id: "dcf",
          field_name: "dcf_number",
          field_type: "text",
          label: "DCF number",
          value: "",
        },
        {
          field_id: "title",
          field_name: "title",
          field_type: "text",
          label: "Title",
          value: "Leasing",
        },
      ],
      [
        { id: "dcf", type: "text", name: "dcf_number", label: "DCF number", options: { assigned_on_publish: true } },
        { id: "title", type: "text", name: "title", label: "Title" },
      ],
      [],
    );

    const labels = groups.flatMap((group) => group.items.map((item) => item.value.field_name));
    expect(labels).toEqual(["title"]);
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
