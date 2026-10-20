"use client";

import Link from "next/link";
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
    createTextColumn("dcf_number", "DCF number", (row) => row.dcf_number ?? "—", {
      className: "font-mono text-xs tabular-nums",
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
        <span className="inline-flex flex-wrap items-center gap-1.5">
          <span className={statusToneClassName(controlledDocumentStatusTone(row.status))}>
            {row.status}
          </span>
          {row.incoming_submission_id && row.incoming_revision != null ? (
            <Link
              href={`/e-approval/submissions/${row.incoming_submission_id}`}
              className={statusToneClassName("warning")}
            >
              Rev {row.incoming_revision} incoming
            </Link>
          ) : null}
        </span>
      ),
      { enableSorting: true },
    ),
    createTextColumn("effective_date", "Effective", (row) => row.effective_date ?? "—", {
      className: "text-muted-foreground",
      enableSorting: true,
    }),
    createTextColumn("next_review_date", "Next review", (row) => row.next_review_date ?? "—", {
      className: "text-muted-foreground tabular-nums",
      enableSorting: true,
    }),
    createTextColumn(
      "has_file",
      "File",
      (row) => (
        <span className={statusToneClassName(row.has_file ? "success" : "neutral")}>
          {row.has_file ? "Has file" : "Missing file"}
        </span>
      ),
    ),
    createActionsColumn("Actions", (row) => {
      const revisionFormId = row.original.revision_form_id || row.original.e_approval_form_id;
      const canRevise =
        options.canCreate && Boolean(revisionFormId) && row.original.status !== "obsolete";
      const revisionHref =
        canRevise && revisionFormId
          ? controlledDocumentSubmissionUrl({
              formId: revisionFormId,
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
