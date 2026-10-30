import type { EApprovalWorkflowPreviewResponse } from "@/lib/api/modules/e-approval-api";
import type {
  EApprovalApprovalRow,
  EApprovalCommentRow,
  EApprovalPrintPayload,
  EApprovalSubmissionListRow,
} from "@/modules/e-approval/types";

import {
  eApprovalTourSampleRequestor,
  formatTourSampleTimestamp,
} from "@/lib/help/e-approval-tour-sample-data";

/**
 * Ephemeral cash advance, liquidation, and reimbursement samples for the finance tour.
 * Never persisted.
 */

export type EApprovalFinanceTourFamily = "cash_advance" | "liquidation" | "reimbursement";

export const E_APPROVAL_FINANCE_TOUR_FAMILY_SLUG: Record<EApprovalFinanceTourFamily, string> = {
  cash_advance: "cash-advance",
  liquidation: "liquidation",
  reimbursement: "reimbursement",
};

const REQUESTOR = eApprovalTourSampleRequestor;

const HEAD = {
  id: "tour-fin-head",
  name: "James Rivera",
  email: "james.rivera@towerco.example",
};
const FINANCE = {
  id: "tour-fin-finance",
  name: "Alya Mendoza",
  email: "alya.mendoza@towerco.example",
};
const CFO = {
  id: "tour-fin-cfo",
  name: "Carlos Nguyen",
  email: "carlos.nguyen@towerco.example",
};
const TREASURY = {
  id: "tour-fin-treasury",
  name: "Priya Santos",
  email: "priya.santos@towerco.example",
};

type Person = { id: string; name: string; email: string };

type ChainStep = { label: string; person: Person };

const CHAINS: Record<EApprovalFinanceTourFamily, ChainStep[]> = {
  cash_advance: [
    { label: "Department Head", person: HEAD },
    { label: "Finance", person: FINANCE },
    { label: "CFO", person: CFO },
    { label: "Release cash", person: TREASURY },
  ],
  liquidation: [
    { label: "Department Head", person: HEAD },
    { label: "Finance", person: FINANCE },
    { label: "CFO", person: CFO },
    { label: "Finance validation", person: FINANCE },
    { label: "Settlement", person: TREASURY },
  ],
  reimbursement: [
    { label: "Department Head", person: HEAD },
    { label: "Finance", person: FINANCE },
    { label: "CFO", person: CFO },
    { label: "Payment", person: TREASURY },
  ],
};

export type FinanceTourMoneyRow = { label: string; value: string };

export type FinanceTourSample = {
  family: EApprovalFinanceTourFamily;
  formName: string;
  documentNo: string;
  purpose: string;
  subsidiary: string;
  department: string;
  submittedAt: string;
  pickerBlurb: string;
  composeLead: string;
  chainSummary: string;
  notes: string;
  fileLabel: string;
  files: string[];
  facts: FinanceTourMoneyRow[];
  columns: string[];
  rows: string[][];
  linesTitle: string;
  totals: FinanceTourMoneyRow[];
  parent?: { documentNo: string; summary: string; openBalance: string };
  settlement?: FinanceTourMoneyRow[];
  relatedTitle: string;
  relatedBody: string;
  listRow: EApprovalSubmissionListRow;
  approvals: EApprovalApprovalRow[];
  comments: EApprovalCommentRow[];
  workflowPreview: EApprovalWorkflowPreviewResponse;
  print: EApprovalPrintPayload;
};

function workflowSteps(family: EApprovalFinanceTourFamily): NonNullable<EApprovalSubmissionListRow["workflow_steps"]> {
  return CHAINS[family].map((step, index) => ({
    step_order: index + 1,
    label: step.label,
    state: index === 0 ? "current" : "upcoming",
    status_label: index === 0 ? "Pending" : "Upcoming",
    approver_name: step.person.name,
    approver_names: [step.person.name],
  }));
}

