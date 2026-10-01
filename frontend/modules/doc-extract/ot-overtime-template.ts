import type { DocExtractField } from "@/modules/doc-extract/types";

/** Keep in sync with backend DocExtractOtOvertimeTemplateCatalog. */
export const DOC_EXTRACT_OT_OVERTIME_TEMPLATE_NAME = "OT overtime (Agency / DocuSign)";

export const DOC_EXTRACT_OT_OVERTIME_TEMPLATE_DESCRIPTION =
  "Agency OT packet fields: WCN / WO / PO, resource, OT period, amount, DocuSign, CPM and LM approvals.";

export function buildOtOvertimeTemplateFields(): DocExtractField[] {
  const rows: Array<{ key: string; label: string; type: DocExtractField["type"]; hint: string }> = [
    { key: "agency_name", label: "Agency Name", type: "text", hint: "Agency Name / Vendor / Supplier" },
    {
      key: "date_received_from_agency",
      label: "Date Received From Agency",
      type: "date",
      hint: "Date Received From Agency / Date Received",
    },
    { key: "wcn_reference_no", label: "WCN Reference No.", type: "text", hint: "WCN Reference No / WCN / Reference No" },
    { key: "work_order_id", label: "Work Order ID", type: "text", hint: "Work Order ID / Work Order / WO ID / WO No" },
    { key: "po_no", label: "PO No.", type: "text", hint: "PO No / PO Number / Purchase Order" },
    {
      key: "resource_name",
      label: "Resource Name (Last, First)",
      type: "text",
      hint: "Resource Name / Employee Name / Name (Last, First)",
    },
    { key: "signum", label: "Signum", type: "text", hint: "Signum / Employee ID / Resource ID" },
    { key: "job_role", label: "Job Role", type: "text", hint: "Job Role / Role / Position / Job Title" },
    { key: "ot_period_from", label: "OT Period (From)", type: "date", hint: "OT Period From / Overtime From / Period From / From" },
    { key: "ot_period_to", label: "OT Period (To)", type: "date", hint: "OT Period To / Overtime To / Period To / To" },
    { key: "type_of_ot", label: "Type of OT", type: "text", hint: "Type of OT / OT Type / Overtime Type" },
    { key: "amount_php", label: "Amount (Php)", type: "currency", hint: "Amount (Php) / Amount / OT Amount / Total Amount" },
    { key: "status", label: "Status", type: "text", hint: "Status / OT Status" },
    { key: "docusign_name", label: "Docusign Name", type: "text", hint: "DocuSign Name / Envelope Name / Document Name" },
    { key: "docusign_id", label: "Docusign Id", type: "text", hint: "DocuSign Id / DocuSign ID / Envelope ID" },
    { key: "docusign_date", label: "Docusign Date", type: "date", hint: "DocuSign Date / Envelope Date / Completed Date" },
    { key: "cpm", label: "CPM", type: "text", hint: "CPM / CPM Approver / Construction Project Manager" },
    { key: "cpm_approval_date", label: "CPM Approval Date", type: "date", hint: "CPM Approval Date / CPM Approved" },
    { key: "lm", label: "LM", type: "text", hint: "LM / Line Manager / LM Approver" },
    {
      key: "lm_approval_date",
      label: "LM Approval Date",
      type: "date",
      hint: "LM Approval Date / Line Manager Approval Date / LM Approved",
    },
  ];

  return rows.map((row) => ({
    key: row.key,
    label: row.label,
    type: row.type,
    description: "",
    hint: row.hint,
    keyManual: true,
    columns: [],
  }));
}
