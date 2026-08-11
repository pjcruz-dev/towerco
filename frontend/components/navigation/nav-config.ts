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
    label: "Sites",
    href: "/sites",
    icon: "map-pin",
    requiredPermissions: [permissions.sitesView],
  },
  {
    label: "Project-One",
    href: "/project-one",
    icon: "briefcase-business",
    requiredPermissions: [permissions.projectOneView],
  },
  {
    label: "TOWER-ONE",
    href: "/tower-one",
    icon: "landmark",
    requiredPermissions: [permissions.towerOneView],
  },
  {
    label: "FIBER-ONE",
    href: "/fiber-one",
    icon: "waypoints",
    requiredPermissions: [permissions.fiberOneView],
  },
  {
    label: "ASSET-ONE",
    href: "/asset-one",
    icon: "package",
    requiredPermissions: [permissions.assetOneView],
  },
  {
    label: "GIS",
    href: "/gis",
    icon: "map",
    requiredPermissions: [permissions.gisView],
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
];
