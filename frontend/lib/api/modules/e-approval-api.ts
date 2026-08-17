import type { PaginatedMeta } from "@/lib/api/paginated";
import type {
  EApprovalApprovalPolicyConfig,
  EApprovalApprovalPolicySnapshot,
} from "@/modules/e-approval/approval-policy-types";
import type {
  EApprovalApprovalRow,
  EApprovalAssignableUser,
  EApprovalAuditRow,
  EApprovalCommentRow,
  EApprovalDashboardResponse,
  EApprovalFormDetail,
  EApprovalFormFieldInput,
  EApprovalFormListRow,
  EApprovalFormRevision,
  EApprovalWorkflowStepInput,
  EApprovalFormTemplate,
  EApprovalFormTemplateDefinition,
  EApprovalHealthResponse,
  EApprovalMeProfile,
  EApprovalOpenCashAdvance,
  EApprovalOpenPurchaseRequisition,
  EApprovalNotificationRow,
  EApprovalPdfLayoutResponse,
  EApprovalPrintPayload,
  EApprovalSubmissionDetail,
  EApprovalSubmissionListRow,
} from "@/modules/e-approval/types";
import type {
  EApprovalFormWorkspaceDashboard,
  EApprovalFormWorkspaceSummary,
} from "@/modules/e-approval/form-workspace-types";
import { apiClient } from "@/lib/api/client";
import { getErrorMessage } from "@/lib/api/error";

export async function fetchEApprovalAssignableUsers(): Promise<EApprovalAssignableUser[]> {
  const response = await apiClient.get<{ data: EApprovalAssignableUser[] }>("/e-approval/assignable-users");
  return response.data.data;
}

export type EApprovalManagerLookupTestResult = {
  ok: boolean;
  code?: string;
  message: string;
  requestor_email?: string;
  manager_email?: string | null;
  manager_name?: string | null;
  manager_user?: { id: string; name: string; email: string } | null;
  auto_provision_enabled?: boolean;
  would_auto_provision?: boolean;
};

export async function testEApprovalManagerLookup(email: string): Promise<EApprovalManagerLookupTestResult> {
  const response = await apiClient.post<{ data: EApprovalManagerLookupTestResult }>(
    "/e-approval/workflow/test-manager-lookup",
    { email },
  );
  return response.data.data;
}

export async function fetchEApprovalDashboard(): Promise<EApprovalDashboardResponse> {
  const response = await apiClient.get<{ data: EApprovalDashboardResponse }>("/e-approval/dashboard");
  return response.data.data;
}

export async function fetchEApprovalHealth(): Promise<EApprovalHealthResponse> {
  const response = await apiClient.get<{ data: EApprovalHealthResponse }>("/e-approval/health");
  return response.data.data;
}

export async function fetchEApprovalFormTemplates(): Promise<EApprovalFormTemplate[]> {
  const response = await apiClient.get<{ data: EApprovalFormTemplate[] }>("/e-approval/form-templates");
  return response.data.data;
}

export async function createEApprovalFormFromTemplate(templateId: string): Promise<EApprovalFormDetail> {
  const response = await apiClient.post<{ data: { form: EApprovalFormDetail } }>("/e-approval/form-templates", {
    template_id: templateId,
  });
  return response.data.data.form;
}

export async function createEApprovalFinanceProcurementBundle(): Promise<{
  bundle: { id: string; name: string; description?: string; template_ids: string[] };
  forms: EApprovalFormDetail[];
  warnings: string[];
}> {
  const response = await apiClient.post<{
    data: {
      bundle: { id: string; name: string; description?: string; template_ids: string[] };
      forms: EApprovalFormDetail[];
      warnings: string[];
    };
  }>("/e-approval/form-templates/finance-procurement-bundle");
  return response.data.data;
}

export async function fetchEApprovalCustomFormTemplate(
  templateId: string,
): Promise<EApprovalFormTemplateDefinition> {
  const response = await apiClient.get<{ data: EApprovalFormTemplateDefinition }>(
    `/e-approval/form-templates/custom/${templateId}`,
  );
  return response.data.data;
}

export async function saveEApprovalCustomFormTemplate(payload: {
  id?: string;
  name: string;
  description?: string | null;
  category?: string;
  fields: EApprovalFormFieldInput[];
  steps?: EApprovalWorkflowStepInput[];
}): Promise<EApprovalFormTemplate> {
  if (payload.id) {
    const response = await apiClient.put<{ data: EApprovalFormTemplate }>(
      `/e-approval/form-templates/custom/${payload.id}`,
      payload,
    );
    return response.data.data;
  }

  const response = await apiClient.post<{ data: EApprovalFormTemplate }>("/e-approval/form-templates/custom", payload);
  return response.data.data;
}

export async function deleteEApprovalCustomFormTemplate(templateId: string): Promise<void> {
  await apiClient.delete(`/e-approval/form-templates/custom/${templateId}`);
}

export async function fetchEApprovalFormsIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: "published" | "draft";
  sort?: string;
}): Promise<{ data: EApprovalFormListRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: EApprovalFormListRow[]; meta: PaginatedMeta }>("/e-approval/forms", {
    params,
  });
  return response.data;
}

export async function fetchEApprovalForm(id: string): Promise<EApprovalFormDetail> {
  const response = await apiClient.get<{ data: EApprovalFormDetail }>(`/e-approval/forms/${id}`, {
    timeout: 60_000,
  });
  return response.data.data;
}

