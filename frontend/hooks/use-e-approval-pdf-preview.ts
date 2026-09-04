"use client";

import { useCallback, useState } from "react";

import { openEApprovalAttachmentPdfPreview } from "@/lib/e-approval/e-approval-attachment-pdf";
import { shouldAppendPrintAttachments } from "@/lib/e-approval/e-approval-print-template-render";
import { fetchEApprovalSubmissionPrint } from "@/lib/api/modules/e-approval-api";

/**
 * Detail "Print / PDF" opens the form-style print page.
 * Per-attachment preview still builds the stamped merge PDF (append documents).
 */
export function useEApprovalPdfPreview() {
  const [isGenerating, setIsGenerating] = useState(false);

  const openPdfPreview = useCallback(
    async (submissionId: string, attachmentId?: string): Promise<"opened" | "fallback_print"> => {
      // Full submission Print / PDF → new print page (document design + append controls).
      if (!attachmentId) {
        window.open(`/e-approval/submissions/${submissionId}/print`, "_blank", "noopener,noreferrer");
        return "fallback_print";
      }

      setIsGenerating(true);
      try {
        const payload = await fetchEApprovalSubmissionPrint(submissionId);
        if (!shouldAppendPrintAttachments(payload.template)) {
          window.open(`/e-approval/submissions/${submissionId}/print`, "_blank", "noopener,noreferrer");
          return "fallback_print";
        }
        try {
          await openEApprovalAttachmentPdfPreview(payload, { attachmentId });
          return "opened";
        } catch (e) {
          const code = e instanceof Error ? e.message : "";
          if (code === "NO_PRINTABLE_ATTACHMENTS" || code === "EMPTY_PDF") {
            window.open(`/e-approval/submissions/${submissionId}/print`, "_blank", "noopener,noreferrer");
            return "fallback_print";
          }
          throw e;
        }
      } finally {
        setIsGenerating(false);
      }
    },
    [],
  );

  return { openPdfPreview, isGenerating };
}
