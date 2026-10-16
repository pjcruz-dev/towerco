"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { FileSpreadsheet, History, Pencil, Plus, Trash2 } from "lucide-react";
import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from "react";

import { useNavigationLayoutStore } from "@/stores/navigation-layout-store";

import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import {
  listRecordActionHistory,
  listWorkspaceActionHistory,
  redoRecordAction,
  reapplyRecordAction,
  revertRecordAction,
  undoRecordAction,
  type RecordActionHistoryRow,
} from "@/lib/api/modules/dynamic-entities-api";
import { notify } from "@/lib/ui/notify";
import { cn } from "@/lib/utils";

function formatWhen(iso: string | null): string {
  if (!iso) return "";
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return "";
  const minutes = Math.round((Date.now() - then) / 60000);
  if (minutes < 1) return "just now";
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  if (days < 14) return `${days}d ago`;
  return new Date(iso).toLocaleDateString(undefined, { month: "numeric", day: "numeric", year: "numeric" });
}

function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;
  const tag = target.tagName;
  if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") return true;
  return target.isContentEditable;
}

function RowIcon({ kind }: { kind: string }) {
  const className = "size-3.5 text-muted-foreground";
  if (kind === "deleted") return <Trash2 className={className} />;
  if (kind === "updated") return <Pencil className={className} />;
  if (kind === "imported") return <FileSpreadsheet className={className} />;
  return <Plus className={className} />;
}

type ActionHistorySlot = {
  entitySlug: string;
  entityLabel: string;
  canRevert: boolean;
  onChanged?: () => void;
};

let actionHistorySlot: ActionHistorySlot | null = null;
const actionHistoryListeners = new Set<() => void>();

function publishActionHistorySlot(next: ActionHistorySlot | null) {
  actionHistorySlot = next;
  actionHistoryListeners.forEach((listener) => listener());
}

function useActionHistorySlot(): ActionHistorySlot | null {
  return useSyncExternalStore(
    (listener) => {
      actionHistoryListeners.add(listener);
      return () => actionHistoryListeners.delete(listener);
    },
    () => actionHistorySlot,
    () => null,
  );
}