export type EApprovalWorkflowPreviewStep = {
  step_order: number;
  type: string;
  label: string;
  resolved_user_id: string | null;
  resolved_user_name: string | null;
  resolved_user_email: string | null;
  mapping_source_field?: string | null;
  mapping_source_value?: string | null;
  mapping_matched_key?: string | null;
  path_reason?: string | null;
  parallel_mode?: "all" | "any" | "n_of_m" | null;
  parallel_quorum?: number | null;
  used_fallback?: boolean;
  warning: string | null;
  runtime_status?: string | null;
  approval_id?: string | null;
  acted_at?: string | null;
  /** Present when the matched approval recorded a signature. */
  signature?: string | null;
  runtime_approver?: { id: string; name: string; email: string } | null;
};

export type EApprovalWorkflowPreviewSkippedStep = {
  step_order: number;
  type: string;
  label: string;
  path_reason?: string | null;
};

export type EApprovalWorkflowPreviewResponse = {
  workflow_mode: string;
  matched_rule_id: string | null;
  matched_rule_label: string | null;
  definition_source?: "workflow_snapshot" | "live_form" | "none";
  resolved_steps: EApprovalWorkflowPreviewStep[];
  skipped_steps?: EApprovalWorkflowPreviewSkippedStep[];
};

export async function previewEApprovalFormWorkflow(
  formId: string,
  values: Record<string, string> = {},
  requestorEmail?: string,
): Promise<EApprovalWorkflowPreviewResponse> {
  const response = await apiClient.post<{ data: EApprovalWorkflowPreviewResponse }>(
    `/e-approval/forms/${formId}/workflow-preview`,
    {
      values: { ...values },
      ...(requestorEmail?.trim() ? { requestor_email: requestorEmail.trim() } : {}),
    },
  );
  return response.data.data;
}

export async function previewEApprovalSubmissionWorkflow(
  submissionId: string,
): Promise<EApprovalWorkflowPreviewResponse> {
  const response = await apiClient.get<{ data: EApprovalWorkflowPreviewResponse }>(
    `/e-approval/submissions/${submissionId}/workflow-preview`,
  );
  return response.data.data;
}

export async function createEApprovalForm(payload: Record<string, unknown>): Promise<EApprovalFormDetail> {
  const response = await apiClient.post<{ data: { form: EApprovalFormDetail } }>("/e-approval/forms", payload);
  return response.data.data.form;
}

export async function updateEApprovalForm(id: string, payload: Record<string, unknown>): Promise<EApprovalFormDetail> {
  const response = await apiClient.put<{ data: { form: EApprovalFormDetail } }>(`/e-approval/forms/${id}`, payload, {
    timeout: 60000,
  });
  return response.data.data.form;
}

export async function deleteEApprovalForm(id: string): Promise<void> {
  await apiClient.delete(`/e-approval/forms/${id}`);
}

export async function publishEApprovalForm(id: string): Promise<EApprovalFormDetail> {
  const response = await apiClient.post<{ data: EApprovalFormDetail }>(`/e-approval/forms/${id}/publish`);
  return response.data.data;
}

export async function fetchEApprovalFormRevisions(formId: string): Promise<EApprovalFormRevision[]> {
  const response = await apiClient.get<{ data: EApprovalFormRevision[] }>(`/e-approval/forms/${formId}/revisions`);
  return response.data.data;
}

export async function restoreEApprovalFormRevision(
  formId: string,
  revision: number,
): Promise<{ form: EApprovalFormDetail; warnings: string[] }> {
  const response = await apiClient.post<{ data: { form: EApprovalFormDetail; warnings: string[] } }>(
    `/e-approval/forms/${formId}/revisions/${revision}/restore`,
  );
  return response.data.data;
}

export async function fetchEApprovalSubmissionsIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  mine?: boolean;
  form_id?: string;
  workspace_all?: boolean;
  from?: string;
  to?: string;
  sort?: string;
}): Promise<{ data: EApprovalSubmissionListRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: EApprovalSubmissionListRow[]; meta: PaginatedMeta }>(
    "/e-approval/submissions",
    {
      params: {
        ...params,
        workspace_all: params.workspace_all ? 1 : undefined,
      },
    },
  );
  return response.data;
}

/** Shared React Query key so the sidebar and command palette reuse one cached workspace fetch. */
export const EAPPROVAL_FORM_WORKSPACES_QUERY_KEY = ["e-approval", "workspaces", "list"] as const;

export async function fetchEApprovalFormWorkspaces(): Promise<EApprovalFormWorkspaceSummary[]> {
  const response = await apiClient.get<{
    data: { items: EApprovalFormWorkspaceSummary[] };
  }>("/e-approval/workspaces");
  return response.data.data.items ?? [];
}

export async function fetchEApprovalFormWorkspaceDashboard(
  slug: string,
): Promise<EApprovalFormWorkspaceDashboard> {
  const response = await apiClient.get<{
    data: EApprovalFormWorkspaceDashboard;
  }>(`/e-approval/workspaces/${encodeURIComponent(slug)}`);
  return response.data.data;
}

export type EApprovalWorkspaceSubmissionRow = EApprovalSubmissionListRow & {
  field_values?: Record<string, string | null>;
};

export async function fetchEApprovalWorkspaceSubmissions(
  slug: string,
  params: {
    page?: number;
    per_page?: number;
    search?: string;
    status?: string;
    mine?: boolean;
    from?: string;
    to?: string;
    sort?: string;
  } = {},
): Promise<{ data: EApprovalWorkspaceSubmissionRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: EApprovalWorkspaceSubmissionRow[]; meta: PaginatedMeta }>(
    `/e-approval/workspaces/${encodeURIComponent(slug)}/submissions`,
    {
      params: {
        ...params,
        mine: params.mine ? 1 : undefined,
      },
    },
  );
  return response.data;
}

export async function fetchEApprovalSubmission(id: string): Promise<EApprovalSubmissionDetail> {
  const response = await apiClient.get<{ data: EApprovalSubmissionDetail }>(`/e-approval/submissions/${id}`);
  return response.data.data;
}

