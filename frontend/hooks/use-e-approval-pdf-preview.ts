"use client";

import { useCallback, useState } from "react";

import { openEApprovalAttachmentPdfPreview } from "@/lib/e-approval/e-approval-attachment-pdf";
import { fetchEApprovalSubmissionPrint } from "@/lib/api/modules/e-approval-api";

export function useEApprovalPdfPreview() {
  const [isGenerating, setIsGenerating] = useState(false);

  const openPdfPreview = useCallback(
    async (submissionId: string, attachmentId?: string): Promise<"opened" | "fallback_print"> => {
    setIsGenerating(true);
    try {
      const payload = await fetchEApprovalSubmissionPrint(submissionId);
      try {
        await openEApprovalAttachmentPdfPreview(payload, attachmentId ? { attachmentId } : undefined);
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
  []);

  return { openPdfPreview, isGenerating };
}
