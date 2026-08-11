"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  platformFetchTenantModulesCatalog,
  type PlatformTenantRow,
} from "@/lib/api/modules/platform-api";
import {
  resolveToggleableWorkspaceModules,
  TENANT_MODULE_DESCRIPTIONS,
  TENANT_MODULE_LABELS,
} from "@/lib/tenant/enabled-modules";
import { cn } from "@/lib/utils";

const EMPTY_MODULES: string[] = [];

function modulesKey(modules: string[] | null | undefined): string {
  return modules?.join("\u0000") ?? "";
}

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenant: PlatformTenantRow;
  isPending: boolean;
  onSave: (payload: { enabled_modules: string[] | null }) => void;
};

export function TenantModulesSheet({ open, onOpenChange, tenant, isPending, onSave }: Props) {
  const catalogQuery = useQuery({
    queryKey: ["platform", "tenant-modules", "catalog"],
    queryFn: platformFetchTenantModulesCatalog,
    enabled: open,
    staleTime: 0,
    refetchOnMount: "always",
  });

  const toggleableModules = useMemo(
    () => resolveToggleableWorkspaceModules(catalogQuery.data),
    [catalogQuery.data],
  );
  const labels = catalogQuery.data?.labels ?? TENANT_MODULE_LABELS;
  const descriptions = catalogQuery.data?.descriptions ?? TENANT_MODULE_DESCRIPTIONS;

  const effectiveModules = useMemo(
    () =>
      tenant.effective_enabled_modules ??
      tenant.enabled_modules ??
      catalogQuery.data?.platform_modules ??
      EMPTY_MODULES,
    [
      tenant.effective_enabled_modules,
      tenant.enabled_modules,
      catalogQuery.data?.platform_modules,
    ],
  );

  const [usePlatformDefault, setUsePlatformDefault] = useState(tenant.enabled_modules == null);
  const [selected, setSelected] = useState<Set<string>>(new Set());

  const enabledModulesKey = modulesKey(tenant.enabled_modules);
  const effectiveModulesKey = modulesKey(effectiveModules);
  const toggleableModulesKey = modulesKey(toggleableModules);

  useEffect(() => {
    if (!open) {
      return;
    }

    setUsePlatformDefault(tenant.enabled_modules == null);
    const initial = tenant.enabled_modules ?? effectiveModules;
    setSelected(
      new Set(toggleableModules.filter((module) => initial.includes(module))),
    );
  }, [open, tenant.id, enabledModulesKey, effectiveModulesKey, toggleableModulesKey]);

  const summary = useMemo(() => {
    if (usePlatformDefault) {
      return "Uses deployment default modules for this tenant.";
    }

    const enabled = ["core", "team_access", ...Array.from(selected)];
    return enabled
      .map((module) => labels[module] ?? TENANT_MODULE_LABELS[module] ?? module)
      .join(" · ");
  }, [labels, selected, usePlatformDefault]);

  const toggleModule = (module: string) => {
    setUsePlatformDefault(false);
    setSelected((current) => {
      const next = new Set(current);
      if (next.has(module)) {
        next.delete(module);
      } else {
        next.add(module);
      }
      return next;
    });
  };

  const handleSave = () => {
    if (usePlatformDefault) {
      onSave({ enabled_modules: null });
      return;
    }

    onSave({
      enabled_modules: ["core", "team_access", ...Array.from(selected)],
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="flex w-full flex-col gap-0 sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Workspace modules</SheetTitle>
          <SheetDescription>
            Control which product modules appear for users in this tenant. Dashboard and Team &amp;
            Access always stay enabled.
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 space-y-5 overflow-y-auto px-4 py-4">
          <div className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
            <p className="font-medium text-foreground">{tenant.domains[0] ?? tenant.id}</p>
            <p className="mt-1 text-xs text-muted-foreground">{summary}</p>
          </div>

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
                environment.
              </span>
            </span>
          </label>

          <div className={cn("space-y-3", usePlatformDefault && "pointer-events-none opacity-50")}>
            <Label className="text-xs text-muted-foreground">Optional modules</Label>
            {toggleableModules.map((module) => {
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
                    onCheckedChange={() => toggleModule(module)}
                  />
                </label>
              );
            })}
          </div>

          {!usePlatformDefault &&
          !selected.has("e_approval") &&
          !selected.has("project_one") &&
          !selected.has("procurement_one") &&
          !selected.has("documents") &&
          !selected.has("document_register") ? (
            <p className="text-xs text-amber-700 dark:text-amber-300">
              Enable at least E-Approval, Project-One, Procurement-One, Documents, or Document register so
              users have an operational workspace module.
            </p>
          ) : null}
        </div>

        <SheetFooter className="border-t border-border">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" onClick={handleSave} disabled={isPending}>
            {isPending ? "Saving…" : "Save modules"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
