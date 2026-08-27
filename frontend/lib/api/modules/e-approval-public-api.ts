import axios from "axios";

import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { publicTenantApiClient } from "@/lib/api/public-tenant-client";

export type EApprovalPublicPlanFeatures = {
  plan_tier: string;
  file_uploads: boolean;
  max_file_fields: number | null;
};

export type EApprovalPublicFormPayload = {
  requires_password: boolean;
  sponsor_label: string | null;
  plan_features: EApprovalPublicPlanFeatures;
  approver_options: { id: string; label: string }[];
  form: {
    id: string;
    name: string;
    description: string | null;
    brand_logo_url: string | null;
    brand_primary_color: string | null;
    fields: EApprovalFormFieldInput[];
  };
};

export type EApprovalPublicSubmitResult = {
  submission_id: string;
  document_no: string;
  upload_token: string;
  upload_token_expires_at: string;
};

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
  sponsor: { id: string; name: string; email: string } | null;
  created_at: string | null;
};

export async function fetchEApprovalPublicForm(
  accessToken: string,
  accessPassword?: string,
): Promise<EApprovalPublicFormPayload> {
  const response = await publicTenantApiClient.get<{ data: EApprovalPublicFormPayload }>(
    `/public/e-approval/forms/${encodeURIComponent(accessToken)}`,
    {
      params: accessPassword ? { access_password: accessPassword } : undefined,
    },
  );
  return response.data.data;
}

export async function submitEApprovalPublicForm(
  accessToken: string,
  payload: {
    submitter_name: string;
    submitter_email: string;
    values: Record<string, string>;
    access_password?: string;
    /** Selected file counts per field — uploaded after create with upload_token. */
    pending_attachment_counts?: Record<string, number>;
  },
): Promise<EApprovalPublicSubmitResult> {
  const response = await publicTenantApiClient.post<{ data: EApprovalPublicSubmitResult }>(
    `/public/e-approval/forms/${encodeURIComponent(accessToken)}/submissions`,
    payload,
  );
  return response.data.data;
}

export async function uploadEApprovalPublicAttachment(
  accessToken: string,
  submissionId: string,
  uploadToken: string,
  file: File,
  fieldName?: string,
  accessPassword?: string,
): Promise<{ id: string; file_name: string; field_name: string | null }> {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("upload_token", uploadToken);
  if (fieldName) {
    formData.append("field_name", fieldName);
  }
  if (accessPassword) {
    formData.append("access_password", accessPassword);
  }

  const response = await publicTenantApiClient.post<{
    data: { id: string; file_name: string; field_name: string | null };
  }>(
    `/public/e-approval/forms/${encodeURIComponent(accessToken)}/submissions/${submissionId}/attachments`,
    formData,
    { timeout: 120_000 },
  );
  return response.data.data;
}

export type EApprovalPublicRevisePayload = {
  submission_id: string;
  document_no: string;
  status: string;
  revision_notes: string | null;
  submitter_name: string | null;
  submitter_email: string | null;
  values: Record<string, string>;
  upload_token: string;
  upload_token_expires_at: string;
  requires_password: boolean;
  sponsor_label: string | null;
  plan_features: EApprovalPublicPlanFeatures;
  approver_options: { id: string; label: string }[];
  form: EApprovalPublicFormPayload["form"];
};

export async function fetchEApprovalPublicRevision(
  submissionId: string,
  resubmitToken: string,
): Promise<EApprovalPublicRevisePayload> {
  const response = await publicTenantApiClient.get<{ data: EApprovalPublicRevisePayload }>(
    `/public/e-approval/submissions/${encodeURIComponent(submissionId)}/revise`,
    { params: { resubmit_token: resubmitToken } },
  );
  return response.data.data;
}

export async function resubmitEApprovalPublicRevision(
  submissionId: string,
  payload: { resubmit_token: string; values: Record<string, string> },
): Promise<EApprovalPublicSubmitResult> {
  const response = await publicTenantApiClient.put<{ data: EApprovalPublicSubmitResult }>(
    `/public/e-approval/submissions/${encodeURIComponent(submissionId)}/resubmit`,
    payload,
  );
  return response.data.data;
}

export async function uploadEApprovalPublicRevisionAttachment(
  submissionId: string,
  uploadToken: string,
  file: File,
  fieldName?: string,
): Promise<{ id: string; file_name: string; field_name: string | null }> {
  const formData = new FormData();
  formData.append("file", file);
  formData.append("upload_token", uploadToken);
  if (fieldName) {
    formData.append("field_name", fieldName);
  }

  const response = await publicTenantApiClient.post<{
    data: { id: string; file_name: string; field_name: string | null };
  }>(`/public/e-approval/submissions/${encodeURIComponent(submissionId)}/attachments`, formData, {
    timeout: 120_000,
  });
  return response.data.data;
}

export async function downloadEApprovalPublicPackage(
  downloadToken: string,
): Promise<{ blob: Blob; fileName: string }> {
  try {
    const response = await publicTenantApiClient.get<Blob>(
      `/public/e-approval/package-downloads/${encodeURIComponent(downloadToken)}`,
      {
        responseType: "blob",
        timeout: 120_000,
      },
    );

    const disposition = String(response.headers["content-disposition"] ?? "");
    const utfMatch = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
    const plainMatch = /filename="?([^";]+)"?/i.exec(disposition);
    let fileName = "download";
    if (utfMatch?.[1]) {
      try {
        fileName = decodeURIComponent(utfMatch[1]);
      } catch {
        fileName = utfMatch[1];
      }
    } else if (plainMatch?.[1]) {
      fileName = plainMatch[1].trim();
    }

    return { blob: response.data, fileName };
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.data instanceof Blob) {
      try {
        const text = await error.response.data.text();
        const parsed = JSON.parse(text) as { message?: string };
        if (parsed.message?.trim()) {
          throw new Error(parsed.message.trim());
        }
      } catch (inner) {
        if (inner instanceof Error && !(inner instanceof SyntaxError)) {
          throw inner;
        }
      }
    }
    throw error;
  }
}

export type EApprovalPublicSharedSubmission = {
  document_no: string;
  status: string;
  form_name: string | null;
  submitted_at: string | null;
  requestor_name: string | null;
  values: Array<{
    field_id: string;
    field_name?: string | null;
    label?: string | null;
    value?: string | null;
    display_value?: string | null;
  }>;
  approvals: Array<{
    status: string;
    approver_name: string | null;
    remarks: string | null;
    decided_at: string | null;
  }>;
  attachments: Array<{
    id: string;
    field_name: string | null;
    file_name: string;
    download_url: string;
  }>;
  expires_at: string | null;
  brand_label: string;
};

export async function fetchEApprovalPublicSharedSubmission(
  shareToken: string,
): Promise<EApprovalPublicSharedSubmission> {
  const response = await publicTenantApiClient.get<{ data: EApprovalPublicSharedSubmission }>(
    `/public/e-approval/shared/${encodeURIComponent(shareToken)}`,
  );
  return response.data.data;
}
