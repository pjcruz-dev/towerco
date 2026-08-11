"use client";

import { useQuery } from "@tanstack/react-query";
import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";

import { EApprovalSubmissionPrintView } from "@/components/e-approval/e-approval-submission-print-view";
import { EApprovalGenericFormPrintView } from "@/components/e-approval/e-approval-generic-form-print-view";
import { Button } from "@/components/ui/button";
import { openEApprovalAttachmentPdfPreview } from "@/lib/e-approval/e-approval-attachment-pdf";
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
  const autoOpenedRef = useRef(false);
  const [pdfError, setPdfError] = useState<string | null>(null);
  const [isGeneratingPdf, setIsGeneratingPdf] = useState(false);

  const { data, isError, isLoading, refetch, isFetching } = useQuery({
    queryKey: ["e-approval", "submission", submissionId, "print"],
    queryFn: () => fetchEApprovalSubmissionPrint(submissionId),
    retry: 1,
  });

  const openMergedPdf = useCallback(async (payload: EApprovalPrintPayload) => {
    setIsGeneratingPdf(true);
    setPdfError(null);
    try {
      await openEApprovalAttachmentPdfPreview(payload);
    } catch (e) {
      const code = e instanceof Error ? e.message : "";
      if (code === "NO_PRINTABLE_ATTACHMENTS" || code === "EMPTY_PDF") {
        setPdfError(
          "No PDF or image attachments to merge. Office files (Word/Excel) must be downloaded — use the attachment section below.",
        );
      } else {
        setPdfError("Could not generate PDF with approval history.");
      }
    } finally {
      setIsGeneratingPdf(false);
    }
  }, []);

  useEffect(() => {
    if (!data || autoOpenedRef.current) return;
    if (!hasPrintableAttachment(data)) return;
    autoOpenedRef.current = true;
    void openMergedPdf(data);
  }, [data, openMergedPdf]);

  const handlePrint = useCallback(() => {
    if (typeof window !== "undefined") {
      window.print();
    }
  }, []);

  const structuredEntry = data ? resolvePrintTemplateEntry(data) : null;
  const StructuredPrintView = structuredEntry?.PrintView ?? EApprovalGenericFormPrintView;
  const canMergePdf = Boolean(data && hasPrintableAttachment(data));
  const showAttachmentPages = Boolean(data && hasAnyAttachment(data));

  return (
    <>
      <div className="eapproval-print-toolbar sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2 print:hidden">
        <Link href={`/e-approval/submissions/${submissionId}`} className="text-sm text-primary hover:underline">
          ← Back to submission
        </Link>
        <div className="flex gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => refetch()} disabled={isFetching}>
            Refresh
          </Button>
          {data && canMergePdf ? (
            <Button type="button" size="sm" onClick={() => void openMergedPdf(data)} disabled={isGeneratingPdf}>
              {isGeneratingPdf ? "Generating…" : "Open PDF preview"}
            </Button>
          ) : (
            <Button type="button" size="sm" onClick={handlePrint} disabled={!data}>
              Print / Save as PDF
            </Button>
          )}
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
      {data && !canMergePdf && hasAnyAttachment(data) ? (
        <p className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-900 print:hidden">
          This submission has Office attachments (e.g. Word). They cannot open in the browser PDF viewer — download
          them from the attachment section below.
        </p>
      ) : null}
      {data && canMergePdf && !pdfError ? (
        <p className="border-b border-slate-200 bg-slate-50 px-4 py-2 text-center text-sm text-slate-600 print:hidden">
          PDF with approval history on every page opens in a new tab. Use <strong>Open PDF preview</strong> to open it
          again, or browser print for the structured document below.
        </p>
      ) : null}
      {data ? <StructuredPrintView data={data} showApprovalFooter={!canMergePdf} /> : null}
      {showAttachmentPages ? (
        <div className="print:break-before-page">
          <EApprovalSubmissionPrintView data={data!} showApprovalHistory />
        </div>
      ) : null}
    </>
  );
}
