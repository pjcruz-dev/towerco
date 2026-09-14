"use client";

import { Check, LayoutTemplate, Pencil, Share2 } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import type { DashboardCatalogEntry } from "@/lib/ui/dashboard-widget-catalog";
import type { DashboardNormalizedData } from "@/lib/ui/dashboard-widget-data";
import {
  applyLayoutPreset,
  DASHBOARD_LAYOUT_PRESETS,
  type DashboardLayoutPresetId,
} from "@/lib/ui/dashboard-layout-presets";
import type { DashboardLayoutPrefs, DashboardWidgetDef } from "@/lib/ui/dashboard-widget-registry";
import { cn } from "@/lib/utils";
import { useDashboardBoardCapabilities } from "@/hooks/use-dashboard-board-capabilities";

type Props = {
  widgets: Array<Pick<DashboardWidgetDef, "id" | "label" | "hideable" | "removable">>;
  catalogModule: DashboardCatalogEntry["modules"][number];
  layout: DashboardLayoutPrefs;
  editing: boolean;
  onEditingChange: (editing: boolean) => void;
  onChange: (next: DashboardLayoutPrefs | ((prev: DashboardLayoutPrefs) => DashboardLayoutPrefs)) => void;
  /** Page-native default section ids (reset target). */
  defaultEnabledIds?: string[];
  bindableCatalog?: DashboardCatalogEntry[];
  data?: DashboardNormalizedData;
  hasTenantDefault?: boolean;
  onPublishTenantDefault?: () => Promise<unknown>;
  onResetToTenantDefault?: () => Promise<void>;
  className?: string;
};

/**
 * Header control to enter dashboard Customize / Rearrange mode.
 * - Tenant/user managers: full Customize (presets, add/remove, Layout & options).
 * - Everyone else: Rearrange only (personal DnD order).
 */