function approvalsFor(sampleId: string, documentNo: string, family: EApprovalFinanceTourFamily): EApprovalApprovalRow[] {
  return CHAINS[family].map((step, index) => ({
    id: `${sampleId}-appr-${index + 1}`,
    status: "pending",
    remarks: null,
    signature: null,
    acted_at: null,
    step_order: index + 1,
    approver: step.person,
    submission: {
      id: sampleId,
      document_no: documentNo,
      status: "pending",
      form_name: family === "cash_advance" ? "Cash advance" : family === "liquidation" ? "Liquidation" : "Reimbursement",
    },
  }));
}

function workflowPreview(family: EApprovalFinanceTourFamily, sampleId: string): EApprovalWorkflowPreviewResponse {
  return {
    workflow_mode: "roles",
    matched_rule_id: null,
    matched_rule_label: null,
    definition_source: "workflow_snapshot",
    resolved_steps: CHAINS[family].map((step, index) => ({
      step_order: index + 1,
      type: index === 0 ? "manager" : "role",
      label: step.label,
      resolved_user_id: step.person.id,
      resolved_user_name: step.person.name,
      resolved_user_email: step.person.email,
      path_reason: null,
      warning: null,
      runtime_status: index === 0 ? "pending" : "waiting",
      approval_id: `${sampleId}-appr-${index + 1}`,
      runtime_approver: step.person,
    })),
    skipped_steps: [],
  };
}

function chainSummary(family: EApprovalFinanceTourFamily): string {
  return CHAINS[family].map((step) => step.label).join(" → ");
}

function printPayload(
  sample: Pick<FinanceTourSample, "family" | "formName" | "documentNo" | "submittedAt" | "columns" | "rows" | "files" | "fileLabel"> & {
    fields: EApprovalPrintPayload["fields"];
  },
): EApprovalPrintPayload {
  return {
    document_no: sample.documentNo,
    form_name: sample.formName,
    form_family: sample.family,
    status: "Pending",
    requestor: REQUESTOR.name,
    created_at: formatTourSampleTimestamp(sample.submittedAt),
    brand_logo_url: null,
    print_template_kind: "generic",
    fields: sample.fields,
    grids: [
      {
        key: "expense_lines",
        label: sample.family === "cash_advance" ? "Forecast lines" : "Expense lines",
        columns: sample.columns,
        rows: sample.rows,
      },
    ],
    approvals: CHAINS[sample.family].map((step, index) => ({
      step: index + 1,
      approver: step.person.name,
      status: "pending",
      remarks: null,
      signature: null,
      acted_at: null,
    })),
    attachments: sample.files.map((fileName, index) => ({
      id: `${sample.documentNo}-file-${index + 1}`,
      field_name: sample.fileLabel === "Receipts" ? "receipts" : "supporting_documents",
      file_name: fileName,
    })),
    template: {
      header: { title: sample.formName },
      footer: { showApprovalHistory: true },
      orientation: "landscape",
      blocks: { signatures: [] },
    },
    show_approval_trail: true,
  };
}

const CA_COLUMNS = [
  "From date – To date",
  "From site / location",
  "To site / location",
  "Expense type",
  "Amount",
  "VAT treatment",
  "VAT",
];

const CA_ROWS = [
  ["3 Nov 2026 – 5 Nov 2026", "Batangas", "Lipa", "Transportation - Land", "4,500.00", "VATable", "540.00"],
  ["3 Nov 2026 – 5 Nov 2026", "Lipa", "Batangas", "Lodging", "8,000.00", "VATable", "960.00"],
  ["3 Nov 2026 – 5 Nov 2026", "Batangas", "Batangas", "Per Diem", "6,000.00", "Non-VATable", "0.00"],
];

const LQ_COLUMNS = [
  "From date – To date",
  "From site / location",
  "To site / location",
  "OR No",
  "Supplier/Payee",
  "Expense type",
  "Amount",
  "Forecast amount",
  "VAT treatment",
  "VAT",
  "Variance reason",
];

