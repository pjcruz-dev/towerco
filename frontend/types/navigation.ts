export type NavItem = {
  label: string;
  href: string;
  icon?: string;
  requiredPermissions?: string[];
  children?: NavItem[];
};
