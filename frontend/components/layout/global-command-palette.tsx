"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { ArrowRight, Search } from "lucide-react";

import { Spinner } from "@/components/ui/spinner";

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { useGlobalCommandPalette } from "@/hooks/use-global-command-palette";
import { fetchWorkspaceSearch } from "@/lib/api/modules/workspace-search-api";
import { workspaceSearchResultsToCommandItems } from "@/lib/navigation/workspace-entity-search";
import {
  buildWorkspaceCommandIndex,
  matchesWorkspaceCommandQuery,
  readWorkspaceCommandRecent,
  rememberWorkspaceCommandItem,
  type WorkspaceCommandItem,
} from "@/lib/navigation/workspace-command-index";
import { formWorkspacesToCommandItems } from "@/lib/navigation/form-workspace-command-items";
import {
  EAPPROVAL_FORM_WORKSPACES_QUERY_KEY,
  fetchEApprovalFormWorkspaces,
} from "@/lib/api/modules/e-approval-api";
import { permissions, hasPermission } from "@/lib/rbac/permissions";
import { resolveEnabledModulesForUser, TENANT_MODULE_LABELS } from "@/lib/tenant/enabled-modules";
import { cn } from "@/lib/utils";
import { eApprovalStatusBadgeClass } from "@/modules/e-approval/status-display";
import { useAuthStore } from "@/stores/auth-store";

function entityStatusChipClass(module: string | undefined, status: string | null | undefined): string {
  if (module === "e_approval" && status) {
    return eApprovalStatusBadgeClass(status, "submission");
  }

  return "border-border bg-muted/50 text-muted-foreground";
}

const ENTITY_SEARCH_DEBOUNCE_MS = 350;
const ENTITY_SEARCH_MIN_CHARS = 2;
const ENTITY_SEARCH_LIMIT = 4;

type PaletteSection = {
  label: string;
  items: WorkspaceCommandItem[];
};

function isMacPlatform(): boolean {
  if (typeof navigator === "undefined") {
    return false;
  }

  return /mac/i.test(navigator.platform) || /mac/i.test(navigator.userAgent);
}

const ENTITY_MODULE_ORDER = [
  "documents",
  "document_register",
  "sites",
  "e_approval",
  "ticketing",
  "project_one",
  "procurement_one",
  "finance_one",
  "team_access",
];

function groupEntityItemsByModule(items: WorkspaceCommandItem[]): PaletteSection[] {
  const groups = new Map<string, WorkspaceCommandItem[]>();

  for (const item of items) {
    const moduleKey = item.module ?? "other";
    const list = groups.get(moduleKey) ?? [];
    list.push(item);
    groups.set(moduleKey, list);
  }

  const sortedKeys = [
    ...ENTITY_MODULE_ORDER.filter((key) => groups.has(key)),
    ...Array.from(groups.keys()).filter((key) => !ENTITY_MODULE_ORDER.includes(key)),
  ];

  return sortedKeys.map((moduleKey) => ({
    label: `Find · ${TENANT_MODULE_LABELS[moduleKey] ?? moduleKey.replace(/_/g, " ")}`,
    items: groups.get(moduleKey)!,
  }));
}

