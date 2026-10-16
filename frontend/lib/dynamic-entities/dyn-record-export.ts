import type { DynRecordListRow } from "@/lib/api/modules/dynamic-entities-api";

export type DynExportColumn = {
  id: string;
  label: string;
};

export type DynExportRelation = {
  entity_slug: string;
  foreign_field: string;
  label: string;
};

const RELATION_ORDER = [
  "saq_trackers",
  "construction_projects",
  "power_trackers",
  "postcon_trackers",
  "telco_contracts",
  "site_colocations",
  "site_permits",
  "land_leases",
];

const RELATION_LABELS: Record<string, string> = {
  telco_contracts: "Telco Contracts / Site Licences",
};

export function relationsForExport(
  tabs: Array<Record<string, unknown>> | undefined,
): DynExportRelation[] {
  const parsed: DynExportRelation[] = [];
  for (const tab of tabs ?? []) {
    const slug = typeof tab.entity_slug === "string" ? tab.entity_slug.trim() : "";
    if (!slug) continue;
    const foreign = typeof tab.foreign_field === "string" ? tab.foreign_field.trim() : "";
    const rawLabel = typeof tab.label === "string" ? tab.label.trim() : "";
    parsed.push({
      entity_slug: slug,
      foreign_field: foreign,
      label: RELATION_LABELS[slug] ?? (rawLabel || slug),
    });
  }
  const rank = (slug: string) => {
    const index = RELATION_ORDER.indexOf(slug);
    return index === -1 ? RELATION_ORDER.length : index;
  };
  return parsed.sort(
    (a, b) => rank(a.entity_slug) - rank(b.entity_slug) || a.label.localeCompare(b.label),
  );
}

export function exportCell(
  row: Pick<DynRecordListRow, "id" | "title" | "status" | "source_external_id" | "columns">,
  columnId: string,
): string {
  if (columnId === "title") return row.title ?? "";
  if (columnId === "status") return row.status ?? "";
  if (columnId === "id") return row.source_external_id || row.id;
  const value = row.columns[columnId];
  if (value == null) return "";
  if (Array.isArray(value)) return value.map((item) => String(item)).join(", ");
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

export function childrenForParent(
  parent: Pick<DynRecordListRow, "id" | "title">,
  relation: DynExportRelation,
  children: DynRecordListRow[],
): DynRecordListRow[] {
  const seen = new Set<string>();
  const matched: DynRecordListRow[] = [];
  const title = parent.title?.trim() ?? "";
  for (const child of children) {
    const foreign = relation.foreign_field ? child.columns[relation.foreign_field] : null;
    const foreignText = foreign == null ? "" : String(foreign).trim();
    const linked =
      child.parent_record_id === parent.id ||
      foreignText === parent.id ||
      (title !== "" && foreignText === title);
    if (!linked || seen.has(child.id)) continue;
    seen.add(child.id);
    matched.push(child);
  }
  return matched;
}

export function assembleExportRows(input: {
  parents: DynRecordListRow[];
  parentColumns: DynExportColumn[];
  relations: DynExportRelation[];
  childrenByRelation: Record<string, DynRecordListRow[]>;
  childColumns: Record<string, DynExportColumn[]>;
}): string[][] {
  const rows: string[][] = [input.parentColumns.map((column) => column.label)];
  for (const parent of input.parents) {
    rows.push(input.parentColumns.map((column) => exportCell(parent, column.id)));
    for (const relation of input.relations) {
      const kids = childrenForParent(
        parent,
        relation,
        input.childrenByRelation[relation.entity_slug] ?? [],
      );
      if (kids.length === 0) continue;
      const columns = input.childColumns[relation.entity_slug] ?? [
        { id: "title", label: "Title" },
      ];
      rows.push(["--- "+relation.label+" ---"]);
      rows.push(columns.map((column) => column.label));
      for (const child of kids) {
        rows.push(columns.map((column) => exportCell(child, column.id)));
      }
      rows.push([]);
    }
  }
  return rows;
}

export function rowsToCsv(rows: string[][]): string {
  const lines = rows.map((row) => row.map(csvCell).join(","));
  return `\uFEFF${lines.join("\r\n")}`;
}

function csvCell(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

export function exportColumnsFromFields(
  fields: Array<{
    name: string;
    label: string;
    show_in_table: boolean;
    is_system_field?: boolean;
  }>,
): DynExportColumn[] {
  const byName = new Map(fields.map((field) => [field.name, field]));
  const columns: DynExportColumn[] = [];
  const status = byName.get("status");
  const title = byName.get("title");
  if (status?.show_in_table) {
    columns.push({ id: "status", label: status.label.trim() || "Status" });
  }
  if (title?.show_in_table) {
    columns.push({ id: "title", label: title.label.trim() || "Title" });
  }
  const rest = fields
    .filter(
      (field) =>
        field.show_in_table &&
        !field.is_system_field &&
        !["actions", "workflows", "print", "id", "status", "title"].includes(field.name),
    )
    .map((field) => ({ id: field.name, label: field.label.trim() || field.name }));
  columns.push(...rest);
  if (columns.length === 0) {
    columns.push({ id: "title", label: "Title" });
  }
  return columns;
}