export async function fetchEApprovalSubmissionPrintData(id: string): Promise<EApprovalPrintPayload> {
  const response = await apiClient.get<{ data: EApprovalPrintPayload }>(`/e-approval/submissions/${id}/print`);
  return response.data.data;
}

export async function fetchEApprovalFormMyDraft(
  formId: string,
): Promise<EApprovalSubmissionDetail | null> {
  const response = await apiClient.get<{ data: { draft: EApprovalSubmissionDetail | null } }>(
    `/e-approval/forms/${formId}/my-draft`,
  );
  return response.data.data.draft ?? null;
}

export async function fetchEApprovalOpenCashAdvances(
  forFormId?: string,
): Promise<EApprovalOpenCashAdvance[]> {
  const response = await apiClient.get<{ data: { items: EApprovalOpenCashAdvance[] } }>(
    "/e-approval/cash-advances/open",
    {
      params: forFormId ? { for_form_id: forFormId } : undefined,
    },
  );
  return response.data.data.items ?? [];
}

export async function fetchEApprovalOpenPurchaseRequisitions(
  forFormId?: string,
  options?: { scope?: "requestor" | "procurement" },
): Promise<EApprovalOpenPurchaseRequisition[]> {
  const response = await apiClient.get<{ data: { items: EApprovalOpenPurchaseRequisition[] } }>(
    "/e-approval/purchase-requisitions/open",
    {
      params: {
        ...(forFormId ? { for_form_id: forFormId } : {}),
        ...(options?.scope === "procurement" ? { scope: "procurement" } : {}),
      },
    },
  );
  return response.data.data.items ?? [];
}

export type EApprovalSubmissionWriteOptions = {
  asDraft?: boolean;
  parentSubmissionId?: string | null;
};

function buildSubmissionWriteBody(
  values: Record<string, unknown>,
  options?: EApprovalSubmissionWriteOptions,
): Record<string, unknown> {
  const body: Record<string, unknown> = { values };

  if (options?.parentSubmissionId !== undefined) {
    body.parent_submission_id = options.parentSubmissionId;
  }

  return body;
}

export async function createEApprovalSubmission(
  formId: string,
  values: Record<string, unknown>,
  options?: EApprovalSubmissionWriteOptions,
): Promise<EApprovalSubmissionDetail> {
  const payload: Record<string, unknown> = {
    form_id: formId,
    values,
    as_draft: options?.asDraft ? true : undefined,
  };

  if (options?.parentSubmissionId !== undefined) {
    payload.parent_submission_id = options.parentSubmissionId;
  }

  const response = await apiClient.post<{ data: EApprovalSubmissionDetail }>(
    "/e-approval/submissions",
    payload,
    { timeout: 60_000 },
  );
  const submission = response.data?.data;
  if (!submission?.id) {
    throw new Error("Submission was created but the server response was incomplete. Check Submissions for your request.");
  }
  return submission;
}

export async function uploadEApprovalSubmissionAttachment(
  submissionId: string,
  file: File,
  fieldName?: string,
  metadata?: Record<string, unknown> | null,
): Promise<{ id: string; file_name: string; field_name: string | null; metadata?: Record<string, unknown> | null }> {
  const form = new FormData();
  form.append("file", file);
  if (fieldName) {
    form.append("field_name", fieldName);
  }
  if (metadata && Object.keys(metadata).length > 0) {
    form.append("metadata", JSON.stringify(metadata));
  }

  const response = await apiClient.post<{
    data: { id: string; file_name: string; field_name: string | null; metadata?: Record<string, unknown> | null };
  }>(`/e-approval/submissions/${submissionId}/attachments`, form, { timeout: 120_000 });
  return response.data.data;
}

export async function uploadEApprovalSubmissionAttachmentsOrThrow(
  submissionId: string,
  attachmentFiles: Record<string, File[]>,
  attachmentMetadata?: Record<string, Record<string, Record<string, unknown>>>,
): Promise<void> {
  const failedUploads: string[] = [];

  for (const [fieldName, files] of Object.entries(attachmentFiles)) {
    for (const file of files) {
      try {
        const metadata = attachmentMetadata?.[fieldName]?.[file.name] ?? null;
        await uploadEApprovalSubmissionAttachment(submissionId, file, fieldName, metadata);
      } catch (uploadError) {
        failedUploads.push(`${fieldName}: ${getErrorMessage(uploadError)}`);
      }
    }
  }

  if (failedUploads.length > 0) {
    throw new Error(
      failedUploads.length === 1
        ? failedUploads[0]!
        : `Some files could not be uploaded: ${failedUploads.join("; ")}`,
    );
  }
}

