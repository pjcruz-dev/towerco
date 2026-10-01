"use client";

import { useProductRelease } from "@/hooks/use-product-release";
import { isProductionEnvironment, resolveAppEnvironmentLabel } from "@/lib/runtime/app-environment";

export function AppFooter() {
  const { displayedVersion } = useProductRelease();

  // Production tenants: no version / stack chrome (operational calm).
  if (isProductionEnvironment()) {
    return null;
  }

  const envLabel = resolveAppEnvironmentLabel();
  const version = displayedVersion;

  return (
    <footer className="flex h-12 flex-wrap items-center justify-between gap-x-6 gap-y-1 border-t border-border bg-card px-6 text-xs text-muted-foreground md:px-8 print:hidden">
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
        <span className="font-medium text-foreground/80">
          {version} · {envLabel}
        </span>
        <span className="hidden h-3 w-px bg-border sm:block" aria-hidden />
        <span>Laravel · Next.js · MySQL</span>
      </div>
      <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
        <div className="flex items-center gap-1.5">
          <div className="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />
          <span>Database connected</span>
        </div>
        <div className="flex items-center gap-1.5">
          <div className="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500" />
          <span>Cache active</span>
        </div>
      </div>
    </footer>
  );
}
