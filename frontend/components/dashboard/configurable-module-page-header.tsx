"use client";

import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useDraggable,
  useDroppable,
  type DragEndEvent,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical } from "lucide-react";
import { Fragment, type ReactNode } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  PAGE_CHROME_IDENTITY_PLACEMENTS,
  isActionVisible,
  pageChromeActionPurpose,
  resolvePageChrome,
  type PageChromeActionDef,
  type PageChromeDefaults,
  type PageChromeIdentityPlacement,
  type PageChromePrefs,
} from "@/lib/ui/page-chrome-config";
import { cn } from "@/lib/utils";
import { useDashboardBoardCapabilities } from "@/hooks/use-dashboard-board-capabilities";

type Props = {
  defaults: PageChromeDefaults;
  prefs?: PageChromePrefs | null;
  editing?: boolean;
  onChromeChange?: (next: PageChromePrefs) => void;
  /**
   * Preferred: map action id → control. Header applies visibility + drag order.
   * Use separate nodes for help vs tour so they can reorder independently.
   */
  actionsById?: Partial<Record<string, ReactNode>>;
  /**
   * Legacy fragment renderer. Still supports title placement; button order
   * requires `actionsById`.
   */
  renderActions?: (ctx: {
    isVisible: (id: string) => boolean;
    orderedVisibleIds: string[];
  }) => ReactNode;
  className?: string;
  dataHelp?: string;
};

const IDENTITY_DRAG_ID = "page-chrome-identity";

function HeaderActionMiniChip({ label, emphasized }: { label: string; emphasized?: boolean }) {
  return (
    <span
      className={cn(
        "inline-flex h-6 max-w-[7.5rem] items-center truncate rounded-md border px-1.5 text-[10px] font-medium",
        emphasized
          ? "border-foreground/20 bg-foreground text-background"
          : "border-border bg-card text-muted-foreground",
      )}
      aria-hidden
    >
      {label}
    </span>
  );
}

function PlacementMini({ id, active }: { id: PageChromeIdentityPlacement; active: boolean }) {
  return (
    <span
      className={cn(
        "flex h-8 w-12 flex-col gap-0.5 rounded border p-0.5",
        active ? "border-sky-500/50 bg-sky-500/10" : "border-border bg-muted/40",
      )}
      aria-hidden
    >
      {id === "start" || id === "end" ? (
        <span className="flex h-full gap-0.5">
          <span
            className={cn("rounded-[1px] bg-foreground/25", id === "start" ? "w-[60%]" : "w-[35%]")}
          />
          <span
            className={cn("rounded-[1px] bg-foreground/15", id === "start" ? "w-[35%]" : "w-[60%]")}
          />
        </span>
      ) : (
        <>
          <span
            className={cn(
              "rounded-[1px] bg-foreground/25",
              id === "above" ? "h-[45%]" : "h-[30%] order-2",
            )}
          />
          <span
            className={cn(
              "rounded-[1px] bg-foreground/15",
              id === "above" ? "h-[30%]" : "h-[45%] order-1",
            )}
          />
        </>
      )}
    </span>
  );
}

function PlacementDropZone({
  id,
  active,
}: {
  id: PageChromeIdentityPlacement;
  active: boolean;
}) {
  const { setNodeRef, isOver } = useDroppable({ id: `placement:${id}` });
  const meta = PAGE_CHROME_IDENTITY_PLACEMENTS.find((item) => item.id === id);
  return (
    <div
      ref={setNodeRef}
      className={cn(
        "min-h-10 rounded-lg border border-dashed px-2 py-1.5 transition-colors",
        active ? "border-sky-500/40 bg-sky-500/5" : "border-border/70 bg-muted/10",
        isOver && "border-sky-500 bg-sky-500/10",
      )}
    >
      <p className="text-[9px] font-medium tracking-wide text-muted-foreground uppercase">
        {meta?.label ?? id}
      </p>
      <p className="mt-0.5 text-[10px] text-muted-foreground">
        {active ? "Title is here" : "Drop title here"}
      </p>
    </div>
  );
}

function SortableActionRow({
  action,
  shown,
  locked,
  onToggle,
}: {
  action: PageChromeActionDef;
  shown: boolean;
  locked: boolean;
  onToggle: (show: boolean) => void;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: action.id,
  });
  const purpose = pageChromeActionPurpose(action);

  return (
    <li
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={cn(
        "flex items-start gap-2 rounded-lg border border-border bg-card px-2 py-2",
        isDragging && "z-10 opacity-90 shadow-md ring-2 ring-sky-500/25",
        !shown && "opacity-70",
      )}
    >
      <button
        type="button"
        className="mt-0.5 flex h-7 w-6 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing"
        aria-label={`Reorder ${action.label}`}
        {...attributes}
        {...listeners}
      >
        <GripVertical className="size-3.5" aria-hidden />
      </button>
      <label
        className={cn("flex min-w-0 flex-1 cursor-pointer items-start gap-2.5", locked && "cursor-default")}
      >
        <Checkbox
          className="mt-0.5 size-4"
          checked={shown}
          disabled={locked}
          onCheckedChange={(value) => onToggle(value === true)}
        />
        <span className="min-w-0 flex-1">
          <span className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-medium text-foreground">
              {action.label}
              {locked ? (
                <span className="ml-1 font-normal text-muted-foreground">(required)</span>
              ) : null}
            </span>
            <HeaderActionMiniChip label={action.label} emphasized={action.id === "new"} />
          </span>
          <span className="mt-1 block text-[10px] leading-snug text-muted-foreground">{purpose}</span>
        </span>
      </label>
    </li>
  );
}

