"use client";

import { ChevronRight } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useMemo } from "react";

import { useIsMobile } from "@/hooks/use-mobile";
import { resolveWorkspaceBreadcrumbs, type WorkspaceBreadcrumb } from "@/lib/navigation/workspace-breadcrumbs";
import { cn } from "@/lib/utils";
import { useWorkspaceBreadcrumbStore } from "@/stores/workspace-breadcrumb-store";

function collapseForMobile(crumbs: WorkspaceBreadcrumb[]): WorkspaceBreadcrumb[] {
  if (crumbs.length <= 2) {
    return crumbs;
  }

  const first = crumbs[0]!;
  const last = crumbs[crumbs.length - 1]!;

  return [first, { label: "…" }, last];
}

export function WorkspaceBreadcrumbs({ className }: { className?: string }) {
  const pathname = usePathname();
  const isMobile = useIsMobile();
  const pageLabel = useWorkspaceBreadcrumbStore((state) => state.pageLabel);

  const crumbs = useMemo(() => {
    const resolved = resolveWorkspaceBreadcrumbs(pathname);
    if (resolved.length === 0) {
      return [];
    }

    const withPageLabel = pageLabel
      ? resolved.map((crumb, index) =>
          index === resolved.length - 1 ? { ...crumb, label: pageLabel } : crumb,
        )
      : resolved;

    return isMobile ? collapseForMobile(withPageLabel) : withPageLabel;
  }, [isMobile, pageLabel, pathname]);

  if (crumbs.length === 0) {
    return null;
  }

  return (
    <nav
      aria-label="Breadcrumb"
      className={cn("min-w-0 flex-1 overflow-hidden text-xs text-muted-foreground sm:text-sm", className)}
    >
      <ol className="flex items-center gap-1.5">
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1;
          const isEllipsis = crumb.label === "…";

          return (
            <li
              key={`${crumb.label}-${index}`}
              className={cn("flex min-w-0 items-center gap-1.5", isLast && "shrink")}
            >
              {index > 0 ? <ChevronRight className="h-3 w-3 shrink-0 opacity-70" aria-hidden /> : null}
              {isEllipsis ? (
                <span className="px-0.5 text-muted-foreground" aria-hidden>
                  …
                </span>
              ) : crumb.href && !isLast ? (
                <Link href={crumb.href} className="shrink-0 hover:text-foreground">
                  {crumb.label}
                </Link>
              ) : (
                <span
                  className={cn(isLast && "truncate font-medium text-foreground")}
                  title={isLast ? crumb.label : undefined}
                >
                  {crumb.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
