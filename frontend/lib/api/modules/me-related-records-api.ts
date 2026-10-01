import { apiClient } from "@/lib/api/client";
import type { RelatedRecordsResponse } from "@/modules/identity/related-records";

export async function fetchMyRelatedRecords(): Promise<RelatedRecordsResponse> {
  const response = await apiClient.get<{ data: RelatedRecordsResponse }>("/me/related-records");
  return response.data.data;
}
