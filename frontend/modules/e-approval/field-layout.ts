import { fieldOptionsToRecord, normalizeFieldOptionsShape } from "@/modules/e-approval/field-options";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import type { EApprovalFieldListEntry } from "@/modules/e-approval/form-field-groups";

export type EApprovalFieldLayoutWidth = "full" | "half" | "third" | "quarter";

export type EApprovalLayoutRowColumns = 2 | 3 | 4;

export type EApprovalFieldLayoutConfig = {
  width: EApprovalFieldLayoutWidth;
  row_id?: string;
  slot?: number;
  /** Vertical order within a column slot (lower = higher on screen). */
  stack_order?: number;
  /** Column count for multi-column rows (stored on fields in the row). */
  row_columns?: EApprovalLayoutRowColumns;
};

export const FIELD_LAYOUT_WIDTH_LABELS: Record<EApprovalFieldLayoutWidth, string> = {
  full: "100% — full width",
  half: "50% — half",
  third: "33% — one third",
  quarter: "25% — quarter",
};

export type EApprovalLayoutRowNode = {
  kind: "row";
  rowId: string;
  entries: EApprovalFieldListEntry[];
};

export type EApprovalLayoutFieldNode = {
  kind: "field";
  entry: EApprovalFieldListEntry;
};

export type EApprovalLayoutClusterNode = EApprovalLayoutRowNode | EApprovalLayoutFieldNode;

function normalizeOptions(field: EApprovalFormFieldInput): Record<string, unknown> {
  return fieldOptionsToRecord(field);
}

export function parseFieldLayout(field: EApprovalFormFieldInput): EApprovalFieldLayoutConfig {
  const opts = normalizeOptions(field);
  const raw = opts.layout;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return { width: "full" };
  }

  const layout = raw as Record<string, unknown>;
  const widthRaw = layout.width;
  const width: EApprovalFieldLayoutWidth =
    widthRaw === "half" || widthRaw === "third" || widthRaw === "quarter" ? widthRaw : "full";

  const row_id = typeof layout.row_id === "string" && layout.row_id.trim() ? layout.row_id.trim() : undefined;
  const slotRaw = layout.slot;
  const slotNum = typeof slotRaw === "number" ? slotRaw : typeof slotRaw === "string" ? Number(slotRaw) : NaN;
  const slot = Number.isFinite(slotNum) ? Math.floor(slotNum) : undefined;
  const stackRaw = layout.stack_order;
  const stackNum =
    typeof stackRaw === "number" ? stackRaw : typeof stackRaw === "string" ? Number(stackRaw) : NaN;
  const stack_order = Number.isFinite(stackNum) ? Math.floor(stackNum) : undefined;
  const rowColumnsRaw = layout.row_columns;
  const row_columns: EApprovalLayoutRowColumns | undefined =
    rowColumnsRaw === 3 || rowColumnsRaw === 4 || rowColumnsRaw === 2 ? rowColumnsRaw : undefined;

  return { width, row_id, slot, stack_order, row_columns };
}

export function patchFieldLayout(
  field: EApprovalFormFieldInput,
  patch: Partial<EApprovalFieldLayoutConfig>,
): Record<string, unknown> {
  const opts = normalizeOptions(field);
  const current = parseFieldLayout(field);
  const next: EApprovalFieldLayoutConfig = { ...current, ...patch };

  if (patch.width === "full" && !next.row_id) {
    next.row_id = undefined;
    next.slot = undefined;
    next.stack_order = undefined;
  }

  if (!next.row_id) {
    delete next.row_id;
    delete next.slot;
    delete next.stack_order;
  }

  const layout: Record<string, unknown> = { width: next.width };
  if (next.row_id) {
    layout.row_id = next.row_id;
  }
  if (next.slot !== undefined) {
    layout.slot = next.slot;
  }
  if (next.stack_order !== undefined) {
    layout.stack_order = next.stack_order;
  }
  if (next.row_columns !== undefined) {
    layout.row_columns = next.row_columns;
  }

  return { ...opts, layout };
}

export function fieldSupportsLayout(type: string): boolean {
  return !["section", "divider", "page_break", "rating", "tags", "checklist_matrix"].includes(type);
}

export function layoutWidthColSpan(width: EApprovalFieldLayoutWidth): number {
  switch (width) {
    case "half":
      return 6;
    case "third":
      return 4;
    case "quarter":
      return 3;
    default:
      return 12;
  }
}

