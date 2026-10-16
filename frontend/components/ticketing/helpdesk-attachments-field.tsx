"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

import {
  AttachmentPreviewGallery,
  formatAttachmentBytes,
} from "@/components/attachments/attachment-preview-gallery";
import { AttachmentDropzone } from "@/components/attachments/attachment-dropzone";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteTicketingAttachment,
  downloadTicketingAttachment,
  fetchTicketingAttachmentBlob,
  fetchTicketingTicket,
  uploadTicketingAttachment,
} from "@/lib/api/modules/ticketing-api";
import { usePermission } from "@/hooks/use-permission";
import { permissions } from "@/lib/rbac/permissions";
import type { TicketingAttachmentRow } from "@/modules/ticketing/types";

/** Real ticket files. The field used to store only the file names as long text. */
export function HelpdeskAttachmentsField({ ticketId }: { ticketId: string }) {
  const canManage = usePermission([permissions.ticketingTicketsManage]);
  const canCreate = usePermission([permissions.ticketingTicketsCreate]);
  const canEdit = canManage || canCreate;
  const [rows, setRows] = useState<TicketingAttachmentRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  const load = useCallback(async () => {
    const ticket = await fetchTicketingTicket(ticketId);
    setRows(ticket.attachments ?? []);
  }, [ticketId]);

  useEffect(() => {
    let cancelled = false;
    fetchTicketingTicket(ticketId)
      .then((ticket) => {
        if (!cancelled) setRows(ticket.attachments ?? []);
      })
      .catch(() => {
        if (!cancelled) setError("Could not load attachments.");
      });
    return () => {
      cancelled = true;
    };
  }, [ticketId]);

  const items = useMemo(
    () =>
      rows.map((row) => {
        const sizeLabel = formatAttachmentBytes(row.size_bytes);
        return {
          id: row.id,
          fileName: row.file_name,
          mimeType: row.mime_type,
          sizeBytes: row.size_bytes,
          title: row.file_name,
          subtitle: sizeLabel,
        };
      }),
    [rows],
  );

  async function onFiles(files: File[]) {
    if (!canEdit || files.length === 0) return;
    setUploading(true);
    setError(null);
    try {
      for (const file of files) {
        await uploadTicketingAttachment(ticketId, file);
      }
      await load();
    } catch (err: unknown) {
      setError(getErrorMessage(err) || "Could not upload attachment.");
    } finally {
      setUploading(false);
    }
  }

  return (
    <div className="space-y-3">
      {canEdit ? (
        <AttachmentDropzone
          files={[]}
          onChange={(files) => void onFiles(files)}
          multiple
          maxFiles={20}
          occupiedSlots={rows.length}
          accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
          enablePaste
          disabled={uploading}
          hint="PNG, JPG, PDF, Office · paste a screenshot with Ctrl+V"
          error={error}
        />
      ) : error ? (
        <p className="text-sm text-destructive">{error}</p>
      ) : null}
      {items.length > 0 ? (
        <AttachmentPreviewGallery
          title="Files"
          hint="Images and PDFs open in the browser. Other files download."
          items={items}
          fetchBlob={fetchTicketingAttachmentBlob}
          onDownload={async (item) => {
            await downloadTicketingAttachment(item.id, item.fileName);
          }}
          onRemove={
            canEdit
              ? async (item) => {
                  await deleteTicketingAttachment(item.id);
                  setRows((current) => current.filter((row) => row.id !== item.id));
                }
              : undefined
          }
        />
      ) : canEdit ? null : (
        <p className="text-sm text-muted-foreground">No files.</p>
      )}
    </div>
  );
}