export function DashboardLayoutToolbar({
  widgets,
  catalogModule: _catalogModule,
  layout,
  editing,
  onEditingChange,
  onChange,
  defaultEnabledIds,
  bindableCatalog = [],
  data: _data,
  hasTenantDefault = false,
  onPublishTenantDefault,
  onResetToTenantDefault,
  className,
}: Props) {
  const [presetOpen, setPresetOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const { canCustomizeBoard } = useDashboardBoardCapabilities();
  const canPublish = canCustomizeBoard;

  if (widgets.length === 0) return null;

  const pageDefaults =
    defaultEnabledIds && defaultEnabledIds.length > 0
      ? defaultEnabledIds
      : widgets.map((widget) => widget.id);

  const applyPreset = (preset: DashboardLayoutPresetId) => {
    const orderedIds =
      layout.widgetOrder.length > 0
        ? layout.widgetOrder
        : layout.enabledWidgetIds.length > 0
          ? layout.enabledWidgetIds
          : pageDefaults;
    const availableIds = Array.from(
      new Set([...pageDefaults, ...widgets.map((w) => w.id), ...bindableCatalog.map((e) => e.id)]),
    );
    onChange(
      applyLayoutPreset(layout, preset, orderedIds, {
        defaultEnabledIds: pageDefaults,
        availableIds,
      }),
    );
    onEditingChange(true);
    setPresetOpen(false);
  };

  const rolePresets = DASHBOARD_LAYOUT_PRESETS.filter((item) => item.kind === "role");
  const densityPresets = DASHBOARD_LAYOUT_PRESETS.filter((item) => item.kind === "density");
  const resetPreset = DASHBOARD_LAYOUT_PRESETS.find((item) => item.kind === "reset");

  return (
    <div className={cn("flex flex-wrap items-center gap-2", className)}>
      {editing && canCustomizeBoard ? (
        <Popover open={presetOpen} onOpenChange={setPresetOpen}>
          <PopoverTrigger
            render={
              <Button type="button" size="sm" variant="outline" className="gap-1.5">
                <LayoutTemplate className="size-3.5" aria-hidden />
                Layout preset
              </Button>
            }
          />
          <PopoverContent className="w-[22rem] space-y-3 p-3" align="end">
            <div>
              <p className="text-[11px] font-medium text-muted-foreground">Role packs</p>
              <p className="mt-0.5 text-[11px] text-muted-foreground">
                Changes which sections are on the board for this page.
              </p>
              <ul className="mt-2 space-y-1">
                {rolePresets.map((preset) => (
                  <li key={preset.id}>
                    <button
                      type="button"
                      className="w-full rounded-md px-2 py-2 text-left transition-colors hover:bg-muted"
                      onClick={() => applyPreset(preset.id)}
                    >
                      <p className="text-sm font-medium text-foreground">{preset.label}</p>
                      <p className="text-[11px] leading-snug text-muted-foreground">
                        {preset.description}
                      </p>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
            <div>
              <p className="text-[11px] font-medium text-muted-foreground">Density</p>
              <ul className="mt-2 space-y-1">
                {densityPresets.map((preset) => (
                  <li key={preset.id}>
                    <button
                      type="button"
                      className="w-full rounded-md px-2 py-2 text-left transition-colors hover:bg-muted"
                      onClick={() => applyPreset(preset.id)}
                    >
                      <p className="text-sm font-medium text-foreground">{preset.label}</p>
                      <p className="text-[11px] leading-snug text-muted-foreground">
                        {preset.description}
                      </p>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
            <div className="space-y-1 border-t border-border pt-2">
              {resetPreset ? (
                <button
                  type="button"
                  className="w-full rounded-md px-2 py-2 text-left transition-colors hover:bg-muted"
                  onClick={() => applyPreset(resetPreset.id)}
                >
                  <p className="text-sm font-medium text-foreground">{resetPreset.label}</p>
                  <p className="text-[11px] leading-snug text-muted-foreground">
                    {resetPreset.description}
                  </p>
                </button>
              ) : null}
              {onResetToTenantDefault ? (
                <button
                  type="button"
                  className="w-full rounded-md px-2 py-2 text-left transition-colors hover:bg-muted disabled:opacity-50"
                  disabled={busy || !hasTenantDefault}
                  onClick={() => {
                    setBusy(true);
                    void onResetToTenantDefault().finally(() => {
                      setBusy(false);
                      setPresetOpen(false);
                    });
                  }}
                >
                  <p className="text-sm font-medium text-foreground">Reset to tenant default</p>
                  <p className="text-[11px] leading-snug text-muted-foreground">
                    {hasTenantDefault
                      ? "Clear your personal override and follow the published tenant layout."
                      : "No tenant default published yet."}
                  </p>
                </button>
              ) : null}
              {canPublish && onPublishTenantDefault ? (
                <button
                  type="button"
                  className="w-full rounded-md border border-border px-2 py-2 text-left transition-colors hover:bg-muted disabled:opacity-50"
                  disabled={busy}
                  onClick={() => {
                    setBusy(true);
                    void onPublishTenantDefault().finally(() => {
                      setBusy(false);
                      setPresetOpen(false);
                    });
                  }}
                >
                  <p className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                    <Share2 className="size-3.5" aria-hidden />
                    Publish as tenant default
                  </p>
                  <p className="text-[11px] leading-snug text-muted-foreground">
                    New users without a personal layout get this board.
                  </p>
                </button>
              ) : null}
            </div>
          </PopoverContent>
        </Popover>
      ) : null}

      {editing && !canCustomizeBoard && onResetToTenantDefault ? (
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={busy || !hasTenantDefault}
          onClick={() => {
            setBusy(true);
            void onResetToTenantDefault().finally(() => setBusy(false));
          }}
        >
          Reset order
        </Button>
      ) : null}

      <Button
        type="button"
        size="sm"
        variant={editing ? "secondary" : "outline"}
        className="gap-1.5"
        onClick={() => onEditingChange(!editing)}
      >
        {editing ? <Check className="size-3.5" aria-hidden /> : <Pencil className="size-3.5" aria-hidden />}
        {editing ? "Done" : canCustomizeBoard ? "Customize" : "Rearrange"}
      </Button>
    </div>
  );
}
