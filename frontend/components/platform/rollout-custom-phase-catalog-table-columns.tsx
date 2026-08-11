"use client";

import type { ColumnDef } from "@tanstack/react-table";

import {
  createActionsColumn,
  createTextColumn,
} from "@/components/ui/data-table-column-helpers";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { PlatformRolloutCustomPhase } from "@/lib/api/modules/platform-api";

export function createRolloutCustomPhaseCatalogTableColumns(options: {
  onArchive: (id: string) => void;
  archivePending: boolean;
}): ColumnDef<PlatformRolloutCustomPhase>[] {
  return [
    createTextColumn("phase_key", "Key", (row) => (
      <span className="font-mono text-sm">{row.phase_key}</span>
    )),
    createTextColumn("label", "Label", (row) => row.label),
    createTextColumn(
      "templates",
      "Templates",
      (row) => <span className="text-muted-foreground">{row.applicable_templates.join(", ")}</span>,
    ),
    createTextColumn("wd_range", "WD range", (row) => (
      <span className="font-mono text-sm">
        {row.default_working_day_start}–{row.default_working_day_end}
      </span>
    )),
    createTextColumn("sla", "SLA", (row) => (row.counts_toward_sla ? "Yes" : "No")),
    createActionsColumn("Actions", (row) => (
      <RowActionsMenu
        disabled={options.archivePending}
        items={[
          {
            key: "archive",
            label: "Archive",
            onSelect: () => options.onArchive(row.original.id),
          },
        ]}
      />
    )),
  ];
}
