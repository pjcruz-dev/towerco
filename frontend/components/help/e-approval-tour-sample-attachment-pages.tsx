"use client";

import { useMemo } from "react";
import { FileSpreadsheet, FileText, ImageIcon } from "lucide-react";

import { ApprovalHistoryPrintBlock } from "@/components/e-approval/print/approval-history-print-block";
import {
  buildApprovalHistorySlots,
  resolvePrintTemplate,
} from "@/modules/e-approval/approval-history-print";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";

type Props = {
  data: EApprovalPrintPayload;
};

function isImageFile(fileName: string): boolean {
  return /\.(png|jpe?g|gif|webp)$/i.test(fileName);
}

function isPdfFile(fileName: string): boolean {
  return /\.pdf$/i.test(fileName);
}

function isOfficeFile(fileName: string): boolean {
  return /\.(xlsx?|docx?|csv)$/i.test(fileName);
}

/**
 * Tour-only attachment pages that mirror live print: each attachment is its own
 * page with the approval-history stamp footer (same idea as PDF every-page stamps).
 * No API downloads — fixtures only.
 */
export function EApprovalTourSampleAttachmentPages({ data }: Props) {
  const attachments = data.attachments ?? [];
  const template = resolvePrintTemplate(data);
  const approvalSlots = useMemo(() => buildApprovalHistorySlots(data, template), [data, template]);

  if (attachments.length === 0) {
    return null;
  }

  return (
    <div
      data-help="ea-print-attachments"
      className="eapproval-attachment-only-print space-y-6 bg-slate-100 print:bg-white"
    >      {attachments.map((attachment, index) => {
        const isPdf = isPdfFile(attachment.file_name);
        const isImage = isImageFile(attachment.file_name);
        const isOffice = isOfficeFile(attachment.file_name);

        return (
          <section
            key={attachment.id}
            className="eapproval-print-attachment-page mx-auto flex min-h-[280mm] w-full max-w-[210mm] flex-col bg-white shadow-sm print:min-h-[277mm] print:shadow-none print:break-before-page"
          >
            <div className="flex flex-1 flex-col">
              <div className="border-b border-slate-200 px-4 py-2 text-xs text-slate-600">
                Attachment {index + 1} of {attachments.length} · {attachment.file_name}
              </div>

              {isPdf ? (
                <div className="flex flex-1 flex-col items-center justify-center gap-3 bg-slate-50 px-6 py-16 text-center">
                  <FileText className="h-12 w-12 text-slate-400" aria-hidden />
                  <p className="text-sm font-medium text-slate-800">{attachment.file_name}</p>
                  <p className="max-w-sm text-xs text-slate-600">
                    Sample PDF page for the tour. In live print, the real file is merged and the
                    approval-history footer is stamped on every page.
                  </p>
                  <div className="mt-4 w-full max-w-md space-y-2 rounded border border-dashed border-slate-300 bg-white p-4 text-left text-xs text-slate-500">
                    <p className="font-medium text-slate-700">Document Approval attachment</p>
                    <p>Title: Network Operations Handover Procedure</p>
                    <p>PDF sample page — tour only</p>
                  </div>
                </div>
              ) : isImage ? (
                <div className="flex flex-1 flex-col">
                  <div className="flex flex-1 items-center justify-center bg-slate-100 px-6 py-12">
                    <div className="flex w-full max-w-lg flex-col items-center gap-3 rounded border border-slate-200 bg-white p-8 shadow-sm">
                      <ImageIcon className="h-16 w-16 text-slate-400" aria-hidden />
                      <p className="text-sm font-medium text-slate-800">{attachment.file_name}</p>
                      <p className="text-xs text-slate-500">
                        {attachment.metadata?.caption ?? "Sample image attachment (tour only)"}
                      </p>
                    </div>
                  </div>
                  {attachment.metadata?.caption ? (
                    <div className="border-t border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-700">
                      {attachment.metadata.caption}
                    </div>
                  ) : null}
                </div>
              ) : isOffice ? (
                <div className="flex flex-1 flex-col items-center justify-center gap-3 bg-slate-50 px-6 py-16 text-center">
                  <FileSpreadsheet className="h-12 w-12 text-slate-400" aria-hidden />
                  <p className="text-sm font-medium text-slate-800">{attachment.file_name}</p>
                  <p className="max-w-sm text-xs text-slate-600">
                    Office files cannot preview in the browser. Live print lists them for download;
                    PDF/image attachments get the stamped footer on every page.
                  </p>
                </div>
              ) : (
                <div className="flex flex-1 items-center justify-center p-8 text-sm text-slate-600">
                  {attachment.file_name}
                </div>
              )}
            </div>

            {approvalSlots.length > 0 ? (
              <ApprovalHistoryPrintBlock
                slots={approvalSlots}
                className="eapproval-attachment-approval-footer mt-auto border-t border-slate-300 bg-white px-6 py-4"
                variant="screen"
                title="Approval history"
              />
            ) : null}
          </section>
        );
      })}
    </div>
  );
}