export function layoutWidthTailwindClass(width: EApprovalFieldLayoutWidth): string {
  const span = layoutWidthColSpan(width);
  if (span === 12) {
    return "col-span-12";
  }
  if (span === 6) {
    return "col-span-12 sm:col-span-6";
  }
  if (span === 4) {
    return "col-span-12 sm:col-span-6 lg:col-span-4";
  }

  return "col-span-12 sm:col-span-6 lg:col-span-3";
}

/** Cluster consecutive fields that share a row_id into horizontal rows. */
export function clusterEntriesByLayoutRow(entries: EApprovalFieldListEntry[]): EApprovalLayoutClusterNode[] {
  const rowGroups = new Map<string, EApprovalFieldListEntry[]>();

  for (const entry of entries) {
    const layout = parseFieldLayout(entry.field);
    if (layout.row_id && fieldSupportsLayout(entry.field.type)) {
      const group = rowGroups.get(layout.row_id) ?? [];
      group.push(entry);
      rowGroups.set(layout.row_id, group);
    }
  }

  const emittedRows = new Set<string>();
  const nodes: EApprovalLayoutClusterNode[] = [];

  for (const entry of entries) {
    const layout = parseFieldLayout(entry.field);
    if (layout.row_id && fieldSupportsLayout(entry.field.type)) {
      if (emittedRows.has(layout.row_id)) {
        continue;
      }

      emittedRows.add(layout.row_id);
      const rowEntries = rowGroups.get(layout.row_id) ?? [entry];
      rowEntries.sort((a, b) => {
        const slotA = parseFieldLayout(a.field).slot ?? a.index;
        const slotB = parseFieldLayout(b.field).slot ?? b.index;
        return slotA - slotB || a.index - b.index;
      });
      nodes.push({ kind: "row", rowId: layout.row_id, entries: rowEntries });
      continue;
    }

    nodes.push({ kind: "field", entry });
  }

  return nodes;
}

export function collectLayoutRowIds(fields: EApprovalFormFieldInput[]): string[] {
  const ids = new Set<string>();
  for (const field of fields) {
    const rowId = parseFieldLayout(field).row_id;
    if (rowId) {
      ids.add(rowId);
    }
  }

  return [...ids].sort();
}

export function createLayoutRowId(): string {
  return `row_${Date.now().toString(36)}`;
}

export function layoutRowSlotDroppableId(rowId: string, slot: number): string {
  return `layout-slot:${rowId}:${slot}`;
}

export function isLayoutRowSlotDroppableId(id: string): boolean {
  return id.startsWith("layout-slot:");
}

export function parseLayoutRowSlotDroppableId(id: string): { rowId: string; slot: number } | null {
  if (!isLayoutRowSlotDroppableId(id)) {
    return null;
  }

  const slotPart = id.lastIndexOf(":");
  if (slotPart <= "layout-slot:".length) {
    return null;
  }

  const rowId = id.slice("layout-slot:".length, slotPart);
  const slot = Number(id.slice(slotPart + 1));
  if (!rowId || !Number.isInteger(slot) || slot < 0) {
    return null;
  }

  return { rowId, slot };
}

export function clampLayoutRowSlot(slot: number, columns: EApprovalLayoutRowColumns): number {
  return Math.min(Math.max(0, slot), columns - 1);
}

/** Remove row layout from any field occupying this slot (optional index to keep). */
export function releaseLayoutRowSlot(
  fields: EApprovalFormFieldInput[],
  rowId: string,
  slot: number,
  exceptFieldIndex?: number,
): EApprovalFormFieldInput[] {
  return fields.map((field, index) => {
    if (exceptFieldIndex !== undefined && index === exceptFieldIndex) {
      return field;
    }
    const layout = parseFieldLayout(field);
    if (layout.row_id === rowId && layout.slot === slot) {
      return detachFieldFromRowLayout(field);
    }

    return field;
  });
}

/** Remove a field from a multi-column row so it renders as a standalone canvas field. */
export function detachFieldFromRowLayout(field: EApprovalFormFieldInput): EApprovalFormFieldInput {
  return {
    ...field,
    options: patchFieldLayout(field, {
      row_id: undefined,
      slot: undefined,
      stack_order: undefined,
      width: "full",
    }),
  };
}

export const E_APPROVAL_CANVAS_DROP_END_ID = "canvas-drop:end";

/** Droppable covering a section body so catalog drops land inside that section (not the previous one). */
export const SECTION_DROP_PREFIX = "section-drop:";