export async function downloadEApprovalAttachment(attachmentId: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/e-approval/attachments/${attachmentId}`, {
    responseType: "blob",
  });
  return response.data;
}

/** Authenticated download of an attachment to the user's device. */
export async function downloadEApprovalAttachmentFile(attachmentId: string, fileName: string): Promise<void> {
  const blob = await downloadEApprovalAttachment(attachmentId);
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = fileName || "attachment";
  anchor.rel = "noopener";
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  window.URL.revokeObjectURL(url);
}

export async function deleteEApprovalAttachment(attachmentId: string): Promise<void> {
  await apiClient.delete(`/e-approval/attachments/${attachmentId}`);
}

export async function updateEApprovalSubmissionDraft(
  submissionId: string,
  values: Record<string, unknown>,
  options?: Pick<EApprovalSubmissionWriteOptions, "parentSubmissionId">,
): Promise<EApprovalSubmissionDetail> {
  const response = await apiClient.put<{ data: EApprovalSubmissionDetail }>(
    `/e-approval/submissions/${submissionId}/draft`,
    buildSubmissionWriteBody(values, options),
  );
  return response.data.data;
}

export async function submitEApprovalSubmissionDraft(
  submissionId: string,
  values: Record<string, unknown>,
  options?: Pick<EApprovalSubmissionWriteOptions, "parentSubmissionId">,
): Promise<EApprovalSubmissionDetail> {
  const response = await apiClient.post<{ data: EApprovalSubmissionDetail }>(
    `/e-approval/submissions/${submissionId}/submit`,
    buildSubmissionWriteBody(values, options),
    { timeout: 60_000 },
  );
  const payload = response.data?.data;
  if (!payload?.id) {
    throw new Error("Submission was submitted but the server response was incomplete.");
  }
  return payload;
}

export async function cancelEApprovalSubmission(id: string): Promise<EApprovalSubmissionDetail> {
  const response = await apiClient.post<{ data: EApprovalSubmissionDetail }>(`/e-approval/submissions/${id}/cancel`);
  return response.data.data;
}

export async function resubmitEApprovalSubmission(
  id: string,
  values: Record<string, unknown>,
): Promise<EApprovalSubmissionDetail> {
  const response = await apiClient.put<{ data: EApprovalSubmissionDetail }>(`/e-approval/submissions/${id}/resubmit`, {
    values,
  });
  return response.data.data;
}

export async function fetchEApprovalApprovalsIndex(params: {
  page?: number;
  per_page?: number;
  status?: string;
  awaiting_me?: boolean;
  sort?: string;
}): Promise<{ data: EApprovalApprovalRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: EApprovalApprovalRow[]; meta: PaginatedMeta }>("/e-approval/approvals", {
    params: {
      ...params,
      awaiting_me: params.awaiting_me ? 1 : undefined,
    },
  });
  return response.data;
}

export async function decideEApprovalApproval(
  id: string,
  payload: {
    decision: "approved" | "rejected";
    remarks?: string;
    signature?: string | null;
    signature_consent?: boolean;
  },
): Promise<void> {
  await apiClient.post(`/e-approval/approvals/${id}/decide`, payload);
}

export async function fetchEApprovalComments(submissionId: string): Promise<EApprovalCommentRow[]> {
  const response = await apiClient.get<{ data: EApprovalCommentRow[] }>(
    `/e-approval/submissions/${submissionId}/comments`,
  );
  return response.data.data;
}

export async function postEApprovalComment(
  submissionId: string,
  payload: { message: string; parent_id?: string },
): Promise<void> {
  await apiClient.post(`/e-approval/submissions/${submissionId}/comments`, payload);
}

export async function fetchEApprovalAuditIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  action?: string;
  from?: string;
  to?: string;
  sort?: string;
}): Promise<{ data: EApprovalAuditRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: EApprovalAuditRow[]; meta: PaginatedMeta }>("/e-approval/audit", {
    params,
  });
  return response.data;
}

export type EApprovalExportColumn = {
  key: string;
  label: string;
  group: string;
};

export type EApprovalExportGridField = {
  key: string;
  label: string;
};

export type EApprovalExportColumnsResult = {
  columns: EApprovalExportColumn[];
  grids: EApprovalExportGridField[];
  forms: Array<{ id: string; name: string; status: string }>;
};

export async function fetchEApprovalSubmissionsExportColumns(
  formId?: string,
): Promise<EApprovalExportColumnsResult> {
  const response = await apiClient.get<{
    data: EApprovalExportColumn[];
    grids?: EApprovalExportGridField[];
    forms?: Array<{ id: string; name: string; status: string }>;
  }>("/e-approval/submissions/export/columns", {
    params: { form_id: formId || undefined },
  });
  return {
    columns: response.data.data,
    grids: response.data.grids ?? [],
    forms: response.data.forms ?? [],
  };
}

export type EApprovalExportHistoryRow = {
  id: string;
  report_definition_id: string | null;
  name: string | null;
  filters: Record<string, unknown>;
  columns: string[] | null;
  layout: string;
  format: string;
  grid_field_id: string | null;
  matched_rows: number;
  exported_rows: number;
  truncated: boolean;
  status: string;
  triggered_by: string;
  filename: string | null;
  remarks: string | null;
  error_message?: string | null;
  expires_at?: string | null;
  download?: { url: string; stream: boolean } | null;
  created_at: string | null;
};

export type EApprovalExportResult =
  | {
      mode: "sync";
      blob: Blob;
      truncated: boolean;
      totalRows: number;
      maxRows: number;
      historyId?: string;
    }
  | {
      mode: "async";
      history: EApprovalExportHistoryRow;
      matchedRows: number;
      maxRows: number;
      message: string;
    };

async function parseExportResponse(response: {
  status: number;
  data: Blob | { data?: Record<string, unknown> };
  headers: Record<string, unknown>;
}): Promise<EApprovalExportResult> {
  const toNumber = (value: unknown): number => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  };

  if (response.status === 202) {
    let payload: Record<string, unknown> | null = null;
    if (response.data instanceof Blob) {
      const text = await response.data.text();
      try {
        const parsed = JSON.parse(text) as { data?: Record<string, unknown> };
        payload = parsed.data ?? null;
      } catch {
        payload = null;
      }
    } else if (response.data && typeof response.data === "object" && "data" in response.data) {
      payload = (response.data.data as Record<string, unknown>) ?? null;
    }

    const history = (payload?.history ?? null) as EApprovalExportHistoryRow | null;
    if (!history) {
      throw new Error("Export was queued but no history payload was returned.");
    }

    return {
      mode: "async",
      history,
      matchedRows: toNumber(payload?.matched_rows),
      maxRows: toNumber(payload?.max_rows) || 100000,
      message:
        typeof payload?.message === "string"
          ? payload.message
          : "Export queued. Download from Recent exports when ready.",
    };
  }

  if (!(response.data instanceof Blob)) {
    throw new Error("Unexpected export response.");
  }

  return {
    mode: "sync",
    blob: response.data,
    truncated: String(response.headers["x-export-truncated"] ?? "0") === "1",
    totalRows: toNumber(response.headers["x-export-total-rows"]),
    maxRows: toNumber(response.headers["x-export-max-rows"]) || 5000,
    historyId: response.headers["x-export-history-id"]
      ? String(response.headers["x-export-history-id"])
      : undefined,
  };
}

export async function downloadEApprovalSubmissionsExport(
  params: {
    form_id?: string;
    statuses?: string[];
    from?: string;
    to?: string;
    search?: string;
    format?: "csv" | "xlsx";
    columns?: string[];
    layout?: "submissions" | "line_items";
    grid_field?: string;
    async?: boolean;
  } = {},
): Promise<EApprovalExportResult> {
  const statuses = (params.statuses ?? []).filter((value) => value && value !== "all");
  const response = await apiClient.get<Blob | { data: Record<string, unknown> }>(
    "/e-approval/submissions/export",
    {
      params: {
        form_id: params.form_id || undefined,
        statuses: statuses.length > 0 ? statuses : undefined,
        from: params.from || undefined,
        to: params.to || undefined,
        search: params.search?.trim() || undefined,
        format: params.format ?? "csv",
        columns: params.columns && params.columns.length > 0 ? params.columns : undefined,
        layout: params.layout && params.layout !== "submissions" ? params.layout : undefined,
        grid_field: params.layout === "line_items" ? params.grid_field || undefined : undefined,
        async: params.async ? 1 : undefined,
      },
      paramsSerializer: {
        indexes: null,
      },
      responseType: "blob",
      validateStatus: (status) => (status >= 200 && status < 300) || status === 202,
    },
  );

  return parseExportResponse(response);
}

export type EApprovalReportSchedule = {
  enabled: boolean;
  frequency: "daily" | "weekly";
  hour: number;
  day_of_week: number;
  recipients: string[];
};

export type EApprovalReportDefinition = {
  id: string;
  name: string;
  description: string | null;
  filters: {
    form_id?: string;
    status?: string;
    statuses?: string[];
    from?: string;
    to?: string;
    search?: string;
    scope?: string;
  };
  columns: string[] | null;
  layout: "submissions" | "line_items";
  format: "csv" | "xlsx";
  grid_field_id: string | null;
  schedule: EApprovalReportSchedule | null;
  last_run_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type EApprovalReportPayload = {
  name: string;
  description?: string | null;
  filters?: EApprovalReportDefinition["filters"];
  columns?: string[] | null;
  layout?: "submissions" | "line_items";
  format?: "csv" | "xlsx";
  grid_field_id?: string | null;
  schedule?: Partial<EApprovalReportSchedule> | null;
};

export async function fetchEApprovalReports(): Promise<EApprovalReportDefinition[]> {
  const response = await apiClient.get<{ data: EApprovalReportDefinition[] }>("/e-approval/reports");
  return response.data.data;
}

export async function createEApprovalReport(
  payload: EApprovalReportPayload,
): Promise<EApprovalReportDefinition> {
  const response = await apiClient.post<{ data: EApprovalReportDefinition }>("/e-approval/reports", payload);
  return response.data.data;
}

export async function updateEApprovalReport(
  id: string,
  payload: Partial<EApprovalReportPayload>,
): Promise<EApprovalReportDefinition> {
  const response = await apiClient.put<{ data: EApprovalReportDefinition }>(
    `/e-approval/reports/${id}`,
    payload,
  );
  return response.data.data;
}

export async function deleteEApprovalReport(id: string): Promise<void> {
  await apiClient.delete(`/e-approval/reports/${id}`);
}

export async function runEApprovalReport(id: string, async = false): Promise<EApprovalExportResult> {
  const response = await apiClient.post<Blob | { data: Record<string, unknown> }>(
    `/e-approval/reports/${id}/run`,
    async ? { async: true } : null,
    {
      responseType: "blob",
      validateStatus: (status) => (status >= 200 && status < 300) || status === 202,
    },
  );
  return parseExportResponse(response);
}

export async function fetchEApprovalExportHistory(limit = 50): Promise<EApprovalExportHistoryRow[]> {
  const response = await apiClient.get<{ data: EApprovalExportHistoryRow[] }>(
    "/e-approval/export-history",
    { params: { limit } },
  );
  return response.data.data;
}

export async function fetchEApprovalExportHistoryItem(
  id: string,
): Promise<EApprovalExportHistoryRow> {
  const response = await apiClient.get<{ data: EApprovalExportHistoryRow }>(
    `/e-approval/export-history/${id}`,
  );
  return response.data.data;
}

export async function downloadEApprovalExportHistoryFile(
  row: EApprovalExportHistoryRow,
): Promise<void> {
  const info = row.download;
  if (!info?.url) {
    throw new Error("Download is not available for this export yet.");
  }

  if (info.stream) {
    const blob = await apiClient.get<Blob>(info.url, { responseType: "blob" }).then((r) => r.data);
    const objectUrl = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = objectUrl;
    a.download = row.filename ?? "export";
    a.click();
    URL.revokeObjectURL(objectUrl);
    return;
  }

  window.open(info.url, "_blank", "noopener,noreferrer");
}

export type EApprovalAnalyticsSeriesRow = {
  key: string;
  label: string;
  value: number;
  href?: string | null;
  avg_age_hours?: number;
};

export type EApprovalAnalyticsResponse = {
  period: { from: string; to: string; days: number };
  kpis: Array<{
    key: string;
    label: string;
    value: string;
    change: string | null;
    tone: string;
    href: string | null;
  }>;
  submissions_over_time: EApprovalAnalyticsSeriesRow[];
  by_status: EApprovalAnalyticsSeriesRow[];
  top_forms: EApprovalAnalyticsSeriesRow[];
  cycle_times: Array<{ key: string; label: string; value: string; unit: string }>;
  bottlenecks: Array<{
    key: string;
    label: string;
    value: number;
    avg_age_hours: number;
    href: string | null;
  }>;
  approver_load: EApprovalAnalyticsSeriesRow[];
  aging: EApprovalAnalyticsSeriesRow[];
  rejection_reasons: Array<{ key: string; label: string; value: number }>;
};

export async function fetchEApprovalAnalytics(params: {
  from?: string;
  to?: string;
} = {}): Promise<EApprovalAnalyticsResponse> {
  const response = await apiClient.get<{ data: EApprovalAnalyticsResponse }>(
    "/e-approval/reports/analytics",
    { params },
  );
  return response.data.data;
}

export async function downloadEApprovalWorkspaceExport(
  slug: string,
  params: {
    status?: string;
    search?: string;
    from?: string;
    to?: string;
    mine?: boolean;
    include_fields?: boolean;
  } = {},
): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/e-approval/workspaces/${encodeURIComponent(slug)}/export`, {
    params: {
      ...params,
      mine: params.mine ? "1" : undefined,
      include_fields: params.include_fields === false ? "0" : undefined,
    },
    responseType: "blob",
  });
  return response.data;
}

