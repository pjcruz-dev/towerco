"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState, type ReactNode } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymLabel } from "@/components/help/acronym-label";
import { RolloutGeographySelect, suggestedTerritoryForRegion } from "@/components/rollout/rollout-geography-select";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import { fetchProjectOneProjectsIndex } from "@/lib/api/modules/project-one-api";
import { bulkUpdateRollouts, fetchRolloutAssignableUsers } from "@/lib/api/modules/rollout-api";
import type { RolloutListRow, UpdateRolloutMetadataInput } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type FieldKey = keyof UpdateRolloutMetadataInput;

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  selectedIds: string[];
  selectedRows: RolloutListRow[];
  onSuccess: () => void;
};

type FieldState = {
  apply: boolean;
  value: string;
};

const FIELD_LABELS: Record<FieldKey, ReactNode> = {
  search_ring_name: <AcronymLabel term="SR">Search ring name</AcronymLabel>,
  region: "Region",
  territory: "Territory",
  endorsement_ref: "Endorsement ref",
  endorsement_date: "Endorsement date",
  saq_owner_id: <AcronymLabel term="SAQ">SAQ owner</AcronymLabel>,
  cme_pm_id: <AcronymLabel term="CME">CME PM</AcronymLabel>,
  pmo_owner_id: <AcronymLabel term="PMO">PMO owner</AcronymLabel>,
  project_id: "Linked project",
};

function emptyFieldState(): Record<FieldKey, FieldState> {
  return {
    search_ring_name: { apply: false, value: "" },
    region: { apply: false, value: "" },
    territory: { apply: false, value: "" },
    endorsement_ref: { apply: false, value: "" },
    endorsement_date: { apply: false, value: "" },
    saq_owner_id: { apply: false, value: "" },
    cme_pm_id: { apply: false, value: "" },
    pmo_owner_id: { apply: false, value: "" },
    project_id: { apply: false, value: "" },
  };
}

function BulkFieldRow({
  label,
  apply,
  onApplyChange,
  children,
}: {
  label: ReactNode;
  apply: boolean;
  onApplyChange: (checked: boolean) => void;
  children: ReactNode;
}) {
  return (
    <div className="rounded-lg border border-border p-3">
      <label className="mb-2 flex cursor-pointer items-center gap-2 text-sm font-medium text-foreground">
        <Checkbox
          className="size-4"
          checked={apply}
          onCheckedChange={(v) => onApplyChange(v === true)}
        />
        Apply {label}
      </label>
      <div className={apply ? "" : "pointer-events-none opacity-50"}>{children}</div>
    </div>
  );
}

