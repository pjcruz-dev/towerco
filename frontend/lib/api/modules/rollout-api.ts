import { apiClient } from "@/lib/api/client";
import type { PaginatedEnvelope, PaginatedMeta } from "@/lib/api/paginated";
import type {
  CreateRolloutBatchInput,
  CreateRolloutInput,
  RolloutBulkPhaseDatesInput,
  RolloutBulkPhaseDatesResult,
  RolloutBulkUpdateResult,
  RolloutDetail,
  RolloutFileContext,
  RolloutGateApprovalRequest,
  RolloutIndexParams,
  RolloutListRow,
  RolloutPlaybookStatus,
  RolloutProfitability,
  RolloutUploadedFile,
  TenantPublicHolidayList,
  TenantPublicHolidayRow,
  RolloutGeographyKind,
  RolloutGeographyLookupList,
  RolloutGeographyLookupRow,
  UpdateRolloutMetadataInput,
  UpdateRolloutSiteProfileInput,
  ReverseGeocodeResult,
  RolloutPermitRow,
} from "@/modules/rollout/types";

export type RolloutAssignableUser = {
  id: string;
  name: string;
  email: string;
  roles: string[];
};

export type MediaLinkInput = {
  file_id: string;
  label?: string;
};

export type LeasePackageInput = {
  lessor_id_type?: string;
  lease_term_months?: number;
  notes?: string;
  documents?: MediaLinkInput[];
};

export type CreateRolloutCandidateInput = {
  client_draft_id?: string;
  label?: string;
  latitude?: number;
  longitude?: number;
  lessor_name?: string;
  lessor_contact?: string;
  proposed_lease_rate_php?: number;
  row_notes?: string;
  power_notes?: string;
  hazard_notes?: string;
  photo_links?: MediaLinkInput[];
  lease_package?: LeasePackageInput;
};

export type CreateHuntingLogInput = {
  client_draft_id?: string;
  log_date?: string;
  summary?: string;
  candidates_identified_count?: number;
  candidate_ids?: string[];
  photo_links?: MediaLinkInput[];
};

export type CreateCmeReportInput = {
  timeline_phase_id?: string;
  client_draft_id?: string;
  report_date?: string;
  day_number?: number;
  construction_working_days_total?: number;
  weather_am?: string;
  weather_pm?: string;
  workforce_count?: number;
  manhours_today?: number;
  manhours_cumulative?: number;
  physical_progress_pct?: number;
  physical_progress_plan_pct?: number;
  activities_completed?: string;
  activities_planned_tomorrow?: string;
  quality_issues?: string;
  safety_incidents?: string;
  toolbox_meeting_held?: boolean;
  lessor_neighbor_issues?: string;
  risks_flagged?: string;
  photo_links?: MediaLinkInput[];
};

export type UpdateRolloutProfitabilityInput = {
  baseline?: Record<string, number>;
  actual?: Record<string, number>;
  vo_cost_cumulative?: number;
  ld_accrued_php?: number;
  variance_category?: string;
  profitability_status?: "on_track" | "watch" | "underperforming" | "at_loss";
  anchor_tenant_lease_fee_php?: number;
};

export type DeliveryPeriodStartInput =
  | { tssr_approved_date: string }
  | { doa_execution_date: string }
  | { site_license_executed_date: string };

export type CreateRolloutResponse = {
  id: string;
  rollout_ref: string;
  sla_working_days: number;
  status: string;
};

export type CreateRolloutBatchResponse = {
  parent: {
    id: string;
    rollout_ref: string;
    status: string;
    batch_label?: string | null;
  };
  children: Array<{
    id: string;
    rollout_ref: string;
    search_ring_name: string | null;
    status: string;
  }>;
};

export type PublicHolidayPayload = {
  holiday_date: string;
  name: string;
  region?: string | null;
};

export type SeedPhilippinesHolidaysResponse = {
  year: number;
  seeded_count: number;
  holidays: TenantPublicHolidayRow[];
};

export async function uploadRolloutFile(
  rolloutId: string,
  context: RolloutFileContext,
  file: File,
): Promise<RolloutUploadedFile> {
  const form = new FormData();
  form.append("file", file);
  form.append("context", context);
  form.append("rollout_id", rolloutId);

  const response = await apiClient.post<{ data: RolloutUploadedFile }>("/project-one/files", form);
  return response.data.data;
}

export async function fetchRolloutFileBlob(fileId: string): Promise<Blob> {
  const response = await apiClient.get<Blob>(`/project-one/files/${fileId}`, {
    responseType: "blob",
  });
  return response.data;
}

