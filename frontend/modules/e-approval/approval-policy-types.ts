export type EApprovalPolicyWorkflowStep = {
  type: string;
  approverId?: string;
  step_order?: number;
  condition?: Record<string, unknown> | null;
};

export type EApprovalPolicyWorkflowProfile = {
  label: string;
  steps: EApprovalPolicyWorkflowStep[];
};

export type EApprovalPolicyRule = {
  priority: number;
  document_family: string;
  amount_field?: string | null;
  amount_min?: number | null;
  amount_max?: number | null;
  department?: string | null;
  category?: string | null;
  urgency?: string | null;
  workflow_profile: string;
};

export type EApprovalApprovalPolicyConfig = {
  currency: string;
  workflow_profiles: Record<string, EApprovalPolicyWorkflowProfile>;
  rules: EApprovalPolicyRule[];
  default_profiles: Record<string, string>;
};

export type EApprovalApprovalPolicyVersion = {
  id: string;
  version_number: number;
  status: string;
  label: string;
  config: EApprovalApprovalPolicyConfig;
  published_at?: string | null;
};

export type EApprovalApprovalPolicySnapshot = {
  policy: {
    id: string;
    key: string;
    name: string;
    description?: string | null;
  };
  published_version: EApprovalApprovalPolicyVersion | null;
  draft_version: EApprovalApprovalPolicyVersion | null;
  defaults: EApprovalApprovalPolicyConfig;
};
