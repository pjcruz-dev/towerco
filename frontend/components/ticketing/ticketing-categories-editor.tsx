"use client";

import { useEffect, useMemo, useState } from "react";
import { Pencil, Plus, Trash2 } from "lucide-react";

import { slugifyTicketingCategory } from "@/components/ticketing/ticketing-utils";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import type { TicketingCategoryOption, TicketingCategoryPack } from "@/modules/ticketing/types";

type Props = {
  rows: TicketingCategoryOption[];
  packs: TicketingCategoryPack[];
  applyingPack: boolean;
  onChange: (rows: TicketingCategoryOption[]) => void;
  onApplyPack: (packId: string) => void;
};

type Draft = {
  id: string;
  label: string;
  sla_response_minutes: string;
  sla_escalation_minutes: string;
  /** Original slug when editing (empty for create). */
  originalId: string | null;
};

function emptyDraft(): Draft {
  return {
    id: "",
    label: "",
    sla_response_minutes: "",
    sla_escalation_minutes: "",
    originalId: null,
  };
}

function minutesToInput(value?: number | null): string {
  return value == null ? "" : String(value);
}

function parseOptionalMinutes(value: string): number | null {
  const trimmed = value.trim();
  if (!trimmed) return null;
  const n = Number(trimmed);
  return Number.isFinite(n) && n >= 1 ? Math.floor(n) : null;
}

function formatSlaCell(row: TicketingCategoryOption): string {
  const response = row.sla_response_minutes;
  const escalation = row.sla_escalation_minutes;
  if (response == null && escalation == null) return "Default";
  const parts: string[] = [];
  if (response != null) parts.push(`${response}m resp`);
  if (escalation != null) parts.push(`${escalation}m esc`);
  return parts.join(" · ");
}

