"use client";

import { useCallback, useMemo } from "react";

import {
  AttachmentPreviewGallery,
  type AttachmentGalleryItem,
} from "@/components/attachments/attachment-preview-gallery";
import { useEApprovalPdfPreview } from "@/hooks/use-e-approval-pdf-preview";
import {
  downloadEApprovalAttachment,
  downloadEApprovalAttachmentFile,
} from "@/lib/api/modules/e-approval-api";
import { useNotificationStore } from "@/stores/notification-store";
import { getErrorMessage } from "@/lib/api/error";
import { hasSignatureValue } from "@/modules/e-approval/signature";
import { cn } from "@/lib/utils";

export type EApprovalSubmissionAttachmentRow = {
  id: string;
  file_name: string;
  field_name?: string | null;
  metadata?: {
    lat?: number;
    lng?: number;
    captured_at?: string;
    caption?: string;
    slot?: string;
  } | null;
};

type Props = {
  submissionId: string;
  attachments: EApprovalSubmissionAttachmentRow[];
  /** Approved steps with signatures — used for footer hint on cards. */
  stampedApprovalCount?: number;
  title?: string;
  className?: string;
  /** Optional field labels keyed by field name (for gallery section titles). */
  fieldLabelsByName?: Record<string, string>;
};

function hasCameraMetadata(metadata: EApprovalSubmissionAttachmentRow["metadata"]): boolean {
  if (!metadata) return false;
  return (
    metadata.slot != null ||
    metadata.caption != null ||
    metadata.lat != null ||
    metadata.lng != null ||
    metadata.captured_at != null
  );
}

function formatGeotag(metadata: NonNullable<EApprovalSubmissionAttachmentRow["metadata"]>): string | null {
  const parts: string[] = [];
  if (metadata.lat != null && metadata.lng != null) {
    parts.push(`${Number(metadata.lat).toFixed(5)}, ${Number(metadata.lng).toFixed(5)}`);
  }
  if (metadata.captured_at) {
    const date = new Date(metadata.captured_at);
    parts.push(Number.isNaN(date.getTime()) ? metadata.captured_at : date.toLocaleString());
  }
  return parts.length > 0 ? parts.join(" · ") : null;
}

function fieldSectionTitle(
  fieldName: string | null | undefined,
  fieldLabelsByName: Record<string, string> | undefined,
): string {
  if (!fieldName) return "Other files";
  const label = fieldLabelsByName?.[fieldName]?.trim();
  return label || fieldName;
}

/** Avoid repeating the panel title (e.g. "Attachments") on every file card. */
function attachmentFieldCaption(
  fieldName: string | null | undefined,
  fieldLabelsByName: Record<string, string> | undefined,
  panelTitle: string,
): string | null {
  if (!fieldName) {
    return null;
  }
  const label = (fieldLabelsByName?.[fieldName] ?? fieldName).trim();
  if (!label) {
    return null;
  }
  const normalizedLabel = label.toLowerCase();
  const normalizedTitle = panelTitle.trim().toLowerCase();
  if (normalizedLabel === normalizedTitle) {
    return null;
  }
  if (
    normalizedLabel === "attachment" ||
    normalizedLabel === "attachments" ||
    normalizedLabel === "supporting documents"
  ) {
    return null;
  }
  return label;
}

export function countStampedApprovals(
  approvals: { status: string; approval_status?: string; signature?: string | null }[] | undefined,
): number {
  return (approvals ?? []).filter(
    (row) =>
      (row.approval_status ?? row.status).toLowerCase() === "approved" &&
      hasSignatureValue(row.signature),
  ).length;
}

export function EApprovalSubmissionAttachmentsPanel({
  submissionId,
  attachments,
  stampedApprovalCount = 0,
  title = "Attachments",
  className,
  fieldLabelsByName,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const { openPdfPreview, isGenerating } = useEApprovalPdfPreview();
  const fetchBlob = useCallback((id: string) => downloadEApprovalAttachment(id), []);

  const items = useMemo((): AttachmentGalleryItem[] => {
    return attachments.map((attachment) => {
      const meta = attachment.metadata;
      const geotag = meta ? formatGeotag(meta) : null;
      const fieldKey = attachment.field_name?.trim() || null;
      const isPhoto =
        hasCameraMetadata(meta) || /\.(png|jpe?g|gif|webp|bmp|heic|heif)$/i.test(attachment.file_name);
      const isPdf = /\.pdf$/i.test(attachment.file_name);
      const fieldCaption = attachmentFieldCaption(fieldKey, fieldLabelsByName, title);
      const titleLine = meta?.slot?.trim() || meta?.caption?.trim() || attachment.file_name;
      const subtitleParts = [
        meta?.caption && meta?.slot ? meta.caption : null,
        geotag,
        !isPhoto ? fieldCaption : null,
      ].filter(Boolean);

      return {
        id: attachment.id,
        fileName: attachment.file_name,
        title: titleLine,
        subtitle: subtitleParts.length > 0 ? subtitleParts.join(" · ") : null,
        groupKey: isPhoto ? fieldKey || "__photos__" : isPdf ? fieldKey || "__documents__" : null,
        groupLabel:
          isPhoto || isPdf
            ? fieldSectionTitle(fieldKey === "__other__" ? null : fieldKey, fieldLabelsByName)
            : null,
        preferImagePreview: hasCameraMetadata(meta),
      };
    });
  }, [attachments, fieldLabelsByName, title]);

  if (attachments.length === 0) {
    return null;
  }

  return (
    <AttachmentPreviewGallery
      className={cn(className)}
      title={title}
      items={items}
      fetchBlob={fetchBlob}
      openPreviewDisabled={isGenerating}
      openPreviewLabel={
        stampedApprovalCount > 0 ? "Open with approval footer" : "Open preview"
      }
      hint={
        stampedApprovalCount > 0
          ? `${stampedApprovalCount} approval signature${stampedApprovalCount === 1 ? "" : "s"} stamped on PDF preview`
          : "Photos show geotag details when captured. PDF/image can open with approval footer."
      }
      onDownload={async (item) => {
        try {
          await downloadEApprovalAttachmentFile(item.id, item.fileName);
        } catch (error) {
          push({
            level: "error",
            title: "Download failed",
            message: getErrorMessage(error) || "Could not download this file.",
          });
        }
      }}
      onOpenPreview={async (item) => {
        try {
          await openPdfPreview(submissionId, item.id);
        } catch (error) {
          push({
            level: "error",
            title: "PDF preview failed",
            message: getErrorMessage(error) || "Could not generate PDF with approval footer.",
          });
        }
      }}
    />
  );
}
