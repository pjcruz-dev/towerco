import { apiClient } from "@/lib/api/client";

export type SearchIndexSummary = {
  records: number;
  tables: number;
  fields: number;
  reports: number;
  workflows: number;
  apps: number;
  pages: number;
  people: number;
};

export type SearchIndexCoverageRow = {
  id: string;
  slug: string;
  name: string;
  record_count: number;
  indexed_rows: number;
  status: "indexed" | "not_indexed" | "partial" | "empty" | string;
};

export type SearchIndexStatus = {
  total_indexed: number;
  summary: SearchIndexSummary;
  drift: {
    detected: boolean;
    slugs: string[];
    message: string | null;
  };
  last_indexed_at: string | null;
  last_action: string | null;
  coverage: SearchIndexCoverageRow[];
};

export async function fetchSearchIndexStatus(): Promise<SearchIndexStatus> {
  const response = await apiClient.get<{ data: SearchIndexStatus }>(
    "/dynamic-entities/search-index",
  );
  return response.data.data;
}

export async function runSearchIndexAction(payload: {
  action: "repair" | "full_rebuild" | "rebuild_metadata" | "rebuild_entity";
  entity_slug?: string;
}): Promise<{
  action: string;
  records_rebuilt?: number;
  entities_rebuilt?: number;
  entity_slug?: string;
  status: SearchIndexStatus;
}> {
  const response = await apiClient.post<{
    data: {
      action: string;
      records_rebuilt?: number;
      entities_rebuilt?: number;
      entity_slug?: string;
      status: SearchIndexStatus;
    };
  }>("/dynamic-entities/search-index/actions", payload);
  return response.data.data;
}
