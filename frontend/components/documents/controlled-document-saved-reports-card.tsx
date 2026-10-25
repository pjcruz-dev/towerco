"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { FilterSelect } from "@/components/forms/filter-select";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  createControlledDocumentSavedReport,
  deleteControlledDocumentSavedReport,
  downloadControlledDocumentSavedReport,
  downloadControlledDocumentSavedReportFile,
  fetchControlledDocumentSavedReports,
  updateControlledDocumentSavedReport,
  type ControlledDocumentSavedReport,
  type ControlledDocumentSavedReportFilters,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { formatDisplayDate } from "@/lib/format-display-date";
import { controlledReportHref } from "@/modules/documents/controlled-document-reports-query";

const WEEKDAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

type CurrentFilters = {
  period: number;
  department: string;
  documentType: string;
  status: string;
  review: string;
  file: string;
};

export function ControlledDocumentSavedReportsCard({
  filters,
  onApply,
}: {
  filters: CurrentFilters;
  onApply: (href: string) => void;
}) {
  const queryClient = useQueryClient();
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);
  const savedQuery = useQuery({
    queryKey: ["documents", "controlled", "reports", "saved"],
    queryFn: fetchControlledDocumentSavedReports,
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createControlledDocumentSavedReport({
        name: name.trim(),
        filters: currentFilterPayload(filters),
      }),
    onSuccess: async () => {
      setName("");
      setError(null);
      await queryClient.invalidateQueries({ queryKey: ["documents", "controlled", "reports", "saved"] });
    },
    onError: (caught) => setError(getErrorMessage(caught)),
  });

  function apply(report: ControlledDocumentSavedReport) {
    const saved = report.filters;
    onApply(
      controlledReportHref(new URLSearchParams(), {
        period: saved.period === 90 ? null : String(saved.period),
        "filter[department]": saved.department,
        "filter[document_type]": saved.document_type,
        "filter[status]": saved.status,
        "filter[review]": saved.review,
        "filter[file]": saved.file,
      }),
    );
  }

  return (
    <section className="space-y-3 rounded-xl border border-border bg-card p-3 shadow-sm">
      <div>
        <h2 className="text-sm font-medium text-foreground">Saved reports</h2>
        <p className="mt-0.5 text-xs text-muted-foreground">
          Save the filters on this page. Run downloads the CSV now. A schedule stores the latest file once each due day.
        </p>
      </div>

      <form
        className="flex flex-wrap items-end gap-2"
        onSubmit={(event) => {
          event.preventDefault();
          if (name.trim() === "") return;
          createMutation.mutate();
        }}
      >
        <div className="min-w-[220px] flex-1">
          <label htmlFor="cdr-saved-name" className="mb-1 block text-xs font-medium text-muted-foreground">
            Report name
          </label>
          <Input
            id="cdr-saved-name"
            value={name}
            maxLength={160}
            placeholder="OMD last 30 days"
            onChange={(event) => setName(event.target.value)}
          />
        </div>
        <Button type="submit" size="sm" disabled={name.trim() === "" || createMutation.isPending}>
          {createMutation.isPending ? "Saving…" : "Save current filters"}
        </Button>
      </form>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      {savedQuery.isError ? (
        <p className="text-sm text-destructive">{getErrorMessage(savedQuery.error)}</p>
      ) : null}

      {savedQuery.isLoading ? (
        <p className="text-sm text-muted-foreground">Loading saved reports…</p>
      ) : (savedQuery.data ?? []).length === 0 ? (
        <p className="text-sm text-muted-foreground">No saved reports yet.</p>
      ) : (
        <ul className="space-y-2">
          {(savedQuery.data ?? []).map((report) => (
            <SavedReportRow key={report.id} report={report} onApply={() => apply(report)} onError={setError} />
          ))}
        </ul>
      )}
    </section>
  );
}

function SavedReportRow({
  report,
  onApply,
  onError,
}: {
  report: ControlledDocumentSavedReport;
  onApply: () => void;
  onError: (message: string | null) => void;
}) {
  const queryClient = useQueryClient();
  const [busy, setBusy] = useState<"run" | "file" | "delete" | null>(null);

  async function download(kind: "run" | "file") {
    setBusy(kind);
    onError(null);
    try {
      const blob =
        kind === "run"
          ? await downloadControlledDocumentSavedReport(report.id)
          : await downloadControlledDocumentSavedReportFile(report.id);
      const filename = kind === "file" && report.filename ? report.filename : `${slug(report.name)}.csv`;
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = filename;
      anchor.click();
      URL.revokeObjectURL(url);
    } catch (caught) {
      onError(getErrorMessage(caught));
    } finally {
      setBusy(null);
    }
  }

  const remove = useMutation({
    mutationFn: () => deleteControlledDocumentSavedReport(report.id),
    onSuccess: async () => {
      onError(null);
      await queryClient.invalidateQueries({ queryKey: ["documents", "controlled", "reports", "saved"] });
    },
    onError: (caught) => onError(getErrorMessage(caught)),
  });

  return (
    <li className="space-y-3 rounded-lg border border-border p-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-foreground">{report.name}</p>
          <p className="mt-1 text-xs text-muted-foreground">{describeFilters(report.filters)}</p>
          {report.last_run_at ? (
            <p className="mt-1 text-xs text-muted-foreground">
              Last scheduled run {formatDisplayDate(report.last_run_at, "datetime") ?? report.last_run_at}
            </p>
          ) : null}
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="button" size="sm" variant="outline" onClick={onApply}>
            Apply
          </Button>
          <Button type="button" size="sm" variant="outline" disabled={busy !== null} onClick={() => void download("run")}>
            {busy === "run" ? "Running…" : "Run"}
          </Button>
          {report.has_file ? (
            <Button type="button" size="sm" variant="outline" disabled={busy !== null} onClick={() => void download("file")}>
              {busy === "file" ? "Downloading…" : "Latest file"}
            </Button>
          ) : null}
          <Button
            type="button"
            size="sm"
            variant="ghost"
            disabled={remove.isPending}
            onClick={() => {
              if (window.confirm(`Delete “${report.name}”?`)) remove.mutate();
            }}
          >
            Delete
          </Button>
        </div>
      </div>
      <ScheduleEditor report={report} onError={onError} />
    </li>
  );
}

