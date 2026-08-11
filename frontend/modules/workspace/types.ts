export type WorkspaceDashboardKpi = {
  key: string;
  label: string;
  value: string;
  change?: string;
  tone?: "neutral" | "success" | "warning" | "danger";
};

export type WorkspaceDashboardAction = {
  id: string;
  label: string;
  count: number;
  href: string;
  priority: "normal" | "high";
};

export type WorkspaceDashboardActivity = {
  id: string;
  module: string;
  label: string;
  detail: string | null;
  href: string | null;
  created_at: string | null;
};

export type WorkspaceDashboardQuickLink = {
  label: string;
  href: string;
};

export type WorkspaceAwaitingMeItem = {
  id: string;
  module: string;
  label: string;
  detail: string | null;
  href: string;
  created_at: string | null;
};

export type WorkspaceAwaitingMe = {
  total: number;
  items: WorkspaceAwaitingMeItem[];
};

export type WorkspaceDashboardResponse = {
  environment: string;
  kpis: WorkspaceDashboardKpi[];
  actions: WorkspaceDashboardAction[];
  awaiting_me?: WorkspaceAwaitingMe;
  recent_activity: WorkspaceDashboardActivity[];
  quick_links: WorkspaceDashboardQuickLink[];
};
