"use client";

import { useQuery } from "@tanstack/react-query";
import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

import { DatePicker } from "@/components/ui/date-picker";
import { DateRangePicker } from "@/components/ui/date-range-picker";
import {
  parseDateRangeValue,
  serializeDateRangeValue,
} from "@/modules/e-approval/field-type-options";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { SelectField } from "@/components/ui/select-field";
import { Textarea } from "@/components/ui/textarea";
import { useEApprovalFieldChoices } from "@/hooks/use-e-approval-field-choices";
import {
  fetchEApprovalMasterData,
  fetchEApprovalSettingsPublic,
  normalizeSiteSearchScope,
} from "@/lib/api/modules/e-approval-api";
import { earliestIsoDate, parseDateBound } from "@/modules/e-approval/date-bounds";
import {
  gridColumnAsSelectField,
  type GridColumnDef,
} from "@/modules/e-approval/field-options";

export type GridCellMeta = {
  address?: string;
  vatable?: boolean;
  per_diem?: boolean;
};

type Props = {
  column: GridColumnDef;
  value: string;
  onChange: (value: string, meta?: GridCellMeta) => void;
  disabled?: boolean;
  comfortable?: boolean;
  allowRemoteLookups?: boolean;
  /** Cash-advance subsidiary. From site and To site only list that subsidiary's site IDs. */
  subsidiary?: string;
  /** Other answers, used when a date column must fall on or after a form field. */
  values?: Record<string, string>;
};

function isSubsidiarySiteColumn(column: GridColumnDef): boolean {
  return /^(from|to) site$/i.test(column.label.trim()) || column.master_data_key === "sites";
}

