import { EApprovalApprovalsPageClient } from "@/app/(platform)/e-approval/approvals/e-approval-approvals-page-client";
import { ISO_FORM_FAMILY } from "@/modules/e-approval/form-workspace-types";

export default function ControlledDocumentApprovalsPage() {
  return <EApprovalApprovalsPageClient formFamily={ISO_FORM_FAMILY} />;
}
