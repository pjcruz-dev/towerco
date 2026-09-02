/**
 * Display labels for Metacoresoft / ATC operational role names.
 */
const ROLE_DISPLAY_LABELS: Record<string, string> = {
  admin: "Admin",
  administrator: "Administrator",
  commercial_sales_officer: "Commercial / Sales Officer",
  finance_officer: "Finance Officer",
  procurement_officer: "Procurement Officer",
  project_manager: "Project Manager",
  sa_officer: "SA Officer",
  sales: "Sales",
  staff: "Staff",
};

export function roleDisplayLabel(name: string): string {
  const key = name.trim().toLowerCase();
  if (ROLE_DISPLAY_LABELS[key]) {
    return ROLE_DISPLAY_LABELS[key];
  }
  return name.replace(/_/g, " ");
}
