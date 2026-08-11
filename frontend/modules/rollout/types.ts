export type RolloutFileContext = "candidate_photo" | "hunting_log" | "cme_report" | "lease_document" | "approval_attachment";

export type RolloutMediaLink = {
  file_id: string;
  url: string;
  label?: string | null;
  mime_type?: string;
  /** Present when lease document was copied into the site binder. */
  document_id?: string | null;
  document_href?: string | null;
};

export type RolloutLeasePackage = {
  lessor_id_type?: string | null;
  lease_term_months?: number | null;
  notes?: string | null;
  documents?: RolloutMediaLink[];
};

export type RolloutUploadedFile = {
  id: string;
  url: string;
  path: string;
  mime_type: string;
  size_bytes: number;
  original_filename: string;
};

export type RolloutListChildRow = {
  id: string;
  rollout_ref: string;
  parent_rollout_ref: string;
  search_ring_name: string | null;
  status: string;
  mno: string;
  project_type: string;
  region: string | null;
  tco_site_id: string | null;
  endorsement_date: string | null;
  tssr_approved_date: string | null;
  target_rfi_working_date: string | null;
};

export type RolloutListRow = {
  id: string;
  rollout_ref: string;
  tco_site_id: string | null;
  mno: string;
  project_type: string;
  status: string;
  region?: string | null;
  territory?: string | null;
  search_ring_name?: string | null;
  is_batch?: boolean;
  child_count?: number;
  batch_children?: RolloutListChildRow[];
  endorsement_date: string | null;
  tssr_approved_date: string | null;
  sla_working_days: number;
  target_rfi_working_date: string | null;
  actual_rfi_date?: string | null;
  sla_variance_working_days?: number | null;
  candidate_count: number;
  phase_count: number;
  cancelled_at?: string | null;
};

export type RolloutIndexParams = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
  mno?: string;
  project_type?: string;
  region?: string;
  sort?: string;
  sla_at_risk?: boolean;
  /** Default on API is `summary` (counts only). Use `full` for embedded phase/candidate payloads. */
  view?: "summary" | "full";
};

export type UpdateRolloutMetadataInput = {
  search_ring_name?: string | null;
  region?: string | null;
  territory?: string | null;
  area?: string | null;
  alliance_tag?: string | null;
  mno_anchor_site_id?: string | null;
  site_license_remarks?: string | null;
  energization_tempo_date?: string | null;
  rfti_signed_tempo_date?: string | null;
  endorsement_ref?: string | null;
  endorsement_date?: string | null;
  saq_owner_id?: string | null;
  cme_pm_id?: string | null;
  pmo_owner_id?: string | null;
  project_id?: string | null;
};

export type UpdateRolloutSiteProfileInput = {
  full_address?: string | null;
  latitude?: number | null;
  longitude?: number | null;
};

export type ReverseGeocodeResult = {
  formatted_address: string;
  provider: string;
  latitude: number;
  longitude: number;
  components?: Record<string, unknown>;
};

export type RolloutPermitRow = {
  id?: string | null;
  permit_type: string;
  label: string;
  applied_date: string | null;
  secured_date: string | null;
  notes: string | null;
  sort_order: number;
  timeline_phase_key: string;
};

export type RolloutBulkUpdateResult = {
  updated: number;
  failed: number;
  results: Array<{
    id: string;
    status: "updated" | "skipped" | "failed";
    reason?: string;
  }>;
};

export type RolloutBulkPhaseDatesInput = {
  phase_key: string;
  actual_date: string;
};

export type RolloutBulkPhaseDatesResult = {
  updated: number;
  failed: number;
  phases_applied: number;
  results: Array<{
    id: string;
    status: "updated" | "skipped" | "failed";
    phases_updated?: number;
    reason?: string;
  }>;
};

export type DocumentBinderGateSummary = {
  applies: boolean;
  site_linked: boolean;
  complete: boolean;
  site_id: string | null;
  missing_labels: string[];
  checklist_href: string | null;
  summary?: { required?: number; met?: number; complete?: boolean } | null;
  items?: Array<{
    node_key?: string | null;
    label?: string | null;
    met?: boolean;
    required?: boolean;
  }>;
};

