"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import {
  Archive,
  ChevronDown,
  CreditCard,
  ScrollText,
  Settings,
  Users,
  type LucideIcon,
} from "lucide-react";

import { AppTopNavUtilities } from "@/components/layout/app-top-nav-utilities";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { useWorkspaceNavGroups, type WorkspaceNavGroupView } from "@/hooks/use-workspace-nav-groups";
import { isNavActive } from "@/lib/navigation/is-nav-active";
import type { WorkspaceSubNavItem, WorkspaceTopNavItem } from "@/lib/navigation/workspace-nav-config";
import { cn } from "@/lib/utils";

function groupHomeHref(item: WorkspaceTopNavItem): string {
  if (item.href) return item.href;
  const overview = item.items?.find((sub) => sub.exact);
  return overview?.href ?? item.items?.[0]?.href ?? "/dashboard";
}

function hrefMatches(pathname: string, href: string, exact?: boolean): boolean {
  return isNavActive(pathname, href, exact);
}

function itemMatchesPath(pathname: string, item: WorkspaceTopNavItem): boolean {
  if (item.href && hrefMatches(pathname, item.href, item.exact)) return true;
  return Boolean(item.items?.some((sub) => hrefMatches(pathname, sub.href, sub.exact)));
}

function groupHasActivePath(pathname: string, group: WorkspaceNavGroupView | undefined): boolean {
  return Boolean(group?.items.some((item) => itemMatchesPath(pathname, item)));
}

function inferSection(itemTitle: string, sub: WorkspaceSubNavItem): string | undefined {
  if (sub.section) return sub.section;

  if (itemTitle === "Sites") {
    if (/dashboard/i.test(sub.title) || /dashboard/i.test(sub.href)) return "Dashboards";
    if (sub.href === "/dynamic-entities" || /all entities/i.test(sub.title)) return "Catalog";
    if (/document|permit|lease|contract|colocation|utilit/i.test(sub.title)) return "Documents & contracts";
    return "Portfolio";
  }

  if (itemTitle === "Finance") {
    if (/report|aging|petty|trial|income|balance|cash|management|bir compliance|cas /i.test(sub.title)) {
      return "Reports";
    }
    return "Ledgers & masters";
  }

  if (itemTitle === "Procurement") {
    if (/report|monitoring|sales|stock on hand|bank recon/i.test(sub.title)) return "Reports";
    return "Transactions";
  }

  if (itemTitle === "System Core") {
    if (/field|printable|html report|report builder|email/i.test(sub.title)) return "Data & print";
    if (/workflow|cron|ai prompt|automation/i.test(sub.title)) return "Automation";
    return "Platform";
  }

  return undefined;
}

