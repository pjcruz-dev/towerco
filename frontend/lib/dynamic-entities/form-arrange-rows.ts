import type { DynField } from "@/lib/api/modules/dynamic-entities-api";

export type ArrangeField = {
  id: string;
  name: string;
  label: string;
  type: string;
  column_span: number;
};

export type ArrangeRow = {
  id: string;
  fields: ArrangeField[];
};

const HIDDEN_NAMES = new Set(["actions", "workflows", "print", "id"]);

export function isArrangeableField(field: DynField): boolean {
  if (HIDDEN_NAMES.has(field.name)) return false;
  if (field.is_system_field && field.name !== "status") return false;
  return true;
}

export function clampSpan(span: number): number {
  if (!Number.isFinite(span)) return 6;
  return Math.min(12, Math.max(1, Math.round(span)));
}

export function rowUsed(row: ArrangeRow): number {
  return row.fields.reduce((sum, f) => sum + clampSpan(f.column_span), 0);
}

/** Pack fields by field_order into rows that fit a 12-column grid. */
export function packFieldsIntoRows(fields: DynField[]): ArrangeRow[] {
  const sorted = [...fields]
    .filter(isArrangeableField)
    .sort((a, b) => a.field_order - b.field_order || a.label.localeCompare(b.label));

  const rows: ArrangeRow[] = [];
  let current: ArrangeField[] = [];
  let used = 0;

  for (const field of sorted) {
    const span = clampSpan(field.column_span || 6);
    if (current.length > 0 && used + span > 12) {
      rows.push({ id: newRowId(), fields: current });
      current = [];
      used = 0;
    }
    current.push({
      id: field.id,
      name: field.name,
      label: field.label,
      type: field.type,
      column_span: span,
    });
    used += span;
  }

  if (current.length > 0) {
    rows.push({ id: newRowId(), fields: current });
  }

  if (rows.length === 0) {
    rows.push({ id: newRowId(), fields: [] });
  }

  return rows;
}

/** Flatten rows into ordered field_order + column_span patches. */
export function flattenRowsToPatches(
  rows: ArrangeRow[],
): Array<{ id: string; field_order: number; column_span: number }> {
  const patches: Array<{ id: string; field_order: number; column_span: number }> = [];
  let order = 10;
  for (const row of rows) {
    for (const field of row.fields) {
      patches.push({
        id: field.id,
        field_order: order,
        column_span: clampSpan(field.column_span),
      });
      order += 10;
    }
  }
  return patches;
}

export function setFieldSpan(rows: ArrangeRow[], fieldId: string, nextSpan: number): ArrangeRow[] {
  return rows.map((row) => {
    const idx = row.fields.findIndex((f) => f.id === fieldId);
    if (idx < 0) return row;
    const others = row.fields.reduce(
      (sum, f, i) => (i === idx ? sum : sum + clampSpan(f.column_span)),
      0,
    );
    const max = Math.max(1, 12 - others);
    const span = Math.min(max, clampSpan(nextSpan));
    return {
      ...row,
      fields: row.fields.map((f, i) => (i === idx ? { ...f, column_span: span } : f)),
    };
  });
}

