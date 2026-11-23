import type { EApprovalSubmissionListRow } from "@/modules/e-approval/types";

/** Liquidation of a released or fully approved cash advance. Hidden once the trip is cancelled or the cash is back. */
export function cashAdvanceLiquidationHref(
  submission: Pick<EApprovalSubmissionListRow, "id" | "form_family" | "status" | "finance_stage">,
  liquidationFormId: string | null | undefined,
): string | null {
  if (!liquidationFormId) {
    return null;
  }
  if (submission.form_family !== "cash_advance" || submission.status !== "approved") {
    return null;
  }
  if (submission.finance_stage === "funds_returned" || submission.finance_stage === "for_return") {
    return null;
  }

  return `/e-approval/request/${liquidationFormId}?parent=${submission.id}`;
}
