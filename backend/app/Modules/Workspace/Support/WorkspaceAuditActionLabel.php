<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Support;

final class WorkspaceAuditActionLabel
{
    public static function label(string $action): string
    {
        $normalized = trim($action);
        if ($normalized === '') {
            return 'Activity';
        }

        return match ($normalized) {
            'auth.login.success' => 'Signed in',
            'auth.login.failed' => 'Sign-in failed',
            'auth.logout' => 'Signed out',
            'auth.logout_all' => 'Signed out all sessions',
            'auth.session.revoked' => 'Session revoked',
            'auth.admin.sessions_revoked' => 'All sessions revoked by administrator',
            'auth.refresh.success' => 'Session refreshed',
            'auth.sso.azure.success' => 'Signed in with Microsoft',
            'auth.sso.azure.failed' => 'Microsoft sign-in failed',
            'auth.impersonation.started' => 'Impersonation started',
            'auth.impersonation.stopped' => 'Impersonation stopped',
            'auth.mfa.challenge.issued' => 'MFA challenge issued',
            'auth.mfa.challenge.verified' => 'MFA challenge verified',
            'auth.mfa.recovery.verified' => 'MFA recovery used',
            'auth.mfa.enrollment.started' => 'MFA enrollment started',
            'auth.mfa.enrollment.completed' => 'MFA enrollment completed',
            'auth.mfa.recovery_codes.regenerated' => 'MFA recovery codes regenerated',

            'submission_created' => 'Submission created',
            'submission_draft_saved' => 'Draft saved',
            'submission_cancelled' => 'Submission cancelled',
            'submission_manual_follow_up' => 'Manual follow-up sent',
            'public_submission_created' => 'Public submission received',
            'request_approved_step' => 'Approval step approved',
            'request_approved_final' => 'Request approved',
            'request_rejected' => 'Request rejected',
            'form_created' => 'Form created',
            'form_updated' => 'Form updated',
            'form_deleted' => 'Form deleted',
            'form_imported' => 'Form imported',
            'form_logo_updated' => 'Form logo updated',
            'form_revision_restored' => 'Form revision restored',
            'revision_requested' => 'Revision requested',
            'sla_reminder_sent' => 'SLA reminder sent',
            'sla_escalation_sent' => 'SLA escalation sent',
            'document_control_gate_entered' => 'Document control gate entered',

            'controlled_document_registered' => 'Controlled document registered',
            'controlled_document_access_updated' => 'Document access updated',
            'controlled_document.obsolete' => 'Controlled document marked obsolete',
            'controlled_register.access_updated' => 'Register access updated',
            'register_access_updated' => 'Register access updated',

            'ticket.created' => 'Ticket created',
            'ticket.updated' => 'Ticket updated',
            'ticket.resolved' => 'Ticket resolved',
            'ticket.reopened' => 'Ticket reopened',

            'dyn_record.created' => 'Dynamic record created',
            'dyn_record.updated' => 'Dynamic record updated',
            'dyn_record.deleted' => 'Dynamic record deleted',
            'dyn_record.imported' => 'Dynamic records imported',
            'dyn_record.bulk_deleted' => 'Dynamic records bulk deleted',
            'dyn_record.bulk_duplicated' => 'Dynamic records bulk duplicated',
            'dyn_record.bulk_updated' => 'Dynamic records bulk updated',
            'dyn_record.workflow_post_certificate' => 'BIR 2307 posted',
            'dyn_record.workflow_cancel_certificate' => 'BIR 2307 cancelled',
            'dyn_entity.created' => 'Dynamic entity created',
            'dyn_entity.updated' => 'Dynamic entity updated',
            'dyn_field.created' => 'Dynamic field created',
            'dyn_field.updated' => 'Dynamic field updated',
            'dyn_field_group.created' => 'Field group created',
            'dyn_field_group.updated' => 'Field group updated',
            'dyn_field_group.deleted' => 'Field group deleted',

            'rbac.role_created' => 'Role created',
            'rbac.role_permissions_updated' => 'Role permissions updated',
            'rbac.role_deleted' => 'Role deleted',
            'rbac.user_created' => 'User created',
            'rbac.user_updated' => 'User updated',
            'rbac.user_deactivated' => 'User deactivated',
            'rbac.user_reactivated' => 'User reactivated',

            'rollout.created' => 'Rollout program created',
            'rollout.cancelled' => 'Rollout cancelled',
            'rollout.gate_updated' => 'Timeline gate updated',
            'rollout.metadata_updated' => 'Rollout metadata updated',
            'rollout.bulk_metadata_updated' => 'Bulk rollout metadata updated',

            'purchase_requisition.cancelled' => 'Purchase requisition cancelled',
            'purchase_requisition.voided' => 'Purchase requisition voided',
            'purchase_order.cancelled' => 'Purchase order cancelled',
            'purchase_order.voided' => 'Purchase order voided',
            'request_for_quotation.created' => 'RFQ created',
            'request_for_quotation.published' => 'RFQ published',

            default => self::fallback($normalized),
        };
    }

    private static function fallback(string $action): string
    {
        $cleaned = str_replace(['.', '_', '-'], ' ', $action);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        return $cleaned === ''
            ? 'Activity'
            : ucwords($cleaned);
    }
}