function DraggableIdentityShell({
  editing,
  children,
}: {
  editing: boolean;
  children: ReactNode;
}) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: IDENTITY_DRAG_ID,
    disabled: !editing,
  });

  if (!editing) return <div className="min-w-0 flex-1">{children}</div>;

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Translate.toString(transform) }}
      className={cn(
        "min-w-0 flex-1 rounded-lg border border-dashed border-border bg-card/80",
        isDragging && "z-20 opacity-90 shadow-md ring-2 ring-sky-500/30",
      )}
    >
      <div className="flex items-start gap-1 p-1.5">
        <button
          type="button"
          className="mt-1 flex h-7 w-6 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground hover:bg-muted active:cursor-grabbing"
          aria-label="Drag page title block"
          {...attributes}
          {...listeners}
        >
          <GripVertical className="size-3.5" aria-hidden />
        </button>
        <div className="min-w-0 flex-1">{children}</div>
      </div>
    </div>
  );
}

/**
 * Module page header with Customize editing for title/description placement
 * and drag-reorderable header actions.
 */
export function ConfigurableModulePageHeader({
  defaults,
  prefs,
  editing = false,
  onChromeChange,
  actionsById,
  renderActions,
  className,
  dataHelp,
}: Props) {
  const { canCustomizeBoard } = useDashboardBoardCapabilities();
  /** Page chrome (title/actions) is admin Customize only — normal users rearrange widgets elsewhere. */
  const chromeEditing = Boolean(editing && canCustomizeBoard);
  const chrome = resolvePageChrome(defaults, prefs);
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const patch = (partial: PageChromePrefs) => {
    onChromeChange?.({
      title: prefs?.title,
      description: prefs?.description,
      hiddenActionIds: prefs?.hiddenActionIds,
      actionOrder: prefs?.actionOrder ?? chrome.actionOrder,
      identityPlacement: prefs?.identityPlacement ?? chrome.identityPlacement,
      ...partial,
    });
  };

  const toggleAction = (id: string, show: boolean) => {
    const action = defaults.actions.find((item) => item.id === id);
    if (!action || action.hideable === false) return;
    const hidden = new Set(
      prefs?.hiddenActionIds !== undefined
        ? prefs.hiddenActionIds
        : (defaults.defaultHiddenActionIds ?? []),
    );
    if (show) hidden.delete(id);
    else hidden.add(id);
    patch({ hiddenActionIds: [...hidden] });
  };

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || !onChromeChange) return;

    const activeId = String(active.id);
    const overId = String(over.id);

    if (activeId === IDENTITY_DRAG_ID && overId.startsWith("placement:")) {
      const next = overId.replace("placement:", "") as PageChromeIdentityPlacement;
      if (PAGE_CHROME_IDENTITY_PLACEMENTS.some((item) => item.id === next)) {
        patch({ identityPlacement: next });
      }
      return;
    }

    if (activeId === IDENTITY_DRAG_ID) return;

    const order = [...chrome.actionOrder];
    const from = order.indexOf(activeId);
    const to = order.indexOf(overId);
    if (from < 0 || to < 0 || from === to) return;
    patch({ actionOrder: arrayMove(order, from, to) });
  };

  const hasCustomChrome = Boolean(
    prefs?.title ||
      prefs?.description ||
      prefs?.hiddenActionIds !== undefined ||
      (prefs?.actionOrder?.length ?? 0) > 0 ||
      prefs?.identityPlacement,
  );

  const identityEditor =
    chromeEditing && onChromeChange ? (
      <div className="space-y-2">
        <div className="space-y-1">
          <Label htmlFor="page-chrome-title" className="text-xs">
            Page title
          </Label>
          <Input
            id="page-chrome-title"
            value={prefs?.title ?? defaults.title}
            onChange={(event) => patch({ title: event.target.value })}
            className="h-9 text-base font-semibold"
          />
        </div>
        <div className="space-y-1">
          <Label htmlFor="page-chrome-description" className="text-xs">
            Page description
          </Label>
          <textarea
            id="page-chrome-description"
            value={prefs?.description ?? defaults.description}
            onChange={(event) => patch({ description: event.target.value })}
            rows={2}
            className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-muted-foreground"
          />
          <p className="text-[10px] text-muted-foreground">
            Drag the grip onto a drop zone · also used by Welcome banner when added.
          </p>
        </div>
      </div>
    ) : (
      <>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{chrome.title}</h1>
        {chrome.description ? (
          <p className="mt-1 text-sm text-muted-foreground">{chrome.description}</p>
        ) : null}
      </>
    );

  const actionNodes =
    actionsById != null
      ? chrome.visibleActionIds
          .map((id) => {
            const node = actionsById[id];
            if (node == null || node === false) return null;
            return <Fragment key={id}>{node}</Fragment>;
          })
          .filter(Boolean)
      : renderActions?.({
          isVisible: (id) => isActionVisible(chrome, id),
          orderedVisibleIds: chrome.visibleActionIds,
        });

  const actionsCluster = (
    <div
      className={cn(
        "flex flex-wrap items-center gap-2",
        chromeEditing && actionsById && "rounded-lg border border-dashed border-border/80 bg-muted/10 p-1.5",
      )}
      data-help={actionsById ? undefined : undefined}
    >
      {actionNodes}
    </div>
  );

  const identityBlock = (
    <DraggableIdentityShell editing={Boolean(chromeEditing && onChromeChange)}>
      {identityEditor}
    </DraggableIdentityShell>
  );

  const placement = chrome.identityPlacement;

  const headerBody =
    placement === "above" ? (
      <div className="space-y-3">
        {identityBlock}
        <div className="flex justify-end">{actionsCluster}</div>
      </div>
    ) : placement === "below" ? (
      <div className="space-y-3">
        <div className="flex justify-end">{actionsCluster}</div>
        {identityBlock}
      </div>
    ) : placement === "end" ? (
      <div className="flex flex-wrap items-start justify-between gap-3">
        {actionsCluster}
        <div className="min-w-0 max-w-2xl flex-1">{identityBlock}</div>
      </div>
    ) : (
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0 max-w-2xl flex-1">{identityBlock}</div>
        {actionsCluster}
      </div>
    );

  return (
    <div className={cn("space-y-3", className)}>
      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
        <header data-help={dataHelp}>{headerBody}</header>

        {chromeEditing && onChromeChange ? (
          <div className="space-y-3 rounded-xl border border-dashed border-border bg-muted/20 px-3 py-3">
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p className="text-xs font-medium text-foreground">Header layout</p>
                <p className="mt-0.5 text-[10px] leading-snug text-muted-foreground">
                  Drag the title grip onto a zone, or tap a placement. Drag action grips to reorder.
                </p>
              </div>
              {hasCustomChrome ? (
                <Button
                  type="button"
                  size="sm"
                  variant="ghost"
                  className="h-7 text-xs"
                  onClick={() => onChromeChange({})}
                >
                  Reset header defaults
                </Button>
              ) : null}
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
              {PAGE_CHROME_IDENTITY_PLACEMENTS.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  className={cn(
                    "flex items-start gap-2 rounded-lg border px-2.5 py-2 text-left transition-colors",
                    placement === item.id
                      ? "border-sky-500/40 bg-sky-500/5"
                      : "border-border bg-card hover:bg-muted/40",
                  )}
                  onClick={() => patch({ identityPlacement: item.id })}
                >
                  <PlacementMini id={item.id} active={placement === item.id} />
                  <span className="min-w-0">
                    <span className="block text-xs font-medium text-foreground">{item.label}</span>
                    <span className="mt-0.5 block text-[10px] leading-snug text-muted-foreground">
                      {item.purpose}
                    </span>
                  </span>
                </button>
              ))}
            </div>

            <div className="grid gap-2 md:grid-cols-2">
              <PlacementDropZone id="above" active={placement === "above"} />
              <PlacementDropZone id="below" active={placement === "below"} />
              <PlacementDropZone id="start" active={placement === "start"} />
              <PlacementDropZone id="end" active={placement === "end"} />
            </div>

            <div>
              <p className="text-xs font-medium text-foreground">Header actions</p>
                <p className="mt-0.5 text-[10px] leading-snug text-muted-foreground">
                  Drag grips to reorder — the live header above updates immediately. Checkbox
                  shows/hides each control.
                </p>
              <SortableContext items={chrome.actionOrder} strategy={verticalListSortingStrategy}>
                <ul className="mt-2 space-y-1.5">
                  {chrome.actionOrder.map((id) => {
                    const action = defaults.actions.find((item) => item.id === id);
                    if (!action) return null;
                    return (
                      <SortableActionRow
                        key={action.id}
                        action={action}
                        shown={isActionVisible(chrome, action.id)}
                        locked={action.hideable === false}
                        onToggle={(show) => toggleAction(action.id, show)}
                      />
                    );
                  })}
                </ul>
              </SortableContext>
              {!actionsById ? (
                <p className="mt-2 text-[10px] text-amber-700 dark:text-amber-400">
                  Wire this page with actionsById so live header buttons follow the drag order.
                </p>
              ) : null}
            </div>
          </div>
        ) : null}
      </DndContext>
    </div>
  );
}
