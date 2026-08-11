"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymText } from "@/components/help/acronym-text";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { createRolloutPublicHolidaysTableColumns } from "@/components/rollout/rollout-public-holidays-table-columns";
import { RolloutGeographySelect } from "@/components/rollout/rollout-geography-select";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import {
  createTenantPublicHoliday,
  deleteTenantPublicHoliday,
  fetchTenantPublicHolidays,
  seedPhilippinesPublicHolidays,
  updateTenantPublicHoliday,
} from "@/lib/api/modules/rollout-api";
import type { TenantPublicHolidayRow } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type HolidayFormState = {
  holiday_date: string;
  name: string;
  region: string;
};

const emptyForm = (): HolidayFormState => ({
  holiday_date: "",
  name: "",
  region: "",
});

export function RolloutPublicHolidaysPanel({ canConfigure }: { canConfigure: boolean }) {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const currentYear = new Date().getFullYear();
  const [year, setYear] = useState(currentYear);
  const [regionFilter, setRegionFilter] = useState<"all" | "national" | "regional">("all");
  const [sheetOpen, setSheetOpen] = useState(false);
  const [editing, setEditing] = useState<TenantPublicHolidayRow | null>(null);
  const [form, setForm] = useState<HolidayFormState>(emptyForm());

  const holidaysQuery = useQuery({
    queryKey: ["project-one", "public-holidays", year],
    queryFn: () => fetchTenantPublicHolidays(year),
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ["project-one", "public-holidays"] });
    void queryClient.invalidateQueries({ queryKey: ["project-one", "rollout-playbook"] });
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        holiday_date: form.holiday_date,
        name: form.name.trim(),
        region: form.region.trim() || null,
      };
      if (editing) {
        return updateTenantPublicHoliday(editing.id, payload);
      }
      return createTenantPublicHoliday(payload);
    },
    onSuccess: () => {
      invalidate();
      setSheetOpen(false);
      setEditing(null);
      setForm(emptyForm());
      notify({
        level: "success",
        title: editing ? "Holiday updated" : "Holiday added",
        message: "Working-day SLA math will exclude this date.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not save holiday",
        message: getErrorMessage(error),
      }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteTenantPublicHoliday(id),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Holiday removed", message: "Date is no longer excluded from SLAs." });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not delete holiday",
        message: getErrorMessage(error),
      }),
  });

  const seedMutation = useMutation({
    mutationFn: () => seedPhilippinesPublicHolidays(year),
    onSuccess: (data) => {
      invalidate();
      notify({
        level: "success",
        title: "PH holidays seeded",
        message: `${data.holidays.length} dates loaded for ${year}.`,
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Seed failed",
        message: getErrorMessage(error),
      }),
  });

  const rows = holidaysQuery.data?.holidays ?? [];
  const filteredRows = rows.filter((row) => {
    if (regionFilter === "national") return !row.region;
    if (regionFilter === "regional") return Boolean(row.region);
    return true;
  });

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm());
    setSheetOpen(true);
  };

  const openEdit = (row: TenantPublicHolidayRow) => {
    setEditing(row);
    setForm({
      holiday_date: row.holiday_date,
      name: row.name,
      region: row.region ?? "",
    });
    setSheetOpen(true);
  };

  const columns = useMemo(
    () =>
      createRolloutPublicHolidaysTableColumns({
        canConfigure,
        onEdit: openEdit,
        onDelete: (id) => deleteMutation.mutate(id),
        deletePending: deleteMutation.isPending,
      }),
    [canConfigure, deleteMutation],
  );

  return (
    <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-medium text-foreground">Public holiday calendar</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            <AcronymText text="Mon–Fri holidays excluded from rollout SLA math. National dates apply to all rollouts; territory-scoped dates apply when the rollout Territory matches." />
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            Scope
            <select
              className="h-9 rounded-md border border-border bg-background px-2 text-sm"
              value={regionFilter}
              onChange={(e) => setRegionFilter(e.target.value as typeof regionFilter)}
            >
              <option value="all">All</option>
              <option value="national">National</option>
              <option value="regional">Territory-scoped</option>
            </select>
          </label>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            Year
            <select
              className="h-9 rounded-md border border-border bg-background px-2 text-sm"
              value={year}
              onChange={(e) => setYear(Number(e.target.value))}
            >
              {[currentYear - 1, currentYear, currentYear + 1].map((y) => (
                <option key={y} value={y}>
                  {y}
                </option>
              ))}
            </select>
          </label>
          {canConfigure ? (
            <>
              <Button
                size="sm"
                variant="outline"
                disabled={seedMutation.isPending}
                onClick={() => seedMutation.mutate()}
              >
                Seed PH {year}
              </Button>
              <Button size="sm" onClick={openCreate}>
                Add holiday
              </Button>
            </>
          ) : null}
        </div>
      </div>

      <div className="mt-4 overflow-hidden rounded-lg border border-border">
        <RegistryDataTableView
          columns={columns}
          data={filteredRows}
          getRowId={(row) => row.id}
          isLoading={holidaysQuery.isLoading}
          isEmpty={!holidaysQuery.isLoading && filteredRows.length === 0}
          emptyMessage={`No holidays for ${year}${regionFilter !== "all" ? ` (${regionFilter})` : ""}.${
            canConfigure ? " Seed the PH catalog or add a custom date." : ""
          }`}
          enableColumnVisibility
          columnVisibilityStorageKey="toweros.table.columns.project-one.public-holidays"
          scrollClassName="max-h-none"
        />
      </div>

      <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
        <SheetContent className="sm:max-w-md">
          <SheetHeader>
            <SheetTitle>{editing ? "Edit holiday" : "Add holiday"}</SheetTitle>
            <SheetDescription>
              Custom holidays apply to the selected calendar year. Leave territory blank for national (all
              rollouts).
            </SheetDescription>
          </SheetHeader>
          <div className="mt-6 space-y-4">
            <FormInput
              label="Date"
              date
              value={form.holiday_date}
              onChange={(e) => setForm((prev) => ({ ...prev, holiday_date: e.target.value }))}
            />
            <FormInput
              label="Name"
              value={form.name}
              onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))}
              placeholder="e.g. Special non-working day"
            />
            <RolloutGeographySelect
              kind="territory"
              label="Territory scope (optional)"
              value={form.region}
              onChange={(code) => setForm((prev) => ({ ...prev, region: code }))}
            />
            <div className="flex justify-end gap-2 pt-2">
              <Button variant="outline" onClick={() => setSheetOpen(false)}>
                Cancel
              </Button>
              <Button
                disabled={!form.holiday_date || !form.name.trim() || saveMutation.isPending}
                onClick={() => saveMutation.mutate()}
              >
                {editing ? "Save changes" : "Add holiday"}
              </Button>
            </div>
          </div>
        </SheetContent>
      </Sheet>
    </section>
  );
}
