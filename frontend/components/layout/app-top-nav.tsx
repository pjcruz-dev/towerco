"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { ChevronDown } from "lucide-react";

import { SidebarBrand } from "@/components/layout/sidebar-brand";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { useWorkspaceNavGroups } from "@/hooks/use-workspace-nav-groups";
import { isNavActive } from "@/lib/navigation/is-nav-active";
import type { WorkspaceTopNavItem } from "@/lib/navigation/workspace-nav-config";
import { cn } from "@/lib/utils";

function groupHomeHref(item: WorkspaceTopNavItem): string {
  if (item.href) return item.href;
  const overview = item.items?.find((sub) => sub.exact);
  return overview?.href ?? item.items?.[0]?.href ?? "/dashboard";
}

function TopNavDropdown({
  item,
  notificationUnread,
}: {
  item: WorkspaceTopNavItem;
  notificationUnread: number;
}) {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const Icon = item.icon;
  const home = groupHomeHref(item);
  const childActive = Boolean(
    item.items?.some((sub) => isNavActive(pathname, sub.href, sub.exact)),
  );
  const selfActive = item.href ? isNavActive(pathname, item.href, item.exact) : false;
  const active = childActive || selfActive;
  const badge = item.title === "Notifications" ? notificationUnread : undefined;

  if (!item.items?.length) {
    return (
      <Link
        href={home}
        prefetch={false}
        className={cn(
          "inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium transition-colors",
          active
            ? "bg-muted text-foreground"
            : "text-muted-foreground hover:bg-muted/70 hover:text-foreground",
        )}
      >
        <Icon className="size-3.5 shrink-0" />
        <span className="whitespace-nowrap">{item.title}</span>
        {badge !== undefined && badge > 0 ? (
          <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-medium text-primary-foreground">
            {badge > 99 ? "99+" : badge}
          </span>
        ) : null}
      </Link>
    );
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger
        render={
          <button
            type="button"
            className={cn(
              "inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium transition-colors",
              active
                ? "bg-muted text-foreground"
                : "text-muted-foreground hover:bg-muted/70 hover:text-foreground",
            )}
          />
        }
      >
        <Icon className="size-3.5 shrink-0" />
        <span className="whitespace-nowrap">{item.title}</span>
        <ChevronDown className="size-3.5 opacity-60" />
      </PopoverTrigger>
      <PopoverContent align="start" className="w-56 p-1">
        <Link
          href={home}
          prefetch={false}
          onClick={() => setOpen(false)}
          className="block rounded-md px-2.5 py-2 text-sm font-medium hover:bg-muted"
        >
          {item.title} overview
        </Link>
        <div className="my-1 border-t border-border" />
        {item.items.map((sub) => {
          const subActive = isNavActive(pathname, sub.href, sub.exact);
          return (
            <Link
              key={sub.href}
              href={sub.href}
              prefetch={false}
              onClick={() => setOpen(false)}
              className={cn(
                "block rounded-md px-2.5 py-2 text-sm hover:bg-muted",
                subActive ? "bg-muted font-medium text-foreground" : "text-muted-foreground",
              )}
            >
              {sub.title}
            </Link>
          );
        })}
      </PopoverContent>
    </Popover>
  );
}

/**
 * Horizontal top navigation used when Navigation Layout = Navbar.
 */
export function AppTopNav() {
  const { groups, notificationUnread } = useWorkspaceNavGroups();

  return (
    <div className="border-b border-border bg-card print:hidden">
      <div className="flex h-12 items-center gap-3 px-4 sm:px-6 md:px-8">
        <div className="shrink-0">
          <SidebarBrand variant="tenant" compact />
        </div>
        <nav className="scrollbar-hide flex min-w-0 flex-1 items-center gap-0.5 overflow-x-auto">
          {groups.map((group) => (
            <div key={group.group} className="flex shrink-0 items-center gap-0.5">
              <span className="mx-1 hidden px-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground/70 xl:inline">
                {group.group}
              </span>
              {group.items.map((item) => (
                <TopNavDropdown
                  key={`${item.module ?? "nav"}:${item.title}:${item.href ?? item.items?.[0]?.href ?? ""}`}
                  item={item}
                  notificationUnread={notificationUnread}
                />
              ))}
            </div>
          ))}
        </nav>
      </div>
    </div>
  );
}
