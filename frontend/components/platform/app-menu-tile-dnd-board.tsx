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
} from "@dnd-kit/core";
import {
  SortableContext,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import type { PlatformAppMenuGroup, PlatformAppMenuTile } from "@/lib/api/modules/platform-api";
import { cn } from "@/lib/utils";

type ContainerId = string;

function containerKey(groupId: string | null): ContainerId {
  return groupId ?? "ungrouped";
}

function parseContainerKey(key: ContainerId): string | null {
  return key === "ungrouped" ? null : key;
}

function SortableTileRow({
  tile,
  canManage,
  isEditing,
  onEdit,
  onDelete,
  deleteBusy,
}: {
  tile: PlatformAppMenuTile;
  canManage: boolean;
  isEditing: boolean;
  onEdit: (tile: PlatformAppMenuTile) => void;
  onDelete: (tile: PlatformAppMenuTile) => void;
  deleteBusy: boolean;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: tile.id,
    disabled: !canManage,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <TableRow
      ref={setNodeRef}
      style={style}
      className={cn(isEditing ? "bg-muted/40" : undefined, isDragging ? "opacity-40" : undefined)}
    >
      <TableCell className="w-10 align-middle">
        {canManage ? (
          <button
            type="button"
            className="inline-flex size-7 cursor-grab items-center justify-center rounded-md text-muted-foreground hover:bg-muted active:cursor-grabbing"
            aria-label={`Drag ${tile.title}`}
            {...attributes}
            {...listeners}
          >
            <GripVertical className="size-4" />
          </button>
        ) : null}
      </TableCell>
      <TableCell className="align-middle">
        <p className="text-sm font-medium">
          {tile.title}
          {tile.is_system ? (
            <span className="ml-2 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
              system
            </span>
          ) : null}
        </p>
        {tile.subtitle ? <p className="text-xs text-muted-foreground">{tile.subtitle}</p> : null}
      </TableCell>
      <TableCell className="align-middle">
        <a
          href={tile.href}
          className="break-all text-xs text-sky-700 hover:underline dark:text-sky-400"
          target="_blank"
          rel="noopener noreferrer"
        >
          {tile.href}
        </a>
        <p className="mt-0.5 text-[11px] text-muted-foreground">
          {tile.icon_url ? "Custom image" : (tile.icon ?? "Shapes")}
          {tile.accent ? ` · ${tile.accent}` : ""}
          {tile.open_in_new_tab ? " · new tab" : ""}
        </p>
      </TableCell>
      <TableCell className="align-middle text-sm">{tile.is_visible ? "Yes" : "No"}</TableCell>
      <TableCell className="align-middle text-right">
        {canManage ? (
          <div className="flex justify-end gap-1">
            <Button type="button" variant="ghost" size="sm" onClick={() => onEdit(tile)}>
              Edit
            </Button>
            {!tile.is_system ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="text-destructive"
                disabled={deleteBusy}
                onClick={() => onDelete(tile)}
              >
                Delete
              </Button>
            ) : null}
          </div>
        ) : null}
      </TableCell>
    </TableRow>
  );
}

