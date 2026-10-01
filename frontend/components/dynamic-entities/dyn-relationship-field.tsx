"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  fetchDynRecord,
  fetchDynRecords,
  type DynField,
} from "@/lib/api/modules/dynamic-entities-api";
import {
  relationFiltersFromField,
  relationFiltersToRecordFilterParams,
} from "@/lib/dynamic-entities/dyn-relation-filters";
import { cn } from "@/lib/utils";

type Resolved = {
  id: string;
  title: string | null;
  entity_slug: string | null;
};

type PickerProps = {
  field: DynField;
  value: string;
  onChange: (next: string) => void;
  required?: boolean;
};

export function DynRelationshipPicker({ field, value, onChange, required }: PickerProps) {
  const slug = field.target_entity_slug;
  const [label, setLabel] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState(false);
  const [options, setOptions] = useState<Array<{ id: string; title: string | null }>>([]);
  const [loading, setLoading] = useState(false);

  const schemaFilters = useMemo(() => relationFiltersFromField(field), [field]);
  const filterParams = useMemo(
    () => relationFiltersToRecordFilterParams(schemaFilters),
    [schemaFilters],
  );

  useEffect(() => {
    if (!value) {
      setLabel(null);
      return;
    }
    let cancelled = false;
    fetchDynRecord(value)
      .then((row) => {
        if (!cancelled) setLabel(row.title || row.id.slice(0, 8));
      })
      .catch(() => {
        if (!cancelled) setLabel(value.slice(0, 8));
      });
    return () => {
      cancelled = true;
    };
  }, [value]);

  useEffect(() => {
    if (!open || !slug) return;
    let cancelled = false;
    setLoading(true);
    const t = window.setTimeout(() => {
      fetchDynRecords(slug, {
        search: search || undefined,
        per_page: 20,
        filter: Object.keys(filterParams).length > 0 ? filterParams : undefined,
      })
        .then((page) => {
          if (!cancelled) setOptions(page.data.map((r) => ({ id: r.id, title: r.title })));
        })
        .catch(() => {
          if (!cancelled) setOptions([]);
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, 200);
    return () => {
      cancelled = true;
      window.clearTimeout(t);
    };
  }, [filterParams, open, slug, search]);

  if (!slug) {
    return (
      <Input
        value={value}
        onChange={(e) => onChange(e.target.value)}
        required={required}
        placeholder="Related record id"
      />
    );
  }

  return (
    <div className="space-y-1.5">
      {value ? (
        <div className="flex flex-wrap items-center gap-1.5">
          <Link
            href={`/dynamic-entities/records/${value}`}
            className="inline-flex max-w-full items-center rounded-md border border-border bg-muted/50 px-2 py-1 text-xs font-medium text-foreground underline-offset-2 hover:underline"
          >
            <span className="truncate">{label ?? "…"}</span>
          </Link>
          <Button
            type="button"
            variant="ghost"
            size="icon-xs"
            aria-label={`Clear ${field.label}`}
            onClick={() => onChange("")}
          >
            <X className="size-3.5" />
          </Button>
        </div>
      ) : null}
      <div className="relative">
        <Input
          value={open ? search : ""}
          placeholder={value ? "Change…" : `Search ${field.label.toLowerCase()}…`}
          onFocus={() => setOpen(true)}
          onChange={(e) => {
            setSearch(e.target.value);
            setOpen(true);
          }}
          required={required && !value}
        />
        {open ? (
          <div className="absolute z-40 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-border bg-popover shadow-md">
            {schemaFilters.length > 0 ? (
              <p className="border-b border-border px-3 py-1.5 text-[10px] text-muted-foreground">
                Filtered by field rules ({schemaFilters.length})
              </p>
            ) : null}
            {loading ? (
              <p className="px-3 py-2 text-xs text-muted-foreground">Searching…</p>
            ) : options.length === 0 ? (
              <p className="px-3 py-2 text-xs text-muted-foreground">No matches</p>
            ) : (
              options.map((opt) => (
                <button
                  key={opt.id}
                  type="button"
                  className={cn(
                    "flex w-full px-3 py-2 text-left text-sm hover:bg-muted",
                    opt.id === value && "bg-muted",
                  )}
                  onClick={() => {
                    onChange(opt.id);
                    setLabel(opt.title || opt.id.slice(0, 8));
                    setSearch("");
                    setOpen(false);
                  }}
                >
                  <span className="truncate">{opt.title || opt.id.slice(0, 8)}</span>
                </button>
              ))
            )}
            <button
              type="button"
              className="w-full border-t border-border px-3 py-1.5 text-left text-xs text-muted-foreground hover:bg-muted"
              onClick={() => setOpen(false)}
            >
              Close
            </button>
          </div>
        ) : null}
      </div>
    </div>
  );
}

export function DynRelationshipView({
  field,
  raw,
  resolved,
}: {
  field: DynField;
  raw: unknown;
  resolved?: Resolved | null;
}) {
  const id = typeof raw === "string" ? raw : raw != null ? String(raw) : "";
  if (!id) return <span className="text-muted-foreground">N/A</span>;

  const title = resolved?.title?.trim() || null;
  const href = `/dynamic-entities/records/${id}`;

  return (
    <Link
      href={href}
      className="text-sm font-medium text-foreground underline underline-offset-2 hover:text-foreground/80"
      title={field.label}
    >
      <span className="break-all">{title || id.slice(0, 8)}</span>
    </Link>
  );
}
