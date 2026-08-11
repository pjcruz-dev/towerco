import type { EApprovalFieldListEntry } from "@/modules/e-approval/form-field-groups";
import type { EApprovalFieldDisplayGroup } from "@/modules/e-approval/form-field-groups";
import {
  clusterEntriesByLayoutRow,
  collectRowBlockIndices,
  E_APPROVAL_CANVAS_DROP_END_ID,
  layoutRowBlockDragId,
  parseFieldLayout,
  parseLayoutRowBlockDragId,
  type EApprovalLayoutClusterNode,
  type EApprovalLayoutRowColumns,
} from "@/modules/e-approval/field-layout";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

export function canvasFieldSortableId(field: EApprovalFormFieldInput, index: number): string {
  return field.id ?? field.name ?? `idx-${index}`;
}

export const BUILDER_LAYOUT_ROWS_META_KEY = "builder_layout_rows";

export function parseFormMetadataJson(json: string): Record<string, unknown> {
  const trimmed = json.trim();
  if (!trimmed || trimmed === "{}") {
    return {};
  }
  try {
    const parsed = JSON.parse(trimmed) as unknown;
    return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? (parsed as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

export type EApprovalBuilderLayoutRow = {
  id: string;
  columns: EApprovalLayoutRowColumns;
  /** Position in the flat `fields` array where this row is rendered (before that index). */
  insert_index: number;
};

export type EApprovalBuilderCanvasSegment =
  | { kind: "cluster"; node: EApprovalLayoutClusterNode }
  | { kind: "empty-row"; row: EApprovalBuilderLayoutRow };

export function parseBuilderLayoutRows(metadata: Record<string, unknown> | null | undefined): EApprovalBuilderLayoutRow[] {
  const raw = metadata?.[BUILDER_LAYOUT_ROWS_META_KEY];
  if (!Array.isArray(raw)) {
    return [];
  }

  const rows: EApprovalBuilderLayoutRow[] = [];
  for (const item of raw) {
    if (!item || typeof item !== "object" || Array.isArray(item)) {
      continue;
    }
    const row = item as Record<string, unknown>;
    const id = typeof row.id === "string" && row.id.trim() ? row.id.trim() : "";
    const columns = row.columns;
    const insert_index = row.insert_index;
    if (!id) {
      continue;
    }
    if (columns !== 2 && columns !== 3 && columns !== 4) {
      continue;
    }
    if (typeof insert_index !== "number" || !Number.isFinite(insert_index) || insert_index < 0) {
      continue;
    }
    rows.push({ id, columns, insert_index: Math.floor(insert_index) });
  }

  return rows.sort((a, b) => a.insert_index - b.insert_index || a.id.localeCompare(b.id));
}

export function patchBuilderLayoutRows(
  metadata: Record<string, unknown>,
  rows: EApprovalBuilderLayoutRow[],
): Record<string, unknown> {
  const next = { ...metadata };
  if (rows.length === 0) {
    delete next[BUILDER_LAYOUT_ROWS_META_KEY];
    return next;
  }
  next[BUILDER_LAYOUT_ROWS_META_KEY] = rows.map((r) => ({
    id: r.id,
    columns: r.columns,
    insert_index: r.insert_index,
  }));
  return next;
}

export function bumpBuilderLayoutRowInsertIndices(
  rows: EApprovalBuilderLayoutRow[],
  fromIndex: number,
  delta: number,
): EApprovalBuilderLayoutRow[] {
  if (delta === 0) {
    return rows;
  }
  return rows.map((row) =>
    row.insert_index >= fromIndex ? { ...row, insert_index: Math.max(0, row.insert_index + delta) } : row,
  );
}

export function collectActiveLayoutRowIds(entries: EApprovalFieldListEntry[]): Set<string> {
  const ids = new Set<string>();
  for (const entry of entries) {
    const rowId = parseFieldLayout(entry.field).row_id;
    if (rowId) {
      ids.add(rowId);
    }
  }
  return ids;
}

export function buildBuilderGroupSegments(
  entries: EApprovalFieldListEntry[],
  layoutRows: EApprovalBuilderLayoutRow[],
  sectionHeaderIndex: number | null = null,
  nextSectionHeaderIndex: number | null = null,
): EApprovalBuilderCanvasSegment[] {
  if (entries.length === 0 && layoutRows.length === 0) {
    return [];
  }

  const clusters = clusterEntriesByLayoutRow(entries);
  const activeRowIds = collectActiveLayoutRowIds(entries);
  const sectionStart = sectionHeaderIndex !== null ? sectionHeaderIndex + 1 : 0;
  const sectionEndExclusive =
    nextSectionHeaderIndex !== null ? nextSectionHeaderIndex + 1 : Number.POSITIVE_INFINITY;

  const emptyRowInSection = (row: EApprovalBuilderLayoutRow): boolean =>
    !activeRowIds.has(row.id) &&
    row.insert_index >= sectionStart &&
    row.insert_index < sectionEndExclusive;

  if (entries.length === 0) {
    return layoutRows.filter(emptyRowInSection).map((row) => ({ kind: "empty-row" as const, row }));
  }

  const minIdx = Math.min(...entries.map((e) => e.index));
  const maxIdx = Math.max(...entries.map((e) => e.index));
  const groupLower = sectionHeaderIndex !== null ? Math.min(sectionStart, minIdx) : minIdx;
  const groupUpper = Math.min(maxIdx + 1, sectionEndExclusive - 1);

  const emptyRows = layoutRows.filter(
    (row) =>
      !activeRowIds.has(row.id) &&
      row.insert_index >= groupLower &&
      row.insert_index <= groupUpper &&
      row.insert_index < sectionEndExclusive,
  );

  const positioned: { position: number; segment: EApprovalBuilderCanvasSegment }[] = [];

  for (const node of clusters) {
    const position =
      node.kind === "field" ? node.entry.index : Math.min(...node.entries.map((e) => e.index));
    positioned.push({ position, segment: { kind: "cluster", node } });
  }

  for (const row of emptyRows) {
    positioned.push({ position: row.insert_index, segment: { kind: "empty-row", row } });
  }

  positioned.sort((a, b) => {
    if (a.position !== b.position) {
      return a.position - b.position;
    }
    if (a.segment.kind === "empty-row" && b.segment.kind === "empty-row") {
      const rowA = a.segment.row.id;
      const rowB = b.segment.row.id;
      const ai = layoutRows.findIndex((row) => row.id === rowA);
      const bi = layoutRows.findIndex((row) => row.id === rowB);
      return ai - bi;
    }
    return a.segment.kind === "empty-row" ? -1 : 1;
  });

  return positioned.map((p) => p.segment);
}

export function pruneOrphanedBuilderLayoutRows(
  rows: EApprovalBuilderLayoutRow[],
  fields: EApprovalFormFieldInput[],
): EApprovalBuilderLayoutRow[] {
  const activeRowIds = new Set<string>();
  for (const field of fields) {
    const rowId = parseFieldLayout(field).row_id;
    if (rowId) {
      activeRowIds.add(rowId);
    }
  }
  return rows.filter((row) => !activeRowIds.has(row.id));
}

/** Sortable ids in canvas visual order (row blocks + standalone fields). */
export function buildCanvasSortableIds(
  fields: EApprovalFormFieldInput[],
  layoutRows: EApprovalBuilderLayoutRow[],
  displayGroups: EApprovalFieldDisplayGroup[],
): string[] {
  const ids: string[] = [];
  const emittedRows = new Set<string>();

  const pushSegment = (segment: EApprovalBuilderCanvasSegment): void => {
    if (segment.kind === "empty-row") {
      if (!emittedRows.has(segment.row.id)) {
        emittedRows.add(segment.row.id);
        ids.push(layoutRowBlockDragId(segment.row.id));
      }
      return;
    }

    const node = segment.node;
    if (node.kind === "row") {
      if (!emittedRows.has(node.rowId)) {
        emittedRows.add(node.rowId);
        ids.push(layoutRowBlockDragId(node.rowId));
        const rowEntries = [...node.entries].sort((a, b) => {
          const slotA = parseFieldLayout(a.field).slot ?? a.index;
          const slotB = parseFieldLayout(b.field).slot ?? b.index;
          return slotA - slotB || a.index - b.index;
        });
        for (const entry of rowEntries) {
          ids.push(canvasFieldSortableId(entry.field, entry.index));
        }
      }
      return;
    }

    ids.push(canvasFieldSortableId(node.entry.field, node.entry.index));
  };

  if (fields.length === 0) {
    return layoutRows.map((row) => layoutRowBlockDragId(row.id));
  }

  for (let groupIndex = 0; groupIndex < displayGroups.length; groupIndex++) {
    const group = displayGroups[groupIndex]!;
    const nextHeaderIndex = displayGroups[groupIndex + 1]?.header?.index ?? null;
    if (group.header) {
      ids.push(canvasFieldSortableId(group.header.field, group.header.index));
    }
    for (const segment of buildBuilderGroupSegments(
      group.items,
      layoutRows,
      group.header?.index ?? null,
      nextHeaderIndex,
    )) {
      pushSegment(segment);
    }
  }

  return ids;
}

/** Apply canvas sortable order back onto the flat fields array. */
export function flattenSortableOrderToFields(
  orderedIds: string[],
  fields: EApprovalFormFieldInput[],
): EApprovalFormFieldInput[] {
  const used = new Set<number>();
  const result: EApprovalFormFieldInput[] = [];

  const indexOfId = (id: string): number =>
    fields.findIndex((field, i) => canvasFieldSortableId(field, i) === id);

  for (let i = 0; i < orderedIds.length; i++) {
    const id = orderedIds[i]!;
    const rowId = parseLayoutRowBlockDragId(id);
    if (rowId) {
      const memberIndexes = new Set(collectRowBlockIndices(fields, rowId));
      const orderedFromIds: number[] = [];

      // Prefer the explicit field-id sequence after the row handle (supports in-row reorder).
      for (let j = i + 1; j < orderedIds.length; j++) {
        const nextId = orderedIds[j]!;
        if (parseLayoutRowBlockDragId(nextId)) {
          break;
        }
        const fieldIndex = indexOfId(nextId);
        if (fieldIndex < 0) {
          continue;
        }
        if (!memberIndexes.has(fieldIndex)) {
          break;
        }
        if (used.has(fieldIndex)) {
          continue;
        }
        orderedFromIds.push(fieldIndex);
      }

      const emitOrder =
        orderedFromIds.length > 0
          ? orderedFromIds
          : [...memberIndexes].sort((a, b) => a - b);

      for (const index of emitOrder) {
        if (!used.has(index)) {
          used.add(index);
          result.push(fields[index]!);
        }
      }

      for (const index of [...memberIndexes].sort((a, b) => a - b)) {
        if (!used.has(index)) {
          used.add(index);
          result.push(fields[index]!);
        }
      }
      continue;
    }

    const index = indexOfId(id);
    if (index >= 0 && !used.has(index)) {
      used.add(index);
      result.push(fields[index]!);
    }
  }

  for (let index = 0; index < fields.length; index++) {
    if (!used.has(index)) {
      result.push(fields[index]!);
    }
  }

  return result;
}

/** Map a canvas sortable id to the flat field index inserted before that segment. */
export function fieldInsertIndexBeforeSortableId(
  sortableId: string,
  fields: EApprovalFormFieldInput[],
  layoutRows: EApprovalBuilderLayoutRow[],
): number {
  const rowId = parseLayoutRowBlockDragId(sortableId);
  if (rowId) {
    const memberIndices = collectRowBlockIndices(fields, rowId);
    if (memberIndices.length > 0) {
      return Math.min(...memberIndices);
    }

    const scaffold = layoutRows.find((row) => row.id === rowId);
    return scaffold ? Math.min(scaffold.insert_index, fields.length) : fields.length;
  }

  const fieldIndex = fields.findIndex((field, index) => canvasFieldSortableId(field, index) === sortableId);
  return fieldIndex >= 0 ? fieldIndex : fields.length;
}

/** Insert index in the flat fields array for a drop before the sortable segment at `position`. */
export function fieldInsertIndexForSortablePosition(
  position: number,
  fields: EApprovalFormFieldInput[],
  layoutRows: EApprovalBuilderLayoutRow[],
  displayGroups: EApprovalFieldDisplayGroup[],
): number {
  const ids = buildCanvasSortableIds(fields, layoutRows, displayGroups);
  if (position <= 0) {
    return 0;
  }
  if (position >= ids.length) {
    return fields.length;
  }

  return fieldInsertIndexBeforeSortableId(ids[position]!, fields, layoutRows);
}

export function resolveFieldInsertIndexFromCanvasTarget(
  overId: string | number,
  fields: EApprovalFormFieldInput[],
  layoutRows: EApprovalBuilderLayoutRow[],
  displayGroups: EApprovalFieldDisplayGroup[],
  sortableIds: string[],
): number {
  if (overId === E_APPROVAL_CANVAS_DROP_END_ID) {
    return fields.length;
  }

  const over = String(overId);

  // Dropping on a section / page-break header should land *inside* that section
  // (after the header). Insert-before would put the field in the previous section.
  const headerIndex = fields.findIndex((field, index) => canvasFieldSortableId(field, index) === over);
  if (headerIndex >= 0) {
    const header = fields[headerIndex]!;
    if (header.type === "section" || header.type === "page_break") {
      return headerIndex + 1;
    }
  }

  const sortablePosition = sortableIds.indexOf(over);
  if (sortablePosition >= 0) {
    return fieldInsertIndexForSortablePosition(sortablePosition, fields, layoutRows, displayGroups);
  }

  return fieldInsertIndexBeforeSortableId(over, fields, layoutRows);
}

/** Flat insert index at the end of a display group (after its last field / after header). */
export function sectionGroupInsertIndex(
  group: EApprovalFieldDisplayGroup,
  fieldsLength: number,
): number {
  if (group.items.length > 0) {
    return Math.max(...group.items.map((item) => item.index)) + 1;
  }
  if (group.header) {
    return group.header.index + 1;
  }
  return fieldsLength;
}

/** Keep empty layout row scaffolds aligned with canvas drag order. */
export function applySortableOrderToLayoutRows(
  orderedIds: string[],
  fields: EApprovalFormFieldInput[],
  layoutRows: EApprovalBuilderLayoutRow[],
): EApprovalBuilderLayoutRow[] {
  const activeRowIds = collectActiveLayoutRowIds(
    fields.map((field, index) => ({ field, index })),
  );
  const emptyRowIds = new Set(layoutRows.filter((row) => !activeRowIds.has(row.id)).map((row) => row.id));
  if (emptyRowIds.size === 0) {
    return layoutRows;
  }

  const insertByRowId = new Map<string, number>();
  let cursor = 0;

  for (const id of orderedIds) {
    const rowId = parseLayoutRowBlockDragId(id);
    if (rowId && emptyRowIds.has(rowId)) {
      insertByRowId.set(rowId, cursor);
      continue;
    }

    if (rowId) {
      const indices = collectRowBlockIndices(fields, rowId);
      if (indices.length > 0) {
        cursor = Math.max(...indices) + 1;
      }
      continue;
    }

    const fieldIndex = fields.findIndex((field, index) => canvasFieldSortableId(field, index) === id);
    if (fieldIndex >= 0) {
      cursor = fieldIndex + 1;
    }
  }

  const emptyRowOrder = orderedIds
    .map((id) => parseLayoutRowBlockDragId(id))
    .filter((id): id is string => id !== null && emptyRowIds.has(id));

  return layoutRows
    .map((row) => {
      const nextIndex = insertByRowId.get(row.id);
      return nextIndex === undefined ? row : { ...row, insert_index: nextIndex };
    })
    .sort((a, b) => {
      if (a.insert_index !== b.insert_index) {
        return a.insert_index - b.insert_index;
      }
      const ai = emptyRowOrder.indexOf(a.id);
      const bi = emptyRowOrder.indexOf(b.id);
      if (ai >= 0 && bi >= 0) {
        return ai - bi;
      }
      return a.id.localeCompare(b.id);
    });
}
