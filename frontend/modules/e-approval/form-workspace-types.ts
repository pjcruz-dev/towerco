/** Per-form workspace config stored in e_approval_forms.metadata_json.workspace */

export type EApprovalFormWorkspaceVisibility = "own" | "approver" | "workspace_all" | "tenant_all";

export type EApprovalFormWorkspaceWidgetType =
  | "kpis"
  | "status_chart"
  | "recent_activity"
  | "audit_log"
  | "submissions_table";

export type EApprovalFormWorkspaceConfig = {
  enabled: boolean;
  slug: string;
  title?: string | null;
  description?: string | null;
  default_list_scope?: "own" | "approver";
  visibility?: EApprovalFormWorkspaceVisibility;
  nav?: {
    show_in_sidebar?: boolean;
    section?: string;
  };
  actions?: {
    new_request_mode?: "focused" | "standard";
    show_export?: boolean;
  };
  acl?: {
    roles?: string[];
    enforce_form_restricted_to?: boolean;
  };
  forms?: {
    mode?: "single" | "multi";
    linked_form_ids?: string[];
  };
  dashboard?: {
    widgets?: Array<{
      id: string;
      type: EApprovalFormWorkspaceWidgetType;
      enabled: boolean;
      order: number;
    }>;
    table_columns?: Array<{
      key: string;
      label: string;
      kind: "system" | "field";
      field_name?: string;
      visible: boolean;
      order: number;
    }>;
    saved_views?: Array<{
      id: string;
      label: string;
      status?: string;
      mine?: boolean;
      period_days?: number;
      order: number;
    }>;
  };
};

export type EApprovalFormWorkspaceSummary = {
  form_id: string;
  form_name: string;
  slug: string;
  title: string;
  description: string | null;
  is_multi_form?: boolean;
};

export type EApprovalFormWorkspaceDashboard = {
  form: {
    id: string;
    name: string;
    description: string | null;
    status: string;
    category: string;
  };
  forms: Array<{ id: string; name: string }>;
  is_multi_form: boolean;
  workspace: EApprovalFormWorkspaceConfig;
  dashboard: NonNullable<EApprovalFormWorkspaceConfig["dashboard"]>;
  available_columns: Array<{
    key: string;
    label: string;
    kind: "system" | "field";
    field_name?: string;
  }>;
  kpis: Array<{
    key: string;
    label: string;
    value: string;
    change?: string | null;
    tone?: "default" | "success" | "warning" | "danger";
  }>;
  status_breakdown: Array<{
    status: string;
    label: string;
    count: number;
  }>;
  recent_activity: Array<{
    id: string;
    document_no: string;
    status: string;
    form_name?: string;
    requestor_name: string;
    created_at: string | null;
  }>;
  recent_audit: Array<{
    id: string;
    action: string;
    target_id: string;
    remarks: string | null;
    created_at: string | null;
    user_name: string;
  }>;
  viewer: {
    can_submit: boolean;
    can_export: boolean;
    can_manage_form: boolean;
    list_scope: "own" | "all";
    new_request_href: string;
  };
};

export const ISO_FORM_WORKSPACE_SLUG = "iso-approval";
export const ISO_FORM_FAMILY = "iso_document_control";
