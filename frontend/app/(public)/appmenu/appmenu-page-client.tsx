"use client";

import { useQuery } from "@tanstack/react-query";

import { fetchPublicAppMenu } from "@/lib/api/modules/app-menu-api";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import type { PublicAppMenuTile } from "@/lib/api/modules/platform-api";
import {
  resolveAppMenuAccentClass,
  resolveAppMenuIcon,
} from "@/lib/platform/app-menu-options";
import { cn } from "@/lib/utils";

const DESKTOP_GRID_CLASS: Record<number, string> = {
  3: "md:grid-cols-3",
  4: "md:grid-cols-4",
  5: "md:grid-cols-5",
  6: "md:grid-cols-6",
};

const DESKTOP_MAX_WIDTH: Record<number, string> = {
  3: "max-w-2xl",
  4: "max-w-3xl",
  5: "max-w-4xl",
  6: "max-w-5xl",
};

function TileGrid({ tiles, columns }: { tiles: PublicAppMenuTile[]; columns: number }) {
  const cols = Math.min(6, Math.max(3, columns));
  return (
    <ul
      className={cn(
        "mx-auto grid w-full grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 md:gap-x-8 md:gap-y-10",
        DESKTOP_MAX_WIDTH[cols],
        DESKTOP_GRID_CLASS[cols],
      )}
    >
      {tiles.map((tile) => {
        const Icon = resolveAppMenuIcon(tile.icon);
        const accent = resolveAppMenuAccentClass(tile.accent);
        const customIconUrl = resolveBrandingAssetUrl(tile.icon_url);
        return (
          <li key={tile.id} className="flex justify-center">
            <a
              href={tile.href}
              target={tile.open_in_new_tab ? "_blank" : undefined}
              rel={tile.open_in_new_tab ? "noopener noreferrer" : undefined}
              className="group flex w-[7.5rem] flex-col items-center gap-2.5 text-center outline-none"
            >
              <span
                className={cn(
                  "flex size-[4.5rem] items-center justify-center rounded-2xl border border-slate-200/80 bg-white/95 shadow-sm backdrop-blur-sm transition",
                  "group-hover:border-slate-300 group-hover:shadow-md group-focus-visible:ring-2 group-focus-visible:ring-slate-400",
                )}
              >
                {customIconUrl ? (
                  // eslint-disable-next-line @next/next/no-img-element -- hosted app-menu icon
                  <img
                    src={customIconUrl}
                    alt=""
                    className="size-11 rounded-xl object-contain"
                    referrerPolicy="no-referrer"
                  />
                ) : (
                  <span className={cn("flex size-11 items-center justify-center rounded-xl", accent)}>
                    <Icon className="size-5" aria-hidden />
                  </span>
                )}
              </span>
              <span className="space-y-0.5">
                <span className="block text-sm font-medium text-slate-800">{tile.title}</span>
                {tile.subtitle ? (
                  <span className="block text-[11px] leading-snug text-slate-500">{tile.subtitle}</span>
                ) : null}
              </span>
            </a>
          </li>
        );
      })}
    </ul>
  );
}

export function AppMenuPageClient() {
  const query = useQuery({
    queryKey: ["public", "app-menu"],
    queryFn: fetchPublicAppMenu,
    staleTime: 60_000,
    retry: 1,
  });

  const groups = query.data?.groups ?? [];
  const ungrouped = query.data?.ungrouped ?? [];
  const gridColumns = query.data?.settings?.grid_columns ?? 4;
  const hasNamedGroups = groups.length > 0;
  const totalTiles =
    groups.reduce((sum, group) => sum + group.tiles.length, 0) + ungrouped.length;

  return (
    <div className="relative min-h-screen overflow-hidden bg-[#F8FAFC] text-slate-900">
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0"
        style={{
          backgroundImage: "radial-gradient(circle, #CBD5E1 1px, transparent 1px)",
          backgroundSize: "20px 20px",
          maskImage:
            "radial-gradient(ellipse 80% 70% at 50% 40%, black 20%, transparent 75%)",
          WebkitMaskImage:
            "radial-gradient(ellipse 80% 70% at 50% 40%, black 20%, transparent 75%)",
        }}
      />

      <div className="relative z-10 mx-auto flex min-h-screen max-w-5xl flex-col px-6 py-12 sm:px-10 sm:py-16">
        <header className="mb-10 text-center sm:mb-14">
          <h1 className="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">App Menu</h1>
          <p className="mt-2 text-sm text-slate-500">
            Choose a workspace or tool. Sign-in happens on the destination.
          </p>
        </header>

        {query.isLoading ? (
          <p className="text-center text-sm text-slate-500">Loading…</p>
        ) : null}

        {query.isError ? (
          <p className="text-center text-sm text-red-600">Unable to load App Menu. Try again shortly.</p>
        ) : null}

        {!query.isLoading && !query.isError && totalTiles === 0 ? (
          <p className="text-center text-sm text-slate-500">No apps are published yet.</p>
        ) : null}

        <div className="space-y-12">
          {groups.map((group) => (
            <section key={group.id} className="space-y-6">
              {hasNamedGroups ? (
                <h2 className="text-center text-sm font-medium tracking-tight text-slate-600">
                  {group.title}
                </h2>
              ) : null}
              <TileGrid tiles={group.tiles} columns={gridColumns} />
            </section>
          ))}

          {ungrouped.length > 0 ? (
            <section className="space-y-6">
              {hasNamedGroups ? (
                <h2 className="text-center text-sm font-medium tracking-tight text-slate-600">Other</h2>
              ) : null}
              <TileGrid tiles={ungrouped} columns={gridColumns} />
            </section>
          ) : null}
        </div>

        <footer className="mt-auto border-t border-slate-200/80 pt-6" />
      </div>
    </div>
  );
}
