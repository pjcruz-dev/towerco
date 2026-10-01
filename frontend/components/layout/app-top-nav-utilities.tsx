"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import {
  BarChart3,
  Home,
  List,
  History,
  Settings2,
  Star,
  type LucideIcon,
} from "lucide-react";

import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { useGlobalCommandPalette } from "@/hooks/use-global-command-palette";
import { useWorkspaceNavGroups } from "@/hooks/use-workspace-nav-groups";
import { resolveWorkspaceBreadcrumbs } from "@/lib/navigation/workspace-breadcrumbs";
import {
  buildWorkspaceCommandIndex,
  readWorkspaceCommandRecent,
  rememberWorkspaceCommandItem,
  type WorkspaceCommandItem,
} from "@/lib/navigation/workspace-command-index";
import {
  readWorkspaceFavorites,
  toggleWorkspaceFavorite,
  workspaceNavKindTag,
  type WorkspaceFavoriteItem,
} from "@/lib/navigation/workspace-favorites";
import { resolveEnabledModulesForUser } from "@/lib/tenant/enabled-modules";
import { cn } from "@/lib/utils";
import { useAuthStore } from "@/stores/auth-store";

function kindIcon(tag: string): LucideIcon {
  if (tag === "REPORT") return BarChart3;
  if (tag === "LIST") return List;
  return Settings2;
}

function utilityButtonClass(active?: boolean) {
  return cn(
    "inline-flex size-7 items-center justify-center rounded-md transition-colors",
    active
      ? "bg-white/15 text-white"
      : "text-slate-300 hover:bg-white/10 hover:text-white",
  );
}

function RecentRow({
  title,
  href,
  tag,
  onNavigate,
}: {
  title: string;
  href: string;
  tag: string;
  onNavigate: () => void;
}) {
  const Icon = kindIcon(tag);
  return (
    <Link
      href={href}
      prefetch={false}
      onClick={onNavigate}
      className="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-left transition-colors hover:bg-muted"
    >
      <Icon className="size-3.5 shrink-0 text-muted-foreground" aria-hidden />
      <span className="min-w-0 flex-1 truncate text-xs font-normal text-foreground">{title}</span>
      <span className="shrink-0 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
        {tag}
      </span>
    </Link>
  );
}

/**
 * Metacoresoft-style left utilities on the dark module strip:
 * History (recent) · Favorites · Home — compact type.
 */