const LQ_ROWS = [
  ["3 Nov 2026 – 5 Nov 2026", "Batangas", "Lipa", "OR-8811", "South Luzon Transport", "Transportation - Land", "4,000.00", "4,500.00", "VATable", "480.00", "Shared vehicle"],
  ["3 Nov 2026 – 5 Nov 2026", "Lipa", "Batangas", "OR-8820", "Casa Lipa", "Lodging", "7,200.00", "8,000.00", "VATable", "864.00", "Shorter stay"],
  ["3 Nov 2026 – 5 Nov 2026", "Batangas", "Batangas", "—", "Employee", "Per Diem", "5,000.00", "6,000.00", "Non-VATable", "0.00", "One less day"],
];

const RE_COLUMNS = [
  "From date – To date",
  "From site / location",
  "To site / location",
  "OR No",
  "Supplier/Payee",
  "Description",
  "Expense type",
  "Amount",
  "VAT treatment",
  "VAT",
];

const RE_ROWS = [
  ["12 Oct 2026 – 12 Oct 2026", "Batangas", "Lipa", "OR-10442", "Shell Lipa", "Site run fuel", "Gasoline", "2,400.00", "VATable", "288.00"],
  ["12 Oct 2026 – 13 Oct 2026", "Lipa", "Lipa", "OR-10488", "Casa Lipa", "Overnight after late access", "Lodging", "2,400.00", "VATable", "288.00"],
];

function buildCashAdvance(): FinanceTourSample {
  const family: EApprovalFinanceTourFamily = "cash_advance";
  const documentNo = "ATC-CA-F-1042";
  const submittedAt = "2026-10-20T09:15:00+08:00";
  const formName = "Cash advance";
  const id = "tour-finance-cash-advance";
  const fields = [
    { key: "subsidiary", label: "Subsidiary", value: "ATC" },
    { key: "needed_by", label: "Funds needed by", value: "3 Nov 2026" },
    { key: "purpose", label: "Purpose", value: "Site survey, Batangas cluster" },
    { key: "requested_amount", label: "Requested amount", value: "18,500.00" },
    { key: "vat_total", label: "VAT", value: "1,500.00" },
    { key: "non_vatable_total", label: "Non-VATable", value: "6,000.00" },
    { key: "vatable_net", label: "VATable, net of VAT", value: "11,000.00" },
    { key: "notes", label: "Notes", value: "Release before 3 Nov so the team can travel on the first day." },
  ];
  const base = {
    family,
    formName,
    documentNo,
    columns: CA_COLUMNS,
    rows: CA_ROWS,
    files: ["batangas-itinerary.pdf"],
    fileLabel: "Forecast support",
    submittedAt,
  };
  return {
    ...base,
    purpose: "Site survey, Batangas cluster",
    subsidiary: "ATC",
    department: "Network Operations",
    pickerBlurb: "Forecast each trip line, then Department Head, Finance, and the CFO approve. Finance or Treasury releases the full amount.",
    composeLead: "Each forecast line carries the trip and the expense. Requested amount is the total of the Amount column. Sample only — nothing is saved.",
    chainSummary: chainSummary(family),
    notes: "Release before 3 Nov so the team can travel on the first day.",
    facts: [
      { label: "Subsidiary", value: "ATC" },
      { label: "Funds needed by", value: "3 Nov 2026" },
      { label: "Purpose", value: "Site survey, Batangas cluster" },
    ],
    linesTitle: "Forecast lines",
    totals: [
      { label: "Requested amount", value: "18,500.00" },
      { label: "VAT", value: "1,500.00" },
      { label: "Non-VATable", value: "6,000.00" },
      { label: "VATable, net of VAT", value: "11,000.00" },
    ],
    relatedTitle: "Liquidations",
    relatedBody: "No liquidation yet. After release, file a liquidation against this document. A new cash advance stays blocked while this one is still open.",
    listRow: {
      id,
      document_no: documentNo,
      status: "pending",
      current_step: 1,
      step_count: CHAINS[family].length,
      form_id: id,
      form_name: formName,
      form_family: family,
      subsidiary: "ATC",
      department: "Network Operations",
      workflow_steps: workflowSteps(family),
      requestor: { ...REQUESTOR },
      created_at: submittedAt,
    },
    approvals: approvalsFor(id, documentNo, family),
    comments: [
      {
        id: `${id}-cmt-1`,
        message: "Cash advance for the Batangas cluster survey. Forecast lines are on the request.",
        user_name: REQUESTOR.name,
        created_at: submittedAt,
        replies: [],
      },
    ],
    workflowPreview: workflowPreview(family, id),
    print: printPayload({ ...base, fields }),
  };
}

