import {
  Activity,
  Archive,
  Building2,
  CircleHelp,
  ClipboardCheck,
  CreditCard,
  LifeBuoy,
  Landmark,
  LayoutDashboard,
  Package,
  PiggyBank,
  PlusCircle,
  Settings,
  ScrollText,
  Shapes,
  Users,
  Waypoints,
  type LucideIcon,
} from "lucide-react";

const ICON_BY_NAME: Record<string, LucideIcon> = {
  Activity,
  Archive,
  Building2,
  CircleHelp,
  ClipboardCheck,
  CreditCard,
  LifeBuoy,
  Landmark,
  LayoutDashboard,
  Package,
  PiggyBank,
  PlusCircle,
  Settings,
  ScrollText,
  Shapes,
  Users,
  Waypoints,
};

export function resolveLucideIcon(name: string | null | undefined): LucideIcon {
  if (!name) return Shapes;
  return ICON_BY_NAME[name] ?? Shapes;
}
