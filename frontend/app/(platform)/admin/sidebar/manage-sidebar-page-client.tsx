"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import {
  DndContext,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from "@dnd-kit/core";
import {
  SortableContext,
  arrayMove,
  useSortable,
  verticalListSortingStrategy,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { useQueryClient } from "@tanstack/react-query";
import {
  ChevronDown,
  ChevronRight,
  GripVertical,
  Pencil,
  Plus,
  RefreshCw,
  Trash2,
} from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { getErrorMessage } from "@/lib/api/error";
import {
  createSidebarNavItem,
  deleteSidebarNavItem,
  fetchAdminSidebar,
  reorderSidebarNavItems,
  seedSidebarNav,
  updateSidebarNavItem,
  type SidebarNavAdminItem,
  type SidebarNavItemType,
  type SidebarNavRoleOption,
} from "@/lib/api/modules/sidebar-nav-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

const TYPE_LABELS: Record<SidebarNavItemType, string> = {
  header: "Header",
  entity_link: "Entity Link",
  internal_page: "Internal Page",
  external_link: "External Link",
  divider: "Divider",
};

const COMMON_ICONS = [
  "LayoutDashboard",
  "Waypoints",
  "Activity",
  "PiggyBank",
  "Package",
  "Landmark",
  "LifeBuoy",
  "Building2",
  "Shapes",
  "ClipboardCheck",
  "CircleHelp",
  "Users",
  "Settings",
  "ScrollText",
  "Archive",
  "CreditCard",
];

type TreeNode = SidebarNavAdminItem & { children: TreeNode[] };

function buildTree(items: SidebarNavAdminItem[]): TreeNode[] {
  const map = new Map<string, TreeNode>();
  for (const item of items) {
    map.set(item.id, { ...item, children: [] });
  }
  const roots: TreeNode[] = [];
  for (const node of map.values()) {
    if (node.parent_id && map.has(node.parent_id)) {
      map.get(node.parent_id)!.children.push(node);
    } else {
      roots.push(node);
    }
  }
  const sortRec = (nodes: TreeNode[]) => {
    nodes.sort((a, b) => a.sort_order - b.sort_order || a.title.localeCompare(b.title));
    nodes.forEach((n) => sortRec(n.children));
  };
  sortRec(roots);
  return roots;
}

function flattenVisible(
  nodes: TreeNode[],
  collapsed: Set<string>,
  depth = 0,
): Array<{ node: TreeNode; depth: number }> {
  const out: Array<{ node: TreeNode; depth: number }> = [];
  for (const node of nodes) {
    out.push({ node, depth });
    if (node.children.length > 0 && !collapsed.has(node.id)) {
      out.push(...flattenVisible(node.children, collapsed, depth + 1));
    }
  }
  return out;
}

export function ManageSidebarPageClient() {
  const queryClient = useQueryClient();
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const [items, setItems] = useState<SidebarNavAdminItem[]>([]);
  const [roles, setRoles] = useState<SidebarNavRoleOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [typeFilter, setTypeFilter] = useState<string>("all");
  const [collapsed, setCollapsed] = useState<Set<string>>(new Set());
  const [editing, setEditing] = useState<SidebarNavAdminItem | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  const refreshWorkspaceNav = useCallback(async () => {
    await queryClient.invalidateQueries({ queryKey: ["workspace-sidebar", activeTenantId] });
  }, [activeTenantId, queryClient]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchAdminSidebar();
      setItems(data.items);
      setRoles(data.roles);
    } catch (e) {
      setError(getErrorMessage(e) || "Unable to load sidebar.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const tree = useMemo(() => buildTree(items), [items]);

  const filteredTree = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q && typeFilter === "all") return tree;

    const match = (n: TreeNode): TreeNode | null => {
      const selfOk =
        (!q ||
          n.title.toLowerCase().includes(q) ||
          (n.key ?? "").toLowerCase().includes(q) ||
          (n.href ?? "").toLowerCase().includes(q)) &&
        (typeFilter === "all" || n.type === typeFilter);
      const kids = n.children.map(match).filter(Boolean) as TreeNode[];
      if (selfOk || kids.length > 0) {
        return { ...n, children: kids.length ? kids : n.children.filter(() => selfOk) };
      }
      return null;
    };

    return tree.map(match).filter(Boolean) as TreeNode[];
  }, [search, tree, typeFilter]);

  const flat = useMemo(
    () => flattenVisible(filteredTree, collapsed),
    [collapsed, filteredTree],
  );

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  async function onDragEnd(event: DragEndEvent) {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const activeId = String(active.id);
    const overId = String(over.id);
    const activeItem = items.find((i) => i.id === activeId);
    const overItem = items.find((i) => i.id === overId);
    if (!activeItem || !overItem) return;
    if ((activeItem.parent_id ?? null) !== (overItem.parent_id ?? null)) {
      setError("Drag reorder only works among siblings. Change Parent in Edit to move branches.");
      return;
    }
    const parentId = activeItem.parent_id ?? null;
    const siblings = items
      .filter((i) => (i.parent_id ?? null) === parentId)
      .sort((a, b) => a.sort_order - b.sort_order);
    const oldIndex = siblings.findIndex((i) => i.id === activeId);
    const newIndex = siblings.findIndex((i) => i.id === overId);
    if (oldIndex < 0 || newIndex < 0) return;
    const next = arrayMove(siblings, oldIndex, newIndex);
    setBusy(true);
    setError(null);
    try {
      await reorderSidebarNavItems({
        parent_id: parentId,
        ordered_ids: next.map((i) => i.id),
      });
      await load();
      await refreshWorkspaceNav();
    } catch (e) {
      setError(getErrorMessage(e) || "Reorder failed.");
    } finally {
      setBusy(false);
    }
  }

  async function onDelete(item: SidebarNavAdminItem) {
    if (!window.confirm(`Delete “${item.title}”? Child items will also be removed.`)) return;
    setBusy(true);
    setError(null);
    try {
      await deleteSidebarNavItem(item.id);
      await load();
      await refreshWorkspaceNav();
    } catch (e) {
      setError(getErrorMessage(e) || "Delete failed.");
    } finally {
      setBusy(false);
    }
  }

  async function onSeed(force: boolean) {
    if (force && !window.confirm("Reset sidebar to TowerOS defaults? Custom items will be removed.")) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const data = await seedSidebarNav(force);
      setItems(data.items);
      setRoles(data.roles);
      setCollapsed(new Set());
      await refreshWorkspaceNav();
    } catch (e) {
      setError(getErrorMessage(e) || "Seed failed.");
    } finally {
      setBusy(false);
    }
  }

  function expandAll() {
    setCollapsed(new Set());
  }

  function collapseAll() {
    const ids = new Set(items.filter((i) => i.type === "header").map((i) => i.id));
    setCollapsed(ids);
  }

  return (
    <PermissionGate requiredPermissions={[permissions.sidebarManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="Manage Sidebar"
          description={
            <>
              Configure the workspace menu tree, permissions, and role login defaults.
              {items.length > 0 ? (
                <span className="text-foreground/80">
                  {" "}
                  · {items.length} items
                  {tree.length > 0
                    ? ` · ${tree.filter((n) => n.children.length > 0).length} branches (use Expand All if collapsed)`
                    : null}
                </span>
              ) : null}
            </>
          }
          actions={
            <>
              <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => void onSeed(false)}>
                <RefreshCw className="mr-1.5 size-3.5" />
                Ensure seeded
              </Button>
              <Button type="button" variant="outline" size="sm" disabled={busy} onClick={() => void onSeed(true)}>
                Reset to defaults
              </Button>
              <Button
                type="button"
                size="sm"
                disabled={busy}
                onClick={() => {
                  setIsCreating(true);
                  setEditing(null);
                }}
              >
                <Plus className="mr-1.5 size-3.5" />
                Add New Item
              </Button>
            </>
          }
        />

        <div className="flex flex-wrap items-center gap-2">
          <Input
            className="h-9 max-w-sm"
            placeholder="Search sidebar items…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <Select
            className="h-9 w-44"
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value)}
          >
            <option value="all">All Types</option>
            {Object.entries(TYPE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </Select>
          <Button type="button" variant="ghost" size="sm" onClick={collapseAll}>
            Collapse All
          </Button>
          <Button type="button" variant="ghost" size="sm" onClick={expandAll}>
            Expand All
          </Button>
        </div>

        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        {loading ? <p className="text-sm text-muted-foreground">Loading sidebar…</p> : null}

        {!loading ? (
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={(e) => void onDragEnd(e)}>
              <SortableContext items={flat.map((r) => r.node.id)} strategy={verticalListSortingStrategy}>
                <ul>
                  {flat.map(({ node, depth }) => (
                    <SortableRow
                      key={node.id}
                      item={node}
                      depth={depth}
                      collapsed={collapsed.has(node.id)}
                      hasChildren={node.children.length > 0}
                      onToggle={() =>
                        setCollapsed((prev) => {
                          const next = new Set(prev);
                          if (next.has(node.id)) next.delete(node.id);
                          else next.add(node.id);
                          return next;
                        })
                      }
                      onEdit={() => {
                        setIsCreating(false);
                        setEditing(node);
                      }}
                      onDelete={() => void onDelete(node)}
                      disabled={busy}
                    />
                  ))}
                  {flat.length === 0 ? (
                    <li className="px-4 py-8 text-center text-sm text-muted-foreground">
                      No sidebar items. Click Ensure seeded or Add New Item.
                    </li>
                  ) : null}
                </ul>
              </SortableContext>
            </DndContext>
          </div>
        ) : null}

        {editing || isCreating ? (
          <EditSidebarItemDialog
            item={editing}
            items={items}
            roles={roles}
            busy={busy}
            onClose={() => {
              setEditing(null);
              setIsCreating(false);
            }}
            onSave={async (payload) => {
              setBusy(true);
              setError(null);
              try {
                if (editing) {
                  await updateSidebarNavItem(editing.id, payload);
                } else {
                  await createSidebarNavItem({
                    title: payload.title || "New item",
                    type: (payload.type as SidebarNavItemType) || "internal_page",
                    ...payload,
                  });
                }
                setEditing(null);
                setIsCreating(false);
                await load();
                await refreshWorkspaceNav();
              } catch (e) {
                setError(getErrorMessage(e) || "Save failed.");
              } finally {
                setBusy(false);
              }
            }}
          />
        ) : null}
      </div>
    </PermissionGate>
  );
}

