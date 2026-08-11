"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { platformFetchTenantModulesCatalog } from "@/lib/api/modules/platform-api";
import {
  resolveToggleableWorkspaceModules,
  TENANT_MODULE_DESCRIPTIONS,
  TENANT_MODULE_LABELS,
} from "@/lib/tenant/enabled-modules";
import { cn } from "@/lib/utils";

export type TenantModulesPickerValue = string[] | null;

const EMPTY_MODULES: string[] = [];
const REQUIRED_PREFIX = ["core", "team_access"] as const;

type Props = {
  value: TenantModulesPickerValue;
  onChange: (value: TenantModulesPickerValue) => void;
  className?: string;
};

function toggleableFromValue(
  value: TenantModulesPickerValue,
  toggleableModules: string[],
  platformModules: string[],
): string[] {
  if (value == null) {
    return toggleableModules.filter((module) => platformModules.includes(module));
  }
  return toggleableModules.filter((module) => value.includes(module));
}

function sameModuleSet(a: Set<string>, b: string[]): boolean {
  return a.size === b.length && b.every((module) => a.has(module));
}

/**
 * Shared module enable UI for create-tenant and the modules sheet.
 * `null` = use deployment default (`TOWEROS_TENANT_ENABLED_MODULES`).
 */
export function TenantModulesPicker({ value, onChange, className }: Props) {
  const catalogQuery = useQuery({
    queryKey: ["platform", "tenant-modules", "catalog"],
    queryFn: platformFetchTenantModulesCatalog,
    staleTime: 60_000,
  });

  const toggleableModules = useMemo(
    () => resolveToggleableWorkspaceModules(catalogQuery.data),
    [catalogQuery.data],
  );
  const labels = catalogQuery.data?.labels ?? TENANT_MODULE_LABELS;
  const descriptions = catalogQuery.data?.descriptions ?? TENANT_MODULE_DESCRIPTIONS;
  // Stable empty fallback — a fresh `[]` each render retriggers the sync effect forever.
  const platformModules = catalogQuery.data?.platform_modules ?? EMPTY_MODULES;

  const usePlatformDefault = value == null;
  const [selected, setSelected] = useState<Set<string>>(
    () => new Set(toggleableFromValue(value, toggleableModules, platformModules)),
  );

  useEffect(() => {
    const nextModules = toggleableFromValue(value, toggleableModules, platformModules);
    setSelected((current) => (sameModuleSet(current, nextModules) ? current : new Set(nextModules)));
  }, [value, toggleableModules, platformModules]);

  const summary = useMemo(() => {
    if (usePlatformDefault) {
      return "Uses deployment default modules for this tenant.";
    }

    const enabled = [...REQUIRED_PREFIX, ...Array.from(selected)];
    return enabled
      .map((module) => labels[module] ?? TENANT_MODULE_LABELS[module] ?? module)
      .join(" · ");
  }, [labels, selected, usePlatformDefault]);

  const emitChange = (next: TenantModulesPickerValue) => {
    // Base UI may invoke onCheckedChange while reconciling controlled state.
    // Defer so we never update TenantEnvironmentSheet during TenantModulesPicker render.
    queueMicrotask(() => onChange(next));
  };

  const emitSelection = (nextSelected: Set<string>) => {
    emitChange([...REQUIRED_PREFIX, ...Array.from(nextSelected)]);
  };

  const toggleModule = (module: string) => {
    const next = new Set(selected);
    if (next.has(module)) {
      next.delete(module);
    } else {
      next.add(module);
    }
    setSelected(next);
    emitSelection(next);
  };

  const setUsePlatformDefault = (enabled: boolean) => {
    if (enabled === usePlatformDefault) {
      return;
    }
    if (enabled) {
      emitChange(null);
      return;
    }
    const next = new Set(
      toggleableModules.filter((module) => platformModules.includes(module)),
    );
    setSelected(next);
    emitSelection(next);
  };

  return (
    <div className={cn("space-y-4", className)}>
      <p className="text-xs text-muted-foreground">{summary}</p>

      <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border px-3 py-3">
        <Checkbox
          className="mt-0.5 size-4"
          checked={usePlatformDefault}
          onCheckedChange={(v) => setUsePlatformDefault(v === true)}
        />
        <span>
          <span className="block text-sm font-medium">Use deployment default</span>
          <span className="mt-0.5 block text-xs text-muted-foreground">
            Follows <code className="text-[11px]">TOWEROS_TENANT_ENABLED_MODULES</code> for this
            environment. Dashboard and Team &amp; Access always stay enabled.
          </span>
        </span>
      </label>

      <div className={cn("space-y-3", usePlatformDefault && "pointer-events-none opacity-50")}>
        <Label className="text-xs text-muted-foreground">Optional modules</Label>
        {catalogQuery.isLoading ? (
          <p className="text-xs text-muted-foreground">Loading module catalog…</p>
        ) : (
          toggleableModules.map((module) => {
            const label = labels[module] ?? TENANT_MODULE_LABELS[module] ?? module;
            const description = descriptions[module] ?? TENANT_MODULE_DESCRIPTIONS[module];
            const checked = selected.has(module);

            return (
              <label
                key={module}
                className="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-border px-3 py-3"
              >
                <span>
                  <span className="block text-sm font-medium">{label}</span>
                  {description ? (
                    <span className="mt-0.5 block text-xs text-muted-foreground">{description}</span>
                  ) : (
                    <span className="mt-0.5 block text-xs text-muted-foreground">{module}</span>
                  )}
                </span>
                <Checkbox
                  className="size-4"
                  checked={checked}
                  disabled={usePlatformDefault}
                  onCheckedChange={(next) => {
                    const wantChecked = next === true;
                    if (wantChecked === checked) {
                      return;
                    }
                    toggleModule(module);
                  }}
                />
              </label>
            );
          })
        )}
      </div>
    </div>
  );
}
