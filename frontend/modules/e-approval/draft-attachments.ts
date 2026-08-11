export type EApprovalSavedAttachmentRef = {
  id: string;
  file_name: string;
};

export function groupSavedAttachmentsByField(
  attachments: { id: string; field_name: string | null; file_name: string }[],
): Record<string, EApprovalSavedAttachmentRef[]> {
  const grouped: Record<string, EApprovalSavedAttachmentRef[]> = {};

  for (const attachment of attachments) {
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
  existingAttachments: { field_name: string | null; file_name: string }[],
): Record<string, File[]> {
  const savedKeys = new Set(
    existingAttachments
      .filter((attachment) => attachment.field_name?.trim())
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
