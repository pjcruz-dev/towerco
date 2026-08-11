import type { ProjectOneKpi } from "@/modules/project-one/types";

export type AssetOneCategoryRow = {
  category: string;
  count: number;
};

export type AssetOneRow = {
  id: string;
  asset_code: string;
  name: string;
  category: string;
  status: string;
};

export type AssetListRow = {
  id: string;
  asset_code: string;
  name: string;
  category: string;
  status: string;
  rfid_tag: string | null;
  location_type: string | null;
  location_id: string | null;
  warranty_expiry: string | null;
  purchase_value: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type AssetDetail = AssetListRow;

export type AssetOneDashboardResponse = {
  kpis: ProjectOneKpi[];
  by_category: AssetOneCategoryRow[];
  assets: AssetOneRow[];
};
