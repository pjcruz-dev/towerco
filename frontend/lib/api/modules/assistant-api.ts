import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";

export type AssistantCitation = {
  type?: "document" | "live_data" | string;
  chunk_id: string;
  source_id: string;
  title: string;
  slug: string | null;
  module: string | null;
  scope: string;
  version: number;
  score: number;
  related_routes: string[];
  excerpt: string;
  tool?: string;
  ok?: boolean;
  row_count?: number;
};

export type AssistantRelatedLink = {
  label: string;
  href: string;
};

export type AssistantProposedAction = {
  id: string;
  action: string;
  status: string;
  title: string;
  summary: string | null;
  payload: Record<string, unknown>;
  preview: Record<string, unknown>;
  editable_fields: Array<{
    key: string;
    label: string;
    type: string;
    required?: boolean;
  }>;
  confirm_label: string;
  module_key: string | null;
  expires_at: string | null;
  requires_confirmation: boolean;
  message_id?: string;
};

export type AssistantProviderNotice = {
  provider: string;
  title: string;
  message: string;
  admin_action: string;
};

export type AssistantAskResponse = {
  conversation_id: string;
  message_id: string;
  answer: string;
  citations: AssistantCitation[];
  suggested_followups: string[];
  related_links: AssistantRelatedLink[];
  status:
    | "completed"
    | "insufficient_context"
    | "failed"
    | "placeholder"
    | "provider_quota_exceeded";
  model_name: string | null;
  used_live_data?: boolean;
  proposed_action?: AssistantProposedAction | null;
  error_code?: string | null;
  provider_notice?: AssistantProviderNotice | null;
};

export type AssistantActionConfirmResponse = {
  proposal: {
    id: string;
    action: string;
    status: string;
    confirmed_at: string | null;
  };
  result: {
    ok: boolean;
    entity_type: string | null;
    entity_id: string | null;
    entity_label: string | null;
    href: string | null;
    meta: Record<string, unknown>;
  };
};

export type AssistantConversationMessage = {
  id: string;
  conversation_id: string;
  role: "user" | "assistant" | "system";
  content: string;
  citations: AssistantCitation[] | Record<string, unknown>[] | [];
  model_name: string | null;
  status: string;
  proposed_action?: AssistantProposedAction | null;
  created_at: string | null;
};

export type AssistantConversationDetail = {
  id: string;
  title: string | null;
  module_context: string | null;
  page_path: string | null;
  status: string;
  messages: AssistantConversationMessage[];
};

export type AskAssistantPayload = {
  question: string;
  conversation_id?: string | null;
  module_context?: string | null;
  page_path?: string | null;
};

export type AssistantKnowledgeStatus = "draft" | "published" | "archived";

export type AssistantKnowledgeRow = {
  id: string;
  slug: string | null;
  scope: string;
  title: string;
  module_key: string | null;
  source_type: string;
  audience: string | null;
  status: AssistantKnowledgeStatus | string;
  version: number;
  chunk_count: number;
  published_at: string | null;
  last_indexed_at: string | null;
  updated_at: string | null;
  created_at: string | null;
};

export type AssistantKnowledgeDetail = AssistantKnowledgeRow & {
  body: string | null;
  required_permissions: string[];
  related_routes: string[];
  content_checksum: string | null;
  created_by: string | null;
  updated_by: string | null;
};

export type AssistantKnowledgePayload = {
  title: string;
  body: string;
  slug?: string | null;
  module_key?: string | null;
  audience?: string | null;
  required_permissions?: string[];
  related_routes?: string[];
};

export async function askAssistant(payload: AskAssistantPayload): Promise<AssistantAskResponse> {
  const response = await apiClient.post<{ data: AssistantAskResponse }>("/assistant/ask", {
    question: payload.question,
    conversation_id: payload.conversation_id ?? undefined,
    module_context: payload.module_context ?? undefined,
    page_path: payload.page_path ?? undefined,
  });

  return response.data.data;
}

export async function fetchAssistantConversation(
  conversationId: string,
): Promise<AssistantConversationDetail> {
  const response = await apiClient.get<{ data: AssistantConversationDetail }>(
    `/assistant/conversations/${conversationId}`,
  );

  return response.data.data;
}

export async function submitAssistantFeedback(payload: {
  message_id: string;
  rating: "up" | "down";
  comment?: string | null;
}): Promise<{ id: string; rating: string }> {
  const response = await apiClient.post<{ data: { id: string; rating: string } }>(
    "/assistant/feedback",
    payload,
  );

  return response.data.data;
}

export async function confirmAssistantAction(payload: {
  proposal_id: string;
  payload?: Record<string, unknown> | null;
}): Promise<AssistantActionConfirmResponse> {
  const response = await apiClient.post<{ data: AssistantActionConfirmResponse }>(
    "/assistant/actions/confirm",
    {
      proposal_id: payload.proposal_id,
      payload: payload.payload ?? undefined,
    },
  );

  return response.data.data;
}

export async function cancelAssistantAction(
  proposalId: string,
): Promise<AssistantProposedAction> {
  const response = await apiClient.post<{ data: AssistantProposedAction }>(
    `/assistant/actions/${proposalId}/cancel`,
  );

  return response.data.data;
}

export async function fetchAssistantKnowledgeIndex(params: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}): Promise<PaginatedEnvelope<AssistantKnowledgeRow>> {
  const response = await apiClient.get<{ data: AssistantKnowledgeRow[]; meta: PaginatedMeta }>(
    "/assistant/knowledge",
    { params },
  );

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchAssistantKnowledge(id: string): Promise<AssistantKnowledgeDetail> {
  const response = await apiClient.get<{ data: AssistantKnowledgeDetail }>(
    `/assistant/knowledge/${id}`,
  );

  return response.data.data;
}

export async function createAssistantKnowledge(
  payload: AssistantKnowledgePayload,
): Promise<AssistantKnowledgeDetail> {
  const response = await apiClient.post<{ data: AssistantKnowledgeDetail }>(
    "/assistant/knowledge",
    payload,
  );

  return response.data.data;
}

export async function updateAssistantKnowledge(
  id: string,
  payload: Partial<AssistantKnowledgePayload>,
): Promise<AssistantKnowledgeDetail> {
  const response = await apiClient.put<{ data: AssistantKnowledgeDetail }>(
    `/assistant/knowledge/${id}`,
    payload,
  );

  return response.data.data;
}

export async function publishAssistantKnowledge(id: string): Promise<AssistantKnowledgeDetail> {
  const response = await apiClient.post<{ data: AssistantKnowledgeDetail }>(
    `/assistant/knowledge/${id}/publish`,
  );

  return response.data.data;
}

export async function archiveAssistantKnowledge(id: string): Promise<AssistantKnowledgeDetail> {
  const response = await apiClient.post<{ data: AssistantKnowledgeDetail }>(
    `/assistant/knowledge/${id}/archive`,
  );

  return response.data.data;
}

export async function reindexAssistantKnowledge(id: string): Promise<{
  ingest: Record<string, unknown>;
  source: AssistantKnowledgeDetail;
}> {
  const response = await apiClient.post<{
    data: { ingest: Record<string, unknown>; source: AssistantKnowledgeDetail };
  }>(`/assistant/knowledge/${id}/reindex`);

  return response.data.data;
}

export async function deleteAssistantKnowledge(id: string): Promise<void> {
  await apiClient.delete(`/assistant/knowledge/${id}`);
}