function DroppableGroupTable({
  containerId,
  title,
  tiles,
  canManage,
  editingId,
  onEdit,
  onDelete,
  deleteBusy,
  isOver,
}: {
  containerId: ContainerId;
  title: string;
  tiles: PlatformAppMenuTile[];
  canManage: boolean;
  editingId: string | null;
  onEdit: (tile: PlatformAppMenuTile) => void;
  onDelete: (tile: PlatformAppMenuTile) => void;
  deleteBusy: boolean;
  isOver: boolean;
}) {
  const { setNodeRef } = useDroppable({ id: containerId });

  return (
    <div className="space-y-2">
      <h2 className="text-sm font-medium text-foreground">{title}</h2>
      <div
        ref={setNodeRef}
        className={cn(
          "overflow-hidden rounded-xl border bg-card transition",
          isOver ? "border-sky-400 ring-2 ring-sky-200 dark:ring-sky-900" : "border-border",
        )}
      >
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10" />
              <TableHead>Title</TableHead>
              <TableHead>Href</TableHead>
              <TableHead className="w-24">Visible</TableHead>
              <TableHead className="w-36 text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <SortableContext items={tiles.map((t) => t.id)} strategy={verticalListSortingStrategy}>
              {tiles.map((tile) => (
                <SortableTileRow
                  key={tile.id}
                  tile={tile}
                  canManage={canManage}
                  isEditing={editingId === tile.id}
                  onEdit={onEdit}
                  onDelete={onDelete}
                  deleteBusy={deleteBusy}
                />
              ))}
            </SortableContext>
            {tiles.length === 0 ? (
              <TableRow>
                <TableCell colSpan={5} className="py-8 text-center text-sm text-muted-foreground">
                  {canManage ? "Drop tiles here to assign this group." : "No tiles in this group."}
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}

type Props = {
  groups: PlatformAppMenuGroup[];
  tiles: PlatformAppMenuTile[];
  canManage: boolean;
  editingId: string | null;
  busy: boolean;
  onEdit: (tile: PlatformAppMenuTile) => void;
  onDelete: (tile: PlatformAppMenuTile) => void;
  onPlace: (groupId: string | null, orderedIds: string[]) => Promise<void>;
};

export function AppMenuTileDndBoard({
  groups,
  tiles,
  canManage,
  editingId,
  busy,
  onEdit,
  onDelete,
  onPlace,
}: Props) {
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: { distance: 6 },
    }),
  );

  const buildBuckets = useMemo(() => {
    return () => {
      const map = new Map<ContainerId, PlatformAppMenuTile[]>();
      for (const group of groups) {
        map.set(containerKey(group.id), []);
      }
      map.set("ungrouped", []);
      for (const tile of tiles) {
        const key =
          tile.group_id && groups.some((g) => g.id === tile.group_id)
            ? containerKey(tile.group_id)
            : "ungrouped";
        const list = map.get(key) ?? [];
        list.push(tile);
        map.set(key, list);
      }
      for (const [key, list] of map) {
        list.sort((a, b) => a.sort_order - b.sort_order || a.title.localeCompare(b.title));
        map.set(key, list);
      }
      return map;
    };
  }, [groups, tiles]);

  const [buckets, setBuckets] = useState(buildBuckets);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [overContainer, setOverContainer] = useState<ContainerId | null>(null);

  useEffect(() => {
    setBuckets(buildBuckets());
  }, [buildBuckets]);

  const tileById = useMemo(() => {
    const map = new Map<string, PlatformAppMenuTile>();
    for (const tile of tiles) {
      map.set(tile.id, tile);
    }
    return map;
  }, [tiles]);

  function findContainer(id: string): ContainerId | null {
    if (buckets.has(id)) {
      return id;
    }
    for (const [key, list] of buckets) {
      if (list.some((t) => t.id === id)) {
        return key;
      }
    }
    return null;
  }

  function handleDragStart(event: DragStartEvent) {
    setActiveId(String(event.active.id));
  }

  function handleDragOver(event: DragOverEvent) {
    const { active, over } = event;
    if (!over) return;

    const activeContainer = findContainer(String(active.id));
    const overId = String(over.id);
    const overContainerId = buckets.has(overId) ? overId : findContainer(overId);
    if (!activeContainer || !overContainerId) return;

    setOverContainer(overContainerId);

    if (activeContainer === overContainerId) return;

    setBuckets((prev) => {
      const next = new Map(prev);
      const from = [...(next.get(activeContainer) ?? [])];
      const to = [...(next.get(overContainerId) ?? [])];
      const fromIndex = from.findIndex((t) => t.id === active.id);
      if (fromIndex < 0) return prev;
      const [moved] = from.splice(fromIndex, 1);
      if (!moved) return prev;

      const overIndex = to.findIndex((t) => t.id === overId);
      if (overIndex >= 0) {
        to.splice(overIndex, 0, moved);
      } else {
        to.push(moved);
      }

      next.set(activeContainer, from);
      next.set(overContainerId, to);
      return next;
    });
  }

  async function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event;
    setActiveId(null);
    setOverContainer(null);
    if (!over || !canManage) {
      setBuckets(buildBuckets());
      return;
    }

    const activeContainer = findContainer(String(active.id));
    const overId = String(over.id);
    const overContainerId = buckets.has(overId) ? overId : findContainer(overId);
    if (!activeContainer || !overContainerId) {
      setBuckets(buildBuckets());
      return;
    }

    let nextBuckets = buckets;
    if (activeContainer === overContainerId) {
      const list = [...(buckets.get(activeContainer) ?? [])];
      const oldIndex = list.findIndex((t) => t.id === active.id);
      const newIndex = list.findIndex((t) => t.id === overId);
      if (oldIndex >= 0 && newIndex >= 0 && oldIndex !== newIndex) {
        const [moved] = list.splice(oldIndex, 1);
        if (moved) {
          list.splice(newIndex, 0, moved);
          nextBuckets = new Map(buckets);
          nextBuckets.set(activeContainer, list);
          setBuckets(nextBuckets);
        }
      }
    }

    const sourceIds = (nextBuckets.get(activeContainer) ?? []).map((t) => t.id);
    const targetIds = (nextBuckets.get(overContainerId) ?? []).map((t) => t.id);

    try {
      if (activeContainer !== overContainerId) {
        await onPlace(parseContainerKey(activeContainer), sourceIds);
        await onPlace(parseContainerKey(overContainerId), targetIds);
      } else {
        await onPlace(parseContainerKey(activeContainer), sourceIds);
      }
    } catch {
      setBuckets(buildBuckets());
    }
  }

  const sections: Array<{ id: ContainerId; title: string }> = [
    ...groups.map((g) => ({ id: containerKey(g.id), title: g.title })),
    { id: "ungrouped", title: "Ungrouped" },
  ];

  const activeTile = activeId ? tileById.get(activeId) : null;

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCorners}
      onDragStart={handleDragStart}
      onDragOver={handleDragOver}
      onDragEnd={(event) => {
        void handleDragEnd(event);
      }}
      onDragCancel={() => {
        setActiveId(null);
        setOverContainer(null);
        setBuckets(buildBuckets());
      }}
    >
      <div className={cn("space-y-6", busy ? "pointer-events-none opacity-70" : undefined)}>
        <p className="text-xs text-muted-foreground">
          Drag tiles between groups (for example into Workspaces) or reorder within a group.
        </p>
        {sections.map((section) => {
          const sectionTiles = buckets.get(section.id) ?? [];
          if (section.id === "ungrouped" && sectionTiles.length === 0 && !activeId) {
            return null;
          }
          return (
            <DroppableGroupTable
              key={section.id}
              containerId={section.id}
              title={section.title}
              tiles={sectionTiles}
              canManage={canManage}
              editingId={editingId}
              onEdit={onEdit}
              onDelete={onDelete}
              deleteBusy={busy}
              isOver={overContainer === section.id}
            />
          );
        })}
      </div>
      <DragOverlay>
        {activeTile ? (
          <div className="rounded-md border border-border bg-card px-3 py-2 text-sm shadow-md">
            {activeTile.title}
          </div>
        ) : null}
      </DragOverlay>
    </DndContext>
  );
}