function buildLiquidation(): FinanceTourSample {
  const family: EApprovalFinanceTourFamily = "liquidation";
  const documentNo = "ATC-LQ-F-0881";
  const submittedAt = "2026-10-18T14:40:00+08:00";
  const formName = "Liquidation";
  const id = "tour-finance-liquidation";
  const fields = [
    { key: "cash_advance_document_no", label: "Cash advance document no.", value: "ATC-CA-F-0990" },
    { key: "subsidiary", label: "Subsidiary", value: "ATC" },
    { key: "liquidation_date", label: "Date submitted for liquidation", value: "18 Oct 2026" },
    { key: "purpose", label: "Purpose", value: "Site survey, Batangas cluster" },
    { key: "total_reimbursement", label: "Total expenses", value: "16,200.00" },
    { key: "cash_advance_amount", label: "Cash released", value: "18,500.00" },
    { key: "settlement_amount", label: "Settlement amount", value: "-2,300.00" },
    { key: "settlement_summary", label: "Settlement", value: "Surrender 2,300.00" },
    { key: "notes", label: "Notes", value: "Receipts attached. Surrender the unused 2,300.00 to Treasury." },
  ];
  const base = {
    family,
    formName,
    documentNo,
    columns: LQ_COLUMNS,
    rows: LQ_ROWS,
    files: ["batangas-receipts.pdf"],
    fileLabel: "Receipts",
    submittedAt,
  };
  return {
    ...base,
    purpose: "Site survey, Batangas cluster",
    subsidiary: "ATC",
    department: "Network Operations",
    pickerBlurb: "Liquidate one released cash advance. Actual lines compare to the forecast. Finance records the surrender or the payment.",
    composeLead: "Pick a released cash advance first. Total expenses cannot exceed its open balance. Sample only — nothing is saved.",
    chainSummary: chainSummary(family),
    notes: "Receipts attached. Surrender the unused 2,300.00 to Treasury.",
    facts: [
      { label: "Cash advance document no.", value: "ATC-CA-F-0990" },
      { label: "Subsidiary", value: "ATC" },
      { label: "Date submitted for liquidation", value: "18 Oct 2026" },
      { label: "Purpose", value: "Site survey, Batangas cluster" },
    ],
    linesTitle: "Actual expenses",
    totals: [
      { label: "Total expenses", value: "16,200.00" },
      { label: "VAT", value: "1,344.00" },
      { label: "Non-VATable", value: "5,000.00" },
      { label: "VATable, net of VAT", value: "9,856.00" },
    ],
    parent: {
      documentNo: "ATC-CA-F-0990",
      summary: "ATC-CA-F-0990 · Released · Maria Santos",
      openBalance: "18,500.00",
    },
    settlement: [
      { label: "Cash released", value: "18,500.00" },
      { label: "Settlement amount", value: "-2,300.00" },
      { label: "Settlement", value: "Surrender 2,300.00" },
    ],
    relatedTitle: "Cash advance",
    relatedBody: "Linked to released cash advance ATC-CA-F-0990. Open balance before this liquidation is 18,500.00. Actual spend is 16,200.00, so the employee surrenders 2,300.00.",
    listRow: {
      id,
      document_no: documentNo,
      status: "pending",
      current_step: 1,
      step_count: CHAINS[family].length,
      form_id: id,
      form_name: formName,
      form_family: family,
      subsidiary: "ATC",
      department: "Network Operations",
      workflow_steps: workflowSteps(family),
      requestor: { ...REQUESTOR },
      created_at: submittedAt,
    },
    approvals: approvalsFor(id, documentNo, family),
    comments: [
      {
        id: `${id}-cmt-1`,
        message: "Liquidation of ATC-CA-F-0990. Receipts are attached. Unused cash is for surrender.",
        user_name: REQUESTOR.name,
        created_at: submittedAt,
        replies: [],
      },
    ],
    workflowPreview: workflowPreview(family, id),
    print: printPayload({ ...base, fields }),
  };
}