export async function bulkUpdateRollouts(body: {
  rollout_ids: string[];
  updates: UpdateRolloutMetadataInput;
}): Promise<RolloutBulkUpdateResult> {
  const response = await apiClient.post<{ data: RolloutBulkUpdateResult }>(
    "/project-one/rollouts/bulk-update",
    body,
  );
  return response.data.data;
}

export async function bulkBackfillRolloutPhaseDates(body: {
  rollout_ids: string[];
  phases: RolloutBulkPhaseDatesInput[];
  mark_gate_passed?: boolean;
  backfill: true;
}): Promise<RolloutBulkPhaseDatesResult> {
  const response = await apiClient.post<{ data: RolloutBulkPhaseDatesResult }>(
    "/project-one/rollouts/bulk-phase-dates",
    body,
  );
  return response.data.data;
}

export async function bulkBackfillRolloutPhaseDatesGrid(body: {
  rows: Array<{ rollout_id: string; phases: RolloutBulkPhaseDatesInput[] }>;
  mark_gate_passed?: boolean;
  backfill: true;
}): Promise<RolloutBulkPhaseDatesResult> {
  const response = await apiClient.post<{ data: RolloutBulkPhaseDatesResult }>(
    "/project-one/rollouts/bulk-phase-dates-grid",
    body,
  );
  return response.data.data;
}

export async function patchRolloutMetadata(
  id: string,
  body: UpdateRolloutMetadataInput,
): Promise<RolloutDetail> {
  const response = await apiClient.patch<{ data: RolloutDetail }>(`/project-one/rollouts/${id}`, body);
  return response.data.data;
}

export async function patchRolloutSiteProfile(
  id: string,
  body: UpdateRolloutSiteProfileInput,
): Promise<RolloutDetail> {
  const response = await apiClient.patch<{ data: RolloutDetail }>(
    `/project-one/rollouts/${id}/site-profile`,
    body,
  );
  return response.data.data;
}

export async function fetchRolloutPermits(rolloutId: string): Promise<RolloutPermitRow[]> {
  const response = await apiClient.get<{ data: RolloutPermitRow[] }>(
    `/project-one/rollouts/${rolloutId}/permits`,
  );
  return response.data.data;
}

export async function syncRolloutPermits(
  rolloutId: string,
  permits: Array<{
    permit_type: string;
    applied_date?: string | null;
    secured_date?: string | null;
    notes?: string | null;
  }>,
): Promise<RolloutPermitRow[]> {
  const response = await apiClient.put<{ data: RolloutPermitRow[] }>(
    `/project-one/rollouts/${rolloutId}/permits`,
    { permits },
  );
  return response.data.data;
}

export async function fetchRolloutAssignableUsers(): Promise<RolloutAssignableUser[]> {
  const response = await apiClient.get<{ data: RolloutAssignableUser[] }>("/project-one/assignable-users");
  return response.data.data;
}

export async function fetchRolloutsIndex(params: RolloutIndexParams): Promise<PaginatedEnvelope<RolloutListRow>> {
  const response = await apiClient.get<{ data: RolloutListRow[]; meta: PaginatedMeta }>(
    "/project-one/rollouts",
    { params },
  );

  return { data: response.data.data, meta: response.data.meta };
}

export async function fetchRolloutDetail(id: string): Promise<RolloutDetail> {
  const response = await apiClient.get<{ data: RolloutDetail }>(`/project-one/rollouts/${id}`);
  return response.data.data;
}

export type RolloutActivityEntry = {
  id: number;
  event: string | null;
  description: string;
  properties: Record<string, unknown>;
  created_at: string | null;
  causer: { id: string; name: string } | null;
};

export async function fetchRolloutActivity(rolloutId: string, limit = 40): Promise<RolloutActivityEntry[]> {
  const response = await apiClient.get<{ data: RolloutActivityEntry[] }>(
    `/project-one/rollouts/${rolloutId}/activity`,
    { params: { limit } },
  );
  return response.data.data;
}

export async function createRollout(body: CreateRolloutInput): Promise<CreateRolloutResponse> {
  const response = await apiClient.post<{ data: CreateRolloutResponse }>("/project-one/rollouts", body);
  return response.data.data;
}

export async function createRolloutBatch(body: CreateRolloutBatchInput): Promise<CreateRolloutBatchResponse> {
  const response = await apiClient.post<{ data: CreateRolloutBatchResponse }>(
    "/project-one/rollout-batches",
    body,
  );
  return response.data.data;
}

