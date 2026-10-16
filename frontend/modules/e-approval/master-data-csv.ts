import { parseCsv } from "@/lib/dynamic-entities/dyn-import-csv";

export const MASTER_DATA_IMPORT_IGNORE = "__ignore__";

export type MasterDataCsvColumn = {
  key: string;
  label: string;
  type: "text" | "number" | "boolean";
};

export type MasterDataExistingRow = {
  label: string;
  code: string | null;
};

export type MasterDataPlannedRow = {
  rowNumber: number;
  label: string;
  code: string;
  isActive: boolean;
  data: Record<string, unknown>;
  error: string | null;
};

const LABEL = "label";
const CODE = "code";
const ACTIVE = "is_active";

export function masterDataImportTargets(columns: MasterDataCsvColumn[]): { value: string; label: string }[] {
  return [
    { value: LABEL, label: "Label" },
    { value: CODE, label: "Code" },
    { value: ACTIVE, label: "Active" },
    ...columns.map((column) => ({ value: column.key, label: column.label })),
  ];
}

function headerKey(header: string): string {
  return header
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");
}

export function suggestMasterDataColumnMap(
  headers: string[],
  columns: MasterDataCsvColumn[],
): Record<string, string> {
  const used = new Set<string>();
  const map: Record<string, string> = {};
  for (const header of headers) {
    const key = headerKey(header);
    let target = MASTER_DATA_IMPORT_IGNORE;
    if (["label", "name"].includes(key)) {
      target = LABEL;
    } else if (["code", "value"].includes(key)) {
      target = CODE;
    } else if (["active", "is_active", "status"].includes(key)) {
      target = ACTIVE;
    } else {
      const column = columns.find(
        (item) => item.key === key || headerKey(item.label) === key,
      );
      if (column) {
        target = column.key;
      }
    }
    if (target !== MASTER_DATA_IMPORT_IGNORE && used.has(target)) {
      target = MASTER_DATA_IMPORT_IGNORE;
    }
    if (target !== MASTER_DATA_IMPORT_IGNORE) {
      used.add(target);
    }
    map[header] = target;
  }
  return map;
}

function cell(row: string[], index: number): string {
  return (row[index] ?? "").trim();
}

function parseActive(raw: string): boolean {
  if (raw === "") {
    return true;
  }
  return ["1", "true", "yes", "y", "active"].includes(raw.toLowerCase());
}

export function planMasterDataImport(
  matrix: string[][],
  columnMap: Record<string, string>,
  columns: MasterDataCsvColumn[],
  existing: MasterDataExistingRow[],
): MasterDataPlannedRow[] {
  if (matrix.length === 0) {
    return [];
  }
  const headers = matrix[0] ?? [];
  const indexes = new Map<string, number>();
  headers.forEach((header, index) => {
    const target = columnMap[header] ?? MASTER_DATA_IMPORT_IGNORE;
    if (target && target !== MASTER_DATA_IMPORT_IGNORE && !indexes.has(target)) {
      indexes.set(target, index);
    }
  });

  const seenLabels = new Set(
    existing.map((row) => row.label.trim().toLowerCase()).filter((label) => label !== ""),
  );
  const seenCodes = new Set(
    existing
      .map((row) => (row.code ?? "").trim().toLowerCase())
      .filter((code) => code !== ""),
  );
  const planned: MasterDataPlannedRow[] = [];

  matrix.slice(1).forEach((row, offset) => {
    if (row.every((value) => value.trim() === "")) {
      return;
    }
    const rowNumber = offset + 2;
    const label = cell(row, indexes.get(LABEL) ?? -1);
    const code = cell(row, indexes.get(CODE) ?? -1);
    const isActive = parseActive(cell(row, indexes.get(ACTIVE) ?? -1));
    const data: Record<string, unknown> = {};
    let error: string | null = null;

    if (!indexes.has(LABEL)) {
      error = `Row ${rowNumber}: map a column to Label.`;
    } else if (label === "") {
      error = `Row ${rowNumber} needs a label.`;
    }

    for (const column of columns) {
      const index = indexes.get(column.key);
      if (index === undefined) {
        continue;
      }
      const raw = cell(row, index);
      if (raw === "") {
        continue;
      }
      if (column.type === "boolean") {
        data[column.key] = ["1", "true", "yes", "y"].includes(raw.toLowerCase());
      } else if (column.type === "number") {
        const parsed = Number(raw);
        if (!Number.isFinite(parsed)) {
          error = error ?? `Row ${rowNumber}: ${column.label} must be a number.`;
        } else {
          data[column.key] = parsed;
        }
      } else {
        data[column.key] = raw;
      }
    }

    const labelKey = label.toLowerCase();
    const codeKey = code.toLowerCase();
    if (error === null && seenLabels.has(labelKey)) {
      error = `Row ${rowNumber} repeats the label "${label}".`;
    }
    if (error === null && codeKey !== "" && seenCodes.has(codeKey)) {
      error = `Row ${rowNumber} repeats the code "${code}".`;
    }
    if (error === null) {
      seenLabels.add(labelKey);
      if (codeKey !== "") {
        seenCodes.add(codeKey);
      }
    }

    planned.push({ rowNumber, label, code, isActive, data, error });
  });

  return planned;
}

export function parseMasterDataCsv(text: string): string[][] {
  return parseCsv(text).filter((row, index) => index === 0 || row.some((value) => value.trim() !== ""));
}

function csvCell(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

export function masterDataRowsToCsv(
  columns: MasterDataCsvColumn[],
  rows: Array<{ label: string; code: string | null; is_active: boolean; data?: Record<string, unknown> | null }>,
): string {
  const headers = ["Label", "Code", "Active", ...columns.map((column) => column.label)];
  const lines = [headers.map(csvCell).join(",")];
  for (const row of rows) {
    const data = row.data ?? {};
    const values = [
      row.label,
      row.code ?? "",
      row.is_active ? "Yes" : "No",
      ...columns.map((column) => {
        const value = data[column.key];
        if (value === null || value === undefined) {
          return "";
        }
        if (column.type === "boolean") {
          return value === true || value === "true" || value === 1 || value === "1" ? "Yes" : "No";
        }
        return String(value);
      }),
    ];
    lines.push(values.map(csvCell).join(","));
  }
  return `${lines.join("\n")}\n`;
}

export function masterDataTemplateCsv(columns: MasterDataCsvColumn[]): string {
  return masterDataRowsToCsv(columns, []);
}