export function EApprovalGridCell({
  column,
  value,
  onChange,
  disabled,
  comfortable,
  allowRemoteLookups = true,
  subsidiary = "",
  values = {},
}: Props) {
  const inputClass = comfortable
    ? "h-10 w-full min-w-[8rem] text-sm"
    : "h-7 w-full min-w-[4.5rem] px-1.5 text-[11px]";

  if (column.type === "select" && isSubsidiarySiteColumn(column)) {
    return (
      <GridSiteCell
        value={value}
        showFields={column.show_fields}
        multiple={column.multiple === true}
        onChange={onChange}
        disabled={disabled}
        inputClass={inputClass}
        allowRemoteLookups={allowRemoteLookups}
        subsidiary={subsidiary}
      />
    );
  }

  if (column.type === "select") {
    return (
      <GridSelectCell
        column={column}
        value={value}
        onChange={onChange}
        disabled={disabled}
        inputClass={inputClass}
        allowRemoteLookups={allowRemoteLookups}
      />
    );
  }

  if (column.type === "boolean") {
    const checked = value === "true" || value === "1" || value.toLowerCase() === "yes";
    return (
      <label className="inline-flex h-7 items-center gap-1.5 text-[11px]">
        <Checkbox
          disabled={disabled}
          checked={checked}
          onCheckedChange={(next) => onChange(next === true ? "true" : "false")}
        />
        <span className="text-muted-foreground">{checked ? "Yes" : "No"}</span>
      </label>
    );
  }

  if (column.type === "number" || column.type === "currency") {
    const numberClass = comfortable
      ? "h-10 w-full min-w-[8rem] text-sm tabular-nums"
      : column.type === "currency"
        ? "h-7 w-full min-w-[7.5rem] px-1.5 text-[11px] tabular-nums"
        : "h-7 w-full min-w-[5.25rem] px-1.5 text-[11px] tabular-nums";
    return (
      <Input
        disabled={disabled}
        type="number"
        className={numberClass}
        value={value}
        step={column.type === "currency" ? "0.01" : undefined}
        placeholder={column.type === "currency" ? "0.00" : undefined}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (column.type === "date") {
    return (
      <DatePicker
        disabled={disabled}
        minDate={earliestIsoDate(parseDateBound(column), values) ?? undefined}
        value={value}
        onChange={onChange}
        className={inputClass}
        placeholder="Select date"
      />
    );
  }

  if (column.type === "date_range") {
    const includeTime = column.include_time !== false;
    return (
      <DateRangePicker
        withTime={includeTime}
        disabled={disabled}
        minDate={earliestIsoDate(parseDateBound(column), values) ?? undefined}
        value={parseDateRangeValue(value)}
        onChange={(next) => onChange(serializeDateRangeValue(next))}
        className={inputClass}
        placeholder={includeTime ? "From date and time" : "From date"}
      />
    );
  }

  if (column.type === "textarea") {
    return (
      <Textarea
        disabled={disabled}
        className={
          comfortable
            ? "min-h-16 w-full min-w-[10rem] text-sm"
            : "min-h-9 w-full min-w-[7rem] px-1.5 py-1 text-[11px]"
        }
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (column.type === "email") {
    return (
      <Input
        disabled={disabled}
        type="email"
        className={inputClass}
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (column.type === "phone") {
    return (
      <Input
        disabled={disabled}
        type="tel"
        className={inputClass}
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  return (
    <Input
      disabled={disabled}
      className={inputClass}
      value={value}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}

function GridSelectCell({
  column,
  value,
  onChange,
  disabled,
  inputClass,
  allowRemoteLookups = true,
}: Props & { inputClass: string }) {
  const syntheticField = gridColumnAsSelectField(column);
  const { choices, isLoading, isError } = useEApprovalFieldChoices(
    syntheticField,
    !disabled,
    allowRemoteLookups,
  );
  const emptyLabel = isLoading
    ? "Loading…"
    : column.linked_grid && choices.length === 0
      ? "Add an activity first"
      : "Select…";

  return (
    <>
      <SelectField
        disabled={disabled || isLoading}
        className={inputClass}
        value={value}
        placeholder={emptyLabel}
        emptyLabel={emptyLabel}
        options={choices.map((c) => ({
          value: c.value,
          label: c.label,
        }))}
        onChange={(next) => {
          const choice = choices.find((item) => item.value === next);
          onChange(next, {
            address: choice?.data?.address,
            vatable: choice?.data?.vatable,
            per_diem: choice?.data?.per_diem,
          });
        }}
      />
      {isError ? <span className="sr-only">Options failed to load</span> : null}
    </>
  );
}

function parseSiteStop(value: string): { site: string; location: string; fields: Record<string, string> } {
  const trimmed = value.trim();
  if (trimmed.startsWith("{")) {
    try {
      const parsed = JSON.parse(trimmed) as {
        site?: unknown;
        location?: unknown;
        fields?: unknown;
      };
      if (parsed && typeof parsed === "object" && ("site" in parsed || "location" in parsed)) {
        const fields: Record<string, string> = {};
        if (parsed.fields && typeof parsed.fields === "object" && !Array.isArray(parsed.fields)) {
          for (const [key, fieldValue] of Object.entries(parsed.fields)) {
            const text = String(fieldValue ?? "").trim();
            if (key.trim() !== "" && text !== "") {
              fields[key] = text;
            }
          }
        }
        const location = String(parsed.location ?? "").trim();
        if (location !== "" && !fields.address) {
          fields.address = location;
        }
        return {
          site: String(parsed.site ?? "").trim(),
          location,
          fields,
        };
      }
    } catch {
      return { site: trimmed, location: "", fields: {} };
    }
  }

  return { site: trimmed, location: "", fields: {} };
}

function parseSiteStops(value: string): Array<{ site: string; location: string; fields: Record<string, string> }> {
  const trimmed = value.trim();
  if (trimmed.startsWith("{")) {
    try {
      const parsed = JSON.parse(trimmed) as { sites?: unknown };
      if (parsed && Array.isArray(parsed.sites)) {
        return parsed.sites
          .map((entry) => parseSiteStop(typeof entry === "string" ? entry : JSON.stringify(entry)))
          .filter((stop) => stop.site !== "");
      }
    } catch {
      return [];
    }
  }
  const one = parseSiteStop(trimmed);
  return one.site !== "" ? [one] : [];
}

function serializeSiteStops(
  stops: Array<{ site: string; location: string; fields: Record<string, string> }>,
  showFields: string[],
): string {
  const payloads = stops
    .map((stop) => serializeSiteStop(stop.site, showFields, { ...stop.fields, address: stop.location || stop.fields.address || "" }))
    .filter((payload) => payload !== "");
  if (payloads.length === 0) {
    return "";
  }
  if (payloads.length === 1) {
    return payloads[0] ?? "";
  }
  return JSON.stringify({
    sites: payloads.map((payload) => {
      try {
        return JSON.parse(payload) as unknown;
      } catch {
        return { site: payload };
      }
    }),
  });
}

function serializeSiteStop(site: string, showFields: string[], fields: Record<string, string>): string {
  const id = site.trim();
  if (id === "") {
    return "";
  }

  const picked: Record<string, string> = {};
  for (const key of showFields) {
    const text = (fields[key] ?? "").trim();
    if (text !== "") {
      picked[key] = text;
    }
  }

  const location = picked.address ?? "";
  const extras = { ...picked };
  delete extras.address;
  const subsidiary = (fields.subsidiary ?? "").trim();
  if (subsidiary !== "") {
    extras.subsidiary = subsidiary;
  }
  const payload: { site: string; location?: string; fields?: Record<string, string> } = { site: id };
  if (location !== "") {
    payload.location = location;
  }
  if (Object.keys(extras).length > 0) {
    payload.fields = extras;
  }
  if (!payload.location && !payload.fields) {
    return id;
  }

  return JSON.stringify(payload);
}

function optionSiteFields(data: { address?: string; name?: string; fields?: Record<string, string> } | undefined): Record<string, string> {
  const bag: Record<string, string> = {};
  const source = data ?? {};
  for (const [key, value] of Object.entries({ ...source, ...(source.fields ?? {}) })) {
    if (key === "fields" || typeof value !== "string") {
      continue;
    }
    const text = value.trim();
    if (text !== "") {
      bag[key] = text;
    }
  }
  return bag;
}

function siteLineValues(showFields: string[] | undefined, fields: Record<string, string>, location: string): string[] {
  const keys = showFields ?? ["address"];
  const lines: string[] = [];
  for (const key of keys) {
    const text = (key === "address" ? fields.address || location : fields[key] ?? "").trim();
    if (text !== "" && !lines.includes(text)) {
      lines.push(text);
    }
  }
  return lines;
}

function GridSiteCell({
  value,
  showFields,
  multiple = false,
  onChange,
  disabled,
  inputClass,
  allowRemoteLookups = true,
  subsidiary = "",
}: {
  value: string;
  showFields?: string[];
  multiple?: boolean;
  onChange: (value: string, meta?: GridCellMeta) => void;
  disabled?: boolean;
  inputClass: string;
  allowRemoteLookups?: boolean;
  subsidiary?: string;
}) {
  const stops = multiple ? parseSiteStops(value) : [];
  const stop = multiple ? { site: "", location: "", fields: {} } : parseSiteStop(value);
  const lines = siteLineValues(showFields, stop.fields, stop.location);
  const anchorRef = useRef<HTMLDivElement>(null);
  const [query, setQuery] = useState(stop.site);
  const [open, setOpen] = useState(false);
  const [menuBox, setMenuBox] = useState<{ top: number; left: number; width: number } | null>(null);
  const formSubsidiary = subsidiary.trim().toUpperCase();
  const scopeQuery = useQuery({
    queryKey: ["e-approval", "settings", "public"],
    queryFn: fetchEApprovalSettingsPublic,
    enabled: allowRemoteLookups,
    staleTime: 60_000,
  });
  const scope = normalizeSiteSearchScope(scopeQuery.data?.site_search_scope);
  const catalogSubsidiary =
    scope === "all" ? "*" : scope === "atc" ? "ATC" : scope === "adic" ? "ADIC" : formSubsidiary;
  const waitingForRequest = scope === "request" && formSubsidiary === "";

  useEffect(() => {
    if (!multiple) {
      setQuery(parseSiteStop(value).site);
    }
  }, [multiple, value]);

  const typedQuery = query.trim();
  const [debouncedQuery, setDebouncedQuery] = useState(typedQuery);
  useEffect(() => {
    const handle = window.setTimeout(() => setDebouncedQuery(typedQuery), 200);
    return () => window.clearTimeout(handle);
  }, [typedQuery]);
  const search = useQuery({
    queryKey: ["e-approval", "master-data", "sites", catalogSubsidiary, debouncedQuery],
    queryFn: () =>
      fetchEApprovalMasterData("sites", {
        q: debouncedQuery,
        limit: 10,
        subsidiary: catalogSubsidiary,
      }),
    enabled:
      allowRemoteLookups &&
      !disabled &&
      open &&
      debouncedQuery !== "" &&
      !waitingForRequest &&
      !scopeQuery.isLoading,
    staleTime: 30_000,
  });

  const options = search.data?.options ?? [];
  const showMenu = open && !disabled && typedQuery !== "" && (search.isFetching || search.isFetched);

  useLayoutEffect(() => {
    if (!showMenu) {
      return;
    }
    const place = () => {
      const rect = anchorRef.current?.getBoundingClientRect();
      if (!rect) {
        return;
      }
      const width = Math.max(rect.width, 220);
      const menuHeight = 192;
      const below = rect.bottom + 4;
      const openUp = window.innerHeight - below < 120 && rect.top > menuHeight;
      const top = openUp ? Math.max(8, rect.top - 4 - menuHeight) : below;
      const left = Math.min(Math.max(8, rect.left), window.innerWidth - width - 8);
      setMenuBox({ top, left, width });
    };
    place();
    window.addEventListener("resize", place);
    window.addEventListener("scroll", place, true);
    return () => {
      window.removeEventListener("resize", place);
      window.removeEventListener("scroll", place, true);
    };
  }, [showMenu, typedQuery, options.length]);

  const placeholder = waitingForRequest
    ? "Select a subsidiary first"
    : scope === "atc"
      ? "Search ATC site ID"
      : scope === "adic"
        ? "Search ADIC site ID"
        : "Search site ID";
  const showPicked = stop.site !== "" && !open;

  const menu =
    showMenu && menuBox
      ? createPortal(
          <ul
            className="fixed z-[200] max-h-48 overflow-auto rounded-md border border-border bg-popover p-1 text-left shadow-md"
            style={{ top: menuBox.top, left: menuBox.left, width: menuBox.width }}
          >
            {options.length === 0 ? (
              <li className="px-2 py-1.5 text-[11px] text-muted-foreground">
                {search.isFetching ? "Searching…" : "No matching site"}
              </li>
            ) : (
              options.map((option) => {
                const fields = optionSiteFields(option.data);
                const address = fields.address || option.subtitle || "";
                const picked = address !== "" ? { ...fields, address } : { ...fields };
                const preview = siteLineValues(showFields, picked, address).filter((line) => line !== option.value);
                return (
                  <li key={option.value}>
                    <button
                      type="button"
                      className="w-full rounded-md px-2 py-1 text-left hover:bg-muted"
                      onMouseDown={(event) => event.preventDefault()}
                      onClick={() => {
                        const fieldsToShow = showFields ?? ["address"];
                        if (multiple) {
                          const next = parseSiteStops(value);
                          if (!next.some((pickedStop) => pickedStop.site === option.value)) {
                            next.push({ site: option.value, location: address, fields: picked });
                          }
                          setQuery("");
                          setOpen(false);
                          onChange(serializeSiteStops(next, fieldsToShow));
                          return;
                        }
                        setQuery(option.value);
                        setOpen(false);
                        onChange(serializeSiteStop(option.value, fieldsToShow, picked), {
                          address: fieldsToShow.includes("address") ? address : "",
                        });
                      }}
                    >
                      <span className="block text-[12px] font-medium leading-4 text-foreground">{option.value}</span>
                      {preview.map((line) => (
                        <span key={line} className="block text-[11px] leading-4 text-muted-foreground">
                          {line}
                        </span>
                      ))}
                    </button>
                  </li>
                );
              })
            )}
          </ul>,
          document.body,
        )
      : null;

  return (
    <div ref={anchorRef} className="relative min-w-[8.5rem]">
      {multiple && stops.length > 0 ? (
        <ul className="mb-1 space-y-1">
          {stops.map((pickedStop) => (
            <li key={pickedStop.site} className="flex items-start justify-between gap-1 rounded-md border border-border px-1.5 py-1">
              <span className="min-w-0">
                <span className="block text-[12px] font-medium leading-4 text-foreground">{pickedStop.site}</span>
                {siteLineValues(showFields, pickedStop.fields, pickedStop.location)
                  .filter((line) => line !== pickedStop.site)
                  .map((line) => (
                  <span key={line} className="block text-[11px] leading-4 text-muted-foreground">
                    {line}
                  </span>
                ))}
              </span>
              <button
                type="button"
                className="text-[11px] text-muted-foreground"
                disabled={disabled}
                onClick={() =>
                  onChange(
                    serializeSiteStops(
                      stops.filter((pickedStopItem) => pickedStopItem.site !== pickedStop.site),
                      showFields ?? ["address"],
                    ),
                  )
                }
              >
                Remove
              </button>
            </li>
          ))}
        </ul>
      ) : null}
      {!multiple && showPicked ? (
        <button
          type="button"
          disabled={disabled || waitingForRequest}
          className="w-full rounded-md border border-border bg-background px-2 py-1 text-left disabled:cursor-not-allowed disabled:opacity-60"
          onClick={() => {
            setQuery(stop.site);
            setOpen(true);
          }}
        >
          <span className="block text-[12px] font-medium leading-4 text-foreground">{stop.site}</span>
          {lines.filter((line) => line !== stop.site).map((line) => (
            <span key={line} className="block text-[11px] leading-4 text-muted-foreground">
              {line}
            </span>
          ))}
        </button>
      ) : (
        <Input
          disabled={disabled || waitingForRequest}
          className={inputClass}
          value={query}
          placeholder={placeholder}
          autoFocus={open && stop.site !== ""}
          onFocus={() => setOpen(true)}
          onBlur={() => setOpen(false)}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
            if (!multiple && event.target.value.trim() === "") {
              onChange("");
            }
          }}
        />
      )}
      {menu}
    </div>
  );
}
