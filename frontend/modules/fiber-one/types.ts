import type { ProjectOneKpi } from "@/modules/project-one/types";

export type FiberOneRouteRow = {
  id: string;
  name: string;
  status: string;
  from?: string | null;
  to?: string | null;
  length_km: string | null;
};

export type FiberRouteListRow = {
  id: string;
  name: string;
  status: string;
  length_km: string | null;
  from_site: { id: string; site_code: string; name: string } | null;
  to_site: { id: string; site_code: string; name: string } | null;
  created_at: string | null;
  updated_at: string | null;
};

export type FiberOneDashboardResponse = {
  kpis: ProjectOneKpi[];
  routes: FiberOneRouteRow[];
};
