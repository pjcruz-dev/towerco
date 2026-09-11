import type { PaginatedMeta } from "@/lib/api/paginated";
import type {
  CreateTicketingTicketInput,
  TicketingDashboardResponse,
  TicketingMetadata,
  TicketingSettings,
  TicketingTicketDetail,
  TicketingTicketListRow,
  TicketingUserRef,
} from "@/modules/ticketing/types";
import { apiClient } from "@/lib/api/client";
import { moduleListExportParamsSerializer } from "@/lib/api/module-list-export-params";
import { parseModuleListExportResponse, type ModuleListExportResult } from "@/lib/ui/module-list-export-response";

export async function fetchTicketingDashboard(
  params: Pick<
    TicketingTicketListParams,
    "status" | "priority" | "category" | "department" | "mine" | "assigned_me"
  > = {},
): Promise<TicketingDashboardResponse> {
  const response = await apiClient.get<{ data: TicketingDashboardResponse }>("/ticketing/dashboard", {
    params: cleanListParams(params),
  });
  return response.data.data;
}

export async function fetchTicketingMetadata(): Promise<TicketingMetadata> {
  const response = await apiClient.get<{ data: TicketingMetadata }>("/ticketing/metadata");
  return response.data.data;
}

export async function fetchTicketingAssignableUsers(): Promise<TicketingUserRef[]> {
  const response = await apiClient.get<{ data: TicketingUserRef[] }>("/ticketing/assignable-users");
  return response.data.data;
}

export type TicketingTicketListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  priority?: string;
  category?: string;
  department?: string;
  source_module?: string;
  source_reference_id?: string;
  linked_module?: string;
  linked_id?: string;
  mine?: boolean;
  assigned_me?: boolean;
  sla_status?: string;
  sort?: string;
};

function cleanListParams(
  params: TicketingTicketListParams & { format?: "csv" | "xlsx" | "html" },
): Record<string, string | number | boolean> {
  const cleaned: Record<string, string | number | boolean> = {};
  if (params.page) cleaned.page = params.page;
  if (params.per_page) cleaned.per_page = params.per_page;
  if (params.search?.trim()) cleaned.search = params.search.trim();
  if (params.status) cleaned.status = params.status;
  if (params.priority) cleaned.priority = params.priority;
  if (params.category) cleaned.category = params.category;
  if (params.department) cleaned.department = params.department;
  if (params.source_module) cleaned.source_module = params.source_module;
  if (params.source_reference_id) cleaned.source_reference_id = params.source_reference_id;
  if (params.linked_module) cleaned.linked_module = params.linked_module;
  if (params.linked_id) cleaned.linked_id = params.linked_id;
  if (params.mine) cleaned.mine = true;
  if (params.assigned_me) cleaned.assigned_me = true;
  if (params.sla_status) cleaned.sla_status = params.sla_status;
  if (params.sort) cleaned.sort = params.sort;
  if (params.format) cleaned.format = params.format;
  return cleaned;
}

export async function fetchTicketingTickets(
  params: TicketingTicketListParams = {},
): Promise<{ data: TicketingTicketListRow[]; meta: PaginatedMeta }> {
  const response = await apiClient.get<{ data: TicketingTicketListRow[]; meta: PaginatedMeta }>(
    "/ticketing/tickets",
    { params: cleanListParams(params) },
  );
  return response.data;
}

export async function downloadTicketingTicketsExport(
  params: Omit<TicketingTicketListParams, "page" | "per_page" | "sort"> & {
    format?: "csv" | "xlsx" | "html";
    columns?: string[];
    ids?: string[];
    async?: boolean;
  } = {},
): Promise<ModuleListExportResult> {
  const response = await apiClient.get<Blob | { data: Record<string, unknown> }>("/ticketing/tickets/export", {
    params: {
      ...cleanListParams(params),
      columns: params.columns && params.columns.length > 0 ? params.columns : undefined,
      ids: params.ids && params.ids.length > 0 ? params.ids : undefined,
      async: params.async ? 1 : undefined,
    },
    paramsSerializer: moduleListExportParamsSerializer,
    responseType: "blob",
    validateStatus: (status) => (status >= 200 && status < 300) || status === 202,
  });
  return parseModuleListExportResponse(response);
}

export async function fetchTicketingTicket(ticketId: string): Promise<TicketingTicketDetail> {
  const response = await apiClient.get<{ data: TicketingTicketDetail }>(`/ticketing/tickets/${ticketId}`);
  return response.data.data;
}

export async function createTicketingTicket(payload: CreateTicketingTicketInput): Promise<TicketingTicketDetail> {
  const response = await apiClient.post<{ data: TicketingTicketDetail }>("/ticketing/tickets", payload);
  return response.data.data;
}

export async function updateTicketingTicket(
  ticketId: string,
  payload: Partial<CreateTicketingTicketInput> & {
    status?: string;
    priority?: string;
    resolution_comment?: string;
  },
): Promise<TicketingTicketDetail> {
  const response = await apiClient.patch<{ data: TicketingTicketDetail }>(
    `/ticketing/tickets/${ticketId}`,
    payload,
  );
  return response.data.data;
}

export async function addTicketingComment(
  ticketId: string,
  payload: { body: string; is_internal?: boolean },
): Promise<void> {
  await apiClient.post(`/ticketing/tickets/${ticketId}/comments`, payload);
}

export async function uploadTicketingAttachment(
  ticketId: string,
  file: File,
): Promise<{ id: string; file_name: string }> {
  const form = new FormData();
  form.append("file", file);
  const response = await apiClient.post<{ data: { id: string; file_name: string } }>(
    `/ticketing/tickets/${ticketId}/attachments`,
    form,
    { headers: { "Content-Type": "multipart/form-data" } },
  );
  return response.data.data;
}

export async function fetchTicketingSettings(): Promise<TicketingSettings> {
  const response = await apiClient.get<{ data: TicketingSettings }>("/ticketing/settings");
  return response.data.data;
}

export async function updateTicketingSettings(
  payload: Partial<Omit<TicketingSettings, "categories">> & {
    categories?: Array<
      | string
      | {
          id: string;
          label?: string;
          sla_response_minutes?: number | null;
          sla_escalation_minutes?: number | null;
        }
    >;
    apply_category_pack?: string;
  },
): Promise<TicketingSettings> {
  const response = await apiClient.put<{ data: TicketingSettings }>("/ticketing/settings", payload);
  return response.data.data;
}

export type TicketingTestEmailResult = {
  message: string;
  sent_to: string;
  mailer: string;
};

export async function sendTicketingSettingsTestEmail(): Promise<TicketingTestEmailResult> {
  const response = await apiClient.post<{ data: TicketingTestEmailResult }>("/ticketing/settings/test-email");
  return response.data.data;
}

export async function sendTicketingSettingsTestWebhook(
  teamsWebhookUrl?: string,
): Promise<{ message: string }> {
  const response = await apiClient.post<{ data: { message: string } }>("/ticketing/settings/test-webhook", {
    teams_webhook_url: teamsWebhookUrl?.trim() || undefined,
  });
  return response.data.data;
}

export async function fetchTicketingAttachmentBlob(attachmentId: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/ticketing/attachments/${attachmentId}`, {
    responseType: "blob",
  });
  return response.data;
}

export async function downloadTicketingAttachment(attachmentId: string, fileName: string): Promise<void> {
  const blob = await fetchTicketingAttachmentBlob(attachmentId);
  const url = window.URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = fileName;
  anchor.click();
  window.URL.revokeObjectURL(url);
}