export async function downloadEApprovalFormExport(formId: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/e-approval/forms/${formId}/export`, {
    responseType: "blob",
  });
  return response.data;
}

export async function importEApprovalForm(payload: Record<string, unknown>): Promise<{ id: string; warnings: string[] }> {
  const response = await apiClient.post<{ data: { id: string; warnings: string[] } }>("/e-approval/forms/import", payload);
  return response.data.data;
}

export async function fetchEApprovalPdfLayout(formId: string): Promise<EApprovalPdfLayoutResponse> {
  const response = await apiClient.get<{ data: EApprovalPdfLayoutResponse }>(`/e-approval/pdf-layout/${formId}`);
  return response.data.data;
}

export async function updateEApprovalPdfLayout(
  formId: string,
  payload: Record<string, unknown>,
): Promise<void> {
  await apiClient.put(`/e-approval/pdf-layout/${formId}`, payload);
}

export async function fetchEApprovalSubmissionPrint(submissionId: string): Promise<EApprovalPrintPayload> {
  const response = await apiClient.get<{ data: EApprovalPrintPayload }>(
    `/e-approval/submissions/${submissionId}/print`,
  );
  return response.data.data;
}

export type EApprovalMetadataResponse = {
  roles: string[];
  departments: string[];
  emails: string[];
  plan_features?: {
    plan_tier: string;
    file_uploads: boolean;
    max_file_fields: number | null;
  };
  finance_procurement_policy?: {
    liquidation_requires_parent: boolean;
    liquidation_overspend_mode: "block" | "warn";
    liquidation_max_overspend_percent: number;
    po_overspend_mode: "block" | "warn";
    po_max_overspend_percent: number;
  };
};

export async function fetchEApprovalMetadata(): Promise<EApprovalMetadataResponse> {
  const response = await apiClient.get<{ data: EApprovalMetadataResponse }>("/e-approval/metadata");
  return response.data.data;
}

export async function fetchEApprovalMasterData(key: string): Promise<{ options: { value: string; label: string; code?: string }[] }> {
  const response = await apiClient.get<{ data: { options: { value: string; label: string; code?: string }[] } }>(
    `/e-approval/master-data/${key}`,
  );
  return response.data.data;
}

export async function fetchEApprovalSettings(): Promise<Record<string, string | number>> {
  const response = await apiClient.get<{ data: Record<string, string | number> }>("/e-approval/settings");
  return response.data.data;
}

export async function updateEApprovalSettings(payload: Record<string, string | number>): Promise<void> {
  await apiClient.put("/e-approval/settings", payload);
}

export type EApprovalTestEmailResult = {
  message: string;
  sent_to: string;
  mailer: string;
};

/** Sends a TowerOS test message to the current admin (Microsoft 365 SMTP / SES — not legacy mail). */
export async function sendEApprovalSettingsTestEmail(): Promise<EApprovalTestEmailResult> {
  const response = await apiClient.post<{ data: EApprovalTestEmailResult }>(
    "/e-approval/settings/test-email",
  );
  return response.data.data;
}

export type EApprovalTestWebhookResult = {
  sent: boolean;
  message: string;
};

export async function sendEApprovalSettingsTestWebhook(): Promise<EApprovalTestWebhookResult> {
  const response = await apiClient.post<{ data: EApprovalTestWebhookResult }>(
    "/e-approval/settings/test-webhook",
  );
  return response.data.data;
}

export async function fetchEApprovalMasterDataSets(): Promise<
  { id: string; key: string; name: string; status: string; row_count: number }[]
> {
  const response = await apiClient.get<{
    data: { data: { id: string; key: string; name: string; status: string; row_count: number }[] };
  }>("/e-approval/master-data-sets");
  return response.data.data.data;
}

export async function createEApprovalMasterDataSet(payload: { key: string; name?: string }): Promise<void> {
  await apiClient.post("/e-approval/master-data-sets", payload);
}

export async function fetchEApprovalMasterDataRows(setId: string): Promise<
  { id: string; code: string | null; label: string; is_active: boolean }[]
> {
  const response = await apiClient.get<{
    data: { data: { id: string; code: string | null; label: string; is_active: boolean }[] };
  }>(`/e-approval/master-data-sets/${setId}/rows`);
  return response.data.data.data;
}

export async function requestEApprovalRevision(
  submissionId: string,
  remarks: string,
  options?: { force_full_restart?: boolean },
): Promise<EApprovalSubmissionDetail> {
  const response = await apiClient.post<{ data: EApprovalSubmissionDetail }>(
    `/e-approval/submissions/${submissionId}/revision`,
    {
      remarks,
      force_full_restart: options?.force_full_restart === true ? true : undefined,
    },
  );
  return response.data.data;
}

export async function dcfResubmitEApprovalSubmission(
  submissionId: string,
  values: Record<string, unknown>,
): Promise<EApprovalSubmissionDetail> {
  const response = await apiClient.put<{ data: EApprovalSubmissionDetail }>(
    `/e-approval/submissions/${submissionId}/dcf-resubmit`,
    { values },
  );
  return response.data.data;
}

export async function sendEApprovalManualFollowUp(submissionId: string, note?: string): Promise<void> {
  await apiClient.post(`/e-approval/submissions/${submissionId}/manual-follow-up`, { note });
}

export async function fetchEApprovalSettingsPublic(): Promise<{
  feature_delegation_ui: string;
}> {
  const response = await apiClient.get<{ data: { feature_delegation_ui: string } }>(
    "/e-approval/settings/public",
  );
  return response.data.data;
}

export async function fetchEApprovalMeProfile(): Promise<EApprovalMeProfile> {
  const response = await apiClient.get<{ data: EApprovalMeProfile }>("/e-approval/me/profile");
  return response.data.data;
}

export async function updateEApprovalMeSignature(
  signature: string | null,
  options?: { signatureConsent?: boolean },
): Promise<void> {
  await apiClient.put("/e-approval/me/signature", {
    signature,
    ...(options?.signatureConsent === true ? { signature_consent: true } : {}),
  });
}

export async function createEApprovalDelegation(payload: {
  delegate_id: string;
  valid_from?: string;
  valid_until?: string | null;
  notes?: string;
}): Promise<void> {
  await apiClient.post("/e-approval/delegations", payload);
}

export async function revokeEApprovalDelegation(id: string): Promise<void> {
  await apiClient.delete(`/e-approval/delegations/${id}`);
}

export async function fetchEApprovalDelegations(): Promise<Record<string, unknown>[]> {
  const response = await apiClient.get<{ data: { data: Record<string, unknown>[] } }>("/e-approval/delegations");
  return response.data.data.data;
}

export async function rerouteEApprovalApproval(
  approvalId: string,
  payload: { new_approver_id: string; reason: string },
): Promise<void> {
  await apiClient.post(`/e-approval/approvals/${approvalId}/reroute`, payload);
}

export async function uploadEApprovalFormLogo(formId: string, file: File): Promise<{ brand_logo_url: string }> {
  const form = new FormData();
  form.append("file", file);
  const response = await apiClient.post<{ data: { brand_logo_url: string } }>(
    `/e-approval/forms/${formId}/logo`,
    form,
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}

export type EApprovalFormOutboundFileRow = {
  id: string;
  form_id: string;
  file_name: string;
  byte_size: number;
  created_at: string | null;
};

export async function fetchEApprovalFormOutboundFiles(
  formId: string,
): Promise<EApprovalFormOutboundFileRow[]> {
  const response = await apiClient.get<{ data: EApprovalFormOutboundFileRow[] }>(
    `/e-approval/forms/${formId}/outbound-files`,
  );
  return response.data.data;
}

export async function uploadEApprovalFormOutboundFile(
  formId: string,
  file: File,
): Promise<EApprovalFormOutboundFileRow> {
  const form = new FormData();
  form.append("file", file);
  const response = await apiClient.post<{ data: EApprovalFormOutboundFileRow }>(
    `/e-approval/forms/${formId}/outbound-files`,
    form,
    { timeout: 120_000 },
  );
  return response.data.data;
}

export async function deleteEApprovalFormOutboundFile(fileId: string): Promise<void> {
  await apiClient.delete(`/e-approval/outbound-files/${fileId}`);
}

export async function createEApprovalMasterDataRow(
  setId: string,
  payload: { code?: string; label: string; sort_order?: number; is_active?: boolean },
): Promise<void> {
  await apiClient.post(`/e-approval/master-data-sets/${setId}/rows`, payload);
}

export async function updateEApprovalMasterDataRow(
  rowId: string,
  payload: Partial<{ code: string | null; label: string; sort_order: number; is_active: boolean }>,
): Promise<void> {
  await apiClient.put(`/e-approval/master-data-rows/${rowId}`, payload);
}

export async function deleteEApprovalMasterDataRow(rowId: string): Promise<void> {
  await apiClient.delete(`/e-approval/master-data-rows/${rowId}`);
}

export async function bulkImportEApprovalMasterDataRows(
  setId: string,
  rows: Record<string, unknown>[],
): Promise<{ created: number }> {
  const response = await apiClient.post<{ data: { created: number } }>(
    `/e-approval/master-data-sets/${setId}/rows/bulk`,
    { rows },
  );
  return response.data.data;
}

export async function createEApprovalDocumentLink(
  submissionId: string,
  payload: { target_submission_id: string; link_type?: string },
): Promise<{
  link: EApprovalSubmissionDetail["document_links"] extends (infer T)[] | undefined ? T : never;
  document_links: NonNullable<EApprovalSubmissionDetail["document_links"]>;
  incoming_document_links: NonNullable<EApprovalSubmissionDetail["incoming_document_links"]>;
}> {
  const response = await apiClient.post<{
    data: {
      link: NonNullable<EApprovalSubmissionDetail["document_links"]>[number];
      document_links: NonNullable<EApprovalSubmissionDetail["document_links"]>;
      incoming_document_links: NonNullable<EApprovalSubmissionDetail["incoming_document_links"]>;
    };
  }>(`/e-approval/submissions/${submissionId}/document-links`, payload);
  return response.data.data;
}

export async function deleteEApprovalDocumentLink(linkId: string): Promise<{
  document_links: NonNullable<EApprovalSubmissionDetail["document_links"]>;
  incoming_document_links: NonNullable<EApprovalSubmissionDetail["incoming_document_links"]>;
}> {
  const response = await apiClient.delete<{
    data: {
      document_links: NonNullable<EApprovalSubmissionDetail["document_links"]>;
      incoming_document_links: NonNullable<EApprovalSubmissionDetail["incoming_document_links"]>;
    };
  }>(`/e-approval/document-links/${linkId}`);
  return response.data.data;
}

export type EApprovalNotificationsIndexParams = {
  page?: number;
  per_page?: number;
  category?: "action" | "update";
  unread_only?: boolean;
};

export async function fetchEApprovalNotificationsIndex(
  params: EApprovalNotificationsIndexParams = {},
): Promise<{ data: EApprovalNotificationRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: EApprovalNotificationRow[]; meta: PaginatedMeta }>(
    "/e-approval/notifications",
    {
      params: {
        page: params.page,
        per_page: params.per_page,
        category: params.category,
        unread_only: params.unread_only ? 1 : undefined,
      },
    },
  );
  return response.data;
}

/** @deprecated Use fetchEApprovalNotificationsIndex */
export async function fetchEApprovalNotifications(): Promise<EApprovalNotificationRow[]> {
  const result = await fetchEApprovalNotificationsIndex({ page: 1, per_page: 50 });
  return result.data;
}

export async function fetchEApprovalNotificationUnreadCount(): Promise<number> {
  const response = await apiClient.get<{ data: { count: number } }>("/e-approval/notifications/unread-count");
  return response.data.data.count;
}

export async function markEApprovalNotificationRead(id: string): Promise<void> {
  await apiClient.post(`/e-approval/notifications/${id}/read`);
}

export async function markAllEApprovalNotificationsRead(category?: "action" | "update"): Promise<void> {
  await apiClient.post("/e-approval/notifications/mark-all-read", category ? { category } : undefined);
}

export type EApprovalPublicFormLinkRow = {
  id: string;
  form_id: string;
  label: string | null;
  is_enabled: boolean;
  expires_at: string | null;
  max_submissions: number | null;
  submissions_count: number;
  revoked_at: string | null;
  last_used_at: string | null;
  can_reveal_url?: boolean;
  requires_password?: boolean;
  sponsor: { id: string; name: string; email: string } | null;
  created_at: string | null;
};

export type EApprovalPublicFormLinkCreateResult = {
  link: EApprovalPublicFormLinkRow;
  public_url: string;
  token: string;
};

export type EApprovalPublicShareUrlResult = {
  public_url: string;
  label: string | null;
  requires_password: boolean;
  link_id: string;
};

export async function fetchEApprovalPublicFormLinks(formId: string): Promise<EApprovalPublicFormLinkRow[]> {
  const response = await apiClient.get<{ data: EApprovalPublicFormLinkRow[] }>(
    `/e-approval/forms/${formId}/public-links`,
  );
  return response.data.data;
}

export async function createEApprovalPublicFormLink(
  formId: string,
  payload: {
    label?: string;
    sponsor_user_id: string;
    expires_at?: string | null;
    max_submissions?: number | null;
    password?: string | null;
  },
): Promise<EApprovalPublicFormLinkCreateResult> {
  const response = await apiClient.post<{ data: EApprovalPublicFormLinkCreateResult }>(
    `/e-approval/forms/${formId}/public-links`,
    payload,
  );
  return response.data.data;
}

export async function revokeEApprovalPublicFormLink(linkId: string): Promise<EApprovalPublicFormLinkRow> {
  const response = await apiClient.post<{ data: EApprovalPublicFormLinkRow }>(
    `/e-approval/public-links/${linkId}/revoke`,
  );
  return response.data.data;
}

export async function rotateEApprovalPublicFormLink(linkId: string): Promise<EApprovalPublicFormLinkCreateResult> {
  const response = await apiClient.post<{ data: EApprovalPublicFormLinkCreateResult }>(
    `/e-approval/public-links/${linkId}/rotate`,
  );
  return response.data.data;
}

export async function revealEApprovalPublicFormLink(linkId: string): Promise<{
  public_url: string;
  link: EApprovalPublicFormLinkRow;
}> {
  const response = await apiClient.post<{
    data: { public_url: string; link: EApprovalPublicFormLinkRow };
  }>(`/e-approval/public-links/${linkId}/reveal`);
  return response.data.data;
}

export async function fetchEApprovalFormPublicShareUrl(
  formId: string,
): Promise<EApprovalPublicShareUrlResult> {
  const response = await apiClient.get<{ data: EApprovalPublicShareUrlResult }>(
    `/e-approval/forms/${formId}/public-share-url`,
  );
  return response.data.data;
}

export async function fetchEApprovalApprovalPolicy(): Promise<EApprovalApprovalPolicySnapshot> {
  const response = await apiClient.get<{ data: EApprovalApprovalPolicySnapshot }>("/e-approval/approval-policies");
  return response.data.data;
}

export async function updateEApprovalApprovalPolicyDraft(
  config: EApprovalApprovalPolicyConfig,
): Promise<EApprovalApprovalPolicySnapshot> {
  const response = await apiClient.put<{ data: EApprovalApprovalPolicySnapshot }>("/e-approval/approval-policies", {
    config,
  });
  return response.data.data;
}

export async function publishEApprovalApprovalPolicy(): Promise<EApprovalApprovalPolicySnapshot> {
  const response = await apiClient.post<{ data: EApprovalApprovalPolicySnapshot }>(
    "/e-approval/approval-policies/publish",
  );
  return response.data.data;
}
