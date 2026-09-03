export type TicketingKpi = {
  key: string;
  label: string;
  value: number | string;
  tone?: "neutral" | "success" | "warning" | "danger";
};

export type TicketingUserRef = {
  id: string;
  name: string;
  email?: string;
};

export type TicketingTicketListRow = {
  id: string;
  ticket_number: string;
  title: string;
  status: string;
  priority: string;
  category: string | null;
  source_module: string;
  source_label: string | null;
  requester: TicketingUserRef | null;
  assignee: TicketingUserRef | null;
  created_at: string | null;
  updated_at: string | null;
  sla_due_at?: string | null;
  sla_status?: "on_track" | "at_risk" | "breached" | null;
};

export type TicketingCommentRow = {
  id: string;
  body: string;
  is_internal: boolean;
  author: Pick<TicketingUserRef, "id" | "name"> | null;
  created_at: string | null;
};

export type TicketingAttachmentRow = {
  id: string;
  file_name: string;
  mime_type: string | null;
  size_bytes: number;
  uploaded_by: Pick<TicketingUserRef, "id" | "name"> | null;
  created_at: string | null;
};

export type TicketingLinkRow = {
  id: string;
  link_module: string;
  link_type: string;
  link_id: string;
  link_label: string | null;
};

export type TicketingTicketDetail = TicketingTicketListRow & {
  description: string | null;
  source_reference_type: string | null;
  source_reference_id: string | null;
  resolved_at: string | null;
  closed_at: string | null;
  can_reopen?: boolean;
  comments: TicketingCommentRow[];
  attachments: TicketingAttachmentRow[];
  links: TicketingLinkRow[];
};

export type TicketingCategoryAnalyticsRow = {
  category: string | null;
  label: string;
  open: number;
  in_progress: number;
  resolved_7d: number;
  sla_at_risk: number;
  avg_resolve_hours: number | null;
};

export type TicketingDashboardResponse = {
  kpis: TicketingKpi[];
  recent_tickets: TicketingTicketListRow[];
  by_category?: TicketingCategoryAnalyticsRow[];
  message: string;
};

export type TicketingMetadata = {
  statuses: string[];
  priorities: string[];
  categories: string[];
  category_options?: TicketingCategoryOption[];
  source_modules: Array<{ id: string; label: string }>;
};

export type TicketingCategoryPack = {
  id: string;
  label: string;
  description: string;
  categories: string[];
};

export type TicketingCategoryOption = {
  id: string;
  label: string;
  sla_response_minutes?: number | null;
  sla_escalation_minutes?: number | null;
};

export type TicketingAssignmentRule = {
  category: string;
  assignee_id: string;
  enabled: boolean;
};

export type TicketingSettings = {
  it_support_email: string;
  notify_it_on_create: boolean;
  notify_it_on_reopen: boolean;
  notify_requestor_on_resolve: boolean;
  notify_assignee_on_assign: boolean;
  categories: string[];
  category_options?: TicketingCategoryOption[];
  category_packs?: TicketingCategoryPack[];
  assignment_rules?: TicketingAssignmentRule[];
  sla_enabled: boolean;
  sla_response_minutes: number;
  sla_escalation_minutes: number;
  teams_webhook_url: string;
  notify_teams_on_create: boolean;
  notify_teams_on_sla_reminder: boolean;
  notify_teams_on_sla_escalation: boolean;
  notifications_mailer: string;
  notifications_mailer_ready: boolean;
};

export type TicketingLinkInput = {
  link_module: string;
  link_type: string;
  link_id: string;
  link_label?: string;
};

export type CreateTicketingTicketInput = {
  title: string;
  description?: string;
  category?: string;
  source_module?: string;
  source_reference_type?: string;
  source_reference_id?: string;
  source_label?: string;
  /** Managers only: create as this user (requester / created by). */
  requester_id?: string;
  assignee_id?: string;
  links?: TicketingLinkInput[];
};