export function GlobalCommandPalette() {
  const router = useRouter();
  const { open, setOpen } = useGlobalCommandPalette();
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);

  const scopedUser = useMemo(() => {
    if (!user || !activeTenantId) {
      return user;
    }
    return { ...user, permissions: effectivePermissions() };
  }, [activeTenantId, effectivePermissions, user]);

  const enabledModules = useMemo(
    () => resolveEnabledModulesForUser(user, activeTenantId),
    [activeTenantId, user],
  );

  const index = useMemo(
    () => buildWorkspaceCommandIndex(scopedUser, enabledModules),
    [enabledModules, scopedUser],
  );

  const canViewWorkspaces = useMemo(
    () => hasPermission(scopedUser, [permissions.eApprovalView, permissions.eApprovalSubmissionsView]),
    [scopedUser],
  );

  const workspacesQuery = useQuery({
    queryKey: [...EAPPROVAL_FORM_WORKSPACES_QUERY_KEY],
    queryFn: fetchEApprovalFormWorkspaces,
    enabled: open && canViewWorkspaces,
    staleTime: 60_000,
  });

  const workspaceNavigateItems = useMemo(
    () => formWorkspacesToCommandItems(workspacesQuery.data ?? []),
    [workspacesQuery.data],
  );

  const [query, setQuery] = useState("");
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [recent, setRecent] = useState<WorkspaceCommandItem[]>([]);
  const [entityResults, setEntityResults] = useState<WorkspaceCommandItem[]>([]);
  const [entitySearchLoading, setEntitySearchLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);
  const entitySearchRequestId = useRef(0);

  const allLookupItems = useMemo(
    () => [...index.actions, ...index.navigate, ...workspaceNavigateItems],
    [index.actions, index.navigate, workspaceNavigateItems],
  );

  useEffect(() => {
    if (open) {
      setRecent(readWorkspaceCommandRecent(allLookupItems));
      setQuery("");
      setSelectedIndex(0);
      setEntityResults([]);
      setEntitySearchLoading(false);
      window.setTimeout(() => inputRef.current?.focus(), 0);
    }
  }, [allLookupItems, open]);

  useEffect(() => {
    const trimmed = query.trim();

    if (!open || trimmed.length < ENTITY_SEARCH_MIN_CHARS) {
      setEntityResults([]);
      setEntitySearchLoading(false);
      return;
    }

    const requestId = entitySearchRequestId.current + 1;
    entitySearchRequestId.current = requestId;
    const controller = new AbortController();
    setEntitySearchLoading(true);

    const timer = window.setTimeout(() => {
      fetchWorkspaceSearch(trimmed, ENTITY_SEARCH_LIMIT, controller.signal)
        .then((results) => {
          if (entitySearchRequestId.current !== requestId) {
            return;
          }

          setEntityResults(workspaceSearchResultsToCommandItems(results));
        })
        .catch((error: unknown) => {
          if (entitySearchRequestId.current !== requestId) {
            return;
          }
          if (
            (typeof error === "object" &&
              error !== null &&
              "code" in error &&
              (error as { code?: string }).code === "ERR_CANCELED") ||
            (typeof DOMException !== "undefined" &&
              error instanceof DOMException &&
              error.name === "AbortError")
          ) {
            return;
          }

          setEntityResults([]);
        })
        .finally(() => {
          if (entitySearchRequestId.current === requestId) {
            setEntitySearchLoading(false);
          }
        });
    }, ENTITY_SEARCH_DEBOUNCE_MS);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [open, query]);

  const sections = useMemo((): PaletteSection[] => {
    const trimmed = query.trim();
    const recentMatches = recent.filter((item) => matchesWorkspaceCommandQuery(item, trimmed));
    const actionMatches = index.actions.filter((item) => matchesWorkspaceCommandQuery(item, trimmed));
    const navigateMatches = [
      ...index.navigate.filter((item) => matchesWorkspaceCommandQuery(item, trimmed)),
      ...workspaceNavigateItems.filter((item) => matchesWorkspaceCommandQuery(item, trimmed)),
    ];
    const entityMatches = trimmed.length >= ENTITY_SEARCH_MIN_CHARS ? entityResults : [];

    if (!trimmed) {
      const output: PaletteSection[] = [];
      if (recentMatches.length > 0) {
        output.push({ label: "Recent", items: recentMatches });
      }
      if (actionMatches.length > 0) {
        output.push({ label: "Do", items: actionMatches });
      }
      if (navigateMatches.length > 0) {
        output.push({ label: "Go to", items: navigateMatches });
      }
      return output;
    }

    const output: PaletteSection[] = [];
    if (entityMatches.length > 0) {
      output.push(...groupEntityItemsByModule(entityMatches));
    }
    if (actionMatches.length > 0) {
      output.push({ label: "Do", items: actionMatches });
    }
    if (navigateMatches.length > 0) {
      output.push({ label: "Go to", items: navigateMatches });
    }
    if (recentMatches.length > 0) {
      output.push({ label: "Recent", items: recentMatches });
    }
    return output;
  }, [entityResults, index.actions, index.navigate, query, recent, workspaceNavigateItems]);

  const flatItems = useMemo(() => sections.flatMap((section) => section.items), [sections]);

  useEffect(() => {
    setSelectedIndex(0);
  }, [query]);

  useEffect(() => {
    if (selectedIndex >= flatItems.length) {
      setSelectedIndex(Math.max(0, flatItems.length - 1));
    }
  }, [flatItems.length, selectedIndex]);

  const shortcutLabel = isMacPlatform() ? "⌘K" : "Ctrl+K";

  const runItem = (item: WorkspaceCommandItem) => {
    rememberWorkspaceCommandItem(item);
    setRecent(readWorkspaceCommandRecent(allLookupItems));
    setOpen(false);
    router.push(item.href);
  };

  const onInputKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      setSelectedIndex((current) => Math.min(current + 1, Math.max(0, flatItems.length - 1)));
      return;
    }

    if (event.key === "ArrowUp") {
      event.preventDefault();
      setSelectedIndex((current) => Math.max(current - 1, 0));
      return;
    }

    if (event.key === "Enter" && flatItems[selectedIndex]) {
      event.preventDefault();
      runItem(flatItems[selectedIndex]!);
    }
  };

  let runningIndex = -1;

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogContent showCloseButton className="gap-0 p-0">
        <DialogHeader className="gap-0 border-b border-border px-4 py-3">
          <DialogTitle className="sr-only">Search TowerOS</DialogTitle>
          <DialogDescription className="sr-only">
            Jump to modules, pages, and quick actions across your tenant workspace.
          </DialogDescription>
          <div className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              ref={inputRef}
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              onKeyDown={onInputKeyDown}
              placeholder="Search records, modules, pages, and actions…"
              className="h-10 border-0 bg-transparent pr-16 pl-9 shadow-none focus-visible:ring-0"
            />
            <kbd className="pointer-events-none absolute top-1/2 right-2 hidden -translate-y-1/2 rounded border border-border bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground sm:inline">
              {shortcutLabel}
            </kbd>
          </div>
        </DialogHeader>

        <div ref={listRef} className="max-h-[min(60vh,420px)] overflow-y-auto py-2">
          {entitySearchLoading && query.trim().length >= ENTITY_SEARCH_MIN_CHARS ? (
            <div className="flex items-center gap-2 px-4 py-3 text-sm text-muted-foreground">
              <Spinner />
              Searching records…
            </div>
          ) : null}
          {flatItems.length === 0 && !entitySearchLoading ? (
            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
              {query.trim().length >= ENTITY_SEARCH_MIN_CHARS
                ? "No matches. Try a document title, site code, ticket, or page name."
                : "No matches. Try a module name, page, or action."}
            </p>
          ) : flatItems.length > 0 ? (
            sections.map((section) => (
              <div key={section.label} className="px-2 py-1">
                <p className="px-2 py-1.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                  {section.label}
                </p>
                <ul>
                  {section.items.map((item) => {
                    runningIndex += 1;
                    const itemIndex = runningIndex;
                    const Icon = item.icon;
                    const isSelected = itemIndex === selectedIndex;

                    return (
                      <li key={`${section.label}:${item.id}:${item.title}`}>
                        <button
                          type="button"
                          className={cn(
                            "flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm transition-colors",
                            isSelected ? "bg-muted text-foreground" : "hover:bg-muted/60",
                          )}
                          onMouseEnter={() => setSelectedIndex(itemIndex)}
                          onClick={() => runItem(item)}
                        >
                          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-border bg-card text-muted-foreground">
                            <Icon className="h-4 w-4" />
                          </span>
                          <span className="min-w-0 flex-1">
                            <span className="flex min-w-0 items-center gap-2">
                              <span className="truncate font-medium">{item.title}</span>
                              {item.statusLabel ? (
                                <span
                                  className={cn(
                                    "shrink-0 rounded-md border px-1.5 py-0.5 text-[10px] font-medium leading-none",
                                    entityStatusChipClass(item.module, item.status),
                                  )}
                                >
                                  {item.statusLabel}
                                </span>
                              ) : null}
                            </span>
                            {item.description ? (
                              <span className="block truncate text-xs text-muted-foreground">{item.description}</span>
                            ) : null}
                          </span>
                          <ArrowRight className="h-3.5 w-3.5 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100" />
                        </button>
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))
          ) : null}
        </div>

        <div className="flex items-center justify-between border-t border-border px-4 py-2 text-[11px] text-muted-foreground">
          <span>Find records or jump to modules and workflows</span>
          <span className="hidden sm:inline">
            <kbd className="rounded border border-border px-1">↑↓</kbd> move ·{" "}
            <kbd className="rounded border border-border px-1">Enter</kbd> open
          </span>
        </div>
      </DialogContent>
    </Dialog>
  );
}
