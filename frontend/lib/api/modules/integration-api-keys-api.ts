import { apiClient } from "@/lib/api/client";

export type IntegrationApiKeyRow = {
  id: number;
  name: string;
  token_preview: string;
  created_by_id: string;
  created_by_name: string | null;
  created_by_email: string | null;
  created_at: string | null;
  last_used_at: string | null;
  expires_at: string | null;
  status: "operational" | "expired" | "owner_inactive" | string;
};

export type IntegrationApiKeyCreated = {
  id: number;
  name: string;
  plain_text_token: string;
  token_preview: string;
  created_at: string | null;
};

export type IntegrationWorkflowDocCard = {
  entity_slug: string;
  entity_name: string;
  action_id: string;
  label: string;
  from_status: string;
  to_status: string;
  variant: string;
};

export type IntegrationSchemaField = {
  name: string;
  label: string;
  type: string;
  required: boolean;
  filterable: boolean;
};

export type IntegrationSchemaEntity = {
  slug: string;
  name: string;
  module_pack: string;
  field_count: number;
  fields: IntegrationSchemaField[];
};

export type IntegrationApiDocsMeta = {
  integration_prefix: string;
  entity_count: number;
  workflows: IntegrationWorkflowDocCard[];
  schemas: IntegrationSchemaEntity[];
  sample_entity_slug: string;
};

export async function fetchIntegrationApiKeys(): Promise<{
  rows: IntegrationApiKeyRow[];
  total: number;
}> {
  const response = await apiClient.get<{
    data: IntegrationApiKeyRow[];
    meta: { total: number };
  }>("/admin/api-keys");
  return {
    rows: response.data.data ?? [],
    total: response.data.meta?.total ?? 0,
  };
}

export async function createIntegrationApiKey(name: string): Promise<IntegrationApiKeyCreated> {
  const response = await apiClient.post<{ data: IntegrationApiKeyCreated }>("/admin/api-keys", {
    name,
  });
  return response.data.data;
}

export async function revokeIntegrationApiKey(id: number): Promise<void> {
  await apiClient.delete(`/admin/api-keys/${id}`);
}

export async function fetchIntegrationApiDocsMeta(): Promise<IntegrationApiDocsMeta> {
  const response = await apiClient.get<{ data: IntegrationApiDocsMeta }>("/admin/api-keys/docs-meta");
  return response.data.data;
}
