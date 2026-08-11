export type SiteListRow = {
  id: string;
  site_code: string;
  name: string;
  latitude: string | null;
  longitude: string | null;
  type: string | null;
  status: string;
  created_at: string | null;
  updated_at: string | null;
};

export type SiteDetail = SiteListRow & {
  towers_count?: number;
  projects_count?: number;
};
