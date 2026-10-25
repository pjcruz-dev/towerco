export type EApprovalSavedAttachmentRef = {
  id: string;
  file_name: string;
};

function isCurrentAttachment(attachment: { metadata?: { superseded_at?: string } | null }): boolean {
  return !attachment.metadata?.superseded_at;
}

export function groupSavedAttachmentsByField(
  attachments: { id: string; field_name: string | null; file_name: string; metadata?: { superseded_at?: string } | null }[],
): Record<string, EApprovalSavedAttachmentRef[]> {
  const grouped: Record<string, EApprovalSavedAttachmentRef[]> = {};

  for (const attachment of attachments) {
    if (!isCurrentAttachment(attachment)) {
      continue;
    }
    const fieldName = attachment.field_name?.trim();
    if (!fieldName) {
      continue;
    }

    grouped[fieldName] ??= [];
    grouped[fieldName].push({ id: attachment.id, file_name: attachment.file_name });
  }

  return grouped;
}

/** Skip files already stored on the draft (same field + original file name). */
export function pendingAttachmentsNotYetSaved(
  attachmentFiles: Record<string, File[]>,
  existingAttachments: { field_name: string | null; file_name: string; metadata?: { superseded_at?: string } | null }[],
): Record<string, File[]> {
  const savedKeys = new Set(
    existingAttachments
      .filter((attachment) => isCurrentAttachment(attachment) && attachment.field_name?.trim())
      .map((attachment) => `${attachment.field_name!.trim()}::${attachment.file_name}`),
  );

  const pending: Record<string, File[]> = {};

  for (const [fieldName, files] of Object.entries(attachmentFiles)) {
    const fresh = files.filter((file) => !savedKeys.has(`${fieldName}::${file.name}`));
    if (fresh.length > 0) {
      pending[fieldName] = fresh;
    }
  }

  return pending;
}

export function hasPendingAttachmentFiles(attachmentFiles: Record<string, File[]>): boolean {
  return Object.values(attachmentFiles).some((files) => files.length > 0);
}

/** Keep files that were just stored when the draft payload was loaded before the upload. */
export function mergeUploadedDraftAttachments<T extends { id: string }>(
  existing: T[],
  uploaded: T[],
): T[] {
  const byId = new Map(existing.map((attachment) => [attachment.id, attachment]));
  for (const attachment of uploaded) {
    byId.set(attachment.id, attachment);
  }

  return [...byId.values()];
}
