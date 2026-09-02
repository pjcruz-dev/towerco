"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { ExternalLink } from "lucide-react";

import { FormInput } from "@/components/forms/form-input";
import { AppMenuTileDndBoard } from "@/components/platform/app-menu-tile-dnd-board";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { getErrorMessage } from "@/lib/api/error";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import {
  platformClearAppMenuIcon,
  platformCreateAppMenuGroup,
  platformCreateAppMenuTile,
  platformDeleteAppMenuGroup,
  platformDeleteAppMenuTile,
  platformListAppMenu,
  platformPlaceAppMenuTiles,
  platformReorderAppMenuGroups,
  platformSyncAppMenuDefaults,
  platformUpdateAppMenuGroup,
  platformUpdateAppMenuSettings,
  platformUpdateAppMenuTile,
  platformUploadAppMenuIcon,
  type PlatformAppMenuGroup,
  type PlatformAppMenuTile,
} from "@/lib/api/modules/platform-api";
import {
  APP_MENU_ACCENT_OPTIONS,
  APP_MENU_ICON_OPTIONS,
  resolveAppMenuAccentClass,
  resolveAppMenuIcon,
} from "@/lib/platform/app-menu-options";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { cn } from "@/lib/utils";
import { useNotificationStore } from "@/stores/notification-store";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";

type EditDraft = {
  title: string;
  subtitle: string;
  icon: string;
  accent: string;
  href: string;
  group_id: string;
  open_in_new_tab: boolean;
  sort_order: string;
  is_visible: boolean;
};

function emptyDraft(defaultGroupId = ""): EditDraft {
  return {
    title: "",
    subtitle: "",
    icon: "Shapes",
    accent: "sky",
    href: "https://",
    group_id: defaultGroupId,
    open_in_new_tab: true,
    sort_order: "0",
    is_visible: true,
  };
}

function toDraft(row: PlatformAppMenuTile): EditDraft {
  return {
    title: row.title,
    subtitle: row.subtitle ?? "",
    icon: row.icon ?? "Shapes",
    accent: row.accent ?? "sky",
    href: row.href,
    group_id: row.group_id ?? "",
    open_in_new_tab: row.open_in_new_tab,
    sort_order: String(row.sort_order),
    is_visible: row.is_visible,
  };
}

