"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { createRolloutCustomPhaseCatalogTableColumns } from "@/components/platform/rollout-custom-phase-catalog-table-columns";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformCreateRolloutCustomPhase,
  platformDeactivateRolloutCustomPhase,
  platformListRolloutCustomPhases,
} from "@/lib/api/modules/platform-api";
import { useNotificationStore } from "@/stores/notification-store";

const templateOptions = [
  { value: "bts", label: "BTS" },
  { value: "rtb", label: "RTB" },
  { value: "colocation", label: "Colocation" },
] as const;

export function RolloutCustomPhaseCatalog() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const [showCreate, setShowCreate] = useState(false);
  const [phaseKey, setPhaseKey] = useState("lgu_clearance");
  const [label, setLabel] = useState("LGU Clearance");
  const [description, setDescription] = useState("");
  const [ownerRole, setOwnerRole] = useState("saq");
  const [defaultGate, setDefaultGate] = useState("");
  const [wdStart, setWdStart] = useState("10");
  const [wdEnd, setWdEnd] = useState("14");
  const [countsTowardSla, setCountsTowardSla] = useState(false);
  const [selectedTemplates, setSelectedTemplates] = useState<string[]>(["bts", "rtb"]);

  const phasesQuery = useQuery({
    queryKey: ["platform", "rollout-custom-phases"],
    queryFn: () => platformListRolloutCustomPhases(),
    retry: 1,
  });

  const createMutation = useMutation({
    mutationFn: () =>
      platformCreateRolloutCustomPhase({
        phase_key: phaseKey,
        label,
        description: description || undefined,
        owner_role: ownerRole || undefined,
        default_anchor: "tssr_approved",
        default_working_day_start: Number.parseInt(wdStart, 10) || 0,
        default_working_day_end: Number.parseInt(wdEnd, 10) || 0,
        default_gate: defaultGate || undefined,
        counts_toward_sla: countsTowardSla,
        applicable_templates: selectedTemplates,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "rollout-custom-phases"] });
      notify({ level: "success", title: "Custom phase created", message: "Add it to a policy bundle from the policy editor." });
      setShowCreate(false);
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not create phase", message: getErrorMessage(error) }),
  });

  const deactivateMutation = useMutation({
    mutationFn: (id: string) => platformDeactivateRolloutCustomPhase(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "rollout-custom-phases"] });
      notify({ level: "success", title: "Phase archived", message: "Existing policy timelines keep their snapshot." });
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not archive phase", message: getErrorMessage(error) }),
  });

  const phases = phasesQuery.data ?? [];

  const columns = useMemo(
    () =>
      createRolloutCustomPhaseCatalogTableColumns({
        onArchive: (id) => deactivateMutation.mutate(id),
        archivePending: deactivateMutation.isPending,
      }),
    [deactivateMutation],
  );

  const toggleTemplate = (value: string) => {
    setSelectedTemplates((prev) =>
      prev.includes(value) ? prev.filter((item) => item !== value) : [...prev, value],
    );
  };

  return (
    <section className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-semibold text-foreground">Custom phase catalog</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Reusable timeline phases (e.g. LGU clearance, finance capex release). Add them to policy bundles; new
            rollouts instantiate them from the tenant snapshot.
          </p>
        </div>
        <Button type="button" variant="outline" onClick={() => setShowCreate((open) => !open)}>
          {showCreate ? "Cancel" : "Create custom phase"}
        </Button>
      </div>

      {showCreate ? (
        <div className="grid gap-4 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-3">
          <FormInput label="Phase key" value={phaseKey} onChange={(e) => setPhaseKey(e.target.value)} />
          <FormInput label="Label" value={label} onChange={(e) => setLabel(e.target.value)} />
          <FormInput label="Owner role" value={ownerRole} onChange={(e) => setOwnerRole(e.target.value)} />
          <FormInput label="WD start" type="number" min={0} value={wdStart} onChange={(e) => setWdStart(e.target.value)} />
          <FormInput label="WD end" type="number" min={0} value={wdEnd} onChange={(e) => setWdEnd(e.target.value)} />
          <FormInput label="Gate label" value={defaultGate} onChange={(e) => setDefaultGate(e.target.value)} />
          <div className="md:col-span-3">
            <FormInput label="Description" value={description} onChange={(e) => setDescription(e.target.value)} />
          </div>
          <div className="md:col-span-3 flex flex-wrap gap-4">
            <label className="flex items-center gap-2 text-sm">
              <Checkbox
                className="size-4"
                checked={countsTowardSla}
                onCheckedChange={(v) => setCountsTowardSla(v === true)}
              />
              Counts toward SLA budget
            </label>
            {templateOptions.map((option) => (
              <label key={option.value} className="flex items-center gap-2 text-sm">
                <Checkbox
                  className="size-4"
                  checked={selectedTemplates.includes(option.value)}
                  onCheckedChange={() => toggleTemplate(option.value)}
                />
                {option.label}
              </label>
            ))}
          </div>
          <div className="md:col-span-3">
            <Button
              type="button"
              disabled={createMutation.isPending || selectedTemplates.length === 0}
              onClick={() => createMutation.mutate()}
            >
              {createMutation.isPending ? "Creating…" : "Create phase"}
            </Button>
          </div>
        </div>
      ) : null}

      <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
        <RegistryDataTableView
          columns={columns}
          data={phases}
          getRowId={(row) => row.id}
          isLoading={phasesQuery.isLoading}
          isEmpty={!phasesQuery.isLoading && phases.length === 0}
          emptyMessage="No custom phases yet. Create one to extend policy timelines beyond the standard playbook."
          enableColumnVisibility
          columnVisibilityStorageKey="toweros.table.columns.platform.rollout-custom-phases"
          scrollClassName="max-h-none"
        />
      </div>
    </section>
  );
}