function SortableRow({
  item,
  depth,
  collapsed,
  hasChildren,
  onToggle,
  onEdit,
  onDelete,
  disabled,
}: {
  item: TreeNode;
  depth: number;
  collapsed: boolean;
  hasChildren: boolean;
  onToggle: () => void;
  onEdit: () => void;
  onDelete: () => void;
  disabled: boolean;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: item.id,
  });

  return (
    <li
      ref={setNodeRef}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
        paddingLeft: 16 + depth * 20,
      }}
      className={cn(
        "flex items-center gap-2 border-b border-border px-4 py-2.5 last:border-b-0",
        isDragging && "bg-muted/40",
        !item.is_visible && "opacity-50",
      )}
    >
      <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        className="cursor-grab text-muted-foreground hover:text-foreground"
        {...attributes}
        {...listeners}
        aria-label="Drag to reorder"
      >
        <GripVertical className="size-4" />
      </Button>
      {hasChildren ? (
        <Button type="button" variant="ghost" size="icon-sm" onClick={onToggle} aria-label="Toggle">
          {collapsed ? <ChevronRight className="size-4" /> : <ChevronDown className="size-4" />}
        </Button>
      ) : (
        <span className="inline-block w-4" />
      )}
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm font-medium text-foreground">{item.title}</span>
          <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
            {TYPE_LABELS[item.type]}
          </span>
          {hasChildren ? (
            <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
              {item.children.length} child{item.children.length === 1 ? "" : "ren"}
              {collapsed ? " · collapsed" : ""}
            </span>
          ) : null}
          {item.is_global_default ? (
            <span className="rounded-full bg-sky-500/15 px-2 py-0.5 text-[10px] font-medium text-sky-700 dark:text-sky-400">
              Global default
            </span>
          ) : null}
        </div>
        <p className="truncate text-xs text-muted-foreground">
          {item.resolved_href ||
            item.entity_slug ||
            item.permission_key ||
            (item.type === "internal_page" || item.type === "external_link"
              ? "Missing href — won’t appear in nav"
              : "—")}
        </p>
      </div>
      <Button type="button" variant="ghost" size="sm" className="h-8" disabled={disabled} onClick={onEdit}>
        <Pencil className="size-3.5" />
      </Button>
      <Button type="button" variant="ghost" size="sm" className="h-8" disabled={disabled} onClick={onDelete}>
        <Trash2 className="size-3.5" />
      </Button>
    </li>
  );
}

