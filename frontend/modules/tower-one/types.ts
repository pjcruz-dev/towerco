import type { ProjectOneKpi } from "@/modules/project-one/types";

export type TowerOneTowerRow = {
  id: string;
  site: string;
  site_code: string;
  tower_type: string;
  status: string;
  height_m: string | null;
};

export type TowerListRow = {
  id: string;
  tower_type: string;
  height_m: string | null;
  capacity_kg: string | null;
  max_tenants: number | null;
  status: string;
  site: { id: string; site_code: string; name: string } | null;
  created_at: string | null;
  updated_at: string | null;
};

export type TowerDetail = TowerListRow;

export type TowerOneDashboardResponse = {
  kpis: ProjectOneKpi[];
  towers: TowerOneTowerRow[];
};
