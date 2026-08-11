export type TenantNotificationCategory = "action" | "update";

export type TenantNotificationModule = "e_approval" | "project_one" | string;

export type TenantNotificationRow = {
  id: string;
  module: TenantNotificationModule;
  type: string;
  category: TenantNotificationCategory | string;
  subject_type: string | null;
  subject_id: string | null;
  context_primary: string | null;
  context_secondary: string | null;
  actor_user_id: string | null;
  actor_name: string | null;
  message: string;
  body_preview: string | null;
  href: string | null;
  is_read: boolean;
  created_at: string | null;
  /** Present on E-Approval notifications for backward compatibility. */
  submission_id?: string | null;
  document_no?: string | null;
  form_name?: string | null;
};
