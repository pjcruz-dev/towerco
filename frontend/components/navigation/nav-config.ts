import { permissions } from "@/lib/rbac/permissions";
import type { NavItem } from "@/types/navigation";

/** Command palette / quick-nav — tenant workspace scope (board-aligned). */
export const primaryNav: NavItem[] = [
  {
    label: "Dashboard",
    href: "/dashboard",
    icon: "layout-dashboard",
    requiredPermissions: [permissions.dashboardView],
  },
  {
    label: "Dynamic Entities",
    href: "/dynamic-entities",
    icon: "shapes",
    requiredPermissions: [permissions.dynamicEntitiesView],
  },
  {
    label: "Team & Access",
    href: "/users",
    icon: "users",
    requiredPermissions: [permissions.userManage],
  },
  {
    label: "My security",
    href: "/account/security",
    icon: "shield",
    requiredPermissions: [permissions.dashboardView],
  },
  {
    label: "Sign-in & security",
    href: "/admin/settings",
    icon: "shield",
    requiredPermissions: [permissions.tenantManage],
  },
  {
    label: "Backups",
    href: "/admin/backups",
    icon: "database",
    requiredPermissions: [permissions.tenantManage],
  },
];
