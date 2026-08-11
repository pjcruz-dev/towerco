"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { createRolloutGeographyTableColumns } from "@/components/rollout/rollout-geography-table-columns";
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
  createRolloutGeographyLookup,
  deleteRolloutGeographyLookup,
  fetchRolloutGeographyLookups,
  seedRolloutGeographyDefaults,
  updateRolloutGeographyLookup,
} from "@/lib/api/modules/rollout-api";
import type { RolloutGeographyKind, RolloutGeographyLookupRow } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type FormState = {
  code: string;
  label: string;
  sort_order: string;
  is_active: boolean;
};

const emptyForm = (): FormState => ({
  code: "",
  label: "",
  sort_order: "",
  is_active: true,
});

export function RolloutGeographyPanel({ canConfigure }: { canConfigure: boolean }) {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const [kind, setKind] = useState<RolloutGeographyKind>("region");
  const [showInactive, setShowInactive] = useState(true);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<RolloutGeographyLookupRow | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm());

  const query = useQuery({
    queryKey: ["project-one", "geography", kind],
    queryFn: () => fetchRolloutGeographyLookups({ kind }),
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ["project-one", "geography"] });
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        code: form.code.trim(),
        label: form.label.trim(),
        sort_order: form.sort_order.trim() === "" ? null : Number(form.sort_order),
        is_active: form.is_active,
      };
      if (editing) {
        return updateRolloutGeographyLookup(editing.id, payload);
      }
      return createRolloutGeographyLookup({ kind, ...payload });
    },
    onSuccess: () => {
      const wasEdit = Boolean(editing);
      invalidate();
      setDialogOpen(false);
      setEditing(null);
      setForm(emptyForm());
      notify({
        level: "success",
        title: wasEdit ? "Lookup updated" : "Lookup added",
        message: kind === "region" ? "Region list updated." : "Territory list updated.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not save lookup",
        message: getErrorMessage(error),
      }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteRolloutGeographyLookup(id),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Lookup deleted", message: "Row removed from this tenant." });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not delete lookup",
        message: getErrorMessage(error),
      }),
  });

  const toggleMutation = useMutation({
    mutationFn: (row: RolloutGeographyLookupRow) =>
      updateRolloutGeographyLookup(row.id, { is_active: !row.is_active }),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Status updated", message: "Active flag saved." });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not update status",
        message: getErrorMessage(error),
      }),
  });

  const seedMutation = useMutation({
    mutationFn: () => seedRolloutGeographyDefaults(),
    onSuccess: (data) => {
      invalidate();
      notify({
        level: "success",
        title: "Defaults seeded",
        message: `${data.created} new row${data.created === 1 ? "" : "s"} added · ${data.total} total.`,
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Seed failed",
        message: getErrorMessage(error),
      }),
  });

  const rows = (query.data?.items ?? []).filter((row) => (showInactive ? true : row.is_active));
  const kindLabel = kind === "region" ? "region" : "territory";

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm());
    setDialogOpen(true);
  };

  const openEdit = (row: RolloutGeographyLookupRow) => {
    setEditing(row);
    setForm({
      code: row.code,
      label: row.label,
      sort_order: String(row.sort_order),
      is_active: row.is_active,
    });
    setDialogOpen(true);
  };

  const closeDialog = () => {
    setDialogOpen(false);
    setEditing(null);
    setForm(emptyForm());
  };

  const pending = saveMutation.isPending || deleteMutation.isPending || toggleMutation.isPending;
  const canSubmit = form.code.trim() !== "" && form.label.trim() !== "" && !saveMutation.isPending;

  const columns = useMemo(
    () =>
      createRolloutGeographyTableColumns({
        canConfigure,
        onEdit: openEdit,
        onDelete: (id) => deleteMutation.mutate(id),
        onToggleActive: (row) => toggleMutation.mutate(row),
        pending,
      }),
    [canConfigure, pending, deleteMutation, toggleMutation],
  );

  return (
    <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
      <div className="flex flex-col gap-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0 max-w-2xl">
            <h2 className="text-base font-medium text-foreground">Regions &amp; territories</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Codes used on rollout Site profile. Regions are PSA admin codes; territories drive holiday calendars
              and TCO site IDs.
            </p>
          </div>
          {canConfigure ? (
            <div className="flex flex-wrap items-center gap-2">
              <Button
                size="sm"
                variant="outline"
                disabled={seedMutation.isPending}
                onClick={() => seedMutation.mutate()}
              >
                {seedMutation.isPending ? "Seeding…" : "Seed defaults"}
              </Button>
              <Button size="sm" onClick={openCreate}>
                Add {kindLabel}
              </Button>
            </div>
          ) : null}
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
          <div className="inline-flex rounded-md border border-border bg-muted/30 p-0.5">
            <Button
              size="sm"
              variant={kind === "region" ? "default" : "ghost"}
              className={cn("h-8", kind !== "region" && "text-muted-foreground")}
              onClick={() => setKind("region")}
            >
              Regions
            </Button>
            <Button
              size="sm"
              variant={kind === "territory" ? "default" : "ghost"}
              className={cn("h-8", kind !== "territory" && "text-muted-foreground")}
              onClick={() => setKind("territory")}
            >
              Territories
            </Button>
          </div>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            <Checkbox
              className="size-4"
              checked={showInactive}
              onCheckedChange={(v) => setShowInactive(v === true)}
            />
            Show inactive
          </label>
        </div>
      </div>

      <div className="mt-4">
        <RegistryDataTableView
          columns={columns}
          data={rows}
          getRowId={(row) => row.id}
          isLoading={query.isLoading}
          isEmpty={!query.isLoading && rows.length === 0}
          emptyMessage={
            canConfigure
              ? "No rows yet. Seed defaults or add a lookup."
              : "No geography lookups configured."
          }
          enableColumnVisibility
          columnVisibilityStorageKey={`toweros.table.columns.project-one.geography.${kind}`}
          scrollClassName="max-h-none"
        />
      </div>

      <Dialog
        open={dialogOpen}
        onOpenChange={(open) => {
          if (!open) {
            closeDialog();
            return;
          }
          setDialogOpen(true);
        }}
      >
        <DialogContent className="w-[min(calc(100vw-2rem),420px)]">
          <DialogHeader>
            <DialogTitle>
              {editing ? "Edit" : "Add"} {kindLabel}
            </DialogTitle>
            <DialogDescription>
              {kind === "region"
                ? "Use PSA region codes (e.g. 13 for NCR). Stored on rollouts for reporting."
                : "Use telecom territory codes (e.g. NCR, NLZ). Used for holiday calendars and TCO IDs."}
            </DialogDescription>
          </DialogHeader>

          <form
            id="geography-lookup-form"
            onSubmit={(e) => {
              e.preventDefault();
              if (!canSubmit) return;
              saveMutation.mutate();
            }}
          >
            <DialogBody className="space-y-4">
              <FormInput
                label="Code"
                value={form.code}
                onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))}
                required
                autoFocus={!editing}
                placeholder={kind === "region" ? "e.g. 13" : "e.g. NCR"}
              />
              <FormInput
                label="Label"
                value={form.label}
                onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))}
                required
                placeholder={kind === "region" ? "Region 13 — NCR" : "National Capital Region"}
              />
              <FormInput
                label="Sort order"
                type="number"
                value={form.sort_order}
                onChange={(e) => setForm((f) => ({ ...f, sort_order: e.target.value }))}
                placeholder="Optional — lower sorts first"
              />
              <label className="flex items-center gap-2.5 text-sm text-foreground">
                <Checkbox
                  className="size-4"
                  checked={form.is_active}
                  onCheckedChange={(v) => setForm((f) => ({ ...f, is_active: v === true }))}
                />
                Active (available in dropdowns)
              </label>
            </DialogBody>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={closeDialog}>
                Cancel
              </Button>
              <Button type="submit" form="geography-lookup-form" disabled={!canSubmit}>
                {saveMutation.isPending ? "Saving…" : editing ? "Save changes" : "Save"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </section>
  );
}