export type RolloutGateApprovalRequest = {
  id: string;
  status: "in_review" | "approved" | "rejected" | "cancelled";
  phase_key: string;
  gate_label: string | null;
  current_step: number;
  current_approver_role: string | null;
  approval_chain: string[];
  step_log: Array<Record<string, unknown>>;
  request_notes: string | null;
  rejection_notes: string | null;
  submitted_at: string | null;
  current_step_started_at?: string | null;
  last_escalated_at?: string | null;
  step_waiting_working_days?: number | null;
  escalation_due?: boolean;
  completed_at: string | null;
  can_act: boolean;
  acting_for?: { id: string; name: string } | null;
  /** True when this approve action would complete the chain and pass the gate. */
  is_final_step?: boolean;
  document_binder_gate?: DocumentBinderGateSummary | null;
  rollout: { id: string; rollout_ref: string; search_ring_name: string | null } | null;
  phase: { id: string; label: string } | null;
  requested_by: { id: string; name: string } | null;
};

export type RolloutTimelinePhase = {
  id: string;
  phase_key: string;
  label: string;
  owner_role: string | null;
  anchor: string;
  working_day_start: number;
  working_day_end: number;
  target_start_date: string | null;
  target_end_date: string | null;
  actual_start_date: string | null;
  actual_end_date: string | null;
  gate_status: string | null;
  gate_label: string | null;
  /** Platform catalog phase; set when policy bundle includes a custom phase. */
  is_custom?: boolean;
  /** When false, phase appears on timeline but is excluded from post–Day-1 SLA budget math. */
  counts_toward_sla?: boolean;
  phase_progress: "pending" | "active" | "overdue" | "completed";
  approval_required?: boolean;
  approval_chain?: string[];
  active_gate_approval?: RolloutGateApprovalRequest | null;
  latest_gate_approval?: RolloutGateApprovalRequest | null;
  document_binder_gate?: DocumentBinderGateSummary | null;
};

export type RolloutCandidate = {
  id: string;
  candidate_number: number;
  status: string;
  label: string | null;
  latitude: string | number | null;
  longitude: string | number | null;
  coordinate_capture_method?: string | null;
  coordinate_accuracy_m?: string | number | null;
  coordinates_captured_at?: string | null;
  lessor_name: string | null;
  lessor_contact: string | null;
  proposed_lease_rate_php: string | number | null;
  row_notes: string | null;
  power_notes: string | null;
  hazard_notes: string | null;
  photo_links?: RolloutMediaLink[] | null;
  lease_package?: RolloutLeasePackage | null;
  rejection_reason_code: string | null;
  selected_at: string | null;
};

export type RolloutHuntingLog = {
  id: string;
  log_date: string | null;
  summary: string | null;
  candidates_identified_count: number | null;
  photo_links?: RolloutMediaLink[] | null;
};

export type RolloutCmeReport = {
  id: string;
  timeline_phase_id?: string | null;
  report_date: string | null;
  day_number: number | null;
  physical_progress_pct?: string | number | null;
  physical_progress_plan_pct?: string | number | null;
  workforce_count?: number | null;
  weather_am?: string | null;
  weather_pm?: string | null;
  manhours_today?: number | null;
  manhours_cumulative?: number | null;
  quality_issues?: string | null;
  safety_incidents?: string | null;
  activities_completed?: string | null;
  activities_planned_tomorrow?: string | null;
  toolbox_meeting_held?: boolean | null;
  photo_links?: RolloutMediaLink[] | null;
};

export type RolloutMilestoneCycleStatus = "pending" | "active" | "at_risk" | "overdue" | "completed";

export type RolloutMilestoneCycle = {
  phase_key: string;
  label: string;
  anchor: string;
  target_working_days: number;
  target_date: string | null;
  status: RolloutMilestoneCycleStatus;
  variance_wd: number | null;
  /** Source timeline phase when derived from policy snapshot. */
  timeline_phase_key?: string | null;
  /** Catalog / policy custom phase row. */
  is_custom?: boolean;
};

export type RolloutMilestoneCycleSummary = {
  total: number;
  on_track: number;
  overdue: number;
  at_risk: number;
  progress_pct: number;
};

