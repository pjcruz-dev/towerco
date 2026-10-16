export const IMPORT_BATCH_SIZE = 200;

export function parseCsv(text: string): string[][] {
  const source = text.charCodeAt(0) === 0xfeff ? text.slice(1) : text;
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = "";
  let quoted = false;

  for (let index = 0; index < source.length; index += 1) {
    const char = source[index];
    if (quoted) {
      if (char === '"') {
        if (source[index + 1] === '"') {
          cell += '"';
          index += 1;
        } else {
          quoted = false;
        }
      } else {
        cell += char;
      }
      continue;
    }
    if (char === '"') {
      quoted = true;
      continue;
    }
    if (char === ",") {
      row.push(cell);
      cell = "";
      continue;
    }
    if (char === "\n") {
      row.push(cell);
      rows.push(row);
      row = [];
      cell = "";
      continue;
    }
    if (char === "\r") continue;
    cell += char;
  }

  if (cell.length > 0 || row.length > 0) {
    row.push(cell);
    rows.push(row);
  }

  return rows;
}

export type CsvSection = {
  label: string;
  headers: string[];
  rows: string[][];
};

export type CsvBlock = {
  row: string[];
  sections: CsvSection[];
};

export function importBlocksFromCsv(text: string): { headers: string[]; blocks: CsvBlock[] } {
  const parsed = parseCsv(text);
  const header = parsed[0];
  if (!header || header.every((cell) => cell.trim() === "")) {
    return { headers: [], blocks: [] };
  }
  const parentWidth = header.length;
  const blocks: CsvBlock[] = [];
  let current: CsvBlock | null = null;
  let index = 1;
  while (index < parsed.length) {
    const line = parsed[index] ?? [];
    if (lineIsBlank(line)) {
      index += 1;
      continue;
    }
    const marker = sectionMarkerCell(line);
    if (marker?.startsWith("---")) {
      const label = marker.replace(/^---\s+/, "").replace(/\s+---$/, "");
      index += 1;
      let headers: string[] | null = null;
      const rows: string[][] = [];
      while (index < parsed.length) {
        const next = parsed[index] ?? [];
        if (lineIsBlank(next)) {
          index += 1;
          break;
        }
        if (sectionMarkerCell(next) || (parentWidth >= 8 && next.length >= parentWidth)) {
          break;
        }
        if (!headers) headers = next;
        else rows.push(next);
        index += 1;
      }
      if (current) {
        current.sections.push({ label, headers: headers ?? [], rows });
      }
      continue;
    }
    if (marker) {
      index += 2;
      continue;
    }
    current = { row: line, sections: [] };
    blocks.push(current);
    index += 1;
  }
  return { headers: header, blocks };
}

export function parentRowsFromCsv(text: string): { headers: string[]; rows: string[][] } {
  const parsed = importBlocksFromCsv(text);
  return { headers: parsed.headers, rows: parsed.blocks.map((block) => block.row) };
}

export function csvFileFromBlocks(headers: string[], blocks: CsvBlock[], filename: string): File {
  const lines: string[][] = [headers];
  for (const block of blocks) {
    lines.push(block.row);
    for (const section of block.sections) {
      lines.push(["", `--- ${section.label} ---`]);
      if (section.headers.length > 0) lines.push(section.headers);
      lines.push(...section.rows);
      lines.push([]);
    }
  }
  const body = lines.map((row) => row.map(csvCell).join(",")).join("\r\n");
  return new File([body], filename, { type: "text/csv" });
}

export function csvFileFromRows(headers: string[], rows: string[][], filename: string): File {
  const lines = [headers, ...rows].map((row) => row.map(csvCell).join(","));
  return new File([lines.join("\r\n")], filename, { type: "text/csv" });
}

function csvCell(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`;
  }
  return value;
}

function lineIsBlank(line: string[]): boolean {
  return line.every((cell) => cell.trim() === "");
}

function sectionMarkerCell(line: string[]): string | null {
  const filled = line.map((cell) => cell.trim()).filter((cell) => cell !== "");
  if (filled.length !== 1) return null;
  const cell = filled[0] ?? "";
  return /^(?:##|---)\s+.+\s+(?:##|---)$/.test(cell) ? cell : null;
}
