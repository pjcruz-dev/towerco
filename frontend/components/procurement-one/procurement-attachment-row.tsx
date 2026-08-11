"use client";

import { useState } from "react";
import { Download, Eye } from "lucide-react";

import { Button } from "@/components/ui/button";
import { downloadEApprovalAttachment } from "@/lib/api/modules/e-approval-api";
import { downloadProcurementGrnAttachment } from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  fileName: string;
  fieldName?: string | null;
  mimeType?: string | null;
  eApprovalAttachmentId?: string | null;
  grnId?: string | null;
  grnAttachmentId?: string | null;
};

function canPreviewInline(mimeType: string | null | undefined): boolean {
  if (!mimeType) {
    return false;
  }

  return mimeType.startsWith("image/") || mimeType === "application/pdf";
}

export function ProcurementAttachmentRow({
  fileName,
  fieldName,
  mimeType,
  eApprovalAttachmentId,
  grnId,
  grnAttachmentId,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const [busyAction, setBusyAction] = useState<"download" | "preview" | null>(null);

  const canDownload = Boolean(eApprovalAttachmentId) || Boolean(grnId && grnAttachmentId);

  const downloadBlob = async (): Promise<Blob | null> => {
    if (!canDownload) {
      push({
        level: "error",
        title: "Download unavailable",
        message: "This attachment is not linked to a downloadable file yet.",
      });

      return null;
    }

    try {
      if (grnId && grnAttachmentId) {
        return await downloadProcurementGrnAttachment(grnId, grnAttachmentId);
      }

      return await downloadEApprovalAttachment(eApprovalAttachmentId!);
    } catch (error) {
      push({
        level: "error",
        title: "Could not download file",
        message: getErrorMessage(error),
      });

      return null;
    }
  };

  const handleDownload = async () => {
    setBusyAction("download");
    try {
      const blob = await downloadBlob();
      if (!blob) {
        return;
      }

      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = fileName;
      anchor.click();
      URL.revokeObjectURL(url);
    } finally {
      setBusyAction(null);
    }
  };

  const handlePreview = async () => {
    setBusyAction("preview");
    try {
      const blob = await downloadBlob();
      if (!blob) {
        return;
      }

      const url = URL.createObjectURL(blob);
      window.open(url, "_blank", "noopener,noreferrer");
      window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } finally {
      setBusyAction(null);
    }
  };

  return (
    <li className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border px-3 py-2">
      <div className="min-w-0">
        <p className="truncate text-sm font-medium">{fileName}</p>
        {fieldName ? <p className="text-xs text-muted-foreground">{fieldName}</p> : null}
      </div>
      <div className="flex shrink-0 items-center gap-1">
        {canPreviewInline(mimeType) && canDownload ? (
          <Button
            type="button"
            size="sm"
            variant="ghost"
            disabled={busyAction !== null}
            onClick={() => void handlePreview()}
          >
            <Eye className="mr-1.5 h-4 w-4" aria-hidden />
            {busyAction === "preview" ? "Opening…" : "Preview"}
          </Button>
        ) : null}
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={busyAction !== null || !canDownload}
          onClick={() => void handleDownload()}
        >
          <Download className="mr-1.5 h-4 w-4" aria-hidden />
          {busyAction === "download" ? "Downloading…" : "Download"}
        </Button>
      </div>
    </li>
  );
}
