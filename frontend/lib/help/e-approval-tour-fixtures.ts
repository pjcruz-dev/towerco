import { LIVE_TOUR_QUERY } from "@/lib/help/e-approval-live-tour";

/** Client-only sample routes used by the live tour (never persisted). */
export const E_APPROVAL_TOUR_SAMPLE_FORM_ID = "tour-sample";
export const E_APPROVAL_TOUR_SAMPLE_SUBMISSION_ID = "tour-sample";

export const E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH = `/e-approval/request/${E_APPROVAL_TOUR_SAMPLE_FORM_ID}`;
export const E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH = `/e-approval/submissions/${E_APPROVAL_TOUR_SAMPLE_SUBMISSION_ID}`;
export const E_APPROVAL_TOUR_SAMPLE_PRINT_PATH = `${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}/print`;

export function isEApprovalTourActive(searchParams: { get: (key: string) => string | null }): boolean {
  return searchParams.get(LIVE_TOUR_QUERY) === "e-approval";
}

/** Match live Document Approval / Document Control forms for tour targeting. */
export function isEApprovalDocumentApprovalFormName(name: string | null | undefined): boolean {
  const normalized = (name ?? "").trim().toLowerCase();
  if (!normalized) {
    return false;
  }
  return (
    normalized.includes("document approval") ||
    normalized.includes("document control") ||
    normalized.includes("iso document")
  );
}

export function isEApprovalTourSamplePath(pathname: string): boolean {
  return (
    pathname === E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH ||
    pathname === E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH ||
    pathname === E_APPROVAL_TOUR_SAMPLE_PRINT_PATH
  );
}

/** Where to land after ending the tour from a sample screen. */
export function eApprovalTourExitPath(pathname: string): string {
  if (pathname === E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH) {
    return "/e-approval/submissions/new";
  }
  if (pathname === E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH || pathname === E_APPROVAL_TOUR_SAMPLE_PRINT_PATH) {
    return "/e-approval/submissions";
  }
  return pathname;
}