export async function exportRolloutsCsv(params: RolloutIndexParams): Promise<Blob> {
  const response = await apiClient.get<Blob>("/project-one/rollouts/export", {
    params,
    responseType: "blob",
  });
  return response.data;
}

export async function cancelRollout(id: string, reason: string): Promise<RolloutDetail> {
  const response = await apiClient.post<{ data: RolloutDetail }>(`/project-one/rollouts/${id}/cancel`, {
    cancellation_reason: reason,
  });
  return response.data.data;
}

export async function setRolloutDeliveryPeriodStart(
  id: string,
  body: DeliveryPeriodStartInput,
): Promise<RolloutDetail> {
  const response = await apiClient.post<{ data: RolloutDetail }>(
    `/project-one/rollouts/${id}/delivery-period-start`,
    body,
  );
  return response.data.data;
}

export async function recordRolloutRfi(id: string, actualRfiDate: string): Promise<RolloutDetail> {
  const response = await apiClient.post<{ data: RolloutDetail }>(`/project-one/rollouts/${id}/rfi-recorded`, {
    actual_rfi_date: actualRfiDate,
  });
  return response.data.data;
}

export async function recordRolloutSiteLicense(
  id: string,
  siteLicenseExecutedDate: string,
  siteLicenseRemarks?: string | null,
): Promise<RolloutDetail> {
  const response = await apiClient.post<{ data: RolloutDetail }>(
    `/project-one/rollouts/${id}/site-license-recorded`,
    {
      site_license_executed_date: siteLicenseExecutedDate,
      ...(siteLicenseRemarks !== undefined ? { site_license_remarks: siteLicenseRemarks } : {}),
    },
  );
  return response.data.data;
}

export async function updateRolloutPhaseGate(phaseId: string, gateStatus: string): Promise<RolloutDetail> {
  const response = await apiClient.patch<{ data: RolloutDetail }>(
    `/project-one/rollout-phases/${phaseId}/gate`,
    { gate_status: gateStatus },
  );
  return response.data.data;
}

export async function fetchRolloutPlaybookStatus(): Promise<RolloutPlaybookStatus> {
  const response = await apiClient.get<{ data: RolloutPlaybookStatus }>("/project-one/rollout-playbook");
  return response.data.data;
}

export async function patchRolloutPlaybookDayOverrides(
  dayOverrides: Record<string, Record<string, { working_day_end?: number }>>,
): Promise<RolloutPlaybookStatus> {
  const response = await apiClient.patch<{ data: RolloutPlaybookStatus }>("/project-one/rollout-playbook", {
    day_overrides: dayOverrides,
  });
  return response.data.data;
}

export async function patchRolloutPlaybookConfig(body: {
  day_overrides?: Record<string, Record<string, { working_day_end?: number }>>;
  gate_approval_policies?: Record<string, Record<string, { enabled: boolean; chain: string[] }>>;
  email_notification_policies?: RolloutPlaybookStatus["email_notification_policies"];
  gate_approval_escalation_working_days?: number;
}): Promise<RolloutPlaybookStatus> {
  const response = await apiClient.patch<{ data: RolloutPlaybookStatus }>("/project-one/rollout-playbook", body);
  return response.data.data;
}

/** React Query key for the gate-approval inbox count (invalidates with gate-approvals). */
export const GATE_APPROVALS_AWAITING_ME_COUNT_QUERY_KEY = [
  "project-one",
  "gate-approvals",
  "awaiting-me-count",
] as const;

export async function fetchGateApprovalsAwaitingMeCount(): Promise<number> {
  const result = await fetchRolloutGateApprovals({
    status: "in_review",
    awaiting_me: true,
    page: 1,
    per_page: 1,
  });

  return result.meta.total;
}

