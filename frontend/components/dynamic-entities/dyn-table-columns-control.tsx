"use client";

import {
  DndContext,
  DragOverlay,
  PointerSensor,
  closestCorners,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragOverEvent,
  type DragStartEvent,
  type UniqueIdentifier,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import {
  Eye,
  EyeOff,
  GripVertical,
  Pin,
  Save,
  Search,
  Settings2,
  Star,
} from "lucide-react";
import { useEffect, useMemo, useState, type ReactNode } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { useLocalStorageJsonState } from "@/hooks/use-local-storage-json-state";
import { cn } from "@/lib/utils";

export type DynTableColumnOption = {
  id: string;
  label: string;
  /** Used when no saved preference exists for this column. */
  defaultVisible: boolean;
};

export type DynTableColumnPrefs = {
  order: string[];
  hidden: string[];
  /** Max columns shown in the table (default 20, max 60). */
  maxColumns: number;
};

const DEFAULT_MAX = 20;
const ABS_MAX = 60;

function isColumnPrefs(value: unknown): value is DynTableColumnPrefs {
  if (typeof value !== "object" || value === null || Array.isArray(value)) return false;
  const v = value as Record<string, unknown>;
  const maxOk =
    v.maxColumns === undefined || (typeof v.maxColumns === "number" && Number.isFinite(v.maxColumns));
  return (
    Array.isArray(v.order) &&
    v.order.every((id) => typeof id === "string") &&
    Array.isArray(v.hidden) &&
    v.hidden.every((id) => typeof id === "string") &&
    maxOk
  );
}

function clampMax(n: unknown): number {
  const num = typeof n === "number" ? n : Number(n);
  if (!Number.isFinite(num)) return DEFAULT_MAX;
  return Math.min(ABS_MAX, Math.max(1, Math.round(num)));
}

export function mergePrefs(options: DynTableColumnOption[], prefs: DynTableColumnPrefs): DynTableColumnPrefs {
  const known = new Set(options.map((o) => o.id));
  const order: string[] = [];
  for (const id of prefs.order) {
    if (known.has(id) && !order.includes(id)) order.push(id);
  }
  for (const opt of options) {
    if (!order.includes(opt.id)) order.push(opt.id);
  }
  return {
    order,
    hidden: prefs.hidden.filter((id) => known.has(id)),
    maxColumns: clampMax(prefs.maxColumns ?? DEFAULT_MAX),
  };
}

export function defaultPrefs(options: DynTableColumnOption[]): DynTableColumnPrefs {
  return {
    order: options.map((o) => o.id),
    hidden: options.filter((o) => !o.defaultVisible).map((o) => o.id),
    maxColumns: DEFAULT_MAX,
  };
}

/**
 * Resolve visible columns in display order from catalog + user prefs.
 */
export function resolveDynTableColumns(
  options: DynTableColumnOption[],
  prefs: DynTableColumnPrefs,
): DynTableColumnOption[] {
  const merged = mergePrefs(options, prefs);
  const byId = new Map(options.map((o) => [o.id, o]));
  const hidden = new Set(merged.hidden);
  const visible = merged.order
    .map((id) => byId.get(id))
    .filter((opt): opt is DynTableColumnOption => Boolean(opt))
    .filter((opt) => !hidden.has(opt.id));
  return visible.slice(0, merged.maxColumns);
}

export function useDynTableColumnPrefs(storageKey: string | null, options: DynTableColumnOption[]) {
  const defaults = useMemo(() => defaultPrefs(options), [options]);
  const [raw, setRaw] = useLocalStorageJsonState<DynTableColumnPrefs>(
    storageKey,
    defaults,
    isColumnPrefs,
  );

  const prefs = useMemo(() => mergePrefs(options, { ...raw, maxColumns: raw.maxColumns ?? DEFAULT_MAX }), [
    options,
    raw,
  ]);
  const visible = useMemo(() => resolveDynTableColumns(options, prefs), [options, prefs]);

  function savePrefs(next: DynTableColumnPrefs) {
    setRaw(mergePrefs(options, next));
  }

  /** Reorder visible columns (e.g. drag header left/right in the table). */
  function reorderVisible(activeId: string, overId: string) {
    if (activeId === overId) return;
    setRaw((prev) => {
      const merged = mergePrefs(options, { ...prev, maxColumns: prev.maxColumns ?? DEFAULT_MAX });
      const hidden = new Set(merged.hidden);
      const visibleIds = merged.order.filter((id) => !hidden.has(id)).slice(0, merged.maxColumns);
      const oldIndex = visibleIds.indexOf(activeId);
      const newIndex = visibleIds.indexOf(overId);
      if (oldIndex < 0 || newIndex < 0) return merged;
      const nextVisible = arrayMove(visibleIds, oldIndex, newIndex);
      const nextVisibleSet = new Set(nextVisible);
      const hiddenOrdered = merged.order.filter((id) => !nextVisibleSet.has(id));
      return {
        ...merged,
        order: [...nextVisible, ...hiddenOrdered],
      };
    });
  }

  function reset() {
    setRaw(defaultPrefs(options));
  }

  return { prefs, visible, savePrefs, reorderVisible, reset };
}

type DynTableColumnsControlProps = {
  options: DynTableColumnOption[];
  prefs: DynTableColumnPrefs;
  onSave: (prefs: DynTableColumnPrefs) => void;
};

const VISIBLE_LIST = "visible";
const HIDDEN_LIST = "hidden";

/**
 * Gear button + Table View Settings dialog (show/hide + drag reorder).
 */
export function DynTableColumnsControl({ options, prefs, onSave }: DynTableColumnsControlProps) {
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<DynTableColumnPrefs>(() => mergePrefs(options, prefs));
  const [filter, setFilter] = useState("");
  const [activeId, setActiveId] = useState<UniqueIdentifier | null>(null);
  const [pinned, setPinned] = useState<Set<string>>(() => new Set());
  const [starred, setStarred] = useState<Set<string>>(() => new Set());

  useEffect(() => {
    if (!open) return;
    setDraft(mergePrefs(options, prefs));
    setFilter("");
    setActiveId(null);
  }, [open, options, prefs]);

  const byId = useMemo(() => new Map(options.map((o) => [o.id, o])), [options]);
  const hiddenSet = useMemo(() => new Set(draft.hidden), [draft.hidden]);

  const visibleIds = useMemo(
    () => draft.order.filter((id) => byId.has(id) && !hiddenSet.has(id)),
    [draft.order, byId, hiddenSet],
  );
  const hiddenIds = useMemo(
    () => draft.order.filter((id) => byId.has(id) && hiddenSet.has(id)),
    [draft.order, byId, hiddenSet],
  );

  const q = filter.trim().toLowerCase();
  const visibleFiltered = useMemo(
    () =>
      visibleIds.filter((id) => {
        if (!q) return true;
        return (byId.get(id)?.label ?? id).toLowerCase().includes(q);
      }),
    [visibleIds, byId, q],
  );
  const hiddenFiltered = useMemo(
    () =>
      hiddenIds.filter((id) => {
        if (!q) return true;
        return (byId.get(id)?.label ?? id).toLowerCase().includes(q);
      }),
    [hiddenIds, byId, q],
  );

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  function findList(id: UniqueIdentifier): typeof VISIBLE_LIST | typeof HIDDEN_LIST | null {
    const s = String(id);
    if (s === VISIBLE_LIST || s === HIDDEN_LIST) return s;
    if (visibleIds.includes(s)) return VISIBLE_LIST;
    if (hiddenIds.includes(s)) return HIDDEN_LIST;
    return null;
  }

  function onDragStart(event: DragStartEvent) {
    setActiveId(event.active.id);
  }

  function onDragOver(event: DragOverEvent) {
    const { active, over } = event;
    if (!over) return;
    const from = findList(active.id);
    const to = findList(over.id);
    if (!from || !to || from === to) return;

    const activeStr = String(active.id);
    setDraft((prev) => {
      const hidden = new Set(prev.hidden);
      if (to === HIDDEN_LIST) hidden.add(activeStr);
      else hidden.delete(activeStr);

      const order = prev.order.filter((id) => id !== activeStr);
      const overStr = String(over.id);
      let insertAt = order.length;
      if (overStr !== VISIBLE_LIST && overStr !== HIDDEN_LIST) {
        const idx = order.indexOf(overStr);
        if (idx >= 0) insertAt = idx;
      } else if (to === VISIBLE_LIST) {
        // Drop at end of visible block: after last visible in order
        const lastVisible = [...order].reverse().find((id) => !hidden.has(id));
        insertAt = lastVisible ? order.indexOf(lastVisible) + 1 : 0;
      }
      order.splice(insertAt, 0, activeStr);
      return { ...prev, order, hidden: Array.from(hidden) };
    });
  }

  function onDragEnd(event: DragEndEvent) {
    const { active, over } = event;
    setActiveId(null);
    if (!over) return;

    const from = findList(active.id);
    const to = findList(over.id);
    if (!from || !to) return;

    const activeStr = String(active.id);
    const overStr = String(over.id);

    if (from === to && overStr !== VISIBLE_LIST && overStr !== HIDDEN_LIST) {
      setDraft((prev) => {
        const list = from === VISIBLE_LIST
          ? prev.order.filter((id) => !prev.hidden.includes(id))
          : prev.order.filter((id) => prev.hidden.includes(id));
        const oldIndex = list.indexOf(activeStr);
        const newIndex = list.indexOf(overStr);
        if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) return prev;
        const nextList = arrayMove(list, oldIndex, newIndex);
        // Rebuild full order: keep relative positions of the other list
        const other = from === VISIBLE_LIST
          ? prev.order.filter((id) => prev.hidden.includes(id))
          : prev.order.filter((id) => !prev.hidden.includes(id));
        const order =
          from === VISIBLE_LIST ? [...nextList, ...other] : [...other, ...nextList];
        // Prefer interleaving: replace only the moved list segment in original order
        const rebuilt: string[] = [];
        let i = 0;
        for (const id of prev.order) {
          const inList =
            from === VISIBLE_LIST ? !prev.hidden.includes(id) : prev.hidden.includes(id);
          if (inList) {
            rebuilt.push(nextList[i]!);
            i += 1;
          } else {
            rebuilt.push(id);
          }
        }
        return { ...prev, order: rebuilt.length === prev.order.length ? rebuilt : order };
      });
    }
  }

  function hideAll() {
    setDraft((prev) => ({
      ...prev,
      hidden: prev.order.filter((id) => byId.has(id)),
    }));
  }

  function showAll() {
    setDraft((prev) => ({ ...prev, hidden: [] }));
  }

  function hideOne(id: string) {
    setDraft((prev) => ({
      ...prev,
      hidden: prev.hidden.includes(id) ? prev.hidden : [...prev.hidden, id],
    }));
  }

  function showOne(id: string) {
    setDraft((prev) => ({
      ...prev,
      hidden: prev.hidden.filter((h) => h !== id),
    }));
  }

  function handleSave() {
    const next = mergePrefs(options, draft);
    // If over max, hide overflow from the end of visible list
    const visible = next.order.filter((id) => !next.hidden.includes(id));
    if (visible.length > next.maxColumns) {
      const keep = new Set(visible.slice(0, next.maxColumns));
      next.hidden = next.order.filter((id) => !keep.has(id));
    }
    onSave(next);
    setOpen(false);
  }

  const activeOpt = activeId ? byId.get(String(activeId)) : null;
  const overLimit = visibleIds.length > draft.maxColumns;

  return (
    <>
      <Button
        type="button"
        size="icon-sm"
        variant="outline"
        className="size-9 shrink-0"
        aria-label="Table view settings"
        title="Table view settings"
        onClick={() => setOpen(true)}
      >
        <Settings2 className="size-4" />
      </Button>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="w-[min(calc(100vw-2rem),720px)]" showCloseButton>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <Settings2 className="size-4 text-muted-foreground" />
              Table View Settings
            </DialogTitle>
            <DialogDescription>
              Drag and drop fields to reorder them or move them between lists to show/hide columns.
            </DialogDescription>
          </DialogHeader>

          <DialogBody className="space-y-4">
            <div className="flex flex-wrap items-end gap-3">
              <div className="space-y-1">
                <label className="text-xs font-medium text-muted-foreground" htmlFor="dyn-col-max">
                  Columns this table can show
                </label>
                <Input
                  id="dyn-col-max"
                  type="number"
                  min={1}
                  max={ABS_MAX}
                  className="h-9 w-24"
                  value={draft.maxColumns}
                  onChange={(e) =>
                    setDraft((prev) => ({ ...prev, maxColumns: clampMax(e.target.value) }))
                  }
                />
              </div>
              <p className="pb-2 text-xs text-muted-foreground">default {DEFAULT_MAX}, up to {ABS_MAX}.</p>
              {overLimit ? (
                <p className="pb-2 text-xs text-amber-700 dark:text-amber-400">
                  {visibleIds.length} visible — save will keep the first {draft.maxColumns}.
                </p>
              ) : null}
            </div>

            <div className="relative">
              <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
              <Input
                className="h-9 pl-8"
                placeholder="Filter fields by name…"
                value={filter}
                onChange={(e) => setFilter(e.target.value)}
              />
            </div>

            <DndContext
              sensors={sensors}
              collisionDetection={closestCorners}
              onDragStart={onDragStart}
              onDragOver={onDragOver}
              onDragEnd={onDragEnd}
              onDragCancel={() => setActiveId(null)}
            >
              <div className="grid gap-3 md:grid-cols-[1fr_auto_1fr] md:items-stretch">
                <ColumnListPanel
                  id={VISIBLE_LIST}
                  title="Visible"
                  titleIcon={<Eye className="size-3.5 text-emerald-600" />}
                  actionLabel="Hide All ›"
                  onAction={hideAll}
                  emptyLabel="No visible columns"
                  ids={visibleFiltered}
                  byId={byId}
                  pinned={pinned}
                  starred={starred}
                  onTogglePin={(id) =>
                    setPinned((prev) => {
                      const next = new Set(prev);
                      if (next.has(id)) next.delete(id);
                      else next.add(id);
                      return next;
                    })
                  }
                  onToggleStar={(id) =>
                    setStarred((prev) => {
                      const next = new Set(prev);
                      if (next.has(id)) next.delete(id);
                      else next.add(id);
                      return next;
                    })
                  }
                  onQuickMove={hideOne}
                  quickMoveTitle="Hide column"
                />

                <div className="flex flex-row items-center justify-center gap-2 md:flex-col">
                  <Button type="button" size="sm" variant="outline" className="min-w-16" onClick={showAll}>
                    ‹ All
                  </Button>
                  <Button type="button" size="sm" variant="outline" className="min-w-16" onClick={hideAll}>
                    All ›
                  </Button>
                </div>

                <ColumnListPanel
                  id={HIDDEN_LIST}
                  title="Available / Hidden"
                  titleIcon={<EyeOff className="size-3.5 text-muted-foreground" />}
                  actionLabel="‹ Show All"
                  onAction={showAll}
                  emptyLabel="Drop fields here to hide"
                  ids={hiddenFiltered}
                  byId={byId}
                  pinned={pinned}
                  starred={starred}
                  onTogglePin={(id) =>
                    setPinned((prev) => {
                      const next = new Set(prev);
                      if (next.has(id)) next.delete(id);
                      else next.add(id);
                      return next;
                    })
                  }
                  onToggleStar={(id) =>
                    setStarred((prev) => {
                      const next = new Set(prev);
                      if (next.has(id)) next.delete(id);
                      else next.add(id);
                      return next;
                    })
                  }
                  onQuickMove={showOne}
                  quickMoveTitle="Show column"
                  dashedEmpty
                />
              </div>

              <DragOverlay>
                {activeOpt ? (
                  <div className="flex items-center gap-2 rounded-md border border-sky-300 bg-card px-2.5 py-2 text-sm shadow-lg ring-2 ring-sky-500/20">
                    <GripVertical className="size-3.5 text-muted-foreground" />
                    <span className="font-medium">{activeOpt.label}</span>
                  </div>
                ) : null}
              </DragOverlay>
            </DndContext>
          </DialogBody>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="button" onClick={handleSave} className="gap-1.5">
              <Save className="size-3.5" />
              Save Configuration
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function ColumnListPanel({
  id,
  title,
  titleIcon,
  actionLabel,
  onAction,
  emptyLabel,
  ids,
  byId,
  pinned,
  starred,
  onTogglePin,
  onToggleStar,
  onQuickMove,
  quickMoveTitle,
  dashedEmpty,
}: {
  id: string;
  title: string;
  titleIcon: ReactNode;
  actionLabel: string;
  onAction: () => void;
  emptyLabel: string;
  ids: string[];
  byId: Map<string, DynTableColumnOption>;
  pinned: Set<string>;
  starred: Set<string>;
  onTogglePin: (id: string) => void;
  onToggleStar: (id: string) => void;
  onQuickMove: (id: string) => void;
  quickMoveTitle: string;
  dashedEmpty?: boolean;
}) {
  const { setNodeRef, isOver } = useDroppable({ id });

  return (
    <div
      className={cn(
        "flex min-h-56 flex-col rounded-xl border border-border bg-card",
        isOver && "ring-2 ring-sky-500/30",
      )}
    >
      <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
        <div className="flex items-center gap-1.5 text-xs font-medium text-foreground">
          {titleIcon}
          {title}
          <span className="text-muted-foreground">({ids.length})</span>
        </div>
        <button
          type="button"
          className="text-[11px] font-medium text-sky-700 hover:underline dark:text-sky-400"
          onClick={onAction}
        >
          {actionLabel}
        </button>
      </div>
      <div ref={setNodeRef} className="flex-1 overflow-y-auto p-2">
        <SortableContext items={ids} strategy={verticalListSortingStrategy}>
          {ids.length === 0 ? (
            <div
              className={cn(
                "flex h-40 items-center justify-center rounded-lg px-3 text-center text-xs text-muted-foreground",
                dashedEmpty && "border border-dashed border-border",
              )}
            >
              {emptyLabel}
            </div>
          ) : (
            <ul className="space-y-1">
              {ids.map((colId) => {
                const opt = byId.get(colId);
                if (!opt) return null;
                return (
                  <SortableColumnRow
                    key={colId}
                    option={opt}
                    pinned={pinned.has(colId)}
                    starred={starred.has(colId)}
                    onTogglePin={() => onTogglePin(colId)}
                    onToggleStar={() => onToggleStar(colId)}
                    onQuickMove={() => onQuickMove(colId)}
                    quickMoveTitle={quickMoveTitle}
                  />
                );
              })}
            </ul>
          )}
        </SortableContext>
      </div>
    </div>
  );
}

function SortableColumnRow({
  option,
  pinned,
  starred,
  onTogglePin,
  onToggleStar,
  onQuickMove,
  quickMoveTitle,
}: {
  option: DynTableColumnOption;
  pinned: boolean;
  starred: boolean;
  onTogglePin: () => void;
  onToggleStar: () => void;
  onQuickMove: () => void;
  quickMoveTitle: string;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: option.id,
  });

  return (
    <li
      ref={setNodeRef}
      style={{
        transform: CSS.Translate.toString(transform),
        transition,
        opacity: isDragging ? 0.35 : undefined,
      }}
      className="flex items-center gap-1 rounded-md border border-border/80 bg-background px-1.5 py-1.5"
    >
      <button
        type="button"
        className="cursor-grab touch-none text-muted-foreground hover:text-foreground active:cursor-grabbing"
        aria-label={`Drag ${option.label}`}
        {...attributes}
        {...listeners}
      >
        <GripVertical className="size-3.5" />
      </button>
      <span className="min-w-0 flex-1 truncate text-sm">{option.label}</span>
      <button
        type="button"
        className={cn(
          "rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground",
          pinned && "bg-sky-500/15 text-sky-700 dark:text-sky-400",
        )}
        title={pinned ? "Unpin" : "Pin"}
        onClick={onTogglePin}
      >
        <Pin className="size-3.5" />
      </button>
      <button
        type="button"
        className={cn(
          "rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground",
          starred && "bg-amber-500/15 text-amber-700 dark:text-amber-400",
        )}
        title={starred ? "Unstar" : "Star"}
        onClick={onToggleStar}
      >
        <Star className="size-3.5" />
      </button>
      <button
        type="button"
        className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
        title={quickMoveTitle}
        onClick={onQuickMove}
      >
        <EyeOff className="size-3.5" />
      </button>
    </li>
  );
}
