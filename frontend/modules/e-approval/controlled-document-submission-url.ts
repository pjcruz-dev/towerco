export type ControlledDocumentSubmissionMode = "new" | "revision";

type SearchParamsLike = { get(name: string): string | null };

export function controlledDocumentSubmissionUrl(options: {
  formId: string;
  mode?: ControlledDocumentSubmissionMode;
  documentCode?: string;
}): string {
  const params = new URLSearchParams();
  params.set("form_id", options.formId);
  if (options.mode) {
    params.set("controlled_mode", options.mode);
  }
  if (options.documentCode?.trim()) {
    params.set("document_code", options.documentCode.trim());
  }

  return `/e-approval/submissions/new?${params.toString()}`;
}

/** When /submissions/new is opened with form_id, jump to the full request compose page. */
export function eApprovalRequestUrlFromNewSubmissionQuery(
  searchParams: SearchParamsLike,
): string | null {
  const formId = searchParams.get("form_id")?.trim();
  if (!formId) {
    return null;
  }

  const params = new URLSearchParams();
  const mode = searchParams.get("controlled_mode")?.trim();
  const documentCode = searchParams.get("document_code")?.trim();

  if (mode === "new" || mode === "revision") {
    params.set("controlled_mode", mode);
  }
  if (documentCode) {
    params.set("document_code", documentCode);
  }

  const query = params.toString();
  return `/e-approval/request/${formId}${query ? `?${query}` : ""}`;
}

/** Minimal focused compose URL — preserves controlled-document deep-link params when present. */
export function eApprovalFocusUrl(formId: string, searchParams?: SearchParamsLike | null): string {
  const params = new URLSearchParams();
  const mode = searchParams?.get("controlled_mode")?.trim();
  const documentCode = searchParams?.get("document_code")?.trim();
  const resubmit = searchParams?.get("resubmit")?.trim();

  if (mode === "new" || mode === "revision") {
    params.set("controlled_mode", mode);
  } else if (!documentCode && !resubmit) {
    params.set("controlled_mode", "new");
  }
  if (documentCode) {
    params.set("document_code", documentCode);
  }
  if (resubmit) {
    params.set("resubmit", resubmit);
  }

  const query = params.toString();
  return `/e-approval/focus/${formId}${query ? `?${query}` : ""}`;
}

/** Standard in-app compose URL with the same deep-link params as focus. */
export function eApprovalRequestUrl(formId: string, searchParams?: SearchParamsLike | null): string {
  const params = new URLSearchParams();
  const mode = searchParams?.get("controlled_mode")?.trim();
  const documentCode = searchParams?.get("document_code")?.trim();
  const resubmit = searchParams?.get("resubmit")?.trim();

  if (mode === "new" || mode === "revision") {
    params.set("controlled_mode", mode);
  }
  if (documentCode) {
    params.set("document_code", documentCode);
  }
  if (resubmit) {
    params.set("resubmit", resubmit);
  }

  const query = params.toString();
  return `/e-approval/request/${formId}${query ? `?${query}` : ""}`;
}

/** Compose URL to edit and resubmit a returned/rejected submission. */
export function eApprovalResubmitUrl(formId: string, submissionId: string): string {
  const params = new URLSearchParams();
  params.set("resubmit", submissionId);
  return `/e-approval/request/${formId}?${params.toString()}`;
}

export function controlledDocumentFocusUrl(options: {
  formId: string;
  mode?: ControlledDocumentSubmissionMode;
  documentCode?: string;
}): string {
  const params = new URLSearchParams();
  if (options.mode) {
    params.set("controlled_mode", options.mode);
  }
  if (options.documentCode?.trim()) {
    params.set("document_code", options.documentCode.trim());
  }
  const query = params.toString();
  return `/e-approval/focus/${options.formId}${query ? `?${query}` : ""}`;
}
