export const CONTROLLED_REPORTS_PATH = "/documents/controlled/reports";

export type ControlledReportPeriod = 30 | 90 | 365;

export type ControlledReportQuery = {
  period: ControlledReportPeriod;
  department: string;
  documentType: string;
  status: string;
  review: string;
  file: string;
};

const PERIODS = new Set<ControlledReportPeriod>([30, 90, 365]);

export function readControlledReportQuery(params: URLSearchParams): ControlledReportQuery {
  const periodRaw = Number(params.get("period") ?? "90");
  const period: ControlledReportPeriod = PERIODS.has(periodRaw as ControlledReportPeriod)
    ? (periodRaw as ControlledReportPeriod)
    : 90;

  return {
    period,
    department: params.get("filter[department]") ?? "",
    documentType: params.get("filter[document_type]") ?? "",
    status: params.get("filter[status]") ?? "",
    review: params.get("filter[review]") ?? "",
    file: params.get("filter[file]") ?? "",
  };
}

export function controlledReportHref(
  current: { toString(): string },
  patch: Record<string, string | null>,
): string {
  const next = new URLSearchParams(current.toString());

  for (const [key, value] of Object.entries(patch)) {
    if (value === null || value === "") {
      next.delete(key);
    } else {
      next.set(key, value);
    }
  }

  if ((next.get("period") ?? "90") === "90") {
    next.delete("period");
  }

  const qs = next.toString();

  return qs ? `${CONTROLLED_REPORTS_PATH}?${qs}` : CONTROLLED_REPORTS_PATH;
}