function sectionedItems(
  itemTitle: string,
  items: WorkspaceSubNavItem[],
): Array<{ section: string | null; items: WorkspaceSubNavItem[] }> {
  const enriched = items.map((sub) => ({
    ...sub,
    section: inferSection(itemTitle, sub),
  }));

  const order: string[] = [];
  const map = new Map<string | null, WorkspaceSubNavItem[]>();

  for (const sub of enriched) {
    const key = sub.section ?? null;
    if (!map.has(key)) {
      map.set(key, []);
      if (key) order.push(key);
    }
    map.get(key)!.push(sub);
  }

  const unsectioned = map.get(null);
  const blocks: Array<{ section: string | null; items: WorkspaceSubNavItem[] }> = [];

  if (unsectioned?.length && order.length === 0) {
    return [{ section: null, items: unsectioned }];
  }

  for (const section of order) {
    blocks.push({ section, items: map.get(section) ?? [] });
  }
  if (unsectioned?.length) {
    blocks.push({ section: null, items: unsectioned });
  }

  return blocks;
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
  const active = itemMatchesPath(pathname, item);
  const badge = item.title === "Notifications" ? notificationUnread : undefined;

  const blocks = useMemo(
    () => (item.items?.length ? sectionedItems(item.title, item.items) : []),
    [item.items, item.title],
  );
  const multiSection = blocks.filter((b) => b.section).length > 1;
  const wide = (item.items?.length ?? 0) >= 10;

  const triggerClass = cn(
    "inline-flex h-7 items-center gap-1 rounded-md px-2 text-xs font-medium transition-colors",
    active
      ? "bg-background text-foreground shadow-sm"
      : "text-slate-200 hover:bg-white/10 hover:text-white",
  );

  if (!item.items?.length) {
    return (
      <Link href={home} prefetch={false} className={triggerClass}>
        <Icon className="hidden size-3 shrink-0 opacity-90 xl:inline" />
        <span className="whitespace-nowrap">{item.title}</span>
        {badge !== undefined && badge > 0 ? (
          <span
            className={cn(
              "inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[9px] font-medium",
              active ? "bg-foreground/10 text-foreground" : "bg-sky-500 text-white",
            )}
          >
            {badge > 99 ? "99+" : badge}
          </span>
        ) : null}
      </Link>
    );
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger render={<button type="button" className={triggerClass} />}>
        <Icon className="hidden size-3 shrink-0 opacity-90 xl:inline" />
        <span className="whitespace-nowrap">{item.title}</span>
        <ChevronDown className="size-3 opacity-60" />
      </PopoverTrigger>
      <PopoverContent align="start" className={cn("p-1", wide ? "w-[28rem]" : "w-64")}>
        <Link
          href={home}
          prefetch={false}
          onClick={() => setOpen(false)}
          className="block rounded-md px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted"
        >
          {item.title} overview
        </Link>
        <div className="my-1 border-t border-border" />
        <div className={cn(multiSection && wide && "grid grid-cols-2 gap-x-2")}>
          {blocks.map((block) => (
            <div key={block.section ?? "_"} className="min-w-0">
              {block.section ? (
                <p className="px-2.5 pt-1.5 pb-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                  {block.section}
                </p>
              ) : null}
              {block.items.map((sub) => {
                const subActive = hrefMatches(pathname, sub.href, sub.exact);
                return (
                  <Link
                    key={sub.href}
                    href={sub.href}
                    prefetch={false}
                    onClick={() => setOpen(false)}
                    className={cn(
                      "block rounded-md px-2.5 py-1.5 text-xs hover:bg-muted",
                      subActive
                        ? "bg-muted font-medium text-foreground"
                        : "text-muted-foreground",
                    )}
                  >
                    {sub.title}
                  </Link>
                );
              })}
            </div>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
}

type SetupTile = {
  id: string;
  title: string;
  icon: LucideIcon;
  href: string;
  items?: WorkspaceSubNavItem[];
};

function setupTileIcon(title: string, fallback: LucideIcon): LucideIcon {
  const key = title.toLowerCase();
  if (key.includes("team") || key.includes("access") || key.includes("user")) return Users;
  if (key.includes("audit")) return ScrollText;
  if (key.includes("backup")) return Archive;
  if (key.includes("billing")) return CreditCard;
  if (key.includes("setting")) return Settings;
  return fallback;
}

/** Flatten Administration into Metacoresoft-style Setup tiles (Governance leaves become tiles). */
function buildSetupTiles(group: WorkspaceNavGroupView): SetupTile[] {
  const tiles: SetupTile[] = [];

  for (const item of group.items) {
    if (item.title === "Governance" && item.items?.length) {
      for (const sub of item.items) {
        tiles.push({
          id: sub.href,
          title: sub.title,
          icon: setupTileIcon(sub.title, item.icon),
          href: sub.href,
        });
      }
      continue;
    }

    tiles.push({
      id: item.title,
      title: item.title,
      icon: setupTileIcon(item.title, item.icon),
      href: groupHomeHref(item),
      items: item.items,
    });
  }

  return tiles;
}

/** Right-side Setup control — icon grid + sub-list (Operations stays visible). */
function SetupMenu({ group }: { group: WorkspaceNavGroupView }) {
  const pathname = usePathname();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const tiles = useMemo(() => buildSetupTiles(group), [group]);
  const active = groupHasActivePath(pathname, group);

  const defaultTileId = useMemo(() => {
    const match = tiles.find((tile) => {
      if (hrefMatches(pathname, tile.href)) return true;
      return tile.items?.some((sub) => hrefMatches(pathname, sub.href, sub.exact));
    });
    return match?.id ?? tiles[0]?.id ?? "";
  }, [pathname, tiles]);

  const [selectedId, setSelectedId] = useState(defaultTileId);
  const selected = tiles.find((tile) => tile.id === selectedId) ?? tiles[0];

  return (
    <Popover
      open={open}
      onOpenChange={(next) => {
        setOpen(next);
        if (next) setSelectedId(defaultTileId);
      }}
    >
      <PopoverTrigger
        render={
          <button
            type="button"
            className={cn(
              "inline-flex h-7 items-center gap-1 rounded-md px-2 text-[10px] font-medium tracking-wide uppercase transition-colors",
              active || open
                ? "bg-white/12 text-white"
                : "text-slate-400 hover:bg-white/8 hover:text-slate-200",
            )}
            aria-label="Open setup menu"
          />
        }
      >
        Setup
        <ChevronDown className="size-3 opacity-70" aria-hidden />
      </PopoverTrigger>
      <PopoverContent align="end" className="w-[20rem] p-2">
        {tiles.length === 0 ? (
          <p className="px-2 py-3 text-xs text-muted-foreground">No setup items.</p>
        ) : (
          <div className="space-y-2">
            <div role="tablist" aria-label="Setup" className="grid grid-cols-3 gap-1">
              {tiles.map((tile) => {
                const Icon = tile.icon;
                const isSelected = tile.id === selected?.id;
                return (
                  <button
                    key={tile.id}
                    type="button"
                    role="tab"
                    aria-selected={isSelected}
                    onClick={() => {
                      setSelectedId(tile.id);
                      if (!tile.items?.length) {
                        setOpen(false);
                        router.push(tile.href);
                      }
                    }}
                    className={cn(
                      "flex flex-col items-center gap-1 rounded-md px-1.5 py-2 text-center transition-colors",
                      isSelected
                        ? "bg-muted text-foreground"
                        : "text-muted-foreground hover:bg-muted/70 hover:text-foreground",
                    )}
                  >
                    <Icon className="size-3.5 shrink-0" aria-hidden />
                    <span className="line-clamp-2 text-[10px] font-medium leading-tight">
                      {tile.title}
                    </span>
                  </button>
                );
              })}
            </div>

            {selected?.items?.length ? (
              <>
                <div className="border-t border-border" />
                <div role="tabpanel" className="max-h-64 space-y-0.5 overflow-y-auto">
                  <Link
                    href={selected.href}
                    prefetch={false}
                    onClick={() => setOpen(false)}
                    className="block rounded-md px-2.5 py-1.5 text-xs font-medium text-foreground hover:bg-muted"
                  >
                    {selected.title} overview
                  </Link>
                  {selected.items.map((sub) => {
                    const subActive = hrefMatches(pathname, sub.href, sub.exact);
                    return (
                      <Link
                        key={sub.href}
                        href={sub.href}
                        prefetch={false}
                        onClick={() => setOpen(false)}
                        className={cn(
                          "block rounded-md px-2.5 py-1.5 text-xs hover:bg-muted",
                          subActive
                            ? "bg-muted font-medium text-foreground"
                            : "text-muted-foreground",
                        )}
                      >
                        {sub.title}
                      </Link>
                    );
                  })}
                </div>
              </>
            ) : null}
          </div>
        )}
      </PopoverContent>
    </Popover>
  );
}

/**
 * Dark module strip: Operations always visible · Setup on the right opens a tablist.
 */
export function AppTopNav() {
  const { groups, notificationUnread } = useWorkspaceNavGroups();

  const operations = groups.find((g) => g.group === "Operations") ?? groups[0];
  const administration = groups.find((g) => g.group === "Administration");

  return (
    <div className="shrink-0 border-b border-border bg-slate-900 print:hidden dark:bg-slate-950">
      <div className="flex h-10 items-center gap-1.5 px-3 sm:px-4 md:px-6">
        <AppTopNavUtilities />

        <span className="mr-0.5 hidden shrink-0 px-1 text-[9px] font-medium tracking-wide text-slate-500 uppercase sm:inline">
          Operations
        </span>

        <nav className="flex min-w-0 flex-1 flex-wrap items-center gap-0.5">
          {(operations?.items ?? []).map((item) => (
            <TopNavDropdown
              key={`${item.module ?? "nav"}:${item.title}:${item.href ?? item.items?.[0]?.href ?? ""}`}
              item={item}
              notificationUnread={notificationUnread}
            />
          ))}
        </nav>

        {administration ? (
          <div className="ml-auto flex shrink-0 items-center border-l border-white/10 pl-2">
            <SetupMenu group={administration} />
          </div>
        ) : null}
      </div>
    </div>
  );
}