function ScheduleEditor({
  report,
  onError,
}: {
  report: ControlledDocumentSavedReport;
  onError: (message: string | null) => void;
}) {
  const queryClient = useQueryClient();
  const schedule = report.schedule;
  const [enabled, setEnabled] = useState(schedule?.enabled ?? false);
  const [frequency, setFrequency] = useState<"daily" | "weekly">(schedule?.frequency ?? "daily");
  const [hour, setHour] = useState(String(schedule?.hour ?? 8));
  const [dayOfWeek, setDayOfWeek] = useState(String(schedule?.day_of_week ?? 1));
  const [recipients, setRecipients] = useState((schedule?.recipients ?? []).join(", "));

  const save = useMutation({
    mutationFn: () =>
      updateControlledDocumentSavedReport(report.id, {
        schedule: {
          enabled,
          frequency,
          hour: Number(hour),
          day_of_week: Number(dayOfWeek),
          recipients: recipients
            .split(/[,;\n]/)
            .map((email) => email.trim())
            .filter((email) => email !== ""),
        },
      }),
    onSuccess: async () => {
      onError(null);
      await queryClient.invalidateQueries({ queryKey: ["documents", "controlled", "reports", "saved"] });
    },
    onError: (caught) => onError(getErrorMessage(caught)),
  });

  return (
    <div className="grid gap-3 border-t border-border pt-3 sm:grid-cols-2 lg:grid-cols-4">
      <label className="flex items-center gap-2 text-sm text-foreground sm:col-span-2 lg:col-span-4">
        <input
          type="checkbox"
          className="size-4 rounded border-border"
          checked={enabled}
          onChange={(event) => setEnabled(event.target.checked)}
        />
        Schedule this report
      </label>
      <FilterSelect id={`${report.id}-frequency`} label="Frequency" value={frequency} onChange={(value) => setFrequency(value === "weekly" ? "weekly" : "daily")}>
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
      </FilterSelect>
      <div>
        <label htmlFor={`${report.id}-hour`} className="mb-1 block text-xs font-medium text-muted-foreground">
          Hour (0–23)
        </label>
        <Input
          id={`${report.id}-hour`}
          type="number"
          min={0}
          max={23}
          value={hour}
          onChange={(event) => setHour(event.target.value)}
        />
      </div>
      {frequency === "weekly" ? (
        <FilterSelect id={`${report.id}-day`} label="Day" value={dayOfWeek} onChange={setDayOfWeek}>
          {WEEKDAYS.map((label, index) => (
            <option key={label} value={String(index)}>
              {label}
            </option>
          ))}
        </FilterSelect>
      ) : null}
      <div className={frequency === "weekly" ? "" : "sm:col-span-2"}>
        <label htmlFor={`${report.id}-recipients`} className="mb-1 block text-xs font-medium text-muted-foreground">
          Recipients
        </label>
        <Input
          id={`${report.id}-recipients`}
          value={recipients}
          placeholder="ops@example.com, lead@example.com"
          onChange={(event) => setRecipients(event.target.value)}
        />
      </div>
      <div className="flex items-end">
        <Button type="button" size="sm" variant="outline" disabled={save.isPending} onClick={() => save.mutate()}>
          {save.isPending ? "Saving…" : "Save schedule"}
        </Button>
      </div>
    </div>
  );
}

function currentFilterPayload(filters: CurrentFilters): Partial<ControlledDocumentSavedReportFilters> {
  return {
    period: filters.period,
    department: filters.department || null,
    document_type: filters.documentType || null,
    status: filters.status || null,
    review: filters.review || null,
    file: filters.file || null,
  };
}

function describeFilters(filters: ControlledDocumentSavedReportFilters): string {
  const parts = [`Last ${filters.period} days`];
  if (filters.department) parts.push(filters.department);
  if (filters.document_type) parts.push(filters.document_type);
  if (filters.status) parts.push(filters.status);
  if (filters.review) parts.push(filters.review.replaceAll("_", " "));
  if (filters.file) parts.push(filters.file === "has" ? "has file" : "missing file");
  return parts.join(" · ");
}

function slug(name: string): string {
  const value = name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
  return value || "report";
}
