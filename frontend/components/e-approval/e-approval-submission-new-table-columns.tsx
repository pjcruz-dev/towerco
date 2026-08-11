"use client";

import { ArrowRight, Copy } from "lucide-react";
import type { ColumnDef } from "@tanstack/react-table";

import { Button } from "@/components/ui/button";
import {
  createActionsColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import type { EApprovalFormListRow } from "@/modules/e-approval/types";

function formatCategory(category: string): string {
  const trimmed = category.trim();
  if (!trimmed) return "General";
  return trimmed.charAt(0).toUpperCase() + trimmed.slice(1);
}

export function createEApprovalSubmissionNewTableColumns(options: {
  onStart: (formId: string) => void;
  onCopyExternalLink?: (formId: string) => void;
  copyingFormId?: string | null;
}): ColumnDef<EApprovalFormListRow>[] {
  return [
    createTextColumn("name", "Form", (row) => <span className="font-medium">{row.name}</span>, {
      enableSorting: true,
      sortValue: (row) => row.name,
    }),
    createTextColumn(
      "category",
      "Category",
      (row) => <span className="text-muted-foreground">{formatCategory(row.category)}</span>,
      { enableSorting: true, sortValue: (row) => formatCategory(row.category) },
    ),
    createTextColumn(
      "description",
      "Description",
      (row) => (
        <span className="max-w-md truncate text-sm text-muted-foreground">
          {row.description?.trim() || "—"}
        </span>
      ),
    ),
    createActionsColumn("Actions", (row) => {
      const formId = row.original.id;
      const canCopy = Boolean(row.original.has_shareable_public_link && options.onCopyExternalLink);
      const copying = options.copyingFormId === formId;

      return (
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" size="sm" className="gap-1" onClick={() => options.onStart(formId)}>
            Start
            <ArrowRight className="h-3.5 w-3.5" />
          </Button>
          {canCopy ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="gap-1"
              disabled={copying}
              onClick={() => options.onCopyExternalLink?.(formId)}
            >
              <Copy className="h-3.5 w-3.5" />
              {copying ? "Copying…" : "Copy link"}
            </Button>
          ) : null}
        </div>
      );
    }),
  ];
}
