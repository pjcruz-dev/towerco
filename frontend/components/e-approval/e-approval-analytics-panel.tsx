"use client";

import {
  createContext,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { useQuery } from "@tanstack/react-query";

import { DashboardKindWidget } from "@/components/dashboard/widgets/dashboard-kind-widget";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { FilterSelect } from "@/components/forms/filter-select";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import type { ReportsAnalyticsSectionId } from "@/lib/e-approval/reports-board-config";
import {
  fetchEApprovalAnalytics,
  fetchEApprovalFormsIndex,
  fetchEApprovalMetadata,
  type EApprovalAnalyticsResponse,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { getCatalogEntry, type DashboardWidgetOptions } from "@/lib/ui/dashboard-widget-catalog";
import {
  emptyNormalizedData,
  type DashboardNormalizedData,
} from "@/lib/ui/dashboard-widget-data";
import { normalizeEApprovalAnalytics } from "@/lib/ui/normalize-dashboard-data";

function defaultRange(): { from: string; to: string } {
  const to = new Date();
  const from = new Date();
  from.setDate(to.getDate() - 29);
  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
  };
}

type AnalyticsBoardContextValue = {
  data: EApprovalAnalyticsResponse | undefined;
  normalizedData: DashboardNormalizedData;
  isLoading: boolean;
  isFetching: boolean;
  error: unknown;
};

const AnalyticsBoardContext = createContext<AnalyticsBoardContextValue | null>(null);

function useAnalyticsBoard(): AnalyticsBoardContextValue {
  const ctx = useContext(AnalyticsBoardContext);
  if (!ctx) {
    throw new Error("Analytics widgets must be used within EApprovalAnalyticsBoardProvider");
  }
  return ctx;
}

export function useEApprovalAnalyticsBoard(): AnalyticsBoardContextValue {
  return useAnalyticsBoard();
}

export function EApprovalAnalyticsBoardProvider({ children }: { children: ReactNode }) {
  const defaults = useMemo(() => defaultRange(), []);
  const [from, setFrom] = useState(defaults.from);
  const [to, setTo] = useState(defaults.to);
  const [formId, setFormId] = useState("");
  const [subsidiary, setSubsidiary] = useState("");
  const [department, setDepartment] = useState("");
  const [applied, setApplied] = useState({
    from: defaults.from,
    to: defaults.to,
    formId: "",
    subsidiary: "",
    department: "",
  });

  const metadataQuery = useQuery({
    queryKey: ["e-approval", "metadata"],
    queryFn: fetchEApprovalMetadata,
    staleTime: 60_000,
  });
  const formsQuery = useQuery({
    queryKey: ["e-approval", "forms", "filter-options"],
    queryFn: () => fetchEApprovalFormsIndex({ per_page: 100, status: "published", sort: "name:asc" }),
    staleTime: 60_000,
  });

  const query = useQuery({
    queryKey: [
      "e-approval",
      "analytics",
      applied.from,
      applied.to,
      applied.formId,
      applied.subsidiary,
      applied.department,
    ],
    queryFn: () =>
      fetchEApprovalAnalytics({
        from: applied.from,
        to: applied.to,
        form_id: applied.formId || undefined,
        subsidiary: applied.subsidiary || undefined,
        department: applied.department || undefined,
      }),
  });

  const value = useMemo<AnalyticsBoardContextValue>(
    () => ({
      data: query.data,
      normalizedData: normalizeEApprovalAnalytics(query.data),
      isLoading: query.isLoading,
      isFetching: query.isFetching,
      error: query.error,
    }),
    [query.data, query.error, query.isFetching, query.isLoading],
  );

  const subsidiaryOptions = metadataQuery.data?.subsidiaries ?? [];
  const departmentOptions = metadataQuery.data?.departments ?? [];
  const formOptions = formsQuery.data?.data ?? [];
  const hasAdvancedFilters = Boolean(formId || subsidiary || department);

  return (
    <AnalyticsBoardContext.Provider value={value}>
      <AnalyticsFiltersBridge
        from={from}
        to={to}
        formId={formId}
        subsidiary={subsidiary}
        department={department}
        setFrom={setFrom}
        setTo={setTo}
        setFormId={setFormId}
        setSubsidiary={setSubsidiary}
        setDepartment={setDepartment}
        formOptions={formOptions}
        subsidiaryOptions={subsidiaryOptions}
        departmentOptions={departmentOptions}
        hasAdvancedFilters={hasAdvancedFilters}
        isFetching={query.isFetching}
        onApply={() =>
          setApplied({
            from,
            to,
            formId,
            subsidiary,
            department,
          })
        }
        onClear={() => {
          setFormId("");
          setSubsidiary("");
          setDepartment("");
          setApplied((prev) => ({
            ...prev,
            formId: "",
            subsidiary: "",
            department: "",
          }));
        }}
      >
        {children}
      </AnalyticsFiltersBridge>
    </AnalyticsBoardContext.Provider>
  );
}

type FilterBridgeProps = {
  children: ReactNode;
  from: string;
  to: string;
  formId: string;
  subsidiary: string;
  department: string;
  setFrom: (value: string) => void;
  setTo: (value: string) => void;
  setFormId: (value: string) => void;
  setSubsidiary: (value: string) => void;
  setDepartment: (value: string) => void;
  formOptions: Array<{ id: string; name: string }>;
  subsidiaryOptions: string[];
  departmentOptions: string[];
  hasAdvancedFilters: boolean;
  isFetching: boolean;
  onApply: () => void;
  onClear: () => void;
};

const FiltersUiContext = createContext<Omit<FilterBridgeProps, "children"> | null>(null);

function AnalyticsFiltersBridge({ children, ...props }: FilterBridgeProps) {
  return <FiltersUiContext.Provider value={props}>{children}</FiltersUiContext.Provider>;
}

type AnalyticsSectionChrome = {
  title?: string;
  description?: string | null;
  compact?: boolean;
};

function resolveDescription(
  description: string | null | undefined,
  fallback: string,
): string | undefined {
  if (description === null) return undefined;
  if (description !== undefined) return description;
  return fallback;
}

function AnalyticsFiltersCard({ title, description, compact }: AnalyticsSectionChrome) {
  const props = useContext(FiltersUiContext);
  const { data, isLoading, error } = useAnalyticsBoard();
  if (!props) return null;

  return (
    <EApprovalSectionCard
      title={title?.trim() || "Analytics filters"}
      description={resolveDescription(
        description,
        "Operational volume, cycle time, bottlenecks, and SLA aging for the selected period.",
      )}
      className={compact ? "shadow-none" : undefined}
      bodyClassName={compact ? "p-3" : undefined}
      actions={
        <div className="flex flex-wrap items-end gap-2" data-help="ea-analytics-advanced-filters">
          <FilterSelect
            id="analytics-form-filter"
            label="Form"
            value={props.formId}
            onChange={props.setFormId}
            className="w-[12rem]"
          >
            <option value="">All forms</option>
            {props.formOptions.map((form) => (
              <option key={form.id} value={form.id}>
                {form.name}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect
            id="analytics-subsidiary-filter"
            label="Subsidiary"
            value={props.subsidiary}
            onChange={props.setSubsidiary}
            className="w-[9.5rem]"
          >
            <option value="">All subsidiaries</option>
            {props.subsidiaryOptions.map((code) => (
              <option key={code} value={code}>
                {code}
              </option>
            ))}
          </FilterSelect>
          <FilterSelect
            id="analytics-department-filter"
            label="Department"
            value={props.department}
            onChange={props.setDepartment}
            className="w-[11rem]"
          >
            <option value="">All departments</option>
            {props.departmentOptions.map((code) => (
              <option key={code} value={code}>
                {code}
              </option>
            ))}
          </FilterSelect>
          <div className="space-y-1">
            <Label htmlFor="analytics-from" className="text-xs">
              From
            </Label>
            <DatePicker id="analytics-from" value={props.from} onChange={props.setFrom} className="h-8 w-[140px]" />
          </div>
          <div className="space-y-1">
            <Label htmlFor="analytics-to" className="text-xs">
              To
            </Label>
            <DatePicker id="analytics-to" value={props.to} onChange={props.setTo} className="h-8 w-[140px]" />
          </div>
          <Button type="button" size="sm" onClick={props.onApply} disabled={props.isFetching}>
            {props.isFetching ? <Spinner className="mr-1.5 size-3.5" /> : null}
            Apply
          </Button>
          {props.hasAdvancedFilters ? (
            <Button type="button" size="sm" variant="ghost" onClick={props.onClear}>
              Clear
            </Button>
          ) : null}
        </div>
      }
    >
      {error ? <p className="text-sm text-destructive">{getErrorMessage(error)}</p> : null}
      {isLoading && !data ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Spinner className="size-3.5" /> Loading analytics…
        </div>
      ) : null}
      {data ? (
        <p className="text-xs text-muted-foreground">
          Showing {data.period.from} → {data.period.to} ({data.period.days} days). Chart Layout & options can
          switch data source, max items, and sort.
        </p>
      ) : !isLoading ? (
        <p className="text-sm text-muted-foreground">Apply filters to load analytics.</p>
      ) : null}
    </EApprovalSectionCard>
  );
}

/** Kind-based analytics widget — full Layout & options (data source / max / sort). */
export function EApprovalAnalyticsKindSlot({
  entryId,
  title,
  options,
}: {
  entryId: string;
  title?: string;
  options?: DashboardWidgetOptions;
}) {
  const { normalizedData, isLoading, data } = useAnalyticsBoard();
  const entry = getCatalogEntry(entryId);
  if (!entry) return null;
  if (isLoading && !data) {
    return (
      <div className="flex min-h-[8rem] items-center gap-2 rounded-xl border border-border bg-card px-4 text-sm text-muted-foreground shadow-sm">
        <Spinner className="size-3.5" /> Loading {entry.label}…
      </div>
    );
  }
  return (
    <DashboardKindWidget
      entry={entry}
      data={normalizedData.kpis.length || Object.keys(normalizedData.series).length ? normalizedData : emptyNormalizedData()}
      title={title}
      options={options}
    />
  );
}

export function EApprovalAnalyticsSection({
  section,
  title,
  description,
  compact,
}: { section: ReportsAnalyticsSectionId } & AnalyticsSectionChrome) {
  if (section === "filters") {
    return <AnalyticsFiltersCard title={title} description={description} compact={compact} />;
  }
  return null;
}

/** @deprecated Prefer Reports Customize board. */
export function EApprovalAnalyticsPanel() {
  return (
    <EApprovalAnalyticsBoardProvider>
      <div className="space-y-4">
        <EApprovalAnalyticsSection section="filters" />
        <EApprovalAnalyticsKindSlot entryId="reports_analytics_kpis" />
        <div className="grid gap-4 lg:grid-cols-2">
          <EApprovalAnalyticsKindSlot entryId="reports_analytics_trend" />
          <EApprovalAnalyticsKindSlot entryId="reports_analytics_status" />
        </div>
        <div className="grid gap-4 lg:grid-cols-2">
          <EApprovalAnalyticsKindSlot entryId="reports_analytics_top_forms" />
          <EApprovalAnalyticsKindSlot entryId="reports_analytics_aging" />
        </div>
      </div>
    </EApprovalAnalyticsBoardProvider>
  );
}
