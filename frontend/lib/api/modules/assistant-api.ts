import { apiClient, resolveApiBaseUrl, tenantApiRequestHeaders } from "@/lib/api/client";
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

export type AssistantCostEstimate = {
  currency: string;
  low: number;
  high: number;
  label: string;
  intensity: "low" | "medium" | "high";
  prompt_tokens: number | null;
  completion_tokens: number | null;
};

export type AssistantDailyRateLimit = {
  limit: number;
  remaining: number;
  resets_in_seconds: number;
};

export type AssistantRateLimit = {
  limit: number;
  remaining: number;
  resets_in_seconds: number;
  daily?: AssistantDailyRateLimit | null;
};

export type AssistantActivityStep = {
  id: string;
  kind: string;
  title: string;
  detail?: string | null;
  status: string;
};

export type AssistantStreamDelta = {
  kind: "thinking" | "assistant" | "tool";
  text: string;
  name?: string;
  status?: string;
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
  prompt_tokens?: number | null;
  completion_tokens?: number | null;
  cost_estimate?: AssistantCostEstimate | null;
  rate_limit?: AssistantRateLimit | null;
  activity?: AssistantActivityStep[];
};

export type AssistantMeta = {
  enabled: boolean;
  assistant_mode?: "help" | "admin_builder";
  llm_provider: string;
  model_name: string;
  models: string[];
  model_labels?: Record<string, string>;
  model_estimates?: Record<string, AssistantCostEstimate>;
  supports_model_select: boolean;
  retrieval_enabled?: boolean;
  greeting: string;
  rate_limit?: AssistantRateLimit | null;
};

export type AssistantConversationListRow = {
  id: string;
  title: string | null;
  module_context: string | null;
  page_path: string | null;
  status: string;
  message_count: number;
  last_message_at: string | null;
  created_at: string | null;
  updated_at: string | null;
  user_id?: string;
  user_name?: string | null;
  user_email?: string | null;
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
  activity?: AssistantActivityStep[];
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
  plan_mode?: boolean;
  preferred_model?: string | null;
};

type AssistantSseEvent = {
  event: string;
  data: string;
};

function parseAssistantSseBlock(block: string): AssistantSseEvent | null {
  let event = "message";
  const dataLines: string[] = [];
  for (const rawLine of block.split("\n")) {
    const line = rawLine.replace(/\r$/, "");
    if (line.startsWith("event:")) {
      event = line.slice(6).trim();
    } else if (line.startsWith("data:")) {
      dataLines.push(line.slice(5).trim());
    }
  }
  if (dataLines.length === 0) {
    return null;
  }

  return { event, data: dataLines.join("\n") };
}

function isAssistantStreamAbort(error: unknown): boolean {
  if (!(error instanceof Error)) {
    return false;
  }
  if (error.name === "TimeoutError" || error.name === "AbortError") {
    return true;
  }
  const message = error.message.toLowerCase();

  return message.includes("bodystreambuffer") || message.includes("the operation was aborted");
}

export async function askAssistantLive(
  payload: AskAssistantPayload,
  onStep: (step: AssistantActivityStep) => void,
  onDelta?: (delta: AssistantStreamDelta) => void,
): Promise<AssistantAskResponse> {
  let response: Response;
  try {
    response = await fetch(`${resolveApiBaseUrl()}/assistant/ask`, {
      method: "POST",
      headers: {
        ...tenantApiRequestHeaders(),
        Accept: "text/event-stream",
      },
      signal: AbortSignal.timeout(360_000),
      body: JSON.stringify({
        question: payload.question,
        conversation_id: payload.conversation_id ?? undefined,
        module_context: payload.module_context ?? undefined,
        page_path: payload.page_path ?? undefined,
        plan_mode: payload.plan_mode === true ? true : undefined,
        preferred_model: payload.preferred_model ?? undefined,
      }),
    });
  } catch (error) {
    if (isAssistantStreamAbort(error)) {
      throw new Error("The assistant took too long. Ask again.");
    }
    if (error instanceof TypeError) {
      throw new Error("The assistant connection dropped before the reply finished. Ask again.");
    }
    throw error;
  }

  const contentType = response.headers.get("content-type") ?? "";
  if (!response.ok || !contentType.includes("text/event-stream") || response.body === null) {
    const json = (await response.json().catch(() => null)) as { message?: string } | null;
    throw new Error(json?.message?.trim() || "The assistant could not answer.");
  }

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";
  let result: AssistantAskResponse | null = null;

  while (true) {
    let chunk: ReadableStreamReadResult<Uint8Array>;
    try {
      chunk = await reader.read();
    } catch (error) {
      if (isAssistantStreamAbort(error)) {
        throw new Error("The assistant took too long. Ask again.");
      }
      if (error instanceof TypeError) {
        throw new Error("The assistant connection dropped before the reply finished. Ask again.");
      }
      throw error;
    }
    if (chunk.done) {
      break;
    }
    buffer += decoder.decode(chunk.value, { stream: true }).replace(/\r\n/g, "\n");
    const blocks = buffer.split("\n\n");
    buffer = blocks.pop() ?? "";
    for (const block of blocks) {
      const parsed = parseAssistantSseBlock(block);
      if (parsed === null) {
        continue;
      }
      if (parsed.event === "step") {
        onStep(JSON.parse(parsed.data) as AssistantActivityStep);
        continue;
      }
      if (parsed.event === "delta") {
        onDelta?.(JSON.parse(parsed.data) as AssistantStreamDelta);
        continue;
      }
      if (parsed.event === "result") {
        result = JSON.parse(parsed.data) as AssistantAskResponse;
        continue;
      }
      if (parsed.event === "error") {
        const body = JSON.parse(parsed.data) as { message?: string };
        throw new Error(body.message?.trim() || "The assistant could not finish this question.");
      }
    }
  }

  if (result === null) {
    throw new Error("The assistant stopped before it finished.");
  }

  return result;
}

