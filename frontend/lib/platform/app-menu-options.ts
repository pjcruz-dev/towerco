import {
  Activity,
  Archive,
  Building2,
  ClipboardCheck,
  CreditCard,
  ExternalLink,
  FlaskConical,
  Landmark,
  LayoutDashboard,
  LayoutGrid,
  LifeBuoy,
  Package,
  Settings,
  Shapes,
  Users,
  Waypoints,
  type LucideIcon,
} from "lucide-react";

export const APP_MENU_ICON_OPTIONS: Array<{ value: string; label: string; Icon: LucideIcon }> = [
  { value: "Shapes", label: "Shapes", Icon: Shapes },
  { value: "Building2", label: "Building", Icon: Building2 },
  { value: "FlaskConical", label: "Flask", Icon: FlaskConical },
  { value: "LayoutGrid", label: "Grid", Icon: LayoutGrid },
  { value: "LayoutDashboard", label: "Dashboard", Icon: LayoutDashboard },
  { value: "ClipboardCheck", label: "Clipboard", Icon: ClipboardCheck },
  { value: "CreditCard", label: "Credit card", Icon: CreditCard },
  { value: "Users", label: "Users", Icon: Users },
  { value: "Settings", label: "Settings", Icon: Settings },
  { value: "LifeBuoy", label: "Life buoy", Icon: LifeBuoy },
  { value: "Package", label: "Package", Icon: Package },
  { value: "Landmark", label: "Landmark", Icon: Landmark },
  { value: "Waypoints", label: "Waypoints", Icon: Waypoints },
  { value: "Activity", label: "Activity", Icon: Activity },
  { value: "Archive", label: "Archive", Icon: Archive },
  { value: "ExternalLink", label: "External link", Icon: ExternalLink },
];

export const APP_MENU_ACCENT_OPTIONS: Array<{ value: string; label: string; previewClass: string }> = [
  { value: "sky", label: "Sky", previewClass: "bg-sky-500/15 text-sky-700" },
  { value: "emerald", label: "Emerald", previewClass: "bg-emerald-500/15 text-emerald-700" },
  { value: "amber", label: "Amber", previewClass: "bg-amber-500/15 text-amber-700" },
  { value: "rose", label: "Rose", previewClass: "bg-rose-500/15 text-rose-700" },
  { value: "violet", label: "Violet", previewClass: "bg-violet-500/15 text-violet-700" },
  { value: "slate", label: "Slate", previewClass: "bg-slate-500/15 text-slate-700" },
];

const ICON_MAP: Record<string, LucideIcon> = Object.fromEntries(
  APP_MENU_ICON_OPTIONS.map((opt) => [opt.value, opt.Icon]),
);

const ACCENT_CLASS: Record<string, string> = Object.fromEntries(
  APP_MENU_ACCENT_OPTIONS.map((opt) => [opt.value, opt.previewClass]),
);

export function resolveAppMenuIcon(name: string | null | undefined): LucideIcon {
  if (!name) return Shapes;
  return ICON_MAP[name] ?? Shapes;
}

export function resolveAppMenuAccentClass(accent: string | null | undefined): string {
  if (!accent) return ACCENT_CLASS.sky!;
  return ACCENT_CLASS[accent] ?? ACCENT_CLASS.sky!;
}