export async function fetchRolloutGateApprovals(params?: {
  status?: string;
  mine?: boolean;
  awaiting_me?: boolean;
  page?: number;
  per_page?: number;
  sort?: string;
}): Promise<{
  data: RolloutGateApprovalRequest[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
}> {
  const query: Record<string, string | number> = {};
  if (params?.status) {
    query.status = params.status;
  }
  if (params?.mine) {
    query.mine = 1;
  }
  if (params?.awaiting_me) {
    query.awaiting_me = 1;
  }
  if (params?.page) {
    query.page = params.page;
  }
  if (params?.per_page) {
    query.per_page = params.per_page;
  }
  if (params?.sort) {
    query.sort = params.sort;
  }

  const response = await apiClient.get<{
    data: {
      data: RolloutGateApprovalRequest[];
      meta: { current_page: number; last_page: number; per_page: number; total: number };
    };
  }>("/project-one/gate-approvals", { params: query });
  return response.data.data;
}

export async function submitRolloutGateApproval(
  phaseId: string,
  body?: { request_notes?: string },
): Promise<{ approval: RolloutGateApprovalRequest; rollout: RolloutDetail | null }> {
  const response = await apiClient.post<{
    data: { approval: RolloutGateApprovalRequest; rollout: RolloutDetail | null };
  }>(`/project-one/rollout-phases/${phaseId}/gate-approvals`, body ?? {});
  return response.data.data;
}

export async function decideRolloutGateApproval(
  approvalId: string,
  body: { decision: "approve" | "reject"; notes?: string },
): Promise<{ approval: RolloutGateApprovalRequest; rollout: RolloutDetail | null }> {
  const response = await apiClient.post<{
    data: { approval: RolloutGateApprovalRequest; rollout: RolloutDetail | null };
  }>(`/project-one/gate-approvals/${approvalId}/decide`, body);
  return response.data.data;
}

export type GateApprovalDelegation = {
  id: string;
  role_key: string | null;
  valid_from: string;
  valid_until: string | null;
  notes: string | null;
  is_active: boolean;
  delegator: { id: string; name: string } | null;
  delegate: { id: string; name: string } | null;
};

export async function fetchGateApprovalDelegations(): Promise<GateApprovalDelegation[]> {
  const response = await apiClient.get<{ data: { delegations: GateApprovalDelegation[] } }>(
    "/project-one/gate-approval-delegations",
  );
  return response.data.data.delegations;
}

export async function createGateApprovalDelegation(body: {
  delegate_id: string;
  role_key?: string;
  valid_from?: string;
  valid_until?: string;
  notes?: string;
}): Promise<GateApprovalDelegation> {
  const response = await apiClient.post<{ data: GateApprovalDelegation }>(
    "/project-one/gate-approval-delegations",
    body,
  );
  return response.data.data;
}

export async function revokeGateApprovalDelegation(id: string): Promise<GateApprovalDelegation> {
  const response = await apiClient.delete<{ data: GateApprovalDelegation }>(
    `/project-one/gate-approval-delegations/${id}`,
  );
  return response.data.data;
}

export async function exportGateApprovalsCsv(status?: string): Promise<Blob> {
  const response = await apiClient.get<Blob>("/project-one/gate-approvals/export", {
    params: status ? { status } : undefined,
    responseType: "blob",
  });
  return response.data;
}

export async function fetchTenantPublicHolidays(year: number): Promise<TenantPublicHolidayList> {
  const response = await apiClient.get<{ data: TenantPublicHolidayList }>("/project-one/public-holidays", {
    params: { year },
  });
  return response.data.data;
}

export async function createTenantPublicHoliday(body: PublicHolidayPayload): Promise<TenantPublicHolidayRow> {
  const response = await apiClient.post<{ data: TenantPublicHolidayRow }>("/project-one/public-holidays", body);
  return response.data.data;
}

export async function updateTenantPublicHoliday(
  id: string,
  body: PublicHolidayPayload,
): Promise<TenantPublicHolidayRow> {
  const response = await apiClient.patch<{ data: TenantPublicHolidayRow }>(
    `/project-one/public-holidays/${id}`,
    body,
  );
  return response.data.data;
}

export async function deleteTenantPublicHoliday(id: string): Promise<void> {
  await apiClient.delete(`/project-one/public-holidays/${id}`);
}

export async function seedPhilippinesPublicHolidays(
  year: number,
  region?: string | null,
): Promise<SeedPhilippinesHolidaysResponse> {
  const response = await apiClient.post<{ data: SeedPhilippinesHolidaysResponse }>(
    "/project-one/public-holidays/seed-philippines",
    { year, region: region ?? undefined },
  );
  return response.data.data;
}

export type GeographyLookupPayload = {
  kind?: RolloutGeographyKind;
  code?: string;
  label?: string;
  sort_order?: number | null;
  is_active?: boolean;
};

export async function fetchRolloutGeographyLookups(params?: {
  kind?: RolloutGeographyKind;
  activeOnly?: boolean;
}): Promise<RolloutGeographyLookupList> {
  const response = await apiClient.get<{ data: RolloutGeographyLookupList }>("/project-one/geography", {
    params: {
      kind: params?.kind,
      active_only: params?.activeOnly ? 1 : undefined,
    },
  });
  return response.data.data;
}

export async function createRolloutGeographyLookup(
  body: Required<Pick<GeographyLookupPayload, "kind" | "code" | "label">> &
    Omit<GeographyLookupPayload, "kind" | "code" | "label">,
): Promise<RolloutGeographyLookupRow> {
  const response = await apiClient.post<{ data: RolloutGeographyLookupRow }>("/project-one/geography", body);
  return response.data.data;
}

export async function updateRolloutGeographyLookup(
  id: string,
  body: GeographyLookupPayload,
): Promise<RolloutGeographyLookupRow> {
  const response = await apiClient.patch<{ data: RolloutGeographyLookupRow }>(
    `/project-one/geography/${id}`,
    body,
  );
  return response.data.data;
}

export async function deleteRolloutGeographyLookup(id: string): Promise<void> {
  await apiClient.delete(`/project-one/geography/${id}`);
}

export async function seedRolloutGeographyDefaults(): Promise<{
  created: number;
  total: number;
  items: RolloutGeographyLookupRow[];
}> {
  const response = await apiClient.post<{
    data: { created: number; total: number; items: RolloutGeographyLookupRow[] };
  }>("/project-one/geography/seed-defaults");
  return response.data.data;
}

export async function reverseGeocodeCoordinates(body: {
  latitude: number;
  longitude: number;
}): Promise<ReverseGeocodeResult> {
  const response = await apiClient.post<{ data: ReverseGeocodeResult }>(
    "/project-one/geocode/reverse",
    body,
  );
  return response.data.data;
}

export async function forwardGeocodeAddress(body: {
  query: string;
}): Promise<ReverseGeocodeResult> {
  const response = await apiClient.post<{ data: ReverseGeocodeResult }>(
    "/project-one/geocode/forward",
    body,
  );
  return response.data.data;
}

export async function createRolloutCandidate(
  rolloutId: string,
  body: CreateRolloutCandidateInput,
): Promise<{ id: string; candidate_number: number; status: string }> {
  const response = await apiClient.post<{ data: { id: string; candidate_number: number; status: string } }>(
    `/project-one/rollouts/${rolloutId}/candidates`,
    body,
  );
  return response.data.data;
}

export async function updateRolloutCandidate(
  candidateId: string,
  body: CreateRolloutCandidateInput,
): Promise<{ id: string; status: string }> {
  const response = await apiClient.patch<{ data: { id: string; status: string } }>(
    `/project-one/candidates/${candidateId}`,
    body,
  );
  return response.data.data;
}

export async function rejectRolloutCandidate(
  candidateId: string,
  body: { rejection_reason_code: string; rejection_notes?: string },
): Promise<{ id: string; status: string }> {
  const response = await apiClient.post<{ data: { id: string; status: string } }>(
    `/project-one/candidates/${candidateId}/reject`,
    body,
  );
  return response.data.data;
}

export async function selectRolloutCandidate(candidateId: string): Promise<RolloutDetail> {
  const response = await apiClient.post<{ data: RolloutDetail }>(`/project-one/candidates/${candidateId}/select`);
  return response.data.data;
}

export async function createRolloutHuntingLog(
  rolloutId: string,
  body: CreateHuntingLogInput,
): Promise<{ id: string; log_date: string | null }> {
  const response = await apiClient.post<{ data: { id: string; log_date: string | null } }>(
    `/project-one/rollouts/${rolloutId}/hunting-logs`,
    body,
  );
  return response.data.data;
}

export async function createRolloutCmeReport(
  rolloutId: string,
  body: CreateCmeReportInput,
): Promise<{ id: string; report_date: string | null; day_number: number | null }> {
  const response = await apiClient.post<{
    data: { id: string; report_date: string | null; day_number: number | null };
  }>(`/project-one/rollouts/${rolloutId}/cme-reports`, body);
  return response.data.data;
}

export async function fetchRolloutProfitability(rolloutId: string): Promise<RolloutProfitability> {
  const response = await apiClient.get<{ data: RolloutProfitability }>(
    `/project-one/rollouts/${rolloutId}/profitability`,
  );
  return response.data.data;
}

export async function updateRolloutProfitability(
  rolloutId: string,
  body: UpdateRolloutProfitabilityInput,
): Promise<RolloutProfitability> {
  const response = await apiClient.patch<{ data: RolloutProfitability }>(
    `/project-one/rollouts/${rolloutId}/profitability`,
    body,
  );
  return response.data.data;
}