export function sectionDroppableId(groupIndex: number): string {
  return `${SECTION_DROP_PREFIX}${groupIndex}`;
}

export function parseSectionDroppableId(id: string): number | null {
  if (!id.startsWith(SECTION_DROP_PREFIX)) {
    return null;
  }
  const groupIndex = Number(id.slice(SECTION_DROP_PREFIX.length));
  if (!Number.isInteger(groupIndex) || groupIndex < 0) {
    return null;
  }
  return groupIndex;
}

export function isSectionDroppableId(id: string): boolean {
  return parseSectionDroppableId(id) !== null;
}

export const LAYOUT_ROW_BLOCK_DRAG_PREFIX = "row-block:";

export function layoutRowBlockDragId(rowId: string): string {
  return `${LAYOUT_ROW_BLOCK_DRAG_PREFIX}${rowId}`;
}

export function parseLayoutRowBlockDragId(id: string): string | null {
  if (!id.startsWith(LAYOUT_ROW_BLOCK_DRAG_PREFIX)) {
    return null;
  }
  const rowId = id.slice(LAYOUT_ROW_BLOCK_DRAG_PREFIX.length);
  return rowId.length > 0 ? rowId : null;
}

export function isLayoutRowBlockDragId(id: string): boolean {
  return parseLayoutRowBlockDragId(id) !== null;
}

/** Indices of all fields that belong to a layout row group. */
export function collectRowBlockIndices(fields: EApprovalFormFieldInput[], rowId: string): number[] {
  return fields
    .map((field, index) => ({ field, index }))
    .filter(({ field }) => parseFieldLayout(field).row_id === rowId)
    .map(({ index }) => index);
}

/** Move a contiguous block of fields to a new index in the flat field list. */
export function moveFieldsBlock(
  fields: EApprovalFormFieldInput[],
  blockIndices: number[],
  targetInsertIndex: number,
): EApprovalFormFieldInput[] {
  const sorted = [...blockIndices].sort((a, b) => a - b);
  if (sorted.length === 0) {
    return fields;
  }

  const block = sorted.map((index) => fields[index]!);
  const remaining = fields.filter((_, index) => !sorted.includes(index));

  let insertAt = targetInsertIndex;
  for (const index of sorted) {
    if (index < targetInsertIndex) {
      insertAt -= 1;
    }
  }
  insertAt = Math.max(0, Math.min(insertAt, remaining.length));

  return [...remaining.slice(0, insertAt), ...block, ...remaining.slice(insertAt)];
}

export const E_APPROVAL_CATALOG_DRAG_PREFIX = "catalog-field:";

export const E_APPROVAL_CATALOG_MASTER_DRAG_ID = "catalog-field:master-data";

export function catalogFieldDragId(type: string): string {
  return `${E_APPROVAL_CATALOG_DRAG_PREFIX}${type}`;
}

export function parseCatalogFieldDragId(id: string): string | null {
  if (!id.startsWith(E_APPROVAL_CATALOG_DRAG_PREFIX)) {
    return null;
  }
  const type = id.slice(E_APPROVAL_CATALOG_DRAG_PREFIX.length);
  return type.length > 0 ? type : null;
}

export const E_APPROVAL_CATALOG_ROW_DRAG_PREFIX = "catalog-row:";

export function catalogRowDragId(columns: EApprovalLayoutRowColumns): string {
  return `${E_APPROVAL_CATALOG_ROW_DRAG_PREFIX}${columns}`;
}

export function parseCatalogRowDragId(id: string): EApprovalLayoutRowColumns | null {
  const match = new RegExp(`^${E_APPROVAL_CATALOG_ROW_DRAG_PREFIX}(2|3|4)$`).exec(id);
  if (!match) {
    return null;
  }
  return Number(match[1]) as EApprovalLayoutRowColumns;
}

export function isCatalogDragId(id: string): boolean {
  return parseCatalogFieldDragId(id) !== null || parseCatalogRowDragId(id) !== null;
}

export function layoutWidthForRowColumns(columns: EApprovalLayoutRowColumns): EApprovalFieldLayoutWidth {
  if (columns === 2) {
    return "half";
  }
  if (columns === 3) {
    return "third";
  }

  return "quarter";
}