export function AppTopNavUtilities() {
  const pathname = usePathname();
  const { setOpen: setCommandOpen } = useGlobalCommandPalette();
  const { groups } = useWorkspaceNavGroups();
  const user = useAuthStore((s) => s.user);
  const activeTenantId = useAuthStore((s) => s.activeTenantId);
  const effectivePermissions = useAuthStore((s) => s.effectivePermissions);

  const [historyOpen, setHistoryOpen] = useState(false);
  const [favoritesOpen, setFavoritesOpen] = useState(false);
  const [favorites, setFavorites] = useState<WorkspaceFavoriteItem[]>([]);
  const [recent, setRecent] = useState<WorkspaceCommandItem[]>([]);
  const lastTrackedPath = useRef("");

  const scopedUser = useMemo(() => {
    if (!user || !activeTenantId) return user;
    return { ...user, permissions: effectivePermissions() };
  }, [activeTenantId, effectivePermissions, user]);

  const enabledModules = useMemo(
    () => resolveEnabledModulesForUser(user, activeTenantId),
    [activeTenantId, user],
  );

  const index = useMemo(
    () => buildWorkspaceCommandIndex(scopedUser, enabledModules, groups),
    [enabledModules, groups, scopedUser],
  );

  const lookupItems = useMemo(
    () => [...index.navigate, ...index.actions],
    [index.actions, index.navigate],
  );

  const pageTitle = useMemo(() => {
    const crumbs = resolveWorkspaceBreadcrumbs(pathname);
    return crumbs[crumbs.length - 1]?.label ?? "Page";
  }, [pathname]);

  const currentFavorite = useMemo(
    () => favorites.some((row) => row.href === pathname),
    [favorites, pathname],
  );

  // Hydrate lists when menus open.
  useEffect(() => {
    if (!historyOpen && !favoritesOpen) return;
    setRecent(readWorkspaceCommandRecent(lookupItems));
    setFavorites(readWorkspaceFavorites());
  }, [lookupItems, historyOpen, favoritesOpen]);

  useEffect(() => {
    setFavorites(readWorkspaceFavorites());
  }, []);

  // Record navigations so History fills without requiring Ctrl+K usage.
  useEffect(() => {
    if (!pathname || pathname === "/") return;
    if (lastTrackedPath.current === pathname) return;
    lastTrackedPath.current = pathname;

    const exact = lookupItems.find((item) => item.href === pathname);
    const prefix =
      exact ??
      lookupItems
        .filter((item) => pathname.startsWith(`${item.href}/`))
        .sort((a, b) => b.href.length - a.href.length)[0];

    rememberWorkspaceCommandItem(
      prefix ?? {
        id: `visit:${pathname}`,
        kind: "navigate",
        title: pageTitle,
        href: pathname,
        icon: Home,
        group: "Recent",
      },
    );
  }, [lookupItems, pageTitle, pathname]);

  return (
    <div className="flex shrink-0 items-center gap-0.5 border-r border-white/10 pr-2 mr-1">
      <Popover open={historyOpen} onOpenChange={setHistoryOpen}>
        <PopoverTrigger
          render={
            <button
              type="button"
              className={utilityButtonClass(historyOpen)}
              aria-label="Recent pages"
              title="Recent"
            />
          }
        >
          <History className="size-3.5" aria-hidden />
        </PopoverTrigger>
        <PopoverContent align="start" sideOffset={8} className="w-72 p-1">
          <button
            type="button"
            className="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs font-medium text-foreground transition-colors hover:bg-muted"
            onClick={() => {
              setHistoryOpen(false);
              setCommandOpen(true);
            }}
          >
            <List className="size-3.5 shrink-0 text-muted-foreground" aria-hidden />
            All Recent Records
          </button>
          <div className="my-1 border-t border-border" />
          {recent.length === 0 ? (
            <p className="px-2.5 py-3 text-[11px] text-muted-foreground">
              Pages you open will show up here.
            </p>
          ) : (
            <div className="max-h-60 space-y-0.5 overflow-y-auto">
              {recent.map((item) => (
                <RecentRow
                  key={item.href}
                  title={item.title}
                  href={item.href}
                  tag={workspaceNavKindTag(item)}
                  onNavigate={() => setHistoryOpen(false)}
                />
              ))}
            </div>
          )}
        </PopoverContent>
      </Popover>

      <Popover open={favoritesOpen} onOpenChange={setFavoritesOpen}>
        <PopoverTrigger
          render={
            <button
              type="button"
              className={utilityButtonClass(favoritesOpen || currentFavorite)}
              aria-label="Favorites"
              title="Favorites"
            />
          }
        >
          <Star className={cn("size-3.5", currentFavorite && "fill-current")} aria-hidden />
        </PopoverTrigger>
        <PopoverContent align="start" sideOffset={8} className="w-72 p-1">
          <button
            type="button"
            className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs font-medium hover:bg-muted"
            onClick={() => {
              const next = toggleWorkspaceFavorite({
                href: pathname,
                title: pageTitle,
                kind: workspaceNavKindTag({ href: pathname, title: pageTitle }),
              });
              setFavorites(next);
            }}
          >
            <Star className={cn("size-3.5", currentFavorite && "fill-current text-amber-500")} aria-hidden />
            {currentFavorite ? "Remove current page" : "Star current page"}
          </button>
          <div className="my-1 border-t border-border" />
          {favorites.length === 0 ? (
            <p className="px-2 py-3 text-[11px] text-muted-foreground">
              Star pages to pin them here.
            </p>
          ) : (
            <div className="max-h-72 space-y-0.5 overflow-y-auto">
              {favorites.map((item) => (
                <RecentRow
                  key={item.href}
                  title={item.title}
                  href={item.href}
                  tag={item.kind || "PAGE"}
                  onNavigate={() => setFavoritesOpen(false)}
                />
              ))}
            </div>
          )}
        </PopoverContent>
      </Popover>

      <Link
        href="/dashboard"
        prefetch={false}
        className={utilityButtonClass(pathname === "/dashboard")}
        aria-label="Home"
        title="Home"
      >
        <Home className="size-3.5" aria-hidden />
      </Link>
    </div>
  );
}
