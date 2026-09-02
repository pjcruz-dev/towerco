"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Braces, Eye, Plus, Sparkles, Trash2 } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchDynEntities,
  fetchDynEntity,
  fetchDynHtmlReport,
  listDynHtmlReports,
  previewDynReportBuilder,
  saveDynReportBuilder,
  aiBuildDynReportBuilder,
  type DynEntityDetail,
  type DynEntitySummary,
  type DynHtmlReportRow,
  type DynReportBuilderDef,
  type DynReportBuilderFilter,
  type DynReportBuilderPreview,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

type Format = "summary" | "detail" | "matrix";

const emptyDef = (): DynReportBuilderDef => ({
  title: "",
  caption: "",
  entity_slug: "",
  format: "summary",
  metric: "count",
  metric_field: "",
  group_by: "",
  group_by_2: "",
  matrix_column: "",
  date_grouping: "exact",
  filters: [],
  chart: "bar",
  sort_by: "metric",
  direction: "desc",
  row_limit: 500,
  currency_prefix: "",
});

function formatMoney(value: unknown, prefix: string): string {
  const n = typeof value === "number" ? value : Number(value);
  if (!Number.isFinite(n)) return String(value ?? "");
  const s = n.toLocaleString(undefined, { maximumFractionDigits: 2 });
  return prefix ? `${prefix}${s}` : s;
}