export function inferLayoutRowColumnCount(entries: EApprovalFieldListEntry[]): EApprovalLayoutRowColumns {
  let maxSlot = 0;
  let columns: EApprovalLayoutRowColumns = 2;

  for (const entry of entries) {
    const layout = parseFieldLayout(entry.field);
    if (layout.row_columns) {
      columns = layout.row_columns;
    }
    maxSlot = Math.max(maxSlot, layout.slot ?? 0);
  }

  if (columns === 4 || maxSlot >= 3) {
    return 4;
  }
  if (columns === 3 || maxSlot >= 2) {
    return 3;
  }

  return 2;
}

/** Builder canvas grid — keep column count stable so slot 4 stays droppable below the `lg` breakpoint. */
export function layoutRowBuilderGridClass(columns: EApprovalLayoutRowColumns): string {
  switch (columns) {
    case 3:
      return "grid grid-cols-3 gap-2 max-[480px]:grid-cols-1";
    case 4:
      return "grid grid-cols-4 gap-2 max-[560px]:grid-cols-2";
    default:
      return "grid grid-cols-2 gap-2 max-[480px]:grid-cols-1";
  }
}

/** Requestor / preview compose grid — same column stacking as the builder. */
export function layoutRowComposeGridClass(columns: EApprovalLayoutRowColumns): string {
  switch (columns) {
    case 3:
      return "grid grid-cols-1 gap-5 md:grid-cols-3 md:gap-4";
    case 4:
      return "grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 lg:gap-4";
    default:
      return "grid grid-cols-1 gap-5 md:grid-cols-2 md:gap-4";
  }
}

function readLegacyLayoutSpan(field: EApprovalFormFieldInput): number | null {
  const raw = field.validation;
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return null;
  }
  const layout = (raw as Record<string, unknown>)._layout;
  if (!layout || typeof layout !== "object" || Array.isArray(layout)) {
    return null;
  }
  const span = (layout as Record<string, unknown>).layoutSpan;
  return typeof span === "number" && Number.isFinite(span) ? span : null;
}

export function layoutSpanToWidth(span: number): EApprovalFieldLayoutWidth {
  if (span <= 3) {
    return "quarter";
  }
  if (span <= 4) {
    return "third";
  }
  if (span <= 6) {
    return "half";
  }

  return "full";
}

function applyLegacyLayoutWidth(field: EApprovalFormFieldInput): EApprovalFormFieldInput {
  const migrated = normalizeFieldOptionsShape(field);
  const opts = normalizeOptions(migrated);
  const rawLayout = opts.layout;
  if (rawLayout && typeof rawLayout === "object" && !Array.isArray(rawLayout)) {
    return field;
  }

  const span = readLegacyLayoutSpan(field);
  if (span === null) {
    return field;
  }

  const width = layoutSpanToWidth(span);

  return { ...migrated, options: patchFieldLayout(migrated, { width }) };
}

export function findNextAvailableRowSlot(
  rowId: string,
  fields: EApprovalFormFieldInput[],
  columnCount: number,
  exceptFieldIndex?: number,
): number {
  const used = new Set<number>();
  fields.forEach((field, index) => {
    if (index === exceptFieldIndex) {
      return;
    }
    const layout = parseFieldLayout(field);
    if (layout.row_id === rowId && layout.slot !== undefined) {
      used.add(layout.slot);
    }
  });

  for (let slot = 0; slot < columnCount; slot++) {
    if (!used.has(slot)) {
      return slot;
    }
  }

  return Math.max(0, columnCount - 1);
}

export type LayoutRowScaffold = {
  id: string;
  columns: EApprovalLayoutRowColumns;
  insert_index?: number;
};

/**
 * Ensure each field in a row group has a valid column slot and matching row_columns.
 * Multiple fields may share the same slot (stacked vertically in that column).
 */