export function RolloutBulkEditSheet({ open, onOpenChange, selectedIds, selectedRows, onSuccess }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [fields, setFields] = useState(emptyFieldState);
  const [confirming, setConfirming] = useState(false);

  const usersQuery = useQuery({
    queryKey: ["project-one", "assignable-users"],
    queryFn: fetchRolloutAssignableUsers,
    enabled: open,
  });

  const projectsQuery = useQuery({
    queryKey: ["project-one", "projects", "rollout-bulk-edit"],
    queryFn: () => fetchProjectOneProjectsIndex({ page: 1, per_page: 100 }),
    enabled: open,
  });

  const users = usersQuery.data ?? [];
  const projects = projectsQuery.data?.data ?? [];

  useEffect(() => {
    if (!open) {
      setFields(emptyFieldState());
      setConfirming(false);
    }
  }, [open]);

  const appliedFields = useMemo(
    () => (Object.keys(fields) as FieldKey[]).filter((key) => fields[key].apply),
    [fields],
  );

  const previewRefs = useMemo(
    () => selectedRows.slice(0, 5).map((row) => row.rollout_ref),
    [selectedRows],
  );

  function setField<K extends FieldKey>(key: K, patch: Partial<FieldState>) {
    setFields((current) => ({
      ...current,
      [key]: { ...current[key], ...patch },
    }));
  }

  function buildUpdates(): UpdateRolloutMetadataInput {
    const updates: UpdateRolloutMetadataInput = {};

    for (const key of appliedFields) {
      const { value } = fields[key];
      if (key === "endorsement_date") {
        updates[key] = value.trim() || null;
      } else if (key.endsWith("_id")) {
        updates[key] = value || null;
      } else {
        updates[key] = value.trim() || null;
      }
    }

    return updates;
  }

  const mutation = useMutation({
    mutationFn: () =>
      bulkUpdateRollouts({
        rollout_ids: [...selectedIds],
        updates: buildUpdates(),
      }),
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      await queryClient.invalidateQueries({ queryKey: ["project-one", "projects"] });

      const skipped = result.results.filter((row) => row.status === "skipped").length;
      const failed = result.results.filter((row) => row.status === "failed").length;

      push({
        level: result.updated > 0 ? "success" : "warning",
        title: "Bulk update complete",
        message: `${result.updated} updated${skipped + failed > 0 ? ` · ${skipped + failed} skipped or failed` : ""}.`,
      });

      onSuccess();
      onOpenChange(false);
    },
    onError: (error) => {
      push({ level: "error", title: "Bulk update failed", message: getErrorMessage(error) });
    },
  });

  const canProceed = appliedFields.length > 0 && selectedIds.length > 0;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="flex w-full flex-col sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>Bulk edit rollouts</SheetTitle>
          <SheetDescription>
            {selectedIds.length} rollout{selectedIds.length === 1 ? "" : "s"} selected
            {previewRefs.length > 0 ? `: ${previewRefs.join(", ")}${selectedIds.length > 5 ? "…" : ""}` : ""}.
            Enable Apply for each field you want to overwrite.
          </SheetDescription>
        </SheetHeader>

        {!confirming ? (
          <div className="flex-1 space-y-3 overflow-y-auto px-4 py-2">
            <BulkFieldRow
              label={FIELD_LABELS.search_ring_name}
              apply={fields.search_ring_name.apply}
              onApplyChange={(checked) => setField("search_ring_name", { apply: checked })}
            >
              <FormInput
                label={FIELD_LABELS.search_ring_name}
                value={fields.search_ring_name.value}
                onChange={(event) => setField("search_ring_name", { value: event.target.value })}
              />
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.region}
              apply={fields.region.apply}
              onApplyChange={(checked) => setField("region", { apply: checked })}
            >
              <RolloutGeographySelect
                kind="region"
                label="Region"
                value={fields.region.value}
                onChange={(code) => {
                  setField("region", { value: code, apply: true });
                  const suggested = suggestedTerritoryForRegion(code);
                  if (suggested) {
                    setField("territory", { value: suggested, apply: true });
                  }
                }}
              />
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.territory}
              apply={fields.territory.apply}
              onApplyChange={(checked) => setField("territory", { apply: checked })}
            >
              <RolloutGeographySelect
                kind="territory"
                label="Territory"
                value={fields.territory.value}
                onChange={(code) => setField("territory", { value: code, apply: true })}
              />
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.endorsement_ref}
              apply={fields.endorsement_ref.apply}
              onApplyChange={(checked) => setField("endorsement_ref", { apply: checked })}
            >
              <FormInput
                label={FIELD_LABELS.endorsement_ref}
                value={fields.endorsement_ref.value}
                onChange={(event) => setField("endorsement_ref", { value: event.target.value })}
              />
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.endorsement_date}
              apply={fields.endorsement_date.apply}
              onApplyChange={(checked) => setField("endorsement_date", { apply: checked })}
            >
              <FormInput
                label={FIELD_LABELS.endorsement_date}
                date
                value={fields.endorsement_date.value}
                onChange={(event) => setField("endorsement_date", { value: event.target.value })}
              />
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.saq_owner_id}
              apply={fields.saq_owner_id.apply}
              onApplyChange={(checked) => setField("saq_owner_id", { apply: checked })}
            >
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-foreground">{FIELD_LABELS.saq_owner_id}</span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={fields.saq_owner_id.value}
                  onChange={(event) => setField("saq_owner_id", { value: event.target.value })}
                >
                  <option value="">Unassigned</option>
                  {users.map((user) => (
                    <option key={user.id} value={user.id}>
                      {user.name} · {user.email}
                    </option>
                  ))}
                </select>
              </label>
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.cme_pm_id}
              apply={fields.cme_pm_id.apply}
              onApplyChange={(checked) => setField("cme_pm_id", { apply: checked })}
            >
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-foreground">{FIELD_LABELS.cme_pm_id}</span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={fields.cme_pm_id.value}
                  onChange={(event) => setField("cme_pm_id", { value: event.target.value })}
                >
                  <option value="">Unassigned</option>
                  {users.map((user) => (
                    <option key={user.id} value={user.id}>
                      {user.name} · {user.email}
                    </option>
                  ))}
                </select>
              </label>
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.pmo_owner_id}
              apply={fields.pmo_owner_id.apply}
              onApplyChange={(checked) => setField("pmo_owner_id", { apply: checked })}
            >
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-foreground">{FIELD_LABELS.pmo_owner_id}</span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={fields.pmo_owner_id.value}
                  onChange={(event) => setField("pmo_owner_id", { value: event.target.value })}
                >
                  <option value="">Unassigned</option>
                  {users.map((user) => (
                    <option key={user.id} value={user.id}>
                      {user.name} · {user.email}
                    </option>
                  ))}
                </select>
              </label>
            </BulkFieldRow>

            <BulkFieldRow
              label={FIELD_LABELS.project_id}
              apply={fields.project_id.apply}
              onApplyChange={(checked) => setField("project_id", { apply: checked })}
            >
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-foreground">{FIELD_LABELS.project_id}</span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={fields.project_id.value}
                  onChange={(event) => setField("project_id", { value: event.target.value })}
                >
                  <option value="">No project</option>
                  {projects.map((project) => (
                    <option key={project.id} value={project.id}>
                      {project.name}
                      {project.site ? ` · ${project.site.site_code}` : ""}
                    </option>
                  ))}
                </select>
              </label>
            </BulkFieldRow>
          </div>
        ) : (
          <div className="space-y-3 px-4 py-2 text-sm text-muted-foreground">
            <p>
              You are about to update <span className="font-medium text-foreground">{selectedIds.length}</span> rollout
              {selectedIds.length === 1 ? "" : "s"} with{" "}
              <span className="font-medium text-foreground">{appliedFields.length}</span> field
              {appliedFields.length === 1 ? "" : "s"}:
            </p>
            <ul className="list-inside list-disc space-y-1">
              {appliedFields.map((key) => (
                <li key={key}>{FIELD_LABELS[key]}</li>
              ))}
            </ul>
            <p>Completed, cancelled, and batch rows are skipped automatically.</p>
          </div>
        )}

        <SheetFooter className="flex-row justify-end gap-2 border-t border-border pt-4">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          {!confirming ? (
            <Button type="button" disabled={!canProceed} onClick={() => setConfirming(true)}>
              Review changes
            </Button>
          ) : (
            <>
              <Button type="button" variant="outline" onClick={() => setConfirming(false)}>
                Back
              </Button>
              <Button type="button" disabled={mutation.isPending} onClick={() => mutation.mutate()}>
                {mutation.isPending ? "Applying…" : `Apply to ${selectedIds.length}`}
              </Button>
            </>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
