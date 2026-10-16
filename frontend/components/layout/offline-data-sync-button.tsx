"use client";

import { CloudDownload } from "lucide-react";
import { useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { getErrorMessage } from "@/lib/api/error";
import {
  downloadOfflineData,
  fetchOfflineDataCatalog,
  type OfflineDataCatalogRow,
} from "@/lib/api/modules/dynamic-entities-api";
import { hasAnyPermission, permissions } from "@/lib/rbac/permissions";
import { notify } from "@/lib/ui/notify";
import { useAuthStore } from "@/stores/auth-store";

export function OfflineDataSyncButton() {
  const user = useAuthStore((state) => state.user);
  const activeTenantId = useAuthStore((state) => state.activeTenantId);
  const effectivePermissions = useAuthStore((state) => state.effectivePermissions);
  const allowed = useMemo(() => {
    const scoped = user
      ? { ...user, permissions: activeTenantId ? effectivePermissions() : user.permissions }
      : null;
    return hasAnyPermission(scoped, [permissions.dynamicEntitiesView, permissions.assetsView]);
  }, [activeTenantId, effectivePermissions, user]);

  const [open, setOpen] = useState(false);
  const [rows, setRows] = useState<OfflineDataCatalogRow[]>([]);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [loading, setLoading] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!allowed) return null;

  const allChecked = rows.length > 0 && rows.every((row) => selected.has(row.slug));

  async function openDialog() {
    setOpen(true);
    setLoading(true);
    setError(null);
    try {
      const catalog = await fetchOfflineDataCatalog();
      setRows(catalog);
      setSelected(new Set(catalog.map((row) => row.slug)));
    } catch (err) {
      setRows([]);
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }

  function toggleAll(checked: boolean) {
    setSelected(checked ? new Set(rows.map((row) => row.slug)) : new Set());
  }

  function toggle(slug: string, checked: boolean) {
    setSelected((current) => {
      const next = new Set(current);
      if (checked) next.add(slug);
      else next.delete(slug);
      return next;
    });
  }

  async function startSync() {
    const slugs = rows.filter((row) => selected.has(row.slug)).map((row) => row.slug);
    if (slugs.length === 0) return;
    setSyncing(true);
    setError(null);
    try {
      const payload = await downloadOfflineData(slugs);
      const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      const stamp = new Date().toISOString().slice(0, 10);
      link.href = url;
      link.download = `toweros-offline-${stamp}.json`;
      link.click();
      URL.revokeObjectURL(url);
      const truncated = payload.entities.filter((entity) => entity.truncated).map((entity) => entity.name);
      notify.success(
        "Offline data downloaded",
        truncated.length > 0
          ? `${payload.entities.length} entities. ${truncated.join(", ")} was capped at 5,000 rows.`
          : `${payload.entities.length} entities`,
      );
      setOpen(false);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSyncing(false);
    }
  }

  return (
    <>
      <button
        type="button"
        className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
        title="Download Data for Offline Use"
        aria-label="Download Data for Offline Use"
        onClick={() => void openDialog()}
      >
        <CloudDownload className="size-4" />
      </button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="w-[min(calc(100vw-2rem),720px)]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <CloudDownload className="size-4 text-muted-foreground" />
              Selective cloud sync
            </DialogTitle>
            <DialogDescription>Choose which entities to download for offline use.</DialogDescription>
          </DialogHeader>
          <DialogBody className="px-0 py-0">
            {loading ? <p className="px-5 py-6 text-sm text-muted-foreground">Loading entities…</p> : null}
            {error ? <p className="px-5 py-4 text-sm text-destructive">{error}</p> : null}
            {!loading && !error && rows.length === 0 ? (
              <p className="px-5 py-6 text-sm text-muted-foreground">No entities to download.</p>
            ) : null}
            {!loading && rows.length > 0 ? (
              <table className="w-full text-sm">
                <thead className="sticky top-0 z-10 bg-popover text-xs text-muted-foreground">
                  <tr className="border-b border-border">
                    <th className="h-10 w-10 px-4 text-left align-middle">
                      <Checkbox
                        aria-label="Select all entities"
                        checked={allChecked}
                        data-indeterminate={selected.size > 0 && !allChecked ? "" : undefined}
                        onCheckedChange={(value) => toggleAll(value === true)}
                      />
                    </th>
                    <th className="h-10 px-2 text-left align-middle font-medium">Entity</th>
                    <th className="h-10 px-2 text-right align-middle font-medium">Rows</th>
                    <th className="h-10 px-4 text-right align-middle font-medium">Size</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.slug} className="border-b border-border last:border-0 hover:bg-muted/50">
                      <td className="px-4 py-2 align-middle">
                        <Checkbox
                          aria-label={row.name}
                          checked={selected.has(row.slug)}
                          onCheckedChange={(value) => toggle(row.slug, value === true)}
                        />
                      </td>
                      <td className="px-2 py-2 align-middle">{row.name}</td>
                      <td className="px-2 py-2 text-right align-middle tabular-nums">
                        {row.rows.toLocaleString()}
                      </td>
                      <td className="px-4 py-2 text-right align-middle tabular-nums">{row.size}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : null}
          </DialogBody>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={syncing}>
              Cancel
            </Button>
            <Button type="button" onClick={() => void startSync()} disabled={syncing || selected.size === 0}>
              {syncing ? "Syncing…" : "Start sync"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}
