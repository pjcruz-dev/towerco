"use client";

import Link from "next/link";
import { Eye, Pencil, Plus, Trash2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { getErrorMessage } from "@/lib/api/error";
import {
  deleteDynRecord,
  fetchDynEntity,
  fetchDynRecords,
  type DynEntityDetail,
  type DynField,
  type DynRecordListRow,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  dynStatusTone,
  formatDynListCell,
} from "@/lib/dynamic-entities/dyn-list-cell-format";
import {
  dynSelectChoiceBadgeClass,
  dynSelectChoiceLabel,
} from "@/lib/dynamic-entities/select-choices";
import { cn } from "@/lib/utils";

type Props = {
  parentRecordId: string;
  entitySlug: string;
  label: string;
  foreignField?: string | null;
  canCreate: boolean;
  canEdit: boolean;
  canDelete: boolean;
  returnTo: string;
  onCountChange?: (count: number) => void;
};

export function DynRelatedRecordsPanel({
  parentRecordId,
  entitySlug,
  label,
  foreignField,
  canCreate,
  canEdit,
  canDelete,
  returnTo,
  onCountChange,
}: Props) {
  const [entity, setEntity] = useState<DynEntityDetail | null>(null);
  const [rows, setRows] = useState<DynRecordListRow[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<string | null>(null);

  const columns = useMemo(() => {
    const fields = (entity?.fields ?? []).filter(
      (f) =>
        !f.is_system_field &&
        !["actions", "workflows", "print", "id"].includes(f.name) &&
        f.show_in_table,
    );
    // Fall back to first few non-system fields when none marked show_in_table.
    const source =
      fields.length > 0
        ? fields
        : (entity?.fields ?? [])
            .filter(
              (f) =>
                !f.is_system_field &&
                !["actions", "workflows", "print", "id"].includes(f.name) &&
                f.name !== foreignField,
            )
            .slice(0, 6);
    return source.sort((a, b) => a.field_order - b.field_order);
  }, [entity, foreignField]);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const [detail, page] = await Promise.all([
        fetchDynEntity(entitySlug),
        fetchDynRecords(entitySlug, {
          per_page: 50,
          parent_record_id: parentRecordId,
          foreign_field: foreignField || undefined,
        }),
      ]);
      setEntity(detail);
      setRows(page.data);
      setTotal(page.meta.total);
      onCountChange?.(page.meta.total);
    } catch (e) {
      setError(getErrorMessage(e) || "Unable to load related records.");
      setRows([]);
      setTotal(0);
      onCountChange?.(0);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reload when tab identity changes
  }, [parentRecordId, entitySlug, foreignField]);

  const newHref = useMemo(() => {
    const params = new URLSearchParams();
    params.set("parent_record_id", parentRecordId);
    if (foreignField) params.set("foreign_field", foreignField);
    params.set("return_to", returnTo);
    return `/dynamic-entities/${entitySlug}/new?${params.toString()}`;
  }, [entitySlug, foreignField, parentRecordId, returnTo]);

  async function onDelete(row: DynRecordListRow) {
    if (!canDelete) return;
    if (!window.confirm(`Delete “${row.title || row.id.slice(0, 8)}”?`)) return;
    setBusyId(row.id);
    try {
      await deleteDynRecord(row.id);
      await load();
    } catch (e) {
      setError(getErrorMessage(e) || "Delete failed.");
    } finally {
      setBusyId(null);
    }
  }

  return (
    <Card className="rounded-xl">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2 space-y-0">
        <div className="flex items-center gap-2">
          <CardTitle className="text-base font-medium">{label}</CardTitle>
          <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">
            {total} {total === 1 ? "record" : "records"}
          </span>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant="outline" render={<Link href={`/dynamic-entities/${entitySlug}`} />}>
            Open list
          </Button>
          {canCreate ? (
            <Button size="sm" render={<Link href={newHref} />}>
              <Plus className="size-3.5" />
              Add New {label}
            </Button>
          ) : null}
        </div>
      </CardHeader>
      <CardContent>
        {error ? <p className="mb-3 text-sm text-destructive">{error}</p> : null}
        {loading ? <p className="text-sm text-muted-foreground">Loading…</p> : null}
        {!loading && rows.length === 0 ? (
          <p className="text-sm text-muted-foreground">No related records yet.</p>
        ) : null}
        {!loading && rows.length > 0 ? (
          <div className="overflow-x-auto rounded-lg border border-border">
            <table className="min-w-full text-[13px]">
              <thead className="bg-muted/50 text-left text-xs font-medium text-muted-foreground">
                <tr>
                  <th className="px-3 py-2">ID</th>
                  <th className="px-3 py-2">Status</th>
                  {columns.map((col) => (
                    <th key={col.id} className="px-3 py-2 whitespace-nowrap">
                      {col.label}
                    </th>
                  ))}
                  <th className="px-3 py-2 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row, idx) => (
                  <tr
                    key={row.id}
                    className={cn("border-t border-border/70", idx % 2 === 1 && "bg-muted/20")}
                  >
                    <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                      {row.source_external_id ?? row.id.slice(0, 8)}
                    </td>
                    <td className="px-3 py-2">
                      <RelatedStatusPill value={row.status} />
                    </td>
                    {columns.map((col) => (
                      <td key={col.id} className="px-3 py-2 whitespace-nowrap text-muted-foreground">
                        <RelatedCell field={col} row={row} />
                      </td>
                    ))}
                    <td className="px-3 py-2">
                      <div className="flex items-center justify-end gap-1">
                        <Link
                          href={`/dynamic-entities/records/${row.id}`}
                          className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                          title="View"
                        >
                          <Eye className="h-3.5 w-3.5" />
                        </Link>
                        {canEdit ? (
                          <Link
                            href={`/dynamic-entities/records/${row.id}?edit=1`}
                            className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            title="Edit"
                          >
                            <Pencil className="h-3.5 w-3.5" />
                          </Link>
                        ) : null}
                        {canDelete ? (
                          <button
                            type="button"
                            className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive disabled:opacity-50"
                            title="Delete"
                            disabled={busyId === row.id}
                            onClick={() => void onDelete(row)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </button>
                        ) : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="border-t border-border px-3 py-2 text-xs text-muted-foreground">
              Showing 1 to {rows.length} of {total} results
            </div>
          </div>
        ) : null}
      </CardContent>
    </Card>
  );
}

function RelatedCell({ field, row }: { field: DynField; row: DynRecordListRow }) {
  const value = row.columns[field.name];
  if (field.type === "select" || field.name.includes("status") || field.name.includes("type")) {
    return <RelatedStatusPill value={value} field={field} />;
  }
  if (field.name === "title" || field.name.includes("name")) {
    return (
      <Link
        href={`/dynamic-entities/records/${row.id}`}
        className="font-medium text-foreground underline-offset-4 hover:underline"
      >
        {formatDynListCell(value ?? row.title, field)}
      </Link>
    );
  }
  return <>{formatDynListCell(value, field)}</>;
}

function RelatedStatusPill({ value, field }: { value: unknown; field?: DynField }) {
  if (value === null || value === undefined || value === "") {
    return <span className="text-muted-foreground">—</span>;
  }
  const text = String(value);
  const label = field ? dynSelectChoiceLabel(field.options, text) : text;
  const badgeClass = field ? dynSelectChoiceBadgeClass(field.options, text) : null;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ${
        badgeClass ?? dynStatusTone(text)
      }`}
    >
      {label}
    </span>
  );
}
