"use client";

import type { MouseEvent } from "react";

import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import { getErrorMessage } from "@/lib/api/error";
import { downloadEApprovalFormExport } from "@/lib/api/modules/e-approval-api";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  formId: string;
  formName: string;
  canManage: boolean;
  layout?: "inline" | "stacked";
  onExportStart?: () => void;
  onExportEnd?: () => void;
};

export function EApprovalFormListActions({
  formId,
  formName,
  canManage,
  onExportStart,
  onExportEnd,
}: Props) {
  const push = useNotificationStore((s) => s.push);
  const href = `/e-approval/forms/${formId}`;

  const handleExport = async (event?: MouseEvent) => {
    event?.preventDefault();
    event?.stopPropagation();
    onExportStart?.();
    try {
      const blob = await downloadEApprovalFormExport(formId);
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = `${formName}.export.json`;
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (error) {
      push({ level: "error", title: "Export failed", message: getErrorMessage(error) });
    } finally {
      onExportEnd?.();
    }
  };

  if (!canManage) {
    return (
      <RowActionsMenu
        items={[
          {
            key: "open",
            label: "Open form",
            href,
          },
        ]}
      />
    );
  }

  return (
    <RowActionsMenu
      items={[
        {
          key: "edit",
          label: "Edit form",
          href,
        },
        {
          key: "export",
          label: "Export JSON",
          onSelect: () => {
            void handleExport();
          },
        },
      ]}
    />
  );
}
