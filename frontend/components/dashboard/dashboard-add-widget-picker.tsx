"use client";

import { DashboardWidgetMiniPreview } from "@/components/dashboard/dashboard-widget-mini-preview";
import {
  DASHBOARD_PICKER_GROUP_HINTS,
  DASHBOARD_PICKER_GROUP_LABELS,
  catalogEntryPurpose,
  resolvePickerGroup,
  type DashboardCatalogEntry,
  type DashboardPickerGroup,
} from "@/lib/ui/dashboard-widget-catalog";

type Props = {
  entries: DashboardCatalogEntry[];
  onSelect: (entry: DashboardCatalogEntry) => void;
  emptyMessage?: string;
};

const GROUP_ORDER: DashboardPickerGroup[] = ["sections", "enhancements", "charts", "other"];

export function DashboardAddWidgetPicker({
  entries,
  onSelect,
  emptyMessage = "Nothing left to add. Remove a section first, or use Duplicate on a widget’s Layout & options.",
}: Props) {
  const grouped = entries.reduce<Partial<Record<DashboardPickerGroup, DashboardCatalogEntry[]>>>(
    (acc, entry) => {
      const key = resolvePickerGroup(entry);
      acc[key] = acc[key] ?? [];
      acc[key]!.push(entry);
      return acc;
    },
    {},
  );

  if (entries.length === 0) {
    return <p className="px-2 py-4 text-center text-xs text-muted-foreground">{emptyMessage}</p>;
  }

  return (
    <div className="max-h-[26rem] space-y-3 overflow-auto p-1">
      {GROUP_ORDER.map((group) => {
        const items = grouped[group];
        if (!items?.length) return null;
        return (
          <div key={group}>
            <div className="mb-1.5 px-1">
              <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                {DASHBOARD_PICKER_GROUP_LABELS[group]}
              </p>
              <p className="text-[10px] leading-snug text-muted-foreground/90">
                {DASHBOARD_PICKER_GROUP_HINTS[group]}
              </p>
            </div>
            <ul className="space-y-1">
              {items.map((entry) => (
                <li key={entry.id}>
                  <button
                    type="button"
                    className="flex w-full items-start gap-2.5 rounded-lg border border-transparent px-2 py-2 text-left transition-colors hover:border-border hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40"
                    onClick={() => onSelect(entry)}
                  >
                    <DashboardWidgetMiniPreview entry={entry} />
                    <span className="min-w-0 flex-1">
                      <span className="block text-sm font-medium text-foreground">{entry.label}</span>
                      <span className="mt-0.5 block text-[11px] leading-snug text-muted-foreground">
                        {entry.description}
                      </span>
                      <span className="mt-1 block text-[10px] font-medium leading-snug text-sky-700 dark:text-sky-400">
                        {catalogEntryPurpose(entry)}
                      </span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        );
      })}
    </div>
  );
}
