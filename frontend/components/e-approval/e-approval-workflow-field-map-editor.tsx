"use client";

import { Plus, Trash2 } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { Select } from "@/components/ui/select";
import { useEApprovalFieldChoices } from "@/hooks/use-e-approval-field-choices";
import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";
import {
  canonicalizeFieldMapMappings,
  mergeFieldMapMappings,
  nextUnmappedFieldValue,
  normalizeFieldMapMappings,
} from "@/modules/e-approval/field-map-mappings";

type Props = {
  fields: EApprovalFormFieldInput[];
  step: EApprovalWorkflowStepInput;
  onChange: (step: EApprovalWorkflowStepInput) => void;
  approverOptions: { id: string; label: string }[];
};

type MappingRow = {
  id: string;
  value: string;
  userId: string;
};

function newRowId(): string {
  if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") {
    return crypto.randomUUID();
  }
  return `map-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function rowsFromMappings(mappings: Record<string, string>, previous: MappingRow[]): MappingRow[] {
  const unused = [...previous];
  const next: MappingRow[] = [];

  for (const [value, userId] of Object.entries(mappings)) {
    const matchIndex = unused.findIndex((row) => row.value === value);
    if (matchIndex >= 0) {
      const [row] = unused.splice(matchIndex, 1);
      next.push({ ...row, value, userId: userId ?? "" });
      continue;
    }
    next.push({ id: newRowId(), value, userId: userId ?? "" });
  }

  return next;
}

function mappingsFromRows(rows: MappingRow[]): Record<string, string> {
  const next: Record<string, string> = {};
  for (const row of rows) {
    const value = row.value.trim();
    if (!value) {
      continue;
    }
    next[value] = row.userId;
  }
  return next;
}

export function EApprovalWorkflowFieldMapEditor({ fields, step, onChange, approverOptions }: Props) {
  const sourceFieldName = (step.source_field ?? step.approverId ?? "").trim();
  const sourceField = useMemo(
    () => fields.find((field) => field.name === sourceFieldName) ?? null,
    [fields, sourceFieldName],
  );

  const { choices, isLoading } = useEApprovalFieldChoices(
    sourceField ?? { type: "text", name: sourceFieldName, label: sourceFieldName },
    Boolean(sourceField),
  );

  const mappings = useMemo(() => normalizeFieldMapMappings(step.mappings), [step.mappings]);
  const hasChoiceCatalog = choices.length > 0;
  const lastCanonicalizedRef = useRef<string>("");
  const [rows, setRows] = useState<MappingRow[]>(() => rowsFromMappings(mappings, []));

  useEffect(() => {
    setRows((previous) => {
      const synced = rowsFromMappings(mappings, previous);
      const syncedIds = new Set(synced.map((row) => row.id));
      // Empty drafts are not stored in mappings yet — keep them so Add mapping works.
      const drafts = previous.filter(
        (row) => row.value.trim() === "" && !syncedIds.has(row.id),
      );
      return [...synced, ...drafts];
    });
  }, [mappings]);

  useEffect(() => {
    if (!hasChoiceCatalog || isLoading) {
      return;
    }

    const canonical = canonicalizeFieldMapMappings(mappings, choices);
    const fingerprint = JSON.stringify(canonical);
    if (fingerprint === lastCanonicalizedRef.current || fingerprint === JSON.stringify(mappings)) {
      lastCanonicalizedRef.current = fingerprint;
      return;
    }

    lastCanonicalizedRef.current = fingerprint;
    onChange({ ...step, mappings: canonical });
  }, [choices, hasChoiceCatalog, isLoading, mappings, onChange, step]);

  const commitRows = (nextRows: MappingRow[]) => {
    setRows(nextRows);
    onChange({ ...step, mappings: mappingsFromRows(nextRows) });
  };

  const updateMappings = (nextMappings: Record<string, string>) => {
    onChange({ ...step, mappings: nextMappings });
  };

  const loadAllOptions = () => {
    updateMappings(mergeFieldMapMappings(mappings, choices));
  };

  const addMappingRow = () => {
    if (hasChoiceCatalog) {
      const nextValue = nextUnmappedFieldValue(choices, mappings);
      if (nextValue === "") {
        return;
      }
      updateMappings({ ...mappings, [nextValue]: "" });
      return;
    }

    // Local draft only until the value is typed — empty keys cannot live in mappings.
    setRows((previous) => [...previous, { id: newRowId(), value: "", userId: "" }]);
  };

  return (
    <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="min-w-0">
          <p className="text-xs font-medium text-foreground">Value → approver mappings</p>
          <p className="text-xs text-muted-foreground">
            {hasChoiceCatalog
              ? "Pick values from the field options — no manual typing."
              : "Type each field value, then choose its approver. Add options on Design for a dropdown source."}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          {hasChoiceCatalog ? (
            <Button type="button" size="sm" variant="outline" onClick={loadAllOptions}>
              Load all options
            </Button>
          ) : null}
          <Button type="button" size="sm" variant="outline" onClick={addMappingRow} disabled={isLoading}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Add mapping
          </Button>
        </div>
      </div>

      {isLoading ? <RefreshingHint label="Loading options" /> : null}

      {rows.length === 0 ? (
        <p className="text-xs text-muted-foreground">
          {hasChoiceCatalog
            ? 'Click "Load all options" to map every value, or add mappings one at a time.'
            : "No mappings yet. Add a row for each value (e.g. Taytay)."}
        </p>
      ) : null}

      <div className="space-y-3">
        {rows.map((row) => (
          <div
            key={row.id}
            className="space-y-2 rounded-lg border border-border/50 bg-card/60 p-3"
          >
            <div className="flex items-start justify-between gap-2">
              <p className="text-[11px] font-medium text-muted-foreground">Mapping</p>
              <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                className="shrink-0 text-muted-foreground hover:text-destructive"
                aria-label="Remove mapping"
                onClick={() => commitRows(rows.filter((item) => item.id !== row.id))}
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
            <label className="block space-y-1">
              <span className="text-xs font-medium text-muted-foreground">Field value</span>
              {hasChoiceCatalog ? (
                <Select
                  value={row.value || ""}
                  onChange={(e) => {
                    const nextValue = e.target.value;
                    commitRows(
                      rows.map((item) =>
                        item.id === row.id ? { ...item, value: nextValue } : item,
                      ),
                    );
                  }}
                >
                  <option value="" disabled>
                    Select value
                  </option>
                  {choices.map((choice) => (
                    <option
                      key={choice.value}
                      value={choice.value}
                      disabled={choice.value !== row.value && choice.value in mappings}
                    >
                      {choice.label}
                    </option>
                  ))}
                </Select>
              ) : (
                <Input
                  value={row.value}
                  className="h-9 w-full text-sm"
                  placeholder="Field value (e.g. Taytay)"
                  onChange={(e) => {
                    const nextValue = e.target.value;
                    setRows((previous) =>
                      previous.map((item) =>
                        item.id === row.id ? { ...item, value: nextValue } : item,
                      ),
                    );
                  }}
                  onBlur={() => {
                    onChange({ ...step, mappings: mappingsFromRows(rows) });
                  }}
                />
              )}
            </label>
            <label className="block space-y-1">
              <span className="text-xs font-medium text-muted-foreground">Approver</span>
              <Select
                value={row.userId || ""}
                onChange={(e) => {
                  commitRows(
                    rows.map((item) =>
                      item.id === row.id ? { ...item, userId: e.target.value } : item,
                    ),
                  );
                }}
              >
                <option value="">Select approver</option>
                {approverOptions.map((user) => (
                  <option key={user.id} value={user.id}>
                    {user.label}
                  </option>
                ))}
              </Select>
            </label>
          </div>
        ))}
      </div>

      <label className="block space-y-1">
        <span className="text-xs font-medium text-muted-foreground">Default approver (optional)</span>
        <Select
          value={step.default_approver_id ?? ""}
          onChange={(e) => onChange({ ...step, default_approver_id: e.target.value || undefined })}
        >
          <option value="">None — unmapped values need a mapping</option>
          {approverOptions.map((user) => (
            <option key={user.id} value={user.id}>
              {user.label}
            </option>
          ))}
        </Select>
      </label>
    </div>
  );
}