export function PlatformAppMenuPageClient() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const accessToken = usePlatformAuthStore((state) => state.accessToken);
  const isHydrated = usePlatformAuthStore((state) => state.isHydrated);
  const user = usePlatformAuthStore((state) => state.user);
  const canManage = platformHasPermission(user, PLATFORM_PERMS.tenantsManage);

  const [showCreate, setShowCreate] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [createDraft, setCreateDraft] = useState<EditDraft>(emptyDraft());
  const [editDraft, setEditDraft] = useState<EditDraft | null>(null);
  const [newGroupTitle, setNewGroupTitle] = useState("");
  const [editingGroupId, setEditingGroupId] = useState<string | null>(null);
  const [editingGroupTitle, setEditingGroupTitle] = useState("");
  const [publicOrigin, setPublicOrigin] = useState("http://localhost");

  useEffect(() => {
    if (!isHydrated) return;
    if (!accessToken) {
      router.replace("/platform/login");
    }
  }, [accessToken, isHydrated, router]);

  useEffect(() => {
    if (typeof window !== "undefined") {
      setPublicOrigin(window.location.origin);
    }
  }, []);

  const publicLauncherUrl = `${publicOrigin}/appmenu`;

  const menuQuery = useQuery({
    queryKey: ["platform", "app-menu"],
    queryFn: platformListAppMenu,
    enabled: Boolean(isHydrated && accessToken),
    retry: 1,
  });

  const groups = useMemo(() => menuQuery.data?.groups ?? [], [menuQuery.data]);
  const tiles = useMemo(() => menuQuery.data?.tiles ?? [], [menuQuery.data]);
  const gridColumns = menuQuery.data?.settings?.grid_columns ?? 4;
  const editingTile = useMemo(
    () => (editingId ? tiles.find((t) => t.id === editingId) ?? null : null),
    [editingId, tiles],
  );

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ["platform", "app-menu"] });
    void queryClient.invalidateQueries({ queryKey: ["public", "app-menu"] });
  };

  const syncMutation = useMutation({
    mutationFn: platformSyncAppMenuDefaults,
    onSuccess: (data) => {
      invalidate();
      notify({ level: "success", title: "Defaults synced", message: data.message });
    },
    onError: (error) =>
      notify({ level: "error", title: "Sync failed", message: getErrorMessage(error) }),
  });

  const createGroupMutation = useMutation({
    mutationFn: () => platformCreateAppMenuGroup({ title: newGroupTitle.trim() }),
    onSuccess: () => {
      invalidate();
      setNewGroupTitle("");
      notify({ level: "success", title: "Group added", message: "New App Menu group created." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not add group", message: getErrorMessage(error) }),
  });

  const updateGroupMutation = useMutation({
    mutationFn: ({ id, title, is_visible }: { id: string; title?: string; is_visible?: boolean }) =>
      platformUpdateAppMenuGroup(id, { title, is_visible }),
    onSuccess: () => {
      invalidate();
      setEditingGroupId(null);
      setEditingGroupTitle("");
      notify({ level: "success", title: "Group saved", message: "Group updated." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not save group", message: getErrorMessage(error) }),
  });

  const deleteGroupMutation = useMutation({
    mutationFn: (id: string) => platformDeleteAppMenuGroup(id),
    onSuccess: () => {
      invalidate();
      notify({
        level: "success",
        title: "Group deleted",
        message: "Tiles in that group are now ungrouped.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not delete group", message: getErrorMessage(error) }),
  });

  const moveGroupMutation = useMutation({
    mutationFn: (orderedIds: string[]) => platformReorderAppMenuGroups(orderedIds),
    onSuccess: () => invalidate(),
    onError: (error) =>
      notify({ level: "error", title: "Reorder failed", message: getErrorMessage(error) }),
  });

  const settingsMutation = useMutation({
    mutationFn: (grid_columns: number) => platformUpdateAppMenuSettings({ grid_columns }),
    onSuccess: () => {
      invalidate();
      notify({
        level: "success",
        title: "Grid updated",
        message: "Public /appmenu desktop columns saved.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not save grid", message: getErrorMessage(error) }),
  });

  const placeMutation = useMutation({
    mutationFn: ({ groupId, orderedIds }: { groupId: string | null; orderedIds: string[] }) =>
      platformPlaceAppMenuTiles(groupId, orderedIds),
    onSuccess: () => invalidate(),
    onError: (error) =>
      notify({ level: "error", title: "Could not move tile", message: getErrorMessage(error) }),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      platformCreateAppMenuTile({
        title: createDraft.title,
        subtitle: createDraft.subtitle || null,
        icon: createDraft.icon || null,
        accent: createDraft.accent || null,
        href: createDraft.href,
        group_id: createDraft.group_id || null,
        open_in_new_tab: createDraft.open_in_new_tab,
        sort_order: Number.parseInt(createDraft.sort_order, 10) || 0,
        is_visible: createDraft.is_visible,
      }),
    onSuccess: (tile) => {
      invalidate();
      setShowCreate(false);
      setCreateDraft(emptyDraft(groups[0]?.id ?? ""));
      setEditingId(tile.id);
      setEditDraft(toDraft(tile));
      notify({
        level: "success",
        title: "Tile added",
        message: "You can upload a custom icon now, or save and close.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Create failed", message: getErrorMessage(error) }),
  });

  const updateMutation = useMutation({
    mutationFn: () => {
      if (!editingId || !editDraft) {
        return Promise.reject(new Error("Nothing to save"));
      }
      return platformUpdateAppMenuTile(editingId, {
        title: editDraft.title,
        subtitle: editDraft.subtitle || null,
        icon: editDraft.icon || null,
        accent: editDraft.accent || null,
        href: editDraft.href,
        group_id: editDraft.group_id || null,
        open_in_new_tab: editDraft.open_in_new_tab,
        sort_order: Number.parseInt(editDraft.sort_order, 10) || 0,
        is_visible: editDraft.is_visible,
      });
    },
    onSuccess: () => {
      invalidate();
      setEditingId(null);
      setEditDraft(null);
      notify({ level: "success", title: "Saved", message: "App Menu tile updated." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Save failed", message: getErrorMessage(error) }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => platformDeleteAppMenuTile(id),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Deleted", message: "App Menu tile removed." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Delete failed", message: getErrorMessage(error) }),
  });

  function moveGroup(id: string, direction: -1 | 1) {
    const index = groups.findIndex((g) => g.id === id);
    const next = index + direction;
    if (index < 0 || next < 0 || next >= groups.length) return;
    const ordered = groups.map((g) => g.id);
    const tmp = ordered[index]!;
    ordered[index] = ordered[next]!;
    ordered[next] = tmp;
    moveGroupMutation.mutate(ordered);
  }

  function startEdit(tile: PlatformAppMenuTile) {
    setShowCreate(false);
    setEditingId(tile.id);
    setEditDraft(toDraft(tile));
  }

  function cancelEdit() {
    setEditingId(null);
    setEditDraft(null);
  }

  if (!isHydrated || !accessToken) {
    return <p className="p-6 text-sm text-muted-foreground">Loading…</p>;
  }

  return (
    <div className="space-y-6 p-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">App Menu</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Public launcher (no login). Prefer{" "}
            <span className="font-mono text-foreground">https://appmenu.yourdomain.com</span>{" "}
            (App Menu at the root — no /appmenu path). On this host you can also open:{" "}
            <a
              className="inline-flex items-center gap-1 font-medium text-foreground underline-offset-2 hover:underline"
              href={publicLauncherUrl}
              target="_blank"
              rel="noopener noreferrer"
            >
              {publicLauncherUrl}
              <ExternalLink className="size-3.5 opacity-70" aria-hidden />
            </a>
          </p>
          <p className="mt-1 text-xs text-muted-foreground">
            Point DNS <span className="font-mono">appmenu</span> at the TowerOS web service and include
            that hostname on the TLS certificate (same as app/staging). Keep CORS covering that origin.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={!canManage || syncMutation.isPending}
            onClick={() => syncMutation.mutate()}
          >
            Sync defaults
          </Button>
          <Button
            type="button"
            size="sm"
            disabled={!canManage}
            onClick={() => {
              cancelEdit();
              setCreateDraft(emptyDraft(groups[0]?.id ?? ""));
              setShowCreate(true);
            }}
          >
            Add tile
          </Button>
        </div>
      </header>

      <section className="flex flex-wrap items-end justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div>
          <h2 className="text-sm font-medium">Launcher grid</h2>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Desktop columns on /appmenu (recommended default: 4). Phones stay at 2 columns.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Label htmlFor="app-menu-grid" className="text-xs text-muted-foreground">
            Columns
          </Label>
          <select
            id="app-menu-grid"
            className="h-9 rounded-md border border-border bg-background px-2 text-sm"
            value={gridColumns}
            disabled={!canManage || settingsMutation.isPending}
            onChange={(e) => settingsMutation.mutate(Number.parseInt(e.target.value, 10))}
          >
            {[3, 4, 5, 6].map((n) => (
              <option key={n} value={n}>
                {n}
                {n === 4 ? " (default)" : ""}
              </option>
            ))}
          </select>
        </div>
      </section>

      <section className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 className="text-sm font-medium">Groups</h2>
            <p className="mt-0.5 text-xs text-muted-foreground">
              Section headers on /appmenu. Reorder with ↑↓. Deleting a group ungroups its tiles.
            </p>
          </div>
          {canManage ? (
            <div className="flex flex-wrap items-center gap-2">
              <input
                className="h-9 w-48 rounded-md border border-border bg-background px-2 text-sm"
                placeholder="New group title"
                value={newGroupTitle}
                onChange={(e) => setNewGroupTitle(e.target.value)}
              />
              <Button
                type="button"
                size="sm"
                disabled={!newGroupTitle.trim() || createGroupMutation.isPending}
                onClick={() => createGroupMutation.mutate()}
              >
                Add group
              </Button>
            </div>
          ) : null}
        </div>

        <div className="overflow-hidden rounded-lg border border-border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-20">Order</TableHead>
                <TableHead>Title</TableHead>
                <TableHead className="w-24">Visible</TableHead>
                <TableHead className="w-40 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {groups.map((group, index) => (
                <TableRow key={group.id}>
                  <TableCell className="align-middle text-xs text-muted-foreground">
                    <div className="flex items-center gap-1">
                      <span className="w-4 tabular-nums">{index + 1}</span>
                      {canManage ? (
                        <>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 px-0"
                            disabled={index === 0 || moveGroupMutation.isPending}
                            onClick={() => moveGroup(group.id, -1)}
                            aria-label="Move group up"
                          >
                            ↑
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 px-0"
                            disabled={index === groups.length - 1 || moveGroupMutation.isPending}
                            onClick={() => moveGroup(group.id, 1)}
                            aria-label="Move group down"
                          >
                            ↓
                          </Button>
                        </>
                      ) : null}
                    </div>
                  </TableCell>
                  <TableCell className="align-middle">
                    {editingGroupId === group.id ? (
                      <input
                        className="h-8 w-full max-w-xs rounded-md border border-border bg-background px-2 text-sm"
                        value={editingGroupTitle}
                        onChange={(e) => setEditingGroupTitle(e.target.value)}
                      />
                    ) : (
                      <p className="text-sm font-medium">{group.title}</p>
                    )}
                  </TableCell>
                  <TableCell className="align-middle text-sm">
                    {group.is_visible ? "Yes" : "No"}
                  </TableCell>
                  <TableCell className="align-middle text-right">
                    {canManage ? (
                      <div className="flex justify-end gap-1">
                        {editingGroupId === group.id ? (
                          <>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              disabled={!editingGroupTitle.trim() || updateGroupMutation.isPending}
                              onClick={() =>
                                updateGroupMutation.mutate({
                                  id: group.id,
                                  title: editingGroupTitle.trim(),
                                })
                              }
                            >
                              Save
                            </Button>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              onClick={() => {
                                setEditingGroupId(null);
                                setEditingGroupTitle("");
                              }}
                            >
                              Cancel
                            </Button>
                          </>
                        ) : (
                          <>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              onClick={() => {
                                setEditingGroupId(group.id);
                                setEditingGroupTitle(group.title);
                              }}
                            >
                              Rename
                            </Button>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              disabled={updateGroupMutation.isPending}
                              onClick={() =>
                                updateGroupMutation.mutate({
                                  id: group.id,
                                  is_visible: !group.is_visible,
                                })
                              }
                            >
                              {group.is_visible ? "Hide" : "Show"}
                            </Button>
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              className="text-destructive"
                              disabled={deleteGroupMutation.isPending}
                              onClick={() => {
                                if (
                                  window.confirm(
                                    `Delete group “${group.title}”? Tiles become ungrouped.`,
                                  )
                                ) {
                                  deleteGroupMutation.mutate(group.id);
                                }
                              }}
                            >
                              Delete
                            </Button>
                          </>
                        )}
                      </div>
                    ) : null}
                  </TableCell>
                </TableRow>
              ))}
              {groups.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4} className="py-6 text-center text-sm text-muted-foreground">
                    No groups yet. Sync defaults or add a group.
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
        </div>
      </section>

      {showCreate ? (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <h2 className="text-sm font-medium">New tile</h2>
          <TileForm
            draft={createDraft}
            groups={groups}
            onChange={setCreateDraft}
            onCancel={() => setShowCreate(false)}
            onSave={() => createMutation.mutate()}
            busy={createMutation.isPending}
          />
        </div>
      ) : null}

      {editingId && editDraft ? (
        <div className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="text-sm font-medium">
              Edit tile
              {editingTile?.is_system ? (
                <span className="ml-2 rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                  system
                </span>
              ) : null}
            </h2>
            <Button type="button" variant="ghost" size="sm" onClick={cancelEdit}>
              Close
            </Button>
          </div>
          <TileForm
            draft={editDraft}
            groups={groups}
            onChange={setEditDraft}
            onCancel={cancelEdit}
            onSave={() => updateMutation.mutate()}
            busy={updateMutation.isPending}
            tileId={editingId}
            iconUrl={editingTile?.icon_url}
            onIconChanged={() => invalidate()}
          />
        </div>
      ) : null}

      {menuQuery.isLoading ? <p className="text-sm text-muted-foreground">Loading tiles…</p> : null}
      {menuQuery.isError ? (
        <p className="text-sm text-destructive">{getErrorMessage(menuQuery.error)}</p>
      ) : null}

      {!menuQuery.isLoading && !menuQuery.isError ? (
        <AppMenuTileDndBoard
          groups={groups}
          tiles={tiles}
          canManage={canManage}
          editingId={editingId}
          busy={placeMutation.isPending || deleteMutation.isPending}
          onEdit={startEdit}
          onDelete={(tile) => {
            if (window.confirm(`Delete “${tile.title}”?`)) {
              deleteMutation.mutate(tile.id);
            }
          }}
          onPlace={async (groupId, orderedIds) => {
            await placeMutation.mutateAsync({ groupId, orderedIds });
          }}
        />
      ) : null}
    </div>
  );
}

function TileForm({
  draft,
  groups,
  onChange,
  onCancel,
  onSave,
  busy,
  tileId,
  iconUrl,
  onIconChanged,
}: {
  draft: EditDraft;
  groups: PlatformAppMenuGroup[];
  onChange: (next: EditDraft) => void;
  onCancel: () => void;
  onSave: () => void;
  busy: boolean;
  tileId?: string;
  iconUrl?: string | null;
  onIconChanged?: () => void;
}) {
  const notify = useNotificationStore((state) => state.push);
  const PreviewIcon = resolveAppMenuIcon(draft.icon);
  const accentClass = resolveAppMenuAccentClass(draft.accent);
  const iconValue = APP_MENU_ICON_OPTIONS.some((o) => o.value === draft.icon)
    ? draft.icon
    : "Shapes";
  const accentValue = APP_MENU_ACCENT_OPTIONS.some((o) => o.value === draft.accent)
    ? draft.accent
    : "sky";
  const customIconSrc = resolveBrandingAssetUrl(iconUrl);

  const uploadMutation = useMutation({
    mutationFn: (file: File) => {
      if (!tileId) {
        return Promise.reject(new Error("Save the tile before uploading an icon."));
      }
      return platformUploadAppMenuIcon(tileId, file);
    },
    onSuccess: () => {
      onIconChanged?.();
      notify({
        level: "success",
        title: "Icon uploaded",
        message: "Custom icon is shown on /appmenu.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Upload failed", message: getErrorMessage(error) }),
  });

  const clearMutation = useMutation({
    mutationFn: () => {
      if (!tileId) {
        return Promise.reject(new Error("Nothing to clear"));
      }
      return platformClearAppMenuIcon(tileId);
    },
    onSuccess: () => {
      onIconChanged?.();
      notify({
        level: "success",
        title: "Custom icon removed",
        message: "Tile uses the library icon again.",
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not remove icon", message: getErrorMessage(error) }),
  });

  const iconBusy = uploadMutation.isPending || clearMutation.isPending;

  return (
    <div className="space-y-3">
      <div className="grid gap-3 sm:grid-cols-2">
        <FormInput
          label="Title"
          value={draft.title}
          onChange={(e) => onChange({ ...draft, title: e.target.value })}
        />
        <FormInput
          label="Subtitle"
          value={draft.subtitle}
          onChange={(e) => onChange({ ...draft, subtitle: e.target.value })}
        />
        <div className="sm:col-span-2">
          <FormInput
            label="Href"
            value={draft.href}
            onChange={(e) => onChange({ ...draft, href: e.target.value })}
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="app-menu-group">Group</Label>
          <select
            id="app-menu-group"
            className="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
            value={draft.group_id}
            onChange={(e) => onChange({ ...draft, group_id: e.target.value })}
          >
            <option value="">Ungrouped</option>
            {groups.map((group) => (
              <option key={group.id} value={group.id}>
                {group.title}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="app-menu-accent">Accent</Label>
          <select
            id="app-menu-accent"
            className="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
            value={accentValue}
            onChange={(e) => onChange({ ...draft, accent: e.target.value })}
          >
            {APP_MENU_ACCENT_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="app-menu-icon">Icon library</Label>
          <select
            id="app-menu-icon"
            className="h-9 w-full rounded-md border border-border bg-background px-2 text-sm"
            value={iconValue}
            disabled={Boolean(customIconSrc)}
            onChange={(e) => onChange({ ...draft, icon: e.target.value })}
          >
            {APP_MENU_ICON_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
          {customIconSrc ? (
            <p className="text-[11px] text-muted-foreground">
              Library icon is unused while a custom upload is set.
            </p>
          ) : null}
        </div>

        <div className="space-y-1.5">
          <Label>Preview</Label>
          <div className="flex h-9 items-center gap-2 rounded-md border border-border bg-muted/30 px-2">
            {customIconSrc ? (
              // eslint-disable-next-line @next/next/no-img-element -- hosted app-menu icon
              <img
                src={customIconSrc}
                alt=""
                className="size-7 rounded-md object-contain"
                referrerPolicy="no-referrer"
              />
            ) : (
              <span className={cn("flex size-7 items-center justify-center rounded-md", accentClass)}>
                <PreviewIcon className="size-3.5" aria-hidden />
              </span>
            )}
            <span className="truncate text-sm text-muted-foreground">
              {draft.title.trim() || "Tile title"}
            </span>
          </div>
        </div>

        <div className="space-y-1.5 sm:col-span-2">
          <Label>Custom icon upload</Label>
          {tileId ? (
            <div className="flex flex-wrap items-center gap-2">
              <label className="cursor-pointer">
                <span className="inline-flex h-9 items-center rounded-md border border-input bg-background px-3 text-sm hover:bg-muted">
                  {uploadMutation.isPending ? "Uploading…" : "Upload image"}
                </span>
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/gif,image/webp"
                  className="sr-only"
                  disabled={busy || iconBusy}
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) {
                      uploadMutation.mutate(file);
                    }
                    event.target.value = "";
                  }}
                />
              </label>
              {customIconSrc ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={busy || iconBusy}
                  onClick={() => clearMutation.mutate()}
                >
                  Remove upload
                </Button>
              ) : null}
              <p className="text-[11px] text-muted-foreground">PNG, JPEG, GIF, or WebP · max 512 KB</p>
            </div>
          ) : (
            <p className="text-xs text-muted-foreground">
              Save the tile first, then edit it to upload a custom icon.
            </p>
          )}
        </div>

        <FormInput
          label="Sort order"
          value={draft.sort_order}
          onChange={(e) => onChange({ ...draft, sort_order: e.target.value })}
        />
      </div>
      <div className="flex flex-wrap gap-4">
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={draft.is_visible}
            onCheckedChange={(v) => onChange({ ...draft, is_visible: v === true })}
          />
          Visible on /appmenu
        </label>
        <label className="flex items-center gap-2 text-sm">
          <Checkbox
            checked={draft.open_in_new_tab}
            onCheckedChange={(v) => onChange({ ...draft, open_in_new_tab: v === true })}
          />
          Open in new tab
        </label>
      </div>
      <div className="flex gap-2">
        <Button
          type="button"
          size="sm"
          disabled={busy || iconBusy || !draft.title.trim() || !draft.href.trim()}
          onClick={onSave}
        >
          Save
        </Button>
        <Button type="button" size="sm" variant="outline" disabled={busy || iconBusy} onClick={onCancel}>
          Cancel
        </Button>
      </div>
    </div>
  );
}
