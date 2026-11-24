"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useMemo, useState } from "react";
import { LayoutGrid } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { useWorkspaceNavGroups } from "@/hooks/use-workspace-nav-groups";
import { moduleShellLinksFromGroups, resolveModuleShell } from "@/lib/navigation/module-shell";
import { cn } from "@/lib/utils";

/**
 * Header control that names the current module and links to the others.
 * Each link opens that module's dashboard, which is the only sidebar the next page shows.
 */
export function ModuleAppSwitcher() {
  const pathname = usePathname();
  const shell = resolveModuleShell(pathname);
  const { catalogGroups } = useWorkspaceNavGroups();
  const [open, setOpen] = useState(false);

  const links = useMemo(() => moduleShellLinksFromGroups(catalogGroups), [catalogGroups]);
  const label = shell.kind === "hub" ? "All apps" : shell.title;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        render={
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="max-w-[14rem] shrink-0 gap-1.5"
            aria-label={`Current app: ${label}. Switch app`}
          />
        }
      >
        <LayoutGrid className="size-3.5 shrink-0" aria-hidden />
        <span className="truncate">{label}</span>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-72 p-2">
        <p className="px-2 py-1.5 text-xs text-muted-foreground">
          {shell.kind === "hub"
            ? "Choose an app. You will land on its dashboard."
            : `You are in ${label}. Open another app to switch.`}
        </p>
        <ul className="mt-1 space-y-0.5">
          <li>
            <Link
              href="/dashboard"
              onClick={() => setOpen(false)}
              className={cn(
                "block rounded-md px-2 py-2 outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring",
                shell.kind === "hub" && "bg-muted",
              )}
            >
              <span className="block text-sm font-medium text-foreground">All apps</span>
              <span className="block text-xs text-muted-foreground">Work waiting across modules</span>
            </Link>
          </li>
          {links.map((link) => {
            const active = link.id === shell.id;
            return (
              <li key={link.id}>
                <Link
                  href={link.homeHref}
                  onClick={() => setOpen(false)}
                  className={cn(
                    "block rounded-md px-2 py-2 outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring",
                    active && "bg-muted",
                  )}
                >
                  <span className="block text-sm font-medium text-foreground">{link.title}</span>
                  <span className="block text-xs text-muted-foreground">
                    Opens the {link.title} dashboard
                  </span>
                </Link>
              </li>
            );
          })}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
