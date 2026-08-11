import type { RolloutGateApprovalRequest } from "@/modules/rollout/types";

export type ProjectOneKpi = {
  key: string;
  label: string;
  value: string;
  change?: string;
  tone?: "neutral" | "success" | "warning" | "danger";
};

export type ProjectOneMapSite = {
  id: string;
  name: string;
  lat: number;
  lng: number;
  status: "healthy" | "warning" | "critical";
};

export type ProjectOneMapPin = {
  id: string;
  lat: number;
  lng: number;
  label: string;
  type: "site" | "rollout_site" | "candidate";
  status?: string;
  rollout_id?: string | null;
  rollout_ref?: string | null;
};

export type ProjectOneApproval = {
  id: string;
  type: string;
  title: string;
  requester: string;
  submittedAt: string;
  slaRisk: "low" | "medium" | "high";
};

export type ProjectOneApprovalListRow = ProjectOneApproval & {
  status: string;
  resolvedAt: string | null;
  resolutionNotes: string | null;
  project: { id: string; name: string } | null;
  rollout: { id: string; rollout_ref: string } | null;
  attachment_file_ids?: string[] | null;
  attachments?: Array<{ file_id: string; url: string; label: string; mime_type: string }> | null;
  resolvedBy: { id: string; name: string } | null;
};

export type ProjectOneMilestone = {
  id: string;
  name: string;
  targetDate: string;
  progressPercent: number;
  status: "on_track" | "at_risk" | "blocked";
  workflowStatus: "pending" | "in_progress" | "completed" | "overdue";
};

export type ProjectOneActionWidget = {
  id: string;
  label: string;
  count: number;
  href: string;
  priority: "normal" | "high";
};

export type ProjectOneDashboardResponse = {
  kpis: ProjectOneKpi[];
  sites: ProjectOneMapSite[];
  map_pins?: ProjectOneMapPin[];
  approvals: ProjectOneApproval[];
  milestones: ProjectOneMilestone[];
  actions: ProjectOneActionWidget[];
  rollouts?: {
    active_rollouts: number;
    awaiting_day_one: number;
    sla_at_risk: number;
    pending_gates: number;
    open_saq_programs: number;
    recent_rollouts: Array<{
      id: string;
      rollout_ref: string;
      status: string;
      mno: string;
      target_rfi_working_date: string | null;
    }>;
    active_rollouts_by_project?: Array<{
      project_id: string | null;
      project_name: string;
      active_rollouts: number;
    }>;
    gate_approvals_in_review?: number;
    gate_approvals_awaiting_me?: number;
    gate_approvals_preview?: RolloutGateApprovalRequest[];
  };
};

export type ProjectMilestoneRow = {
  id: string;
  name: string;
  due_date: string | null;
  status: string;
  order_index: number;
};

export type ProjectRolloutSummary = {
  id: string;
  rollout_ref: string;
  status: string;
  mno: string;
  project_type: string;
  search_ring_name: string | null;
  tco_site_id: string | null;
  target_rfi_working_date: string | null;
};

export type ProjectDetail = {
  id: string;
  name: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  site: { id: string; site_code: string; name: string; status?: string } | null;
  project_manager: { id: string; name: string; email: string } | null;
  milestones: ProjectMilestoneRow[];
  approvals: ProjectOneApproval[];
  rollouts: ProjectRolloutSummary[];
  rollout_count: number;
  created_at: string | null;
  updated_at: string | null;
};

export type CreateProjectInput = {
  name: string;
  site_id?: string | null;
  project_manager_id?: string | null;
  status?: "planning" | "active" | "on_hold" | "completed";
  start_date?: string | null;
  end_date?: string | null;
};

export type UpdateProjectInput = Partial<CreateProjectInput>;

export type ProjectListRow = {
  id: string;
  name: string;
  status: string;
  start_date: string | null;
  end_date: string | null;
  site: { id: string; site_code: string; name: string } | null;
  project_manager: { id: string; name: string; email: string } | null;
  created_at: string | null;
  updated_at: string | null;
};
