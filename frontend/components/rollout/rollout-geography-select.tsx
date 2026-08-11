"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useMemo } from "react";

import { fetchRolloutGeographyLookups } from "@/lib/api/modules/rollout-api";
import type { RolloutGeographyKind, RolloutGeographyLookupRow } from "@/modules/rollout/types";
import { cn } from "@/lib/utils";

type Props = {
  kind: RolloutGeographyKind;
  label: string;
  value: string;
  onChange: (code: string) => void;
  required?: boolean;
  disabled?: boolean;
  className?: string;
  /** Keep a legacy free-text value selectable even if not in active lookups. */
  preserveValue?: boolean;
};

function optionLabel(row: RolloutGeographyLookupRow): string {
  return `${row.code} — ${row.label}`;
}

/**
 * Active geography lookup select for region / territory codes.
 */
export function RolloutGeographySelect({
  kind,
  label,
  value,
  onChange,
  required = false,
  disabled = false,
  className,
  preserveValue = true,
}: Props) {
  const query = useQuery({
    queryKey: ["project-one", "geography", kind, "active"],
    queryFn: () => fetchRolloutGeographyLookups({ kind, activeOnly: true }),
    staleTime: 60_000,
  });

  const items = query.data?.items ?? [];

  const options = useMemo(() => {
    const list = [...items];
    const normalized = value.trim();
    if (
      preserveValue &&
      normalized !== "" &&
      !list.some((row) => row.code.toLowerCase() === normalized.toLowerCase())
    ) {
      list.unshift({
        id: `legacy-${normalized}`,
        kind,
        code: normalized,
        label: `${normalized} (current)`,
        sort_order: 0,
        is_active: false,
      });
    }
    return list;
  }, [items, value, kind, preserveValue]);

  const emptyHint =
    !query.isLoading && items.length === 0
      ? kind === "region"
        ? "No regions seeded yet."
        : "No territories seeded yet."
      : null;

  return (
    <label className={cn("space-y-1.5 text-sm", className)}>
      <span className="font-medium text-foreground">
        {label}
        {required ? <span className="text-destructive"> *</span> : null}
      </span>
      <select
        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-60"
        value={value}
        required={required}
        disabled={disabled || query.isLoading}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value="">{required ? "Select…" : "None"}</option>
        {options.map((row) => (
          <option key={row.id} value={row.code}>
            {optionLabel(row)}
          </option>
        ))}
      </select>
      {query.isLoading ? <p className="text-xs text-muted-foreground">Loading lookups…</p> : null}
      {emptyHint ? (
        <p className="text-xs text-amber-800 dark:text-amber-200">
          {emptyHint}{" "}
          <Link href="/project-one/geography" className="font-medium underline-offset-4 hover:underline">
            Seed geography lookups
          </Link>
        </p>
      ) : null}
    </label>
  );
}

/** Suggest territory when region NCR (13) is chosen. */
export function suggestedTerritoryForRegion(regionCode: string): string | null {
  const code = regionCode.trim().toUpperCase();
  if (code === "13") {
    return "NCR";
  }
  return null;
}
