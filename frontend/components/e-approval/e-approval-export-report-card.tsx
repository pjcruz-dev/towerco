"use client";

import { useEffect, useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Download } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import {
  createEApprovalReport,
  downloadEApprovalSubmissionsExport,
  fetchEApprovalSubmissionsExportColumns,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { cn } from "@/lib/utils";

const STATUS_OPTIONS = [
  { value: "pending", label: "Pending" },
  { value: "returned", label: "Returned" },
  { value: "approved", label: "Approved" },
  { value: "rejected", label: "Rejected" },
  { value: "cancelled", label: "Cancelled" },
] as const;

type Scope = "all" | "one";
type ViewerScope = "mine" | "all";
type Format = "csv" | "xlsx";
type Layout = "submissions" | "line_items";

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

type Props = {
  showSave?: boolean;
  onSaved?: () => void;
  onExported?: () => void;
};

export function EApprovalExportReportCard({ showSave = false, onSaved, onExported }: Props) {
  const [scope, setScope] = useState<Scope>("all");
  const [viewerScope, setViewerScope] = useState<ViewerScope>("all");
  const [formId, setFormId] = useState<string>("");
  const [format, setFormat] = useState<Format>("csv");
  const [layout, setLayout] = useState<Layout>("submissions");
  const [gridField, setGridField] = useState<string>("");
  const [statuses, setStatuses] = useState<string[]>([]);
  const [from, setFrom] = useState<string>("");
  const [to, setTo] = useState<string>("");
  const [search, setSearch] = useState<string>("");
  const [selectedColumns, setSelectedColumns] = useState<string[] | null>(null);
  const [saveName, setSaveName] = useState<string>("");
  const [isDownloading, setIsDownloading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  // Forms for the picker come from the export/columns endpoint (export RBAC), not /forms
  // which has a different permission gate and rejects per_page > 100.
  const formsCatalogQuery = useQuery({
    queryKey: ["e-approval", "export", "forms-catalog"],
    queryFn: () => fetchEApprovalSubmissionsExportColumns(undefined),
    staleTime: 60_000,
  });

  const canViewAll = formsCatalogQuery.data?.canViewAll === true;

  useEffect(() => {
    if (formsCatalogQuery.data && !canViewAll) {
      setViewerScope("mine");
    }
  }, [formsCatalogQuery.data, canViewAll]);

  const columnFormId = scope === "one" ? formId : "";
  const columnsReady = scope === "all" || (scope === "one" && formId !== "");
  const columnsQuery = useQuery({
    queryKey: ["e-approval", "export", "columns", columnFormId || "all"],
    queryFn: () => fetchEApprovalSubmissionsExportColumns(columnFormId || undefined),
    enabled: columnsReady,
    staleTime: 60_000,
  });

  const availableColumns = useMemo(
    () => (columnsReady ? (columnsQuery.data?.columns ?? []) : []),
    [columnsReady, columnsQuery.data],
  );
  const gridFields = useMemo(
    () => (columnsReady ? (columnsQuery.data?.grids ?? []) : []),
    [columnsReady, columnsQuery.data],
  );
  const hasGrids = scope === "one" && formId !== "" && gridFields.length > 0;

  // Default to all columns selected whenever the available set changes.
  useEffect(() => {
    if (availableColumns.length > 0) {
      setSelectedColumns(availableColumns.map((column) => column.key));
    } else {
      setSelectedColumns(null);
    }
  }, [availableColumns]);

  // Keep the grid selection valid, and fall back to the submissions layout when no grids exist.
  useEffect(() => {
    if (!hasGrids) {
      setLayout("submissions");
      setGridField("");
      return;
    }
    setGridField((current) =>
      gridFields.some((field) => field.key === current) ? current : gridFields[0].key,
    );
  }, [hasGrids, gridFields]);

  const formOptions = useMemo(
    () => formsCatalogQuery.data?.forms ?? [],
    [formsCatalogQuery.data],
  );
  const selectedForm = useMemo(
    () => formOptions.find((form) => form.id === formId) ?? null,
    [formOptions, formId],
  );

  const allColumnsSelected =
    selectedColumns !== null && selectedColumns.length === availableColumns.length;
  const canDownload =
    (scope === "all" || (scope === "one" && formId !== "")) &&
    (selectedColumns === null || selectedColumns.length > 0);

  const toggleStatus = (value: string) => {
    setStatuses((prev) =>
      prev.includes(value) ? prev.filter((item) => item !== value) : [...prev, value],
    );
  };

  const toggleColumn = (key: string) => {
    setSelectedColumns((prev) => {
      const base = prev ?? availableColumns.map((column) => column.key);
      return base.includes(key) ? base.filter((item) => item !== key) : [...base, key];
    });
  };

  const buildConfig = () => {
    const isLineItems = layout === "line_items" && hasGrids;
    const columns =
      !isLineItems && selectedColumns && !allColumnsSelected ? selectedColumns : undefined;
    const effectiveViewerScope: ViewerScope = canViewAll ? viewerScope : "mine";
    return {
      isLineItems,
      columns,
      filters: {
        form_id: scope === "one" ? formId || undefined : undefined,
        statuses: statuses.length > 0 ? statuses : undefined,
        from: from || undefined,
        to: to || undefined,
        search: search.trim() || undefined,
        scope,
        viewer_scope: effectiveViewerScope,
      },
      layout: (isLineItems ? "line_items" : "submissions") as Layout,
      format,
      grid_field_id: isLineItems ? gridField || null : null,
      viewer_scope: effectiveViewerScope,
    };
  };

  const handleDownload = async () => {
    setError(null);
    setNotice(null);
    setIsDownloading(true);
    try {
      const config = buildConfig();
      const result = await downloadEApprovalSubmissionsExport({
        form_id: config.filters.form_id,
        statuses: config.filters.statuses,
        from: config.filters.from,
        to: config.filters.to,
        search: config.filters.search,
        format: config.format,
        columns: config.columns,
        layout: config.layout,
        grid_field: config.grid_field_id ?? undefined,
        viewer_scope: config.viewer_scope,
      });

      if (result.mode === "async") {
        setNotice(
          `${result.message} ${result.matchedRows.toLocaleString()} matching rows (async cap ${result.maxRows.toLocaleString()}).`,
        );
        onExported?.();
        return;
      }

      const stamp = new Date().toISOString().slice(0, 10);
      const formSlug =
        scope === "one" && selectedForm
          ? `e-approval-${selectedForm.name.toLowerCase().replace(/[^a-z0-9_-]+/g, "-")}`
          : "e-approval-submissions";
      const base = config.isLineItems ? `${formSlug}-line-items` : formSlug;
      saveBlob(result.blob, `${base}-${stamp}.${format}`);

      if (result.truncated) {
        setNotice(
          `Export capped at ${result.maxRows.toLocaleString()} rows (${result.totalRows.toLocaleString()} matched). Larger sets are queued automatically for download from Recent exports.`,
        );
      }
      onExported?.();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsDownloading(false);
    }
  };

  const handleSave = async () => {
    const name = saveName.trim();
    if (!name) {
      setError("Enter a name to save this report.");
      return;
    }
    setError(null);
    setNotice(null);
    setIsSaving(true);
    try {
      const config = buildConfig();
      await createEApprovalReport({
        name,
        filters: config.filters,
        columns: config.columns ?? null,
        layout: config.layout,
        format: config.format,
        grid_field_id: config.grid_field_id,
        schedule: { enabled: false, frequency: "daily", hour: 8, day_of_week: 1, recipients: [] },
      });
      setSaveName("");
      setNotice(`Saved report “${name}”.`);
      onSaved?.();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <EApprovalSectionCard
      title="Export report"
      description="Download submissions as CSV or Excel. Prefer Excel for clickable attachment links. Links open the app download page (works locally and in production with S3)."
    >
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div className="space-y-1.5">
          <Label htmlFor="export-viewer-scope">Data scope</Label>
          <Select
            id="export-viewer-scope"
            value={canViewAll ? viewerScope : "mine"}
            onChange={(event) => setViewerScope(event.target.value as ViewerScope)}
            disabled={!canViewAll}
          >
            <option value="mine">My submissions only</option>
            {canViewAll ? <option value="all">All submissions</option> : null}
          </Select>
          {!canViewAll ? (
            <p className="text-xs text-muted-foreground">Limited to requests you created.</p>
          ) : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="export-scope">Form scope</Label>
          <Select
            id="export-scope"
            value={scope}
            onChange={(event) => {
              const next = event.target.value as Scope;
              setScope(next);
              if (next === "all") {
                setFormId("");
              }
            }}
          >
            <option value="all">All forms</option>
            <option value="one">One form</option>
          </Select>
        </div>

        {scope === "one" ? (
          <div className="space-y-1.5">
            <Label htmlFor="export-form">Form</Label>
            <Select
              id="export-form"
              value={formId}
              onChange={(event) => setFormId(event.target.value)}
              disabled={formsCatalogQuery.isLoading}
            >
              <option value="">
                {formsCatalogQuery.isLoading
                  ? "Loading forms…"
                  : formsCatalogQuery.isError
                    ? "Failed to load forms"
                    : formOptions.length === 0
                      ? "No forms available"
                      : "Select a form"}
              </option>
              {formOptions.map((form) => (
                <option key={form.id} value={form.id}>
                  {form.name}
                  {form.status && form.status !== "published" ? ` (${form.status})` : ""}
                </option>
              ))}
            </Select>
            {formsCatalogQuery.isError ? (
              <p className="text-xs text-destructive">{getErrorMessage(formsCatalogQuery.error)}</p>
            ) : null}
          </div>
        ) : null}

        <div className="space-y-1.5">
          <Label htmlFor="export-format">Format</Label>
          <Select
            id="export-format"
            value={format}
            onChange={(event) => setFormat(event.target.value as Format)}
          >
            <option value="csv">CSV (.csv)</option>
            <option value="xlsx">Excel (.xlsx)</option>
          </Select>
        </div>

        {hasGrids ? (
          <div className="space-y-1.5">
            <Label htmlFor="export-layout">Layout</Label>
            <Select
              id="export-layout"
              value={layout}
              onChange={(event) => setLayout(event.target.value as Layout)}
            >
              <option value="submissions">One row per submission</option>
              <option value="line_items">One row per line item</option>
            </Select>
          </div>
        ) : null}

        {hasGrids && layout === "line_items" && gridFields.length > 1 ? (
          <div className="space-y-1.5">
            <Label htmlFor="export-grid">Line-item table</Label>
            <Select
              id="export-grid"
              value={gridField}
              onChange={(event) => setGridField(event.target.value)}
            >
              {gridFields.map((field) => (
                <option key={field.key} value={field.key}>
                  {field.label}
                </option>
              ))}
            </Select>
          </div>
        ) : null}

        <div className="space-y-1.5">
          <Label htmlFor="export-from">From</Label>
          <DatePicker
            id="export-from"
            value={from}
            onChange={setFrom}
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="export-to">To</Label>
          <DatePicker
            id="export-to"
            value={to}
            onChange={setTo}
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="export-search">Search (optional)</Label>
          <Input
            id="export-search"
            type="search"
            placeholder="Document no, form, requestor…"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>
      </div>

      <div className="mt-4 space-y-1.5">
        <Label>Status</Label>
        <div className="flex flex-wrap gap-2">
          {STATUS_OPTIONS.map((option) => {
            const active = statuses.includes(option.value);
            return (
              <button
                key={option.value}
                type="button"
                onClick={() => toggleStatus(option.value)}
                aria-pressed={active}
                className={cn(
                  "rounded-full border px-3 py-1 text-xs font-medium transition-colors",
                  active
                    ? "border-primary bg-primary text-primary-foreground"
                    : "border-border bg-card text-muted-foreground hover:border-primary/40 hover:text-foreground",
                )}
              >
                {option.label}
              </button>
            );
          })}
        </div>
        <p className="text-xs text-muted-foreground">
          {statuses.length === 0 ? "All statuses included." : `${statuses.length} selected.`}
        </p>
      </div>

      {hasGrids && layout === "line_items" ? (
        <p className="mt-4 rounded-md border border-border bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
          Line-item layout emits one row per line item with the parent submission columns repeated
          {format === "xlsx"
            ? ", plus a summary “Submissions” sheet in the workbook."
            : "."}
        </p>
      ) : null}

      {scope === "one" && formId === "" ? (
        <p className="mt-4 text-sm text-muted-foreground">
          Select a form to load its custom field columns for export.
        </p>
      ) : null}

      {columnsReady && columnsQuery.isLoading ? (
        <div className="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
          <Spinner className="size-3.5" /> Loading columns…
        </div>
      ) : null}

      {availableColumns.length > 0 && layout === "submissions" ? (
        <div className="mt-4 space-y-2 rounded-lg border border-border bg-muted/20 p-3">
          <div className="flex items-center justify-between">
            <Label>
              Columns
              {scope === "one" && selectedForm ? (
                <span className="ml-2 text-xs font-normal text-muted-foreground">
                  including fields from {selectedForm.name}
                </span>
              ) : null}
            </Label>
            <div className="flex gap-3 text-xs">
              <button
                type="button"
                className="font-medium text-primary hover:underline"
                onClick={() => setSelectedColumns(availableColumns.map((column) => column.key))}
              >
                Select all
              </button>
              <button
                type="button"
                className="font-medium text-muted-foreground hover:underline"
                onClick={() => setSelectedColumns([])}
              >
                Clear
              </button>
            </div>
          </div>
          <div className="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
            {availableColumns.map((column) => {
              const checked = selectedColumns === null || selectedColumns.includes(column.key);
              return (
                <label
                  key={column.key}
                  className="flex items-center gap-2 text-sm text-foreground"
                >
                  <Checkbox
                    className="size-4"
                    checked={checked}
                    onCheckedChange={() => toggleColumn(column.key)}
                  />
                  <span className="truncate">
                    {column.label}
                    {column.group === "field" ? (
                      <span className="ml-1 text-xs text-muted-foreground">(field)</span>
                    ) : null}
                    {column.group === "approval" ? (
                      <span className="ml-1 text-xs text-muted-foreground">(approval)</span>
                    ) : null}
                  </span>
                </label>
              );
            })}
          </div>
        </div>
      ) : null}

      {showSave ? (
        <div className="mt-4 grid gap-3 rounded-lg border border-border bg-muted/20 p-3 sm:grid-cols-[1fr_auto]">
          <div className="space-y-1.5">
            <Label htmlFor="export-save-name">Save as report</Label>
            <Input
              id="export-save-name"
              value={saveName}
              placeholder="e.g. Weekly approved PRs"
              onChange={(event) => setSaveName(event.target.value)}
            />
          </div>
          <div className="flex items-end">
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={handleSave}
              disabled={!canDownload || isSaving || saveName.trim() === ""}
            >
              {isSaving ? <Spinner className="mr-1.5 size-3.5" /> : null}
              Save report
            </Button>
          </div>
        </div>
      ) : null}

      <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-xs text-muted-foreground">
          Exports under 5,000 rows download immediately. Larger exports are queued and appear in Recent
          exports (up to 100,000 rows, files expire after 7 days).
        </p>
        <Button type="button" size="sm" onClick={handleDownload} disabled={!canDownload || isDownloading}>
          {isDownloading ? (
            <Spinner className="mr-1.5 size-3.5" />
          ) : (
            <Download className="mr-1.5 size-3.5" aria-hidden />
          )}
          Download {format.toUpperCase()}
        </Button>
      </div>

      {notice ? (
        <p className="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-400">
          {notice}
        </p>
      ) : null}
      {error ? <p className="mt-2 text-sm text-destructive">{error}</p> : null}
    </EApprovalSectionCard>
  );
}