export function normalizeFormFieldLayouts(
  fields: EApprovalFormFieldInput[],
  layoutRows: LayoutRowScaffold[] = [],
): EApprovalFormFieldInput[] {
  let next = fields.map((field) => applyLegacyLayoutWidth(normalizeFieldOptionsShape(field)));
  const rowIds = collectLayoutRowIds(next);

  for (const rowId of rowIds) {
    const members = next
      .map((field, index) => ({ field, index }))
      .filter(({ field }) => parseFieldLayout(field).row_id === rowId)
      .sort((a, b) => a.index - b.index);

    if (members.length === 0) {
      continue;
    }

    const scaffold = layoutRows.find((row) => row.id === rowId);
    const columnCount =
      scaffold?.columns ??
      inferLayoutRowColumnCount(members.map(({ field, index }) => ({ field, index })));

    for (const { field, index } of members) {
      const layout = parseFieldLayout(field);
      const slot =
        layout.slot === undefined || layout.slot < 0 || layout.slot >= columnCount
          ? clampLayoutRowSlot(layout.slot ?? 0, columnCount)
          : layout.slot;

      const needsSlotFix =
        layout.slot !== slot || layout.row_columns !== columnCount || layout.row_id !== rowId;
      if (!needsSlotFix) {
        continue;
      }

      const patchedOptions = patchFieldLayout(field, {
        row_id: rowId,
        slot,
        row_columns: columnCount,
        width: layoutWidthForRowColumns(columnCount),
        ...(layout.stack_order !== undefined ? { stack_order: layout.stack_order } : {}),
      });

      if (JSON.stringify(field.options) !== JSON.stringify(patchedOptions)) {
        next = next.map((f, i) => (i === index ? { ...f, options: patchedOptions } : f));
      }
    }

    next = renumberRowSlotStackOrders(next, rowId, columnCount);
  }

  return next;
}

/** Assign contiguous stack_order values (0..n-1) per column, preserving current visual order. */
export function renumberRowSlotStackOrders(
  fields: EApprovalFormFieldInput[],
  rowId: string,
  columnCount: number,
): EApprovalFormFieldInput[] {
  let next = fields;

  for (let slot = 0; slot < columnCount; slot++) {
    const members = next
      .map((field, index) => ({ field, index }))
      .filter(({ field }) => {
        const layout = parseFieldLayout(field);
        return layout.row_id === rowId && (layout.slot ?? 0) === slot;
      })
      .sort((a, b) => {
        const orderA = parseFieldLayout(a.field).stack_order;
        const orderB = parseFieldLayout(b.field).stack_order;
        if (orderA !== undefined && orderB !== undefined && orderA !== orderB) {
          return orderA - orderB;
        }
        if (orderA !== undefined && orderB === undefined) {
          return -1;
        }
        if (orderA === undefined && orderB !== undefined) {
          return 1;
        }
        return a.index - b.index;
      });

    members.forEach((member, stackOrder) => {
      const layout = parseFieldLayout(member.field);
      if (layout.stack_order === stackOrder) {
        return;
      }
      const patchedOptions = patchFieldLayout(member.field, { stack_order: stackOrder });
      next = next.map((f, i) => (i === member.index ? { ...f, options: patchedOptions } : f));
    });
  }

  return next;
}

export function nextStackOrderForRowSlot(
  fields: EApprovalFormFieldInput[],
  rowId: string,
  slot: number,
): number {
  let max = -1;
  for (const field of fields) {
    const layout = parseFieldLayout(field);
    if (layout.row_id === rowId && (layout.slot ?? 0) === slot) {
      const order = layout.stack_order ?? max + 1;
      max = Math.max(max, order);
    }
  }

  return max + 1;
}

export function fieldLayoutsChanged(
  before: EApprovalFormFieldInput[],
  after: EApprovalFormFieldInput[],
): boolean {
  if (before.length !== after.length) {
    return true;
  }

  return before.some((field, index) => JSON.stringify(field.options) !== JSON.stringify(after[index]?.options));
}

/** Place row fields into column slots for builder rendering (stacks multiple fields per column). */
export function assignEntriesToRowSlots(
  entries: EApprovalFieldListEntry[],
  columnCount: number,
): EApprovalFieldListEntry[][] {
  const columns = Math.max(1, Math.floor(columnCount));
  const slots: EApprovalFieldListEntry[][] = Array.from({ length: columns }, () => []);
  const sorted = [...entries].sort((a, b) => {
    const orderA = parseFieldLayout(a.field).stack_order;
    const orderB = parseFieldLayout(b.field).stack_order;
    if (orderA !== undefined && orderB !== undefined && orderA !== orderB) {
      return orderA - orderB;
    }
    if (orderA !== undefined && orderB === undefined) {
      return -1;
    }
    if (orderA === undefined && orderB !== undefined) {
      return 1;
    }
    return a.index - b.index;
  });

  for (const entry of sorted) {
    const layout = parseFieldLayout(entry.field);
    const rawSlot = layout.slot ?? 0;
    const target = Math.min(Math.max(0, rawSlot), columns - 1);
    slots[target]!.push(entry);
  }

  return slots;
}