export async function askAssistant(payload: AskAssistantPayload): Promise<AssistantAskResponse> {
  const response = await apiClient.post<{ data: AssistantAskResponse }>("/assistant/ask", {
    question: payload.question,
    conversation_id: payload.conversation_id ?? undefined,
    module_context: payload.module_context ?? undefined,
    page_path: payload.page_path ?? undefined,
    plan_mode: payload.plan_mode === true ? true : undefined,
    preferred_model: payload.preferred_model ?? undefined,
  }, {
    // Cursor/Gemini + report-builder actions can exceed the default API timeout.
    timeout: 180_000,
  });

  return response.data.data;
}

export async function fetchAssistantMeta(): Promise<AssistantMeta> {
  const response = await apiClient.get<{ data: AssistantMeta }>("/assistant/meta");
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

export async function fetchAssistantConversations(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}): Promise<PaginatedEnvelope<AssistantConversationListRow>> {
  const response = await apiClient.get<{
    data: AssistantConversationListRow[];
    meta: PaginatedMeta;
  }>("/assistant/conversations", { params });

  return { data: response.data.data, meta: response.data.meta };
}

export async function updateAssistantConversation(
  conversationId: string,
  payload: { title: string },
): Promise<AssistantConversationListRow> {
  const response = await apiClient.patch<{ data: AssistantConversationListRow }>(
    `/assistant/conversations/${conversationId}`,
    payload,
  );

  return response.data.data;
}

export async function deleteAssistantConversation(conversationId: string): Promise<void> {
  await apiClient.delete(`/assistant/conversations/${conversationId}`);
}

export async function exportAssistantConversationJson(
  conversationId: string,
): Promise<Record<string, unknown>> {
  const response = await apiClient.get<{ data: Record<string, unknown> }>(
    `/assistant/conversations/${conversationId}/export`,
    { params: { format: "json" } },
  );

  return response.data.data;
}

export async function downloadAssistantConversationCsv(conversationId: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/assistant/conversations/${conversationId}/export`, {
    params: { format: "csv" },
    responseType: "blob",
  });

  return response.data;
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

export type AiPromptModuleListRow = {
  id: string;
  key: string;
  name: string;
  filename: string | null;
  description: string | null;
  kind: string;
  intent_key: string | null;
  sort_order: number;
  is_enabled: boolean;
  is_system: boolean;
  updated_at: string | null;
  body_chars: number;
};

export type AiPromptModuleDetail = AiPromptModuleListRow & {
  body: string;
};

export async function listAiPromptModules(): Promise<AiPromptModuleListRow[]> {
  const response = await apiClient.get<{ data: AiPromptModuleListRow[] }>("/assistant/prompt-modules");
  return response.data.data;
}

export async function getAiPromptModule(id: string): Promise<AiPromptModuleDetail> {
  const response = await apiClient.get<{ data: AiPromptModuleDetail }>(`/assistant/prompt-modules/${id}`);
  return response.data.data;
}

export async function updateAiPromptModule(
  id: string,
  payload: Partial<{
    name: string;
    description: string | null;
    body: string;
    is_enabled: boolean;
    sort_order: number;
  }>,
): Promise<AiPromptModuleDetail> {
  const response = await apiClient.patch<{ data: AiPromptModuleDetail }>(
    `/assistant/prompt-modules/${id}`,
    payload,
  );
  return response.data.data;
}

export type LlmProviderCredential = {
  id: string;
  label: string;
  uses_token: boolean;
  configured: boolean;
  source: "tenant" | "environment" | "missing";
};

export type LlmCredentialSnapshot = {
  provider: string;
  provider_source: "tenant" | "environment";
  providers: LlmProviderCredential[];
};

export async function fetchLlmCredentials(): Promise<LlmCredentialSnapshot> {
  const response = await apiClient.get<{ data: LlmCredentialSnapshot }>("/assistant/settings/llm");
  return response.data.data;
}

export async function saveLlmCredential(payload: {
  provider: string;
  api_key?: string;
  clear?: boolean;
}): Promise<LlmCredentialSnapshot> {
  const response = await apiClient.put<{ data: LlmCredentialSnapshot }>("/assistant/settings/llm", payload);
  return response.data.data;
}

export async function resetAiPromptModule(id: string): Promise<AiPromptModuleDetail> {
  const response = await apiClient.post<{ data: AiPromptModuleDetail }>(
    `/assistant/prompt-modules/${id}/reset`,
  );
  return response.data.data;
}