function EditSidebarItemDialog({
  item,
  items,
  roles,
  busy,
  onClose,
  onSave,
}: {
  item: SidebarNavAdminItem | null;
  items: SidebarNavAdminItem[];
  roles: SidebarNavRoleOption[];
  busy: boolean;
  onClose: () => void;
  onSave: (payload: Partial<SidebarNavAdminItem> & { role_default_ids?: number[] }) => Promise<void>;
}) {
  const [title, setTitle] = useState(item?.title ?? "");
  const [type, setType] = useState<SidebarNavItemType>(item?.type ?? "internal_page");
  const [icon, setIcon] = useState(item?.icon ?? "");
  const [href, setHref] = useState(item?.href ?? "");
  const [entitySlug, setEntitySlug] = useState(item?.entity_slug ?? "");
  const [parentId, setParentId] = useState<string>(item?.parent_id ?? "");
  const [permissionKey, setPermissionKey] = useState(item?.permission_key ?? "");
  const [sortOrder, setSortOrder] = useState(String(item?.sort_order ?? 0));
  const [visible, setVisible] = useState(item?.is_visible ?? true);
  const [globalDefault, setGlobalDefault] = useState(item?.is_global_default ?? false);
  const [roleDefaults, setRoleDefaults] = useState<number[]>(item?.role_default_ids ?? []);

  const parentOptions = useMemo(() => {
    return items
      .filter((i) => i.type === "header" && i.id !== item?.id)
      .sort((a, b) => a.title.localeCompare(b.title));
  }, [item?.id, items]);

  const linkNeedsTarget =
    type === "entity_link" ? !entitySlug.trim() : type === "internal_page" || type === "external_link" ? !href.trim() : false;
  const canSave = (title.trim() !== "" || type === "divider") && !linkNeedsTarget;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-lg">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-base font-medium">{item ? "Edit Item" : "Add Item"}</h2>
          <Button type="button" variant="ghost" size="sm" onClick={onClose}>
            Close
          </Button>
        </div>

        <div className="space-y-3">
          <div className="space-y-1">
            <Label>Label</Label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} />
          </div>
          <div className="space-y-1">
            <Label>Type</Label>
            <Select
              className="h-9 w-full"
              value={type}
              onChange={(e) => setType(e.target.value as SidebarNavItemType)}
            >
              {Object.entries(TYPE_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </Select>
          </div>
          <div className="space-y-1">
            <Label>Icon</Label>
            <div className="flex gap-2">
              <Input
                className="flex-1"
                placeholder="Lucide name e.g. LayoutDashboard"
                value={icon}
                onChange={(e) => setIcon(e.target.value)}
              />
              <Select
                className="h-9 w-40"
                value=""
                onChange={(e) => {
                  if (e.target.value) setIcon(e.target.value);
                }}
              >
                <option value="">Browse</option>
                {COMMON_ICONS.map((name) => (
                  <option key={name} value={name}>
                    {name}
                  </option>
                ))}
              </Select>
            </div>
          </div>
          <div className="space-y-1">
            <Label>Order</Label>
            <Input
              type="number"
              value={sortOrder}
              onChange={(e) => setSortOrder(e.target.value)}
            />
          </div>
          <div className="space-y-1">
            <Label>Parent Item</Label>
            <Select
              className="h-9 w-full"
              value={parentId}
              onChange={(e) => setParentId(e.target.value)}
            >
              <option value="">— None (Top Level) —</option>
              {parentOptions.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.title}
                </option>
              ))}
            </Select>
          </div>
          {type === "entity_link" ? (
            <div className="space-y-1">
              <Label>Entity slug</Label>
              <Input
                placeholder="tower_sites"
                value={entitySlug}
                onChange={(e) => setEntitySlug(e.target.value)}
              />
            </div>
          ) : type !== "header" && type !== "divider" ? (
            <div className="space-y-1">
              <Label>Href</Label>
              <Input
                placeholder="/dashboard"
                value={href}
                onChange={(e) => setHref(e.target.value)}
              />
              {!href.trim() ? (
                <p className="text-xs text-amber-700 dark:text-amber-400">
                  Required — items without a href are hidden from the live sidebar.
                </p>
              ) : null}
            </div>
          ) : null}
          <div className="space-y-1">
            <Label>Required Permission Key</Label>
            <Input
              placeholder="Optional access control key"
              value={permissionKey}
              onChange={(e) => setPermissionKey(e.target.value)}
            />
          </div>
          <label className="flex items-start gap-2 text-sm">
            <Checkbox checked={visible} onCheckedChange={(v) => setVisible(v === true)} />
            <span>Visible in sidebar</span>
          </label>
          <label className="flex items-start gap-2 text-sm">
            <Checkbox
              checked={globalDefault}
              onCheckedChange={(v) => setGlobalDefault(v === true)}
            />
            <span>
              Set as Global Default Page on Login (Fallback)
              <span className="mt-0.5 block text-xs text-muted-foreground">
                Used when no role-specific default is configured.
              </span>
            </span>
          </label>
          <div className="space-y-2 rounded-lg border border-border p-3">
            <p className="text-sm font-medium">Set as Default Page on Login for Roles</p>
            <div className="grid max-h-40 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2">
              {roles.map((role) => {
                const checked = roleDefaults.includes(role.id);
                return (
                  <label key={role.id} className="flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={checked}
                      onCheckedChange={(v) => {
                        setRoleDefaults((prev) =>
                          v === true ? [...prev, role.id] : prev.filter((id) => id !== role.id),
                        );
                      }}
                    />
                    {role.name}
                  </label>
                );
              })}
            </div>
            <p className="text-xs text-muted-foreground">
              Checking a role overrides any other default landing page for that role.
            </p>
          </div>
        </div>

        <div className="mt-5 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            Close
          </Button>
          <Button
            type="button"
            disabled={busy || !canSave}
            onClick={() =>
              void onSave({
                title: title.trim() || (type === "divider" ? "—" : "Item"),
                type,
                icon: icon.trim() || null,
                href: href.trim() || null,
                entity_slug: entitySlug.trim() || null,
                parent_id: parentId || null,
                permission_key: permissionKey.trim() || null,
                sort_order: Number(sortOrder) || 0,
                is_visible: visible,
                is_global_default: globalDefault,
                role_default_ids: roleDefaults,
              })
            }
          >
            Save changes
          </Button>
        </div>
      </div>
    </div>
  );
}
