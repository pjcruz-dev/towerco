"use client";

import { useCallback, useState } from "react";

import {
  openEApprovalAttachmentPdfPreview,
  openEApprovalPdfPreviewTab,
} from "@/lib/e-approval/e-approval-attachment-pdf";
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

      const preview = openEApprovalPdfPreviewTab();
      const printUrl = new URL(
        `/e-approval/submissions/${submissionId}/print`,
        window.location.origin,
      ).href;
      setIsGenerating(true);
      try {
        const payload = await fetchEApprovalSubmissionPrint(submissionId);
        if (!shouldAppendPrintAttachments(payload.template)) {
          if (preview && !preview.closed) {
            preview.location.replace(printUrl);
          } else {
            window.open(printUrl, "_blank", "noopener,noreferrer");
          }
          return "fallback_print";
        }
        try {
          await openEApprovalAttachmentPdfPreview(payload, { attachmentId }, preview);
          return "opened";
        } catch (e) {
          const code = e instanceof Error ? e.message : "";
          if (code === "NO_PRINTABLE_ATTACHMENTS" || code === "EMPTY_PDF") {
            const fallback = preview && !preview.closed ? preview : window.open("", "_blank");
            fallback?.location.replace(printUrl);
            return "fallback_print";
          }
          throw e;
        }
      } catch (error) {
        if (preview && !preview.closed) {
          preview.close();
        }
        throw error;
      } finally {
        setIsGenerating(false);
      }
    },
    [],
  );

  return { openPdfPreview, isGenerating };
}
