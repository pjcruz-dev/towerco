"use client";

import { Bookmark, Share2, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import type { Table } from "@tanstack/react-table";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { hasAnyPermission, permissions } from "@/lib/rbac/permissions";
import {
  createModuleListLayoutId,
  loadModuleListLayoutsBundle,
  persistModuleListLayouts,
  persistSharedModuleListLayouts,
  readModuleListLayouts,
  type ModuleListLayout,
} from "@/lib/ui/module-list-layouts";
import { useAuthStore } from "@/stores/auth-store";

type ModuleListLayoutMenuProps<TData> = {
  table: Table<TData>;
  storageKey: string;
};

/**
 * Save / apply named column layouts (visibility + order).
 * Personal layouts sync to the signed-in user; shared layouts are tenant-wide.
 */
export function ModuleListLayoutMenu<TData>({ table, storageKey }: ModuleListLayoutMenuProps<TData>) {
  const [layouts, setLayouts] = useState<ModuleListLayout[]>([]);
  const [sharedLayouts, setSharedLayouts] = useState<ModuleListLayout[]>([]);
  const [name, setName] = useState("");
  const [open, setOpen] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const user = useAuthStore((state) => state.user);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const canShareLayouts = hasAnyPermission(
    user
      ? {
          ...user,
          permissions: effectivePermissions(),
        }
      : null,
    [permissions.tenantManage, permissions.userManage],
  );

  useEffect(() => {
    setLayouts(readModuleListLayouts(storageKey));
  }, [storageKey]);

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    setSyncing(true);
    void loadModuleListLayoutsBundle(storageKey)
      .then((next) => {
        if (!cancelled) {
          setLayouts(next.personal);
          setSharedLayouts(next.shared);
        }
      })
      .finally(() => {
        if (!cancelled) setSyncing(false);
      });
    return () => {
      cancelled = true;
    };
  }, [storageKey, open]);

  const persistPersonal = (next: ModuleListLayout[]) => {
    setLayouts(next);
    void persistModuleListLayouts(storageKey, next);
  };

  const saveCurrent = (shareWithTenant: boolean) => {
    const trimmed = name.trim();
    if (!trimmed) return;
    const layout: ModuleListLayout = {
      id: createModuleListLayoutId(),
      name: trimmed.slice(0, 48),
      visibility: { ...table.getState().columnVisibility },
      order: [...table.getState().columnOrder],
      updatedAt: new Date().toISOString(),
    };
    if (shareWithTenant) {
      const nextShared = [
        layout,
        ...sharedLayouts.filter((item) => item.name.toLowerCase() !== layout.name.toLowerCase()),
      ].slice(0, 12);
      setSharedLayouts(nextShared);
      void persistSharedModuleListLayouts(storageKey, nextShared).then(setSharedLayouts);
    } else {
      persistPersonal(
        [layout, ...layouts.filter((item) => item.name.toLowerCase() !== layout.name.toLowerCase())].slice(
          0,
          12,
        ),
      );
    }
    setName("");
  };

  const applyLayout = (layout: ModuleListLayout) => {
    table.setColumnVisibility(layout.visibility);
    if (layout.order.length > 0) {
      table.setColumnOrder(layout.order);
    }
    setOpen(false);
  };

  const removeLayout = (id: string) => {
    persistPersonal(layouts.filter((layout) => layout.id !== id));
  };

  return (
    <DropdownMenu open={open} onOpenChange={setOpen}>
      <DropdownMenuTrigger
        render={
          <Button type="button" size="sm" variant="outline" className="gap-1.5" data-help="dx-list-layouts">
            <Bookmark className="size-3.5" aria-hidden />
            Layouts
          </Button>
        }
      />
      <DropdownMenuContent align="end" className="w-72 p-2">
        <p className="mb-2 px-1 text-xs font-medium text-muted-foreground">Named column layouts</p>
        <div className="mb-2 flex flex-col gap-1.5">
          <Input
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Layout name"
            className="h-8"
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                saveCurrent(false);
              }
            }}
          />
          <div className="flex gap-1.5">
            <Button
              type="button"
              size="sm"
              className="h-8 flex-1"
              disabled={!name.trim()}
              onClick={() => saveCurrent(false)}
            >
              Save mine
            </Button>
            {canShareLayouts ? (
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="h-8 flex-1 gap-1"
                disabled={!name.trim()}
                onClick={() => saveCurrent(true)}
              >
                <Share2 className="size-3.5" aria-hidden />
                Share
              </Button>
            ) : null}
          </div>
        </div>
        {layouts.length === 0 && sharedLayouts.length === 0 ? (
          <p className="px-1 py-2 text-xs text-muted-foreground">
            {syncing ? "Loading layouts…" : "No saved layouts yet."}
          </p>
        ) : (
          <div className="max-h-56 space-y-2 overflow-y-auto">
            {layouts.length > 0 ? (
              <div>
                <p className="mb-1 px-1 text-[11px] font-medium text-muted-foreground">My layouts</p>
                <ul className="space-y-1">
                  {layouts.map((layout) => (
                    <li key={layout.id} className="flex items-center gap-1">
                      <DropdownMenuItem className="min-w-0 flex-1" onClick={() => applyLayout(layout)}>
                        <span className="truncate">{layout.name}</span>
                      </DropdownMenuItem>
                      <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="h-7 w-7 shrink-0"
                        aria-label={`Delete ${layout.name}`}
                        onClick={() => removeLayout(layout.id)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {sharedLayouts.length > 0 ? (
              <div>
                <p className="mb-1 px-1 text-[11px] font-medium text-muted-foreground">Shared with tenant</p>
                <ul className="space-y-1">
                  {sharedLayouts.map((layout) => (
                    <li key={`shared-${layout.id}`}>
                      <DropdownMenuItem onClick={() => applyLayout(layout)}>
                        <span className="truncate">{layout.name}</span>
                      </DropdownMenuItem>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        )}
        <DropdownMenuSeparator />
        <p className="px-1 pt-1 text-[11px] text-muted-foreground">
          Save mine syncs to your account.
          {canShareLayouts
            ? " Share publishes a tenant-wide layout others can apply (admin only)."
            : " Tenant sharing is limited to admins."}
        </p>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