export function TicketingCategoriesEditor({
  rows,
  packs,
  applyingPack,
  onChange,
  onApplyPack,
}: Props) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [draft, setDraft] = useState<Draft>(emptyDraft());
  const [formError, setFormError] = useState<string | null>(null);
  const [slugTouched, setSlugTouched] = useState(false);

  const sortedRows = useMemo(
    () => [...rows].sort((a, b) => a.label.localeCompare(b.label, undefined, { sensitivity: "base" })),
    [rows],
  );

  useEffect(() => {
    if (!dialogOpen) return;
    if (!slugTouched && draft.originalId === null) {
      setDraft((prev) => ({ ...prev, id: slugifyTicketingCategory(prev.label) }));
    }
  }, [draft.label, draft.originalId, dialogOpen, slugTouched]);

  function openCreate() {
    setDraft(emptyDraft());
    setSlugTouched(false);
    setFormError(null);
    setDialogOpen(true);
  }

  function openEdit(row: TicketingCategoryOption) {
    setDraft({
      id: row.id,
      label: row.label,
      sla_response_minutes: minutesToInput(row.sla_response_minutes),
      sla_escalation_minutes: minutesToInput(row.sla_escalation_minutes),
      originalId: row.id,
    });
    setSlugTouched(true);
    setFormError(null);
    setDialogOpen(true);
  }

  function removeRow(row: TicketingCategoryOption) {
    if (rows.length <= 1) return;
    onChange(rows.filter((item) => item.id !== row.id));
  }

  function submitDraft() {
    const label = draft.label.trim();
    const id = slugifyTicketingCategory(draft.id || label);
    if (!label) {
      setFormError("Label is required.");
      return;
    }
    if (!id) {
      setFormError("Slug is required.");
      return;
    }

    const duplicate = rows.some((item) => item.id === id && item.id !== draft.originalId);
    if (duplicate) {
      setFormError("That slug is already in use.");
      return;
    }

    const next: TicketingCategoryOption = {
      id,
      label,
      sla_response_minutes: parseOptionalMinutes(draft.sla_response_minutes),
      sla_escalation_minutes: parseOptionalMinutes(draft.sla_escalation_minutes),
    };

    if (draft.originalId) {
      onChange(rows.map((item) => (item.id === draft.originalId ? next : item)));
    } else {
      onChange([...rows, next]);
    }

    setDialogOpen(false);
    setDraft(emptyDraft());
    setFormError(null);
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="text-sm font-medium text-foreground">Categories</h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Labels appear on tickets; optional SLA minutes override tenant defaults. {rows.length}{" "}
            configured.
          </p>
        </div>
        <Button type="button" size="sm" variant="outline" className="shrink-0" onClick={openCreate}>
          <Plus className="mr-1.5 h-4 w-4" aria-hidden />
          Add category
        </Button>
      </div>

      <div className="max-h-[320px] overflow-auto rounded-lg border border-border">
        <table className="w-full text-left text-[13px]">
          <thead className="sticky top-0 z-10 border-b border-border bg-muted/95 text-xs font-medium text-muted-foreground backdrop-blur-sm">
            <tr>
              <th className="px-3 py-2">Label</th>
              <th className="hidden px-3 py-2 sm:table-cell">Slug</th>
              <th className="hidden px-3 py-2 md:table-cell">SLA</th>
              <th className="w-10 px-2 py-2">
                <span className="sr-only">Actions</span>
              </th>
            </tr>
          </thead>
          <tbody>
            {sortedRows.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-3 py-6 text-center text-xs text-muted-foreground">
                  No categories yet. Add one or apply a preset pack.
                </td>
              </tr>
            ) : (
              sortedRows.map((row) => (
                <tr key={row.id} className="border-b border-border last:border-0 hover:bg-muted/20">
                  <td className="max-w-[1px] px-3 py-2 align-middle">
                    <div className="truncate text-foreground">{row.label}</div>
                    <div className="truncate font-mono text-[11px] text-muted-foreground sm:hidden">
                      {row.id}
                    </div>
                    <div className="truncate text-[11px] text-muted-foreground md:hidden">
                      {formatSlaCell(row)}
                    </div>
                  </td>
                  <td className="hidden max-w-[1px] px-3 py-2 align-middle sm:table-cell">
                    <code className="truncate text-xs text-muted-foreground">{row.id}</code>
                  </td>
                  <td className="hidden px-3 py-2 align-middle text-xs text-muted-foreground md:table-cell">
                    {formatSlaCell(row)}
                  </td>
                  <td className="px-1 py-1.5 text-right align-middle">
                    <RowActionsMenu
                      label={`Actions for ${row.label}`}
                      items={[
                        {
                          key: "edit",
                          label: "Edit",
                          icon: <Pencil className="size-4" />,
                          onSelect: () => openEdit(row),
                        },
                        {
                          key: "delete",
                          label: "Delete",
                          icon: <Trash2 className="size-4" />,
                          destructive: true,
                          disabled: rows.length <= 1,
                          onSelect: () => removeRow(row),
                        },
                      ]}
                    />
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {packs.length > 0 ? (
        <div className="space-y-2">
          <p className="text-xs font-medium text-muted-foreground">Preset packs</p>
          <div className="flex flex-wrap gap-2">
            {packs.map((pack) => (
              <Button
                key={pack.id}
                type="button"
                size="sm"
                variant="outline"
                disabled={applyingPack}
                title={pack.description}
                onClick={() => onApplyPack(pack.id)}
              >
                Apply {pack.label}
              </Button>
            ))}
          </div>
        </div>
      ) : null}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="w-[min(calc(100vw-2rem),420px)]">
          <DialogHeader>
            <DialogTitle>{draft.originalId ? "Edit category" : "Add category"}</DialogTitle>
            <DialogDescription>
              Choose a clear label. Leave SLA blank to inherit tenant defaults (still scaled by
              priority).
            </DialogDescription>
          </DialogHeader>
          <DialogBody className="space-y-3">
            <div className="space-y-2">
              <Label htmlFor="category-label">Label</Label>
              <Input
                id="category-label"
                value={draft.label}
                placeholder="Hardware · Workstations"
                autoFocus
                onChange={(e) => setDraft((prev) => ({ ...prev, label: e.target.value }))}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="category-slug">Slug</Label>
              <Input
                id="category-slug"
                value={draft.id}
                placeholder="hw_workstations"
                className="font-mono text-xs"
                onChange={(e) => {
                  setSlugTouched(true);
                  setDraft((prev) => ({
                    ...prev,
                    id: e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, "").slice(0, 64),
                  }));
                }}
              />
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="category-sla-response">Response (minutes)</Label>
                <Input
                  id="category-sla-response"
                  type="number"
                  min={1}
                  placeholder="Default"
                  value={draft.sla_response_minutes}
                  onChange={(e) =>
                    setDraft((prev) => ({ ...prev, sla_response_minutes: e.target.value }))
                  }
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="category-sla-escalation">Escalation (minutes)</Label>
                <Input
                  id="category-sla-escalation"
                  type="number"
                  min={1}
                  placeholder="Default"
                  value={draft.sla_escalation_minutes}
                  onChange={(e) =>
                    setDraft((prev) => ({ ...prev, sla_escalation_minutes: e.target.value }))
                  }
                />
              </div>
            </div>
            {formError ? <p className="text-xs text-destructive">{formError}</p> : null}
          </DialogBody>
          <DialogFooter>
            <Button type="button" size="sm" variant="outline" onClick={() => setDialogOpen(false)}>
              Cancel
            </Button>
            <Button type="button" size="sm" onClick={submitDraft}>
              {draft.originalId ? "Save" : "Add"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