export function DynReportBuilderPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const editId = searchParams.get("id") ?? "";

  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [entityDetail, setEntityDetail] = useState<DynEntityDetail | null>(null);
  const [existing, setExisting] = useState<DynHtmlReportRow[]>([]);
  const [def, setDef] = useState<DynReportBuilderDef>(emptyDef);
  const [prompt, setPrompt] = useState("");
  const [preview, setPreview] = useState<DynReportBuilderPreview | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const fieldOptions = useMemo(() => {
    const fields = entityDetail?.fields ?? [];
    return [
      { value: "status", label: "Status" },
      { value: "title", label: "Title" },
      { value: "created_at", label: "Created at" },
      { value: "updated_at", label: "Updated at" },
      ...fields
        .filter((f) => !f.is_system_field)
        .map((f) => ({ value: f.name, label: f.label || f.name })),
    ];
  }, [entityDetail]);

  const numericFields = useMemo(() => {
    return (entityDetail?.fields ?? []).filter(
      (f) =>
        f.calculate_totals ||
        /number|decimal|currency|money|integer|float/.test(f.type) ||
        /amount|total|price|cost|qty|quantity/.test(f.name),
    );
  }, [entityDetail]);

  const loadShell = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [ents, reports] = await Promise.all([
        fetchDynEntities({ active_only: true }),
        listDynHtmlReports(),
      ]);
      setEntities(ents);
      setExisting(reports.rows);

      if (editId) {
        const row = await fetchDynHtmlReport(editId);
        if (row.builder_json) {
          setDef({
            ...emptyDef(),
            ...row.builder_json,
            id: row.id,
            title: row.builder_json.title || row.name,
            caption: row.builder_json.caption ?? row.description ?? "",
            slug: row.slug,
          });
        } else {
          setDef({
            ...emptyDef(),
            id: row.id,
            title: row.name,
            caption: row.description ?? "",
            slug: row.slug,
          });
        }
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [editId]);

  useEffect(() => {
    void loadShell();
  }, [loadShell]);

  useEffect(() => {
    const slug = def.entity_slug;
    if (!slug) {
      setEntityDetail(null);
      return;
    }
    let cancelled = false;
    void (async () => {
      try {
        const detail = await fetchDynEntity(slug);
        if (!cancelled) setEntityDetail(detail);
      } catch {
        if (!cancelled) setEntityDetail(null);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [def.entity_slug]);

  function patch(partial: Partial<DynReportBuilderDef>) {
    setDef((prev) => ({ ...prev, ...partial }));
    setNotice(null);
  }

  function updateFilter(index: number, next: Partial<DynReportBuilderFilter>) {
    const filters = [...(def.filters ?? [])];
    filters[index] = { ...filters[index], ...next };
    patch({ filters });
  }

  function addFilter() {
    patch({ filters: [...(def.filters ?? []), { field: "status", op: "eq", value: "" }] });
  }

  function removeFilter(index: number) {
    patch({ filters: (def.filters ?? []).filter((_, i) => i !== index) });
  }

  async function onBuildFromPrompt() {
    if (!prompt.trim()) {
      setError("Describe the report you want first.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const result = await aiBuildDynReportBuilder({
        prompt: prompt.trim(),
        entity_slug: def.entity_slug || undefined,
      });
      patch(result.definition);
      const sourceLabel =
        result.source === "ai"
          ? `AI (${result.model ?? "assistant"})`
          : result.source === "heuristic_fallback"
            ? "AI fallback heuristics"
            : "heuristics";
      setNotice(
        `${result.notes ?? "Structure applied."} Source: ${sourceLabel}. Review and Run Preview.`,
      );
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onPreview() {
    if (!def.entity_slug) {
      setError("Choose a source entity first.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const data = await previewDynReportBuilder(def);
      setPreview(data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onSave(openAfter: boolean) {
    if (!def.title.trim()) {
      setError("Report title is required.");
      return;
    }
    if (!def.entity_slug) {
      setError("Choose a source entity first.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const saved = await saveDynReportBuilder(def);
      setDef((prev) => ({ ...prev, id: saved.id, slug: saved.slug, saved_slug: saved.slug }));
      setNotice(`Saved “${saved.name}”.`);
      const reports = await listDynHtmlReports();
      setExisting(reports.rows);
      if (openAfter) {
        router.push(`/dynamic-entities/html-reports/${saved.slug}`);
      } else if (!editId) {
        router.replace(`/dynamic-entities/report-builder?id=${saved.id}`);
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function loadExisting(row: DynHtmlReportRow) {
    if (row.has_builder) {
      router.push(`/dynamic-entities/report-builder?id=${row.id}`);
      return;
    }
    router.push(`/dynamic-entities/html-reports/edit?id=${row.id}`);
  }

  const formats: Array<{ id: Format; title: string; desc: string }> = [
    {
      id: "summary",
      title: "Summary",
      desc: "One line per group with the metric totalled — the classic subtotal report.",
    },
    {
      id: "detail",
      title: "Detail",
      desc: "Every matching record, optionally banded by a group field with totals.",
    },
    {
      id: "matrix",
      title: "Matrix",
      desc: "A cross-tab: groups down the side, a second field across the top.",
    },
  ];

  return (
    <PermissionGate requiredPermissions={[permissions.htmlReportsManage]}>
      <div className="mx-auto flex max-w-[1400px] flex-col gap-4 p-4 md:p-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <p className="text-xs font-medium text-muted-foreground">Customization</p>
            <h1 className="text-2xl font-semibold tracking-tight">Report Builder</h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Build summary, detail, or matrix reports from dynamic entities. Saves as an HTML report
              with a live definition.
            </p>
          </div>
          <Button variant="outline" render={<Link href="/dynamic-entities/html-reports" />}>
            Manage HTML Reports
          </Button>
        </div>

        {error ? (
          <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
            {error}
          </div>
        ) : null}
        {notice ? (
          <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-100">
            {notice}
          </div>
        ) : null}

        {loading ? (
          <div className="rounded-xl border bg-card p-8 text-sm text-muted-foreground">Loading…</div>
        ) : (
          <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div className="space-y-4">
              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <Label className="text-sm font-medium">Describe it instead (Optional)</Label>
                <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                  <Textarea
                    value={prompt}
                    onChange={(e) => setPrompt(e.target.value)}
                    placeholder="e.g. Total PO amount by category this year, as a bar chart"
                    className="min-h-[72px] flex-1"
                  />
                  <Button
                    type="button"
                    variant="secondary"
                    className="shrink-0 self-start"
                    disabled={busy || !prompt.trim()}
                    onClick={() => void onBuildFromPrompt()}
                  >
                    <Sparkles className="mr-1.5 h-4 w-4" />
                    Build with AI
                  </Button>
                </div>
              </section>

              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <div className="grid gap-3 sm:grid-cols-2">
                  <div className="space-y-1.5 sm:col-span-1">
                    <Label>
                      Report Title <span className="text-destructive">*</span>
                    </Label>
                    <Input
                      value={def.title}
                      onChange={(e) => patch({ title: e.target.value })}
                      placeholder="e.g. Materials Issued per Tower"
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>Sub-caption (optional)</Label>
                    <Input
                      value={def.caption ?? ""}
                      onChange={(e) => patch({ caption: e.target.value })}
                      placeholder="Shown under the title"
                    />
                  </div>
                </div>
              </section>

              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">1. Select the field you want to report on</h2>
                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                  <div className="space-y-1.5">
                    <Label>Source</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.entity_slug}
                      onChange={(e) =>
                        patch({
                          entity_slug: e.target.value,
                          group_by: "",
                          group_by_2: "",
                          metric_field: "",
                          matrix_column: "",
                        })
                      }
                    >
                      <option value="">Choose an entity</option>
                      {entities.map((e) => (
                        <option key={e.id} value={e.slug}>
                          {e.name}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>Metric</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.metric ?? "count"}
                      onChange={(e) => patch({ metric: e.target.value as "count" | "sum" })}
                    >
                      <option value="count">Count of records</option>
                      <option value="sum">Sum of field</option>
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>Of field</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.metric_field ?? ""}
                      disabled={def.metric !== "sum"}
                      onChange={(e) => patch({ metric_field: e.target.value })}
                    >
                      <option value="">—</option>
                      {numericFields.map((f) => (
                        <option key={f.id} value={f.name}>
                          {f.label || f.name}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              </section>

              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">2. Select the format of the report</h2>
                <div className="mt-3 grid gap-2 md:grid-cols-3">
                  {formats.map((f) => (
                    <button
                      key={f.id}
                      type="button"
                      onClick={() => patch({ format: f.id })}
                      className={cn(
                        "rounded-xl border p-3 text-left transition-colors",
                        def.format === f.id
                          ? "border-primary bg-primary/5 ring-1 ring-primary/30"
                          : "hover:bg-muted/40",
                      )}
                    >
                      <div className="text-sm font-medium">{f.title}</div>
                      <p className="mt-1 text-xs text-muted-foreground">{f.desc}</p>
                    </button>
                  ))}
                </div>
              </section>

              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">3. Select how you want to subtotal the report</h2>
                <div className="mt-3 grid gap-3 sm:grid-cols-3">
                  <div className="space-y-1.5">
                    <Label>Component (group by)</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.group_by ?? ""}
                      onChange={(e) => patch({ group_by: e.target.value })}
                    >
                      <option value="">All records</option>
                      {fieldOptions.map((f) => (
                        <option key={f.value} value={f.value}>
                          {f.label}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>Date grouping</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.date_grouping ?? "exact"}
                      onChange={(e) =>
                        patch({
                          date_grouping: e.target.value as DynReportBuilderDef["date_grouping"],
                        })
                      }
                    >
                      <option value="exact">Exact value</option>
                      <option value="day">Day</option>
                      <option value="week">Week</option>
                      <option value="month">Month</option>
                      <option value="year">Year</option>
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>{def.format === "matrix" ? "Across (columns)" : "Then by (optional)"}</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={
                        def.format === "matrix"
                          ? (def.matrix_column || def.group_by_2 || "")
                          : (def.group_by_2 ?? "")
                      }
                      onChange={(e) =>
                        def.format === "matrix"
                          ? patch({ matrix_column: e.target.value, group_by_2: e.target.value })
                          : patch({ group_by_2: e.target.value })
                      }
                    >
                      <option value="">—</option>
                      {fieldOptions.map((f) => (
                        <option key={f.value} value={f.value}>
                          {f.label}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>
              </section>

              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <div className="flex items-center justify-between gap-2">
                  <h2 className="text-base font-medium">4. Filters (optional)</h2>
                  <Button type="button" size="sm" variant="outline" onClick={addFilter}>
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Add Filter
                  </Button>
                </div>
                {(def.filters ?? []).length === 0 ? (
                  <p className="mt-3 text-sm text-muted-foreground">
                    No filters — the report covers every record.
                  </p>
                ) : (
                  <div className="mt-3 space-y-2">
                    {(def.filters ?? []).map((f, i) => (
                      <div key={i} className="grid gap-2 sm:grid-cols-[1fr_100px_1fr_auto]">
                        <select
                          className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                          value={f.field}
                          onChange={(e) => updateFilter(i, { field: e.target.value })}
                        >
                          {fieldOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                              {opt.label}
                            </option>
                          ))}
                        </select>
                        <select
                          className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                          value={f.op ?? "eq"}
                          onChange={(e) => updateFilter(i, { op: e.target.value })}
                        >
                          <option value="eq">is</option>
                        </select>
                        <Input
                          value={String(f.value ?? "")}
                          onChange={(e) => updateFilter(i, { value: e.target.value })}
                          placeholder="Value"
                        />
                        <Button
                          type="button"
                          size="icon"
                          variant="ghost"
                          onClick={() => removeFilter(i)}
                          aria-label="Remove filter"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                )}
              </section>

              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">5. Presentation</h2>
                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                  <div className="space-y-1.5">
                    <Label>Chart</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.chart ?? "bar"}
                      onChange={(e) => patch({ chart: e.target.value })}
                    >
                      <option value="bar">Bar</option>
                      <option value="pie">Pie</option>
                      <option value="line">Line</option>
                      <option value="none">None</option>
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>Sort by</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.sort_by ?? "metric"}
                      onChange={(e) => patch({ sort_by: e.target.value as "metric" | "group" })}
                    >
                      <option value="metric">Metric value</option>
                      <option value="group">Group label</option>
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>Direction</Label>
                    <select
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={def.direction ?? "desc"}
                      onChange={(e) => patch({ direction: e.target.value as "asc" | "desc" })}
                    >
                      <option value="desc">High → low</option>
                      <option value="asc">Low → high</option>
                    </select>
                  </div>
                  <div className="space-y-1.5">
                    <Label>Row limit</Label>
                    <Input
                      type="number"
                      min={1}
                      max={2000}
                      value={def.row_limit ?? 500}
                      onChange={(e) => patch({ row_limit: Number(e.target.value) || 500 })}
                    />
                  </div>
                  <div className="space-y-1.5">
                    <Label>Currency prefix</Label>
                    <Input
                      value={def.currency_prefix ?? ""}
                      onChange={(e) => patch({ currency_prefix: e.target.value })}
                      placeholder="P"
                    />
                  </div>
                </div>
              </section>

              <div className="flex flex-wrap gap-2">
                <Button type="button" variant="outline" disabled={busy} onClick={() => void onPreview()}>
                  Run Preview
                </Button>
                <Button type="button" disabled={busy} onClick={() => void onSave(false)}>
                  Save Report
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={busy}
                  onClick={() => void onSave(true)}
                >
                  Save & Open
                </Button>
              </div>
            </div>

            <aside className="space-y-4 lg:sticky lg:top-4 lg:self-start">
              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">Live Preview</h2>
                {!preview ? (
                  <p className="mt-3 text-sm text-muted-foreground">
                    Choose a source and grouping, then Run Preview.
                  </p>
                ) : (
                  <div className="mt-3 space-y-3">
                    {def.chart && def.chart !== "none" && preview.rows.length > 0 ? (
                      <SimpleBarPreview
                        rows={preview.rows}
                        metricKey={
                          preview.columns.find((c) => c.numeric)?.key ?? "metric"
                        }
                        prefix={def.currency_prefix ?? ""}
                      />
                    ) : null}
                    <div className="max-h-[420px] overflow-auto rounded-lg border">
                      <table className="w-full text-[13px]">
                        <thead className="sticky top-0 bg-muted/80 backdrop-blur">
                          <tr>
                            {preview.columns.map((c) => (
                              <th
                                key={c.key}
                                className={cn(
                                  "border-b px-2 py-2 text-left font-medium",
                                  c.numeric && "text-right",
                                )}
                              >
                                {c.label}
                              </th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          {preview.rows.length === 0 ? (
                            <tr>
                              <td
                                className="px-2 py-3 text-muted-foreground"
                                colSpan={preview.columns.length}
                              >
                                No matching records.
                              </td>
                            </tr>
                          ) : (
                            preview.rows.map((row, idx) => (
                              <tr key={idx} className="border-b last:border-0">
                                {preview.columns.map((c) => (
                                  <td
                                    key={c.key}
                                    className={cn("px-2 py-1.5", c.numeric && "text-right tabular-nums")}
                                  >
                                    {c.numeric
                                      ? formatMoney(row[c.key], def.currency_prefix ?? "")
                                      : String(row[c.key] ?? "")}
                                  </td>
                                ))}
                              </tr>
                            ))
                          )}
                        </tbody>
                        {Object.keys(preview.totals).length > 0 ? (
                          <tfoot>
                            <tr className="bg-muted/50 font-medium">
                              {preview.columns.map((c, i) => (
                                <td
                                  key={c.key}
                                  className={cn("px-2 py-2", c.numeric && "text-right tabular-nums")}
                                >
                                  {i === 0
                                    ? "Total"
                                    : c.numeric && preview.totals[c.key] != null
                                      ? formatMoney(preview.totals[c.key], def.currency_prefix ?? "")
                                      : ""}
                                </td>
                              ))}
                            </tr>
                          </tfoot>
                        ) : null}
                      </table>
                    </div>
                  </div>
                )}
              </section>

              <section className="rounded-xl border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">Existing Reports</h2>
                <ul className="mt-3 max-h-[360px] space-y-2 overflow-auto">
                  {existing.length === 0 ? (
                    <li className="text-sm text-muted-foreground">No reports yet.</li>
                  ) : (
                    existing.map((row) => (
                      <li
                        key={row.id}
                        className="flex items-start justify-between gap-2 rounded-lg border px-2.5 py-2"
                      >
                        <div className="min-w-0">
                          <div className="truncate text-sm font-medium">{row.name}</div>
                          <div className="text-[11px] text-muted-foreground">
                            {row.has_builder ? "Builder" : row.is_system ? "Hard-coded" : "HTML"}
                          </div>
                        </div>
                        <div className="flex shrink-0 gap-1">
                          <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="h-8 w-8"
                            title="View"
                            render={<Link href={`/dynamic-entities/html-reports/${row.slug}`} />}
                          >
                            <Eye className="h-4 w-4" />
                          </Button>
                          <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            className="h-8 w-8"
                            title={row.has_builder ? "Open in builder" : "Edit source"}
                            onClick={() => void loadExisting(row)}
                          >
                            <Braces className="h-4 w-4" />
                          </Button>
                        </div>
                      </li>
                    ))
                  )}
                </ul>
              </section>
            </aside>
          </div>
        )}
      </div>
    </PermissionGate>
  );
}

function SimpleBarPreview({
  rows,
  metricKey,
  prefix,
}: {
  rows: Array<Record<string, unknown>>;
  metricKey: string;
  prefix: string;
}) {
  const sample = rows.slice(0, 12);
  const max = Math.max(
    1,
    ...sample.map((r) => {
      const n = Number(r[metricKey]);
      return Number.isFinite(n) ? n : 0;
    }),
  );

  return (
    <div className="space-y-1.5 rounded-lg border bg-muted/20 p-3">
      {sample.map((r, i) => {
        const n = Number(r[metricKey]);
        const val = Number.isFinite(n) ? n : 0;
        const label = String(r.group ?? r.title ?? `#${i + 1}`);
        return (
          <div key={i} className="grid grid-cols-[88px_1fr_auto] items-center gap-2 text-[11px]">
            <div className="truncate text-muted-foreground" title={label}>
              {label}
            </div>
            <div className="h-2 overflow-hidden rounded bg-muted">
              <div
                className="h-full rounded bg-sky-500/80"
                style={{ width: `${Math.max(4, (val / max) * 100)}%` }}
              />
            </div>
            <div className="tabular-nums text-muted-foreground">{formatMoney(val, prefix)}</div>
          </div>
        );
      })}
    </div>
  );
}
