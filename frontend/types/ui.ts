export type DashboardWidget = {
  id: string;
  title: string;
  value: string;
  trend?: "up" | "down" | "neutral";
  description?: string;
};
