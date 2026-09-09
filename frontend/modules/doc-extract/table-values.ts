import type { DocExtractField, DocExtractTableColumn } from "@/modules/doc-extract/types";

export type DocExtractTableData = {
  columns: DocExtractTableColumn[];
  rows: Array<Record<string, string>>;
};

export function parseDocExtractTableValue(
  raw: string | null | undefined,
  field?: DocExtractField | null,
): DocExtractTableData {
  const columns = (field?.columns ?? []).map((column) => ({
    key: column.key,
    label: column.label,
    type: column.type,
    description: column.description ?? null,
  }));

  if (!raw || raw.trim() === "") {
    return { columns, rows: [] };
  }

  try {
    const parsed = JSON.parse(raw) as unknown;
    if (Array.isArray(parsed)) {
      const rows = parsed
        .filter((row): row is Record<string, unknown> => row !== null && typeof row === "object")
        .map((row) => normalizeRow(row, columns));
      return {
        columns: columns.length > 0 ? columns : inferColumns(rows),
        rows,
      };
    }
    if (parsed && typeof parsed === "object") {
      const object = parsed as { columns?: unknown; rows?: unknown };
      const nestedColumns = Array.isArray(object.columns)
        ? object.columns
            .filter((column): column is Record<string, unknown> => column !== null && typeof column === "object")
            .map((column, index) => ({
              key: String(column.key ?? `col_${index + 1}`),
              label: String(column.label ?? column.key ?? `Column ${index + 1}`),
              type: (String(column.type ?? "text") as DocExtractTableColumn["type"]) || "text",
              description: column.description ? String(column.description) : null,
            }))
        : columns;
      const rows = Array.isArray(object.rows)
        ? object.rows
            .filter((row): row is Record<string, unknown> => row !== null && typeof row === "object")
            .map((row) => normalizeRow(row, nestedColumns))
        : [];
      return {
        columns: nestedColumns.length > 0 ? nestedColumns : inferColumns(rows),
        rows,
      };
    }
  } catch {
    // Fall through to plaintext lines.
  }

  const lines = raw
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);
  if (lines.length === 0) {
    return { columns, rows: [] };
  }

  const delimiter = lines[0].includes("\t") ? "\t" : lines[0].includes("|") ? "|" : ",";
  const header = lines[0].split(delimiter).map((cell) => cell.trim());
  const inferred =
    columns.length > 0
      ? columns
      : header.map((label, index) => ({
          key: label.toLowerCase().replace(/[^a-z0-9]+/g, "_") || `col_${index + 1}`,
          label,
          type: "text" as const,
          description: null,
        }));
  const start = columns.length > 0 ? 0 : 1;
  const rows = lines.slice(start).map((line) => {
    const cells = line.split(delimiter).map((cell) => cell.trim());
    const row: Record<string, string> = {};
    inferred.forEach((column, index) => {
      row[column.key] = cells[index] ?? "";
    });
    return row;
  });

  return { columns: inferred, rows };
}

export function stringifyDocExtractTableValue(data: DocExtractTableData): string {
  return JSON.stringify({
    columns: data.columns.map((column) => ({
      key: column.key,
      label: column.label,
      type: column.type,
      description: column.description ?? null,
    })),
    rows: data.rows,
  });
}

export function isTableLikeField(field: DocExtractField): boolean {
  return field.type === "table";
}

function normalizeRow(
  row: Record<string, unknown>,
  columns: DocExtractTableColumn[],
): Record<string, string> {
  const next: Record<string, string> = {};
  if (columns.length > 0) {
    for (const column of columns) {
      const value = row[column.key] ?? row[column.label];
      next[column.key] = value == null ? "" : String(value);
    }
    return next;
  }
  for (const [key, value] of Object.entries(row)) {
    next[key] = value == null ? "" : String(value);
  }
  return next;
}

function inferColumns(rows: Array<Record<string, string>>): DocExtractTableColumn[] {
  const keys = new Set<string>();
  for (const row of rows) {
    Object.keys(row).forEach((key) => keys.add(key));
  }
  return Array.from(keys).map((key) => ({
    key,
    label: key.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase()),
    type: "text",
    description: null,
  }));
}
