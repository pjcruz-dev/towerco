export type DashboardKpi = {
  key: string;
  label: string;
  value: string | number;
  change?: string;
  tone?: "neutral" | "success" | "warning" | "danger";
  href?: string | null;
};
