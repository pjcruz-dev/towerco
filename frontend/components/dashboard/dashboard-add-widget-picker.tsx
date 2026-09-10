"use client";

import {
  DASHBOARD_PICKER_GROUP_LABELS,
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
    <div className="max-h-[22rem] space-y-3 overflow-auto p-1">
      {GROUP_ORDER.map((group) => {
        const items = grouped[group];
        if (!items?.length) return null;
        return (
          <div key={group}>
            <p className="mb-1 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
              {DASHBOARD_PICKER_GROUP_LABELS[group]}
            </p>
            <ul className="space-y-0.5">
              {items.map((entry) => (
                <li key={entry.id}>
                  <button
                    type="button"
                    className="w-full rounded-md px-2 py-2 text-left transition-colors hover:bg-muted"
                    onClick={() => onSelect(entry)}
                  >
                    <p className="text-sm font-medium text-foreground">{entry.label}</p>
                    <p className="text-[11px] leading-snug text-muted-foreground">{entry.description}</p>
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
