/**
 * Display labels for Metacoresoft / ATC operational role names.
 */
const ROLE_DISPLAY_LABELS: Record<string, string> = {
  tenant_admin: "Tenant admin",
  admin: "Operations admin",
  administrator: "Tenant admin (alias)",
  commercial_sales_officer: "Commercial / Sales Officer",
  finance_officer: "Finance Officer",
  procurement_officer: "Procurement Officer",
  project_manager: "Project Manager",
  sa_officer: "SA Officer",
  sales: "Sales",
  staff: "Staff",
  dynamic_entities_admin: "Dynamic Entities admin",
  dynamic_entities_contributor: "Dynamic Entities contributor",
  dynamic_entities_viewer: "Dynamic Entities viewer",
};

export function roleDisplayLabel(name: string): string {
  const key = name.trim().toLowerCase();
  if (ROLE_DISPLAY_LABELS[key]) {
    return ROLE_DISPLAY_LABELS[key];
  }
  return name.replace(/_/g, " ");
}
