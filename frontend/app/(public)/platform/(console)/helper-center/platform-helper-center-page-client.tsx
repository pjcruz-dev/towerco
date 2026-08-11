"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";

import { AcronymTip } from "@/components/help/acronym-tip";
import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformCreateOperationalAcronym,
  platformDeleteOperationalAcronym,
  platformListOperationalAcronyms,
  platformSyncOperationalAcronymDefaults,
  platformUpdateOperationalAcronym,
  type PlatformOperationalAcronym,
} from "@/lib/api/modules/platform-api";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type EditDraft = {
  acronym: string;
  definition: string;
  category: string;
  sort_order: string;
  is_active: boolean;
};

function toDraft(row: PlatformOperationalAcronym): EditDraft {
  return {
    acronym: row.acronym,
    definition: row.definition,
    category: row.category ?? "",
    sort_order: String(row.sort_order),
    is_active: row.is_active,
  };
}

export function PlatformHelperCenterPageClient() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const accessToken = usePlatformAuthStore((state) => state.accessToken);
  const isHydrated = usePlatformAuthStore((state) => state.isHydrated);

  const [search, setSearch] = useState("");
  const [showCreate, setShowCreate] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [createDraft, setCreateDraft] = useState<EditDraft>({
    acronym: "",
    definition: "",
    category: "",
    sort_order: "0",
    is_active: true,
  });
  const [editDraft, setEditDraft] = useState<EditDraft | null>(null);

  useEffect(() => {
    if (!isHydrated) return;
    if (!accessToken) {
      router.replace("/platform/login");
    }
  }, [accessToken, isHydrated, router]);

  const acronymsQuery = useQuery({
    queryKey: ["platform", "operational-acronyms"],
    queryFn: platformListOperationalAcronyms,
    enabled: Boolean(isHydrated && accessToken),
    retry: 1,
  });

  const syncDefaultsMutation = useMutation({
    mutationFn: platformSyncOperationalAcronymDefaults,
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "operational-acronyms"] });
      void queryClient.invalidateQueries({ queryKey: ["operational-acronyms", "public"] });
      notify({
        level: "success",
        title: "Defaults synced",
        message: data.message ?? `${data.synced} acronyms updated.`,
      });
    },
    onError: (error) =>
      notify({ level: "error", title: "Sync failed", message: getErrorMessage(error) }),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      platformCreateOperationalAcronym({
        acronym: createDraft.acronym,
        definition: createDraft.definition,
        category: createDraft.category || null,
        sort_order: Number.parseInt(createDraft.sort_order, 10) || 0,
        is_active: createDraft.is_active,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "operational-acronyms"] });
      void queryClient.invalidateQueries({ queryKey: ["operational-acronyms", "public"] });
      notify({ level: "success", title: "Acronym added", message: "Tenants will see it on next glossary refresh." });
      setShowCreate(false);
      setCreateDraft({ acronym: "", definition: "", category: "", sort_order: "0", is_active: true });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not create acronym", message: getErrorMessage(error) }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, draft }: { id: string; draft: EditDraft }) =>
      platformUpdateOperationalAcronym(id, {
        acronym: draft.acronym,
        definition: draft.definition,
        category: draft.category || null,
        sort_order: Number.parseInt(draft.sort_order, 10) || 0,
        is_active: draft.is_active,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "operational-acronyms"] });
      void queryClient.invalidateQueries({ queryKey: ["operational-acronyms", "public"] });
      notify({ level: "success", title: "Acronym saved", message: "Hover tooltips use the updated definition." });
      setEditingId(null);
      setEditDraft(null);
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not save acronym", message: getErrorMessage(error) }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => platformDeleteOperationalAcronym(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "operational-acronyms"] });
      void queryClient.invalidateQueries({ queryKey: ["operational-acronyms", "public"] });
      notify({ level: "success", title: "Acronym removed", message: "It will no longer appear in tenant tooltips." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not delete acronym", message: getErrorMessage(error) }),
  });

  const rows = acronymsQuery.data ?? [];
  const filteredRows = useMemo(() => {
    const needle = search.trim().toLowerCase();
    if (!needle) {
      return rows;
    }
    return rows.filter(
      (row) =>
        row.acronym.toLowerCase().includes(needle) ||
        row.definition.toLowerCase().includes(needle) ||
        (row.category ?? "").toLowerCase().includes(needle),
    );
  }, [rows, search]);

  const activeCount = rows.filter((row) => row.is_active).length;

  const startEdit = (row: PlatformOperationalAcronym) => {
    setEditingId(row.id);
    setEditDraft(toDraft(row));
  };

  return (
    <div className="space-y-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-foreground">Helper center</h1>
          <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
            Platform-wide operational acronym glossary. Active entries appear as hover tooltips across all tenant
            workspaces (e.g. <AcronymTip acronym="MNO" />, <AcronymTip acronym="SAQ" />).
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            disabled={syncDefaultsMutation.isPending}
            onClick={() => syncDefaultsMutation.mutate()}
          >
            {syncDefaultsMutation.isPending ? "Syncing…" : "Sync TowerOS defaults"}
          </Button>
          <Button type="button" onClick={() => setShowCreate((open) => !open)}>
            {showCreate ? "Cancel" : "Add acronym"}
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <p className="text-xs font-medium text-muted-foreground">Total entries</p>
          <p className="mt-1 text-2xl font-semibold tabular-nums text-foreground">{rows.length}</p>
        </div>
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <p className="text-xs font-medium text-muted-foreground">Active (tenant-visible)</p>
          <p className="mt-1 text-2xl font-semibold tabular-nums text-foreground">{activeCount}</p>
        </div>
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <p className="text-xs font-medium text-muted-foreground">Public API</p>
          <p className="mt-1 font-mono text-xs text-muted-foreground">GET /api/v1/public/operational-acronyms</p>
        </div>
      </div>

      {showCreate ? (
        <div className="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-2">
          <FormInput label="Acronym" value={createDraft.acronym} onChange={(e) => setCreateDraft((d) => ({ ...d, acronym: e.target.value }))} />
          <FormInput label="Category" value={createDraft.category} onChange={(e) => setCreateDraft((d) => ({ ...d, category: e.target.value }))} />
          <div className="md:col-span-2">
            <FormInput
              label="Definition"
              value={createDraft.definition}
              onChange={(e) => setCreateDraft((d) => ({ ...d, definition: e.target.value }))}
            />
          </div>
          <FormInput
            label="Sort order"
            value={createDraft.sort_order}
            onChange={(e) => setCreateDraft((d) => ({ ...d, sort_order: e.target.value }))}
          />
          <label className="flex items-center gap-2 text-sm text-foreground">
            <Checkbox
              checked={createDraft.is_active}
              onCheckedChange={(v) => setCreateDraft((d) => ({ ...d, is_active: v === true }))}
              className="size-4"
            />
            Active (visible to tenants)
          </label>
          <div className="md:col-span-2">
            <Button type="button" disabled={createMutation.isPending} onClick={() => createMutation.mutate()}>
              {createMutation.isPending ? "Saving…" : "Create acronym"}
            </Button>
          </div>
        </div>
      ) : null}

      <div className="space-y-3">
        <FormInput
          label="Search glossary"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Filter by acronym, definition, or category"
        />

        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Acronym</TableHead>
                <TableHead>Definition</TableHead>
                <TableHead>Category</TableHead>
                <TableHead className="w-20 text-right">Order</TableHead>
                <TableHead className="w-24">Status</TableHead>
                <TableHead className="w-40 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {acronymsQuery.isLoading ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-sm text-muted-foreground">
                    Loading glossary…
                  </TableCell>
                </TableRow>
              ) : null}
              {!acronymsQuery.isLoading && filteredRows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-sm text-muted-foreground">
                    No acronyms yet. Run &quot;Sync TowerOS defaults&quot; or add one manually.
                  </TableCell>
                </TableRow>
              ) : null}
              {filteredRows.map((row) => {
                const isEditing = editingId === row.id && editDraft;

                if (isEditing) {
                  return (
                    <TableRow key={row.id}>
                      <TableCell>
                        <FormInput label="" value={editDraft.acronym} onChange={(e) => setEditDraft((d) => d && { ...d, acronym: e.target.value })} />
                      </TableCell>
                      <TableCell>
                        <FormInput label="" value={editDraft.definition} onChange={(e) => setEditDraft((d) => d && { ...d, definition: e.target.value })} />
                      </TableCell>
                      <TableCell>
                        <FormInput label="" value={editDraft.category} onChange={(e) => setEditDraft((d) => d && { ...d, category: e.target.value })} />
                      </TableCell>
                      <TableCell>
                        <FormInput label="" value={editDraft.sort_order} onChange={(e) => setEditDraft((d) => d && { ...d, sort_order: e.target.value })} />
                      </TableCell>
                      <TableCell>
                        <label className="flex items-center gap-2 text-xs">
                          <Checkbox
                            checked={editDraft.is_active}
                            onCheckedChange={(v) => setEditDraft((d) => d && { ...d, is_active: v === true })}
                            className="size-4"
                          />
                          Active
                        </label>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">
                          <Button
                            type="button"
                            size="sm"
                            disabled={updateMutation.isPending}
                            onClick={() => editDraft && updateMutation.mutate({ id: row.id, draft: editDraft })}
                          >
                            Save
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                              setEditingId(null);
                              setEditDraft(null);
                            }}
                          >
                            Cancel
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                }

                return (
                  <TableRow key={row.id}>
                    <TableCell className="font-medium">
                      <AcronymTip acronym={row.acronym}>{row.acronym}</AcronymTip>
                    </TableCell>
                    <TableCell className="max-w-md text-sm text-muted-foreground">{row.definition}</TableCell>
                    <TableCell className="text-sm text-muted-foreground">{row.category ?? "—"}</TableCell>
                    <TableCell className="text-right tabular-nums text-sm">{row.sort_order}</TableCell>
                    <TableCell>
                      <span
                        className={
                          row.is_active
                            ? "inline-flex rounded-md bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                            : "inline-flex rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                        }
                      >
                        {row.is_active ? "Active" : "Hidden"}
                      </span>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button type="button" size="sm" variant="outline" onClick={() => startEdit(row)}>
                          Edit
                        </Button>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          className="text-destructive"
                          disabled={deleteMutation.isPending}
                          onClick={() => {
                            if (window.confirm(`Remove "${row.acronym}" from the global glossary?`)) {
                              deleteMutation.mutate(row.id);
                            }
                          }}
                        >
                          Delete
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  );
}