export type RolloutDetail = {
  id: string;
  rollout_ref: string;
  tco_site_id: string | null;
  playbook_version: string | null;
  mno: string;
  project_type: string;
  status: string;
  cancellation_reason?: string | null;
  cancelled_at?: string | null;
  endorsement_ref: string | null;
  endorsement_date: string | null;
  search_ring_name: string | null;
  region: string | null;
  territory: string | null;
  area?: string | null;
  alliance_tag?: string | null;
  mno_anchor_site_id?: string | null;
  site_license_remarks?: string | null;
  energization_tempo_date?: string | null;
  rfti_signed_tempo_date?: string | null;
  saq_owner_id?: string | null;
  cme_pm_id?: string | null;
  pmo_owner_id?: string | null;
  tssr_approved_date: string | null;
  doa_execution_date: string | null;
  site_license_executed_date: string | null;
  sla_working_days: number;
  target_rfi_working_date: string | null;
  actual_rfi_date: string | null;
  sla_variance_working_days: number | null;
  sla_working_days_remaining: number | null;
  sla_holiday_scope?: string | null;
  site: {
    id: string;
    site_code: string;
    name: string;
    full_address?: string | null;
    latitude?: string | number | null;
    longitude?: string | number | null;
  } | null;
  project?: { id: string; name: string; status: string } | null;
  is_batch?: boolean;
  parent_rollout_id?: string | null;
  batch_children?: Array<{
    id: string;
    rollout_ref: string;
    search_ring_name: string | null;
    status: string;
    tco_site_id: string | null;
  }>;
  colocation_tenants?: Array<{
    id: string;
    rollout_ref: string;
    mno: string;
    tco_site_id: string | null;
    status: string;
    actual_rfi_date: string | null;
    site_license_remarks: string | null;
    site_name: string | null;
  }>;
  permits?: RolloutPermitRow[];
  timeline_phases: RolloutTimelinePhase[];
  candidates: RolloutCandidate[];
  hunting_logs: RolloutHuntingLog[];
  cme_reports: RolloutCmeReport[];
  milestone_cycles?: RolloutMilestoneCycle[];
  milestone_cycles_summary?: RolloutMilestoneCycleSummary;
};

export type RolloutPlaybookPhaseTemplate = {
  phase_key: string;
  label: string;
  owner_role?: string;
  anchor?: string;
  working_day_start: number;
  working_day_end: number;
  gate?: string;
};

export type RolloutPlaybookStatus = {
  assigned_version: string | null;
  latest_platform_version: string | null;
  upgrade_available: boolean;
  sla_working_days_only: boolean;
  day_overrides?: Record<string, Record<string, { working_day_end?: number }>>;
  gate_approval_policies?: Record<string, Record<string, { enabled: boolean; chain: string[] }>>;
  email_notification_policies?: {
    gate_approval: {
      enabled: boolean;
      events: Record<
        string,
        {
          enabled: boolean;
          recipients: string[];
        }
      >;
    };
  };
  gate_approval_escalation_working_days?: number;
  timeline_templates?: Record<string, RolloutPlaybookPhaseTemplate[]>;
  delivery_periods?: Record<string, { working_days: number; day_one_trigger: string }>;
  public_holidays_count?: number;
  national_holidays_count?: number;
  regional_holidays_count?: number;
  sla_holiday_policy?: string;
};

export type TenantPublicHolidayRow = {
  id: string;
  holiday_date: string;
  name: string;
  region: string | null;
  calendar_year: number;
};

export type TenantPublicHolidayList = {
  year: number;
  holidays: TenantPublicHolidayRow[];
};

export type RolloutGeographyKind = "region" | "territory";

export type RolloutGeographyLookupRow = {
  id: string;
  kind: RolloutGeographyKind;
  code: string;
  label: string;
  sort_order: number;
  is_active: boolean;
};

export type RolloutGeographyLookupList = {
  items: RolloutGeographyLookupRow[];
};

export type RolloutProfitability = {
  rollout_program_id: string;
  baseline?: Record<string, number>;
  actual?: Record<string, number>;
  baseline_total?: number;
  actual_total?: number;
  variance_php?: number;
  vo_cost_cumulative?: number;
  ld_accrued_php?: number;
  variance_category?: string | null;
  profitability_status?: string;
  anchor_tenant_lease_fee_php?: number | null;
  access?: "full" | "discipline" | "summary_only";
};

export type CreateRolloutInput = {
  mno: "globe" | "smart" | "dito";
  project_type: "bts" | "rtb" | "colocation" | "colo";
  project_id?: string;
  endorsement_ref?: string;
  endorsement_date?: string;
  search_ring_name?: string;
  region?: string;
  territory?: string;
  rollout_ref?: string;
  full_address?: string | null;
  latitude?: number | null;
  longitude?: number | null;
};

export type CreateRolloutBatchInput = {
  mno: "globe" | "smart" | "dito";
  project_type: "bts" | "rtb" | "colocation" | "colo";
  batch_label?: string;
  endorsement_ref?: string;
  endorsement_date?: string;
  region?: string;
  territory?: string;
  rollout_ref?: string;
  sites: Array<{
    search_ring_name: string;
    region?: string;
    territory?: string;
    rollout_ref?: string;
  }>;
};
