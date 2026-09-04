"use client";

import { useQuery } from "@tanstack/react-query";
import { useCallback, useState } from "react";
import Link from "next/link";

import { EApprovalGenericFormPrintView } from "@/components/e-approval/e-approval-generic-form-print-view";
import { Button } from "@/components/ui/button";
import { openEApprovalAttachmentPdfPreview } from "@/lib/e-approval/e-approval-attachment-pdf";
import {
  hasCustomPrintDocumentDesign,
  shouldAppendPrintAttachments,
} from "@/lib/e-approval/e-approval-print-template-render";
import { fetchEApprovalSubmissionPrint } from "@/lib/api/modules/e-approval-api";
import { resolvePrintTemplateEntry } from "@/modules/e-approval/print-template-registry";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";

type Props = { submissionId: string };

function hasPrintableAttachment(payload: EApprovalPrintPayload): boolean {
  return (payload.attachments ?? []).some((a) => /\.(pdf|png|jpe?g)$/i.test(a.file_name));
}

function hasAnyAttachment(payload: EApprovalPrintPayload): boolean {
  return (payload.attachments ?? []).length > 0;
}

export function EApprovalSubmissionPrintPageClient({ submissionId }: Props) {
  const [pdfError, setPdfError] = useState<string | null>(null);
  const [isGeneratingPdf, setIsGeneratingPdf] = useState(false);

  const { data, isError, isLoading, refetch, isFetching } = useQuery({
    queryKey: ["e-approval", "submission", submissionId, "print"],
    queryFn: () => fetchEApprovalSubmissionPrint(submissionId),
    retry: 1,
  });

  // Append documents: available on demand; not embedded on the print page body.
  const appendEnabled = shouldAppendPrintAttachments(data?.template);

  const openMergedPdf = useCallback(async (payload: EApprovalPrintPayload) => {
    setIsGeneratingPdf(true);
    setPdfError(null);
    try {
      await openEApprovalAttachmentPdfPreview(payload);
    } catch (e) {
      const code = e instanceof Error ? e.message : "";
      if (code === "NO_PRINTABLE_ATTACHMENTS" || code === "EMPTY_PDF") {
        setPdfError(
          "No PDF or image attachments to merge. Office files (Word/Excel) must be downloaded from the submission.",
        );
      } else {
        setPdfError("Could not generate PDF with approval history.");
      }
    } finally {
      setIsGeneratingPdf(false);
    }
  }, []);

  const handlePrint = useCallback(() => {
    if (typeof window !== "undefined") {
      window.print();
    }
  }, []);

  const structuredEntry = data ? resolvePrintTemplateEntry(data) : null;
  const useCustomDocumentDesign = Boolean(data && hasCustomPrintDocumentDesign(data.template));
  const StructuredPrintView =
    useCustomDocumentDesign || !structuredEntry
      ? EApprovalGenericFormPrintView
      : structuredEntry.PrintView;
  const canMergePdf = Boolean(data && appendEnabled && hasPrintableAttachment(data));

  return (
    <>
      <div className="eapproval-print-toolbar sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2 print:hidden">
        <Link href={`/e-approval/submissions/${submissionId}`} className="text-sm text-primary hover:underline">
          ← Back to submission
        </Link>
        <div className="flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => refetch()} disabled={isFetching}>
            Refresh
          </Button>
          <Button type="button" size="sm" variant="outline" onClick={handlePrint} disabled={!data}>
            Print / Save as PDF
          </Button>
          {data && canMergePdf ? (
            <Button type="button" size="sm" onClick={() => void openMergedPdf(data)} disabled={isGeneratingPdf}>
              {isGeneratingPdf ? "Generating…" : "Open appended PDF"}
            </Button>
          ) : null}
        </div>
      </div>

      {isLoading ? (
        <p className="flex min-h-[40vh] items-center justify-center text-sm text-slate-500">Loading…</p>
      ) : null}
      {isError ? (
        <p className="flex min-h-[40vh] items-center justify-center text-sm text-destructive">
          Could not load print data.
        </p>
      ) : null}
      {pdfError ? (
        <p className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-900 print:hidden">
          {pdfError}
        </p>
      ) : null}
      {data && appendEnabled && !canMergePdf && hasAnyAttachment(data) ? (
        <p className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-900 print:hidden">
          This submission has Office attachments (e.g. Word). They cannot open in the browser PDF viewer — download
          them from the submission.
        </p>
      ) : null}
      {data && !appendEnabled && hasAnyAttachment(data) ? (
        <p className="border-b border-slate-200 bg-slate-50 px-4 py-2 text-center text-sm text-slate-600 print:hidden">
          Attachment merge is turned off for this form&apos;s print layout.
        </p>
      ) : null}
      {data && canMergePdf && !pdfError ? (
        <p className="border-b border-slate-200 bg-slate-50 px-4 py-2 text-center text-sm text-slate-600 print:hidden">
          Print shows the form only. Use <strong>Open appended PDF</strong> when you need attachments with approval
          stamps.
        </p>
      ) : null}
      {data ? <StructuredPrintView data={data} showApprovalFooter /> : null}
    </>
  );
}