function compareRowSlotStack(
  a: { field: EApprovalFormFieldInput; fieldIndex: number },
  b: { field: EApprovalFormFieldInput; fieldIndex: number },
): number {
  const orderA = parseFieldLayout(a.field).stack_order;
  const orderB = parseFieldLayout(b.field).stack_order;
  if (orderA !== undefined && orderB !== undefined && orderA !== orderB) {
    return orderA - orderB;
  }
  if (orderA !== undefined && orderB === undefined) {
    return -1;
  }
  if (orderA === undefined && orderB !== undefined) {
    return 1;
  }
  return a.fieldIndex - b.fieldIndex;
}

/** Previous/next field in the same layout row column (visual stack order). */
export function findAdjacentFieldIndexInRowSlot(
  fields: EApprovalFormFieldInput[],
  index: number,
  direction: -1 | 1,
): number | null {
  const current = fields[index];
  if (!current) {
    return null;
  }
  const layout = parseFieldLayout(current);
  if (!layout.row_id) {
    return null;
  }
  const slot = layout.slot ?? 0;
  const sameColumn = fields
    .map((field, fieldIndex) => ({ field, fieldIndex }))
    .filter(({ field }) => {
      const other = parseFieldLayout(field);
      return other.row_id === layout.row_id && (other.slot ?? 0) === slot;
    })
    .sort(compareRowSlotStack);

  const position = sameColumn.findIndex((entry) => entry.fieldIndex === index);
  if (position < 0) {
    return null;
  }
  const target = sameColumn[position + direction];
  return target ? target.fieldIndex : null;
}

/** Swap vertical order of a field with its neighbor in the same column. */
export function moveFieldInRowSlot(
  fields: EApprovalFormFieldInput[],
  index: number,
  direction: -1 | 1,
): { fields: EApprovalFormFieldInput[]; selectedIndex: number } | null {
  const current = fields[index];
  if (!current) {
    return null;
  }
  const layout = parseFieldLayout(current);
  if (!layout.row_id) {
    return null;
  }

  const columnCount = layout.row_columns ?? 2;
  const prepared = renumberRowSlotStackOrders(fields, layout.row_id, columnCount);
  const targetIndex = findAdjacentFieldIndexInRowSlot(prepared, index, direction);
  if (targetIndex === null) {
    return null;
  }

  const orderA = parseFieldLayout(prepared[index]!).stack_order ?? 0;
  const orderB = parseFieldLayout(prepared[targetIndex]!).stack_order ?? 0;

  const swapped = prepared.map((field, fieldIndex) => {
    if (fieldIndex === index) {
      return { ...field, options: patchFieldLayout(field, { stack_order: orderB }) };
    }
    if (fieldIndex === targetIndex) {
      return { ...field, options: patchFieldLayout(field, { stack_order: orderA }) };
    }
    return field;
  });

  return {
    fields: renumberRowSlotStackOrders(swapped, layout.row_id, columnCount),
    selectedIndex: index,
  };
}

/** Index to insert a field so it stays adjacent to other fields in the same layout row/slot. */
export function findLayoutRowInsertIndex(
  fields: EApprovalFormFieldInput[],
  rowId: string,
  slot: number,
  layoutRows: LayoutRowScaffold[] = [],
): number {
  const rowIndexes: { index: number; slot: number }[] = [];

  fields.forEach((field, index) => {
    const layout = parseFieldLayout(field);
    if (layout.row_id === rowId) {
      rowIndexes.push({ index, slot: layout.slot ?? 0 });
    }
  });

  if (rowIndexes.length === 0) {
    const scaffold = layoutRows.find((row) => row.id === rowId);
    if (scaffold?.insert_index !== undefined) {
      return Math.min(scaffold.insert_index, fields.length);
    }
    return fields.length;
  }

  const sameSlot = rowIndexes
    .filter((entry) => entry.slot === slot)
    .sort((a, b) => a.index - b.index);
  if (sameSlot.length > 0) {
    return sameSlot[sameSlot.length - 1]!.index + 1;
  }

  const higherSlot = rowIndexes
    .filter((entry) => entry.slot > slot)
    .sort((a, b) => a.index - b.index);
  if (higherSlot.length > 0) {
    return higherSlot[0]!.index;
  }

  rowIndexes.sort((a, b) => a.index - b.index);
  return rowIndexes[rowIndexes.length - 1]!.index + 1;
}

export function layoutRowBuilderSlotClass(_columns: EApprovalLayoutRowColumns): string {
  return "min-w-0";
}