export function moveFieldInRows(
  rows: ArrangeRow[],
  activeId: string,
  overId: string,
): ArrangeRow[] {
  if (activeId === overId) return rows;

  const rowOverId = parseArrangeRowDroppableId(overId) ?? overId;

  const fromRowIndex = rows.findIndex((r) => r.fields.some((f) => f.id === activeId));
  if (fromRowIndex < 0) return rows;

  const activeField = rows[fromRowIndex]!.fields.find((f) => f.id === activeId);
  if (!activeField) return rows;

  let toRowIndex = rows.findIndex(
    (r) => r.id === rowOverId || r.fields.some((f) => f.id === overId),
  );
  if (toRowIndex < 0) return rows;

  const fromIndex = rows[fromRowIndex]!.fields.findIndex((f) => f.id === activeId);

  // Already sitting on a row droppable and it's the only/last slot — no change.
  if (parseArrangeRowDroppableId(overId) && fromRowIndex === toRowIndex) {
    const onlyInThisRow = rows[fromRowIndex]!.fields.length === 1;
    const alreadyLast =
      fromIndex === rows[fromRowIndex]!.fields.length - 1 &&
      fromRowIndex === toRowIndex;
    if (onlyInThisRow || alreadyLast) {
      return rows;
    }
  }

  // Already immediately before the hovered field in the same row.
  if (
    fromRowIndex === toRowIndex &&
    !parseArrangeRowDroppableId(overId) &&
    rows[toRowIndex]!.fields[fromIndex + 1]?.id === overId
  ) {
    return rows;
  }

  const next = rows.map((r) => ({ ...r, fields: [...r.fields] }));
  const fromFields = next[fromRowIndex]!.fields;
  fromFields.splice(fromIndex, 1);

  const toRowId = rows[toRowIndex]!.id;
  toRowIndex = next.findIndex((r) => r.id === toRowId);
  if (toRowIndex < 0) return rows;

  const toFields = next[toRowIndex]!.fields;
  let insertAt = toFields.findIndex((f) => f.id === overId);
  if (insertAt < 0) {
    insertAt = toFields.length;
  } else if (fromRowIndex === toRowIndex && fromIndex < insertAt) {
    insertAt -= 1;
  }

  const span = clampSpan(activeField.column_span);
  toFields.splice(insertAt, 0, { ...activeField, column_span: span });

  return overflowRowsToFit(next);
}

/** True when field ids + spans match across rows (ignores row ids). */
export function arrangeLayoutsEqual(a: ArrangeRow[], b: ArrangeRow[]): boolean {
  if (a.length !== b.length) return false;
  for (let i = 0; i < a.length; i++) {
    const af = a[i]!.fields;
    const bf = b[i]!.fields;
    if (af.length !== bf.length) return false;
    for (let j = 0; j < af.length; j++) {
      if (af[j]!.id !== bf[j]!.id || af[j]!.column_span !== bf[j]!.column_span) {
        return false;
      }
    }
  }
  return true;
}

/** Push fields that exceed 12 columns into following rows (keeps 4/4/4 drops usable). */
export function overflowRowsToFit(rows: ArrangeRow[]): ArrangeRow[] {
  const next = rows.map((r) => ({ ...r, fields: [...r.fields] }));
  let i = 0;
  while (i < next.length) {
    const row = next[i]!;
    let used = rowUsed(row);
    while (used > 12 && row.fields.length > 1) {
      const overflow = row.fields.pop()!;
      used = rowUsed(row);
      if (!next[i + 1]) {
        next.splice(i + 1, 0, { id: newRowId(), fields: [] });
      }
      next[i + 1]!.fields.unshift(overflow);
    }
    // Single field wider than 12 — clamp
    if (row.fields.length === 1 && clampSpan(row.fields[0]!.column_span) > 12) {
      row.fields[0] = { ...row.fields[0]!, column_span: 12 };
    }
    i += 1;
  }

  const cleaned = next.filter((r) => r.fields.length > 0);
  return cleaned.length > 0 ? cleaned : [{ id: newRowId(), fields: [] }];
}

export function arrangeRowDroppableId(rowId: string): string {
  return `arrange-row:${rowId}`;
}

export function parseArrangeRowDroppableId(id: string): string | null {
  return id.startsWith("arrange-row:") ? id.slice("arrange-row:".length) : null;
}

export function addEmptyRow(rows: ArrangeRow[]): ArrangeRow[] {
  return [...rows, { id: newRowId(), fields: [] }];
}

function newRowId(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID();
  }
  return `row-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

export function typeGlyph(type: string): string {
  switch (type) {
    case "number":
    case "decimal":
      return "#";
    case "date":
    case "datetime":
      return "D";
    case "relationship":
      return "↔";
    case "automatic_id":
      return "#";
    case "select":
    case "multiselect":
      return "▾";
    case "boolean":
      return "Y/N";
    case "textarea":
      return "¶";
    case "email":
      return "@";
    case "file":
      return "F";
    default:
      return "A";
  }
}

/** CSS grid placement for a 12-column form layout. */
export function dynColumnSpanStyle(span: number): { gridColumn: string } {
  const n = clampSpan(span);
  return { gridColumn: `span ${n} / span ${n}` };
}