export function RecordActionHistoryButton({
  entitySlug,
  entityLabel,
  canRevert,
  onChanged,
  placement = "page",
}: {
  entitySlug: string;
  entityLabel: string;
  canRevert: boolean;
  onChanged?: () => void;
  /** Page header, or the navbar strip beside Home. */
  placement?: "page" | "nav";
}) {
  const layout = useNavigationLayoutStore((s) => s.layout);
  const hydrated = useNavigationLayoutStore((s) => s.hydrated);
  const navbar = hydrated && layout === "navbar";
  const onChangedRef = useRef(onChanged);
  onChangedRef.current = onChanged;

  useEffect(() => {
    if (placement !== "page" || !navbar) return;
    const current: ActionHistorySlot = {
      entitySlug,
      entityLabel,
      canRevert,
      onChanged: () => onChangedRef.current?.(),
    };
    publishActionHistorySlot(current);
    return () => {
      if (actionHistorySlot === current) publishActionHistorySlot(null);
    };
  }, [canRevert, entityLabel, entitySlug, navbar, placement]);

  const visible = placement === "nav" ? navbar : !navbar;
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [scope, setScope] = useState<"mine" | "everyone">("mine");
  const [busyId, setBusyId] = useState<string | null>(null);

  const history = useQuery({
    queryKey: ["record-action-history", entitySlug, scope],
    queryFn: () => listRecordActionHistory(entitySlug, scope),
    enabled: open,
  });

  const run = useCallback(
    async (id: string, label: string, action: () => Promise<unknown>) => {
      if (!canRevert || busyId) return;
      setBusyId(id);
      try {
        await action();
        await queryClient.invalidateQueries({ queryKey: ["record-action-history", entitySlug] });
        onChanged?.();
        notify.success(label);
      } catch (error) {
        notify.error("Could not update history", getErrorMessage(error));
      } finally {
        setBusyId(null);
      }
    },
    [busyId, canRevert, entitySlug, onChanged, queryClient],
  );

  useEffect(() => {
    if (!visible) return;
    function onKey(event: KeyboardEvent) {
      if (!(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== "z") return;
      if (event.altKey || isTypingTarget(event.target) || !canRevert) return;
      event.preventDefault();
      if (event.shiftKey) {
        void run("redo", "Put back", () => redoRecordAction(entitySlug));
      } else {
        void run("undo", "Reverted", () => undoRecordAction(entitySlug));
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [canRevert, entitySlug, run, visible]);

  const rows = history.data ?? [];

  if (!visible) return null;

  return (
    <>
      <Button
        type="button"
        size="icon-sm"
        variant={placement === "nav" ? "ghost" : "outline"}
        className={
          placement === "nav"
            ? "size-7 text-slate-300 hover:bg-white/10 hover:text-white"
            : undefined
        }
        aria-label={`Action history for ${entityLabel}`}
        title="Action history"
        onClick={() => setOpen(true)}
      >
        <History />
      </Button>
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
          <SheetHeader className="border-b border-border pr-10">
            <SheetTitle className="flex items-center gap-2">
              <History className="size-4 text-muted-foreground" />
              Action History
            </SheetTitle>
            <SheetDescription>Revert an import, an edit or a delete</SheetDescription>
          </SheetHeader>
          <div className="flex gap-2 px-4 py-3">
            <Button
              type="button"
              size="sm"
              className="flex-1"
              variant={scope === "mine" ? "default" : "outline"}
              onClick={() => setScope("mine")}
            >
              My actions
            </Button>
            <Button
              type="button"
              size="sm"
              className="flex-1"
              variant={scope === "everyone" ? "default" : "outline"}
              onClick={() => setScope("everyone")}
            >
              Everyone
            </Button>
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
            {history.isLoading ? (
              <p className="px-2 py-6 text-sm text-muted-foreground">Loading history…</p>
            ) : history.isError ? (
              <p className="px-2 py-6 text-sm text-destructive">{getErrorMessage(history.error)}</p>
            ) : rows.length === 0 ? (
              <p className="px-2 py-6 text-sm text-muted-foreground">
                No actions yet. Edits, imports, and deletes show up here.
              </p>
            ) : (
              <ul className="space-y-1">
                {rows.map((row) => (
                  <HistoryRow
                    key={row.id}
                    row={row}
                    busy={busyId === row.id}
                    canRevert={canRevert && !busyId}
                    onRevert={() => void run(row.id, "Reverted", () => revertRecordAction(entitySlug, row.id))}
                    onReapply={() => void run(row.id, "Put back", () => reapplyRecordAction(entitySlug, row.id))}
                  />
                ))}
              </ul>
            )}
          </div>
          <SheetFooter className="border-t border-border">
            <p className="text-xs text-muted-foreground">
              Ctrl+Z reverts your last action · Ctrl+Shift+Z puts it back
            </p>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </>
  );
}

/** Navbar layout only. Renders beside Home when the open page registered a history target. */
export function RecordActionHistoryNavButton() {
  const slot = useActionHistorySlot();
  if (!slot) return null;
  return <RecordActionHistoryButton {...slot} placement="nav" />;
}

/** Header control beside the notification bell. Covers dynamic entities, assets, and ticketing. */
export function WorkspaceActionHistoryButton() {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [scope, setScope] = useState<"mine" | "everyone">("mine");
  const [busyId, setBusyId] = useState<string | null>(null);

  const history = useQuery({
    queryKey: ["workspace-action-history", scope],
    queryFn: () => listWorkspaceActionHistory(scope),
    enabled: open,
  });

  const run = useCallback(
    async (id: string, slug: string, label: string, action: () => Promise<unknown>) => {
      if (busyId || slug === "") return;
      setBusyId(id);
      try {
        await action();
        await queryClient.invalidateQueries({ queryKey: ["workspace-action-history"] });
        await queryClient.invalidateQueries({ queryKey: ["record-action-history", slug] });
        window.dispatchEvent(new CustomEvent("toweros:action-history-changed"));
        notify.success(label);
      } catch (error) {
        notify.error("Could not update history", getErrorMessage(error));
      } finally {
        setBusyId(null);
      }
    },
    [busyId, queryClient],
  );

  const rows = history.data ?? [];

  return (
    <>
      <Button
        type="button"
        size="icon-sm"
        variant="outline"
        aria-label="Action history"
        title="Action history"
        onClick={() => setOpen(true)}
      >
        <History className="h-4 w-4" />
      </Button>
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-md">
          <SheetHeader className="border-b border-border pr-10">
            <SheetTitle className="flex items-center gap-2">
              <History className="size-4 text-muted-foreground" />
              Action history
            </SheetTitle>
            <SheetDescription>
              Changes across dynamic entities, assets, and ticketing. Revert one when you need the previous values back.
            </SheetDescription>
          </SheetHeader>
          <div className="flex gap-2 px-4 py-3">
            <Button
              type="button"
              size="sm"
              className="flex-1"
              variant={scope === "mine" ? "default" : "outline"}
              onClick={() => setScope("mine")}
            >
              My actions
            </Button>
            <Button
              type="button"
              size="sm"
              className="flex-1"
              variant={scope === "everyone" ? "default" : "outline"}
              onClick={() => setScope("everyone")}
            >
              Everyone
            </Button>
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
            {history.isLoading ? (
              <p className="px-2 py-6 text-sm text-muted-foreground">Loading history…</p>
            ) : history.isError ? (
              <p className="px-2 py-6 text-sm text-destructive">{getErrorMessage(history.error)}</p>
            ) : rows.length === 0 ? (
              <p className="px-2 py-6 text-sm text-muted-foreground">
                No actions yet. Edits, imports, and deletes show up here.
              </p>
            ) : (
              <ul className="space-y-1">
                {rows.map((row) => (
                  <HistoryRow
                    key={row.id}
                    row={row}
                    busy={busyId === row.id}
                    canRevert={!busyId}
                    onRevert={() =>
                      void run(row.id, row.entity_slug ?? "", "Reverted", () =>
                        revertRecordAction(row.entity_slug ?? "", row.id),
                      )
                    }
                    onReapply={() =>
                      void run(row.id, row.entity_slug ?? "", "Put back", () =>
                        reapplyRecordAction(row.entity_slug ?? "", row.id),
                      )
                    }
                  />
                ))}
              </ul>
            )}
          </div>
        </SheetContent>
      </Sheet>
    </>
  );
}

function HistoryRow({
  row,
  busy,
  canRevert,
  onRevert,
  onReapply,
}: {
  row: RecordActionHistoryRow;
  busy: boolean;
  canRevert: boolean;
  onRevert: () => void;
  onReapply: () => void;
}) {
  const reverted = row.status === "reverted";
  const disabled = !canRevert || !row.reversible || busy;

  return (
    <li className="flex items-start gap-2 rounded-lg px-2 py-2 hover:bg-muted/60">
      <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-muted">
        <RowIcon kind={row.kind} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium text-foreground">
          {row.title}
          {reverted ? (
            <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
              Reverted
            </span>
          ) : null}
        </p>
        <p className="text-xs text-muted-foreground">{row.detail}</p>
        <p className="text-xs text-muted-foreground">
          {row.actor_name} · {formatWhen(row.created_at)}
        </p>
      </div>
      <Button
        type="button"
        size="sm"
        variant="ghost"
        className={cn("shrink-0", !reverted && "text-destructive hover:text-destructive")}
        disabled={disabled}
        title={row.reversible ? undefined : (row.blocked_reason ?? undefined)}
        onClick={reverted ? onReapply : onRevert}
      >
        {busy ? "Working…" : reverted ? "Re-apply" : "Revert"}
      </Button>
    </li>
  );
}
