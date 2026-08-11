"use client";

import { useEffect, useMemo, useState } from "react";

import { ApprovalHistoryPrintBlock } from "@/components/e-approval/print/approval-history-print-block";
import {
  buildApprovalHistorySlots,
  resolvePrintTemplate,
} from "@/modules/e-approval/approval-history-print";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import { downloadEApprovalAttachment } from "@/lib/api/modules/e-approval-api";

type Props = {
  data: EApprovalPrintPayload;
  showApprovalHistory?: boolean;
};

function isImageFile(fileName: string | null | undefined): boolean {
  const ext = fileName?.split(".").pop()?.toLowerCase() ?? "";
  return ["png", "jpg", "jpeg", "gif", "webp", "svg"].includes(ext);
}

function isPdfFile(fileName: string | null | undefined): boolean {
  const ext = fileName?.split(".").pop()?.toLowerCase() ?? "";
  return ext === "pdf";
}

function inferMimeFromFileName(fileName: string | null | undefined): string | null {
  const ext = fileName?.split(".").pop()?.toLowerCase() ?? "";
  switch (ext) {
    case "pdf":
      return "application/pdf";
    case "png":
      return "image/png";
    case "jpg":
    case "jpeg":
      return "image/jpeg";
    case "gif":
      return "image/gif";
    case "webp":
      return "image/webp";
    case "svg":
      return "image/svg+xml";
    default:
      return null;
  }
}

export function EApprovalSubmissionPrintView({ data, showApprovalHistory = true }: Props) {
  const attachments = data.attachments ?? [];
  const template = resolvePrintTemplate(data);
  const approvalSlots = useMemo(() => buildApprovalHistorySlots(data, template), [data, template]);

  const [attachmentObjectUrls, setAttachmentObjectUrls] = useState<Record<string, string>>({});
  const [attachmentErrors, setAttachmentErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    let cancelled = false;
    const createdUrls: string[] = [];

    const load = async () => {
      if (attachments.length === 0) {
        setAttachmentObjectUrls({});
        setAttachmentErrors({});
        return;
      }

      const urlEntries: Array<readonly [string, string]> = [];
      const errorEntries: Array<readonly [string, string]> = [];

      for (const attachment of attachments) {
        try {
          const blob = await downloadEApprovalAttachment(attachment.id);
          const inferredMime = inferMimeFromFileName(attachment.file_name);
          const typedBlob =
            inferredMime && (blob.type === "" || blob.type === "application/octet-stream")
              ? new Blob([blob], { type: inferredMime })
              : blob;
          const objectUrl = URL.createObjectURL(typedBlob);
          createdUrls.push(objectUrl);
          urlEntries.push([attachment.id, objectUrl] as const);
        } catch {
          errorEntries.push([attachment.id, "Could not load attachment."] as const);
        }
      }

      if (cancelled) {
        createdUrls.forEach((url) => URL.revokeObjectURL(url));
        return;
      }

      setAttachmentObjectUrls(Object.fromEntries(urlEntries));
      setAttachmentErrors(Object.fromEntries(errorEntries));
    };

    void load();

    return () => {
      cancelled = true;
      createdUrls.forEach((url) => URL.revokeObjectURL(url));
    };
  }, [attachments]);

  return (
    <div className="eapproval-attachment-only-print bg-slate-100 print:bg-white">
      <div className="eapproval-attachment-only-body">
        {attachments.length === 0 ? (
          <p className="p-8 text-center text-sm text-slate-600">No attachments on this submission.</p>
        ) : (
          attachments.map((a) => {
            const url = attachmentObjectUrls[a.id];
            const err = attachmentErrors[a.id];
            const isPdf = isPdfFile(a.file_name);
            const isImage = isImageFile(a.file_name);

            return (
              <section
                key={a.id}
                className="eapproval-print-attachment-page mx-auto w-full max-w-[210mm] bg-white shadow-sm print:shadow-none"
              >
                {!url && !err ? (
                  <p className="p-8 text-center text-sm text-slate-500">Loading attachment…</p>
                ) : err ? (
                  <p className="p-8 text-center text-sm text-destructive">{err}</p>
                ) : isImage && url ? (
                  <div className="relative">
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={url}
                      alt={a.file_name}
                      className="block w-full object-contain"
                      style={{ maxHeight: "100vh" }}
                    />
                    {a.metadata && (a.metadata.slot || a.metadata.caption || a.metadata.lat != null) ? (
                      <div className="border-t border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-700">
                        {a.metadata.slot ? <p className="font-medium">{a.metadata.slot}</p> : null}
                        {a.metadata.caption ? <p>{a.metadata.caption}</p> : null}
                        {a.metadata.lat != null && a.metadata.lng != null ? (
                          <p>
                            {Number(a.metadata.lat).toFixed(5)}, {Number(a.metadata.lng).toFixed(5)}
                            {a.metadata.captured_at ? ` · ${a.metadata.captured_at}` : ""}
                          </p>
                        ) : a.metadata.captured_at ? (
                          <p>{a.metadata.captured_at}</p>
                        ) : null}
                      </div>
                    ) : null}
                  </div>
                ) : isPdf && url ? (
                  <iframe
                    src={url}
                    title={a.file_name}
                    className="block w-full border-0"
                    style={{ height: "100vh", minHeight: "100vh" }}
                  />
                ) : (
                  <div className="flex flex-col items-center gap-3 p-8 text-center">
                    <p className="text-sm font-medium text-slate-800">{a.file_name}</p>
                    <p className="text-sm text-slate-600">
                      Browser preview is not available for this file type (Word, Excel, etc.). Download the original
                      to open it.
                    </p>
                    {url ? (
                      <a
                        href={url}
                        download={a.file_name}
                        className="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 print:hidden"
                      >
                        Download {a.file_name}
                      </a>
                    ) : null}
                  </div>
                )}
              </section>
            );
          })
        )}
      </div>

      {showApprovalHistory && approvalSlots.length > 0 ? (
        <ApprovalHistoryPrintBlock
          slots={approvalSlots}
          className="eapproval-attachment-approval-footer mx-auto mt-6 w-full max-w-[210mm] border-t border-slate-300 bg-white px-6 py-6 print:mt-0 print:break-before-page"
          variant="screen"
        />
      ) : null}
    </div>
  );
}