function buildReimbursement(): FinanceTourSample {
  const family: EApprovalFinanceTourFamily = "reimbursement";
  const documentNo = "ATC-RE-F-0554";
  const submittedAt = "2026-10-13T11:05:00+08:00";
  const formName = "Reimbursement";
  const id = "tour-finance-reimbursement";
  const fields = [
    { key: "subsidiary", label: "Subsidiary", value: "ATC" },
    { key: "purpose", label: "Purpose", value: "Out-of-pocket site run, Lipa" },
    { key: "total_reimbursement", label: "Total expenses", value: "4,800.00" },
    { key: "vat_total", label: "VAT", value: "576.00" },
    { key: "non_vatable_total", label: "Non-VATable", value: "0.00" },
    { key: "vatable_net", label: "VATable, net of VAT", value: "4,224.00" },
    { key: "notes", label: "Notes", value: "Paid personally. No cash advance was issued for this run." },
  ];
  const base = {
    family,
    formName,
    documentNo,
    columns: RE_COLUMNS,
    rows: RE_ROWS,
    files: ["lipa-or-10442.pdf"],
    fileLabel: "Receipts",
    submittedAt,
  };
  return {
    ...base,
    purpose: "Out-of-pocket site run, Lipa",
    subsidiary: "ATC",
    department: "Network Operations",
    pickerBlurb: "Reimburse out-of-pocket expenses. No cash advance parent. The same approval chain, then Finance or Treasury pays the total.",
    composeLead: "Receipts are required. Total expenses is the sum of the Amount column. Sample only — nothing is saved.",
    chainSummary: chainSummary(family),
    notes: "Paid personally. No cash advance was issued for this run.",
    facts: [
      { label: "Subsidiary", value: "ATC" },
      { label: "Purpose", value: "Out-of-pocket site run, Lipa" },
    ],
    linesTitle: "Actual expenses",
    totals: [
      { label: "Total expenses", value: "4,800.00" },
      { label: "VAT", value: "576.00" },
      { label: "Non-VATable", value: "0.00" },
      { label: "VATable, net of VAT", value: "4,224.00" },
    ],
    relatedTitle: "Cash advance",
    relatedBody: "Reimbursement stands alone. It does not liquidate a cash advance and does not reduce an open balance.",
    listRow: {
      id,
      document_no: documentNo,
      status: "pending",
      current_step: 1,
      step_count: CHAINS[family].length,
      form_id: id,
      form_name: formName,
      form_family: family,
      subsidiary: "ATC",
      department: "Network Operations",
      workflow_steps: workflowSteps(family),
      requestor: { ...REQUESTOR },
      created_at: submittedAt,
    },
    approvals: approvalsFor(id, documentNo, family),
    comments: [
      {
        id: `${id}-cmt-1`,
        message: "Out-of-pocket Lipa run. Official receipts are attached.",
        user_name: REQUESTOR.name,
        created_at: submittedAt,
        replies: [],
      },
    ],
    workflowPreview: workflowPreview(family, id),
    print: printPayload({ ...base, fields }),
  };
}

export const eApprovalFinanceTourSamples: Record<EApprovalFinanceTourFamily, FinanceTourSample> = {
  cash_advance: buildCashAdvance(),
  liquidation: buildLiquidation(),
  reimbursement: buildReimbursement(),
};

export const eApprovalFinanceTourSampleList: FinanceTourSample[] = [
  eApprovalFinanceTourSamples.cash_advance,
  eApprovalFinanceTourSamples.liquidation,
  eApprovalFinanceTourSamples.reimbursement,
];

export function eApprovalFinanceTourSample(family: EApprovalFinanceTourFamily): FinanceTourSample {
  return eApprovalFinanceTourSamples[family];
}

export { formatTourSampleTimestamp, REQUESTOR as eApprovalFinanceTourRequestor };
