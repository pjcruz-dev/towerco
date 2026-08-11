import {
  AlignLeft,
  AtSign,
  Calculator,
  Calendar,
  CalendarRange,
  CheckSquare,
  CircleDot,
  DollarSign,
  Columns2,
  Grid3x3,
  Hash,
  Info,
  LayoutGrid,
  Link2,
  List,
  ListChecks,
  MapPin,
  Minus,
  Paperclip,
  PenLine,
  Phone,
  Ruler,
  SeparatorHorizontal,
  Star,
  Table2,
  Tags,
  Type,
  UserCheck,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import {
  E_APPROVAL_FIELD_ADD_GROUPS,
  formatEApprovalFieldTypeLabel,
  type EApprovalFieldType,
} from "@/modules/e-approval/field-types";
import {
  E_APPROVAL_FORM_FIELD_BUNDLES,
  formFieldBundleCatalogDragId,
  type EApprovalFormFieldBundleId,
} from "@/modules/e-approval/custom-form-presets";
import { catalogRowDragId, type EApprovalLayoutRowColumns } from "@/modules/e-approval/field-layout";

export const E_APPROVAL_FIELD_ICONS: Partial<Record<EApprovalFieldType, LucideIcon>> = {
  text: Type,
  textarea: AlignLeft,
  email: AtSign,
  phone: Phone,
  url: Link2,
  number: Hash,
  currency: DollarSign,
  date: Calendar,
  date_range: CalendarRange,
  select: List,
  radio: CircleDot,
  checkbox: CheckSquare,
  matrix: LayoutGrid,
  size_matrix: Ruler,
  checklist_matrix: ListChecks,
  approver: UserCheck,
  file: Paperclip,
  signature: PenLine,
  rating: Star,
  location: MapPin,
  tags: Tags,
  grid: Table2,
  section: Grid3x3,
  divider: Minus,
  page_break: SeparatorHorizontal,
  instruction: Info,
};

export type EApprovalCatalogPick =
  | { kind: "field"; type: EApprovalFieldType }
  | { kind: "master-data" }
  | { kind: "layout-row"; columns: EApprovalLayoutRowColumns }
  | { kind: "bundle"; bundle: EApprovalFormFieldBundleId };

export const E_APPROVAL_LAYOUT_ROW_PICKS: { columns: EApprovalLayoutRowColumns; label: string }[] = [
  { columns: 2, label: "2-column row" },
  { columns: 3, label: "3-column row" },
  { columns: 4, label: "4-column row" },
];

export const E_APPROVAL_CATALOG_PICKS: { group: string; picks: EApprovalCatalogPick[] }[] = [
  {
    group: "Finance shortcuts",
    picks: E_APPROVAL_FORM_FIELD_BUNDLES.map((bundle) => ({ kind: "bundle" as const, bundle: bundle.id })),
  },
  ...E_APPROVAL_FIELD_ADD_GROUPS.map((g) => ({
    group: g.label,
    picks: g.types.map((type) => ({ kind: "field" as const, type })),
  })),
  {
    group: "Data sources",
    picks: [{ kind: "master-data" as const }],
  },
];

export function catalogPickDragId(pick: EApprovalCatalogPick): string {
  if (pick.kind === "master-data") {
    return "catalog-field:master-data";
  }
  if (pick.kind === "bundle") {
    return formFieldBundleCatalogDragId(pick.bundle);
  }
  if (pick.kind === "layout-row") {
    return catalogRowDragId(pick.columns);
  }

  return `catalog-field:${pick.type}`;
}

export function catalogPickLabel(pick: EApprovalCatalogPick): string {
  if (pick.kind === "master-data") {
    return "Dropdown (master data)";
  }
  if (pick.kind === "bundle") {
    return E_APPROVAL_FORM_FIELD_BUNDLES.find((bundle) => bundle.id === pick.bundle)?.label ?? "Field bundle";
  }
  if (pick.kind === "layout-row") {
    return E_APPROVAL_LAYOUT_ROW_PICKS.find((r) => r.columns === pick.columns)?.label ?? `${pick.columns}-column row`;
  }

  return formatEApprovalFieldTypeLabel(pick.type);
}

export function catalogPickIcon(pick: EApprovalCatalogPick): LucideIcon {
  if (pick.kind === "master-data") {
    return List;
  }
  if (pick.kind === "bundle") {
    return Calculator;
  }
  if (pick.kind === "layout-row") {
    return Columns2;
  }

  return E_APPROVAL_FIELD_ICONS[pick.type] ?? Type;
}
