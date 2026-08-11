"use client";

import type { ColumnDef } from "@tanstack/react-table";

import {
  createActionsColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { ControlledDocumentRow } from "@/lib/api/modules/controlled-documents-api";
import { controlledDocumentStatusTone, statusToneClassName } from "@/lib/ui/status-tone";
import { controlledDocumentSubmissionUrl } from "@/modules/documents/controlled-document-submission-url";

export function createControlledDocumentsTableColumns(options: {
  canCreate: boolean;
  onOpen: (documentId: string) => void;
}): ColumnDef<ControlledDocumentRow>[] {
  return [
    createTextColumn("document_code", "Code", (row) => row.document_code, {
      className: "font-mono text-xs",
      enableSorting: true,
    }),
    createTextColumn(
      "title",
      "Title",
      (row) => (
        <button
          type="button"
          className="text-left font-medium text-foreground hover:text-primary"
          onClick={() => options.onOpen(row.id)}
        >
          {row.title}
        </button>
      ),
      { enableSorting: true },
    ),
    createTextColumn("document_type", "Type", (row) => row.document_type ?? "—", {
      className: "text-muted-foreground",
      enableSorting: true,
    }),
    createTextColumn("department", "Department", (row) => row.department ?? "—", {
      className: "text-muted-foreground",
      enableSorting: true,
    }),
    createTextColumn("current_revision", "Rev", (row) => row.current_revision, {
      className: "tabular-nums",
    }),
    createTextColumn(
      "status",
      "Status",
      (row) => (
        <span className={statusToneClassName(controlledDocumentStatusTone(row.status))}>
          {row.status}
        </span>
      ),
      { enableSorting: true },
    ),
    createTextColumn("effective_date", "Effective", (row) => row.effective_date ?? "—", {
      className: "text-muted-foreground",
      enableSorting: true,
    }),
    createActionsColumn("Actions", (row) => {
      const canRevise =
        options.canCreate &&
        Boolean(row.original.e_approval_form_id) &&
        row.original.status !== "obsolete";
      const revisionHref =
        canRevise && row.original.e_approval_form_id
          ? controlledDocumentSubmissionUrl({
              formId: row.original.e_approval_form_id,
              mode: "revision",
              documentCode: row.original.document_code,
            })
          : null;

      return (
        <RowActionsMenu
          items={[
            {
              key: "open",
              label: "Open",
              onSelect: () => options.onOpen(row.original.id),
            },
            {
              key: "revision",
              label: "Submit revision",
              hidden: !revisionHref,
              href: revisionHref ?? undefined,
            },
          ]}
        />
      );
    }),
  ];
}
