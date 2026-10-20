import { describe, expect, it } from "vitest";

import {
  E_APPROVAL_FINANCE_TOUR_ID,
  financeFamilyForStepId,
  financeFamilyFromFormName,
  financeTourChapterStartHref,
  financeTourComposePath,
  financeTourFamilyFromSlug,
  resolveEApprovalFinanceTourSteps,
} from "@/lib/help/e-approval-finance-tour";

const both = { canApprove: true, canCreate: true };
const requestor = { canApprove: false, canCreate: true };
const approver = { canApprove: true, canCreate: false };

describe("finance E-Forms tour", () => {
  it("walks cash advance, liquidation, and reimbursement as their own chapters", () => {
    const steps = resolveEApprovalFinanceTourSteps(both);
    const chapters = new Set(steps.map((step) => step.chapter));

    expect(chapters.has("cash_advance")).toBe(true);
    expect(chapters.has("liquidation")).toBe(true);
    expect(chapters.has("reimbursement")).toBe(true);
    expect(steps.some((step) => step.id === "fin-lq-compose-parent")).toBe(true);
    expect(steps.some((step) => step.id === "fin-lq-detail-settlement")).toBe(true);
    expect(steps.some((step) => step.id === "fin-re-detail-related")).toBe(true);
    expect(steps.some((step) => step.id === "fin-ca-compose-parent")).toBe(false);
    expect(new Set(steps.map((step) => step.id)).size).toBe(steps.length);
    expect(steps.every((step) => step.path.includes("tour-sample"))).toBe(false);
  });

  it("keeps compose on the requestor and Decide on the approver", () => {
    const requestorSteps = resolveEApprovalFinanceTourSteps(requestor);
    const approverSteps = resolveEApprovalFinanceTourSteps(approver);

    expect(requestorSteps.some((step) => step.id === "fin-ca-new")).toBe(true);
    expect(requestorSteps.some((step) => step.id === "decide-actions")).toBe(false);
    expect(approverSteps.some((step) => step.id === "fin-ca-new")).toBe(false);
    expect(approverSteps.some((step) => step.id === "fin-ca-detail-header")).toBe(true);
    expect(approverSteps.some((step) => step.id === "decide-actions")).toBe(true);
  });

  it("starts a liquidation chapter on the finance tour, not Document Approval", () => {
    const href = financeTourChapterStartHref("liquidation", both);

    expect(href).toContain(`tour=${E_APPROVAL_FINANCE_TOUR_ID}`);
    expect(href).toContain("tourChapter=liquidation");
    expect(href.startsWith("/e-approval/submissions")).toBe(true);
    expect(financeTourComposePath("liquidation")).toBe("/e-approval/request/tour-finance/liquidation");
    expect(financeTourFamilyFromSlug("cash-advance")).toBe("cash_advance");
    expect(financeFamilyFromFormName("Cash advance")).toBe("cash_advance");
    expect(financeFamilyFromFormName("Liquidation")).toBe("liquidation");
    expect(financeFamilyFromFormName("Reimbursement")).toBe("reimbursement");
    expect(financeFamilyFromFormName("Document Approval")).toBeNull();
    expect(financeFamilyForStepId("fin-re-compose-lines")).toBe("reimbursement");
    expect(financeFamilyForStepId("follow-up-open-request")).toBe("cash_advance");
  });
});
