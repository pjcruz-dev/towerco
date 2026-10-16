"use client";

import {
  forwardRef,
  useEffect,
  useImperativeHandle,
  useLayoutEffect,
  useRef,
  useState,
} from "react";
import {
  AlignCenter,
  AlignLeft,
  AlignRight,
  BetweenHorizontalStart,
  BetweenVerticalStart,
  Bold,
  ChevronDown,
  ChevronRight,
  CodeXml,
  Columns2,
  Grid2x2,
  Italic,
  Link2,
  List,
  ListOrdered,
  Redo2,
  Rows2,
  Table2,
  Trash2,
  Underline,
  Undo2,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { expandPrintFieldChips } from "@/lib/dynamic-entities/dyn-print-template";
import {
  Dialog,
  DialogContent,
  DialogBody,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { cn } from "@/lib/utils";

type Mode = "design" | "styles" | "source";

export type PrintTokenOption = { token: string; label: string };

export type DynPrintableWysiwygHandle = {
  insertToken: (token: string, label?: string) => void;
};

type Props = {
  html: string;
  css: string;
  onHtmlChange: (html: string) => void;
  onCssChange: (css: string) => void;
  orientation?: "portrait" | "landscape" | null;
  /** Show value inserts a labeled chip. Manual input inserts the raw {{token}}. */
  insertAsValue?: boolean;
  tokens?: PrintTokenOption[];
  className?: string;
};

type TableToolbarState = {
  table: HTMLTableElement;
  top: number;
  left: number;
};

/** Keep @page / html / body rules from blowing out the app chrome in Design. */
function cssForDesignPreview(css: string): string {
  const trimmed = css.trim();
  if (!trimmed) return "";
  return trimmed
    .replace(/@page\s*\{[\s\S]*?\}/gi, "/* @page omitted in Design preview */")
    .replace(/\{\{\s*theme\.accent\s*\}\}/gi, "#2563EB")
    .replace(/\{\{\s*[a-zA-Z0-9_.]+\s*\}\}/g, "")
    .replace(/\bhtml\s*\{/gi, ".dyn-print-template {")
    .replace(/\bbody\s*\{/gi, ".dyn-print-template {");
}

/** Metacoresoft-style dashed structure guides — Design only; outline so Styles borders stay. */
const DESIGN_STRUCTURE_GUIDES_CSS = `
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template table:not(.dyn-print-table-selected),
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template td,
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template th {
  outline: 1px dashed #b0b0b0;
  outline-offset: -1px;
}
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template td,
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template th {
  min-width: 1.25em;
  min-height: 1.15em;
}
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template div,
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template section,
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template article,
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template aside,
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template header,
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template footer {
  outline: 1px dashed #c8c8c8;
  outline-offset: -1px;
}
.dyn-print-design-canvas[data-guides="1"] .dyn-print-template {
  outline: none !important;
}
.dyn-print-design-canvas .dyn-print-template table.dyn-print-table-selected {
  outline: 2px solid #38bdf8 !important;
  outline-offset: 1px;
  position: relative;
}
.dyn-print-template td.dyn-print-col-hot,
.dyn-print-template th.dyn-print-col-hot {
  background: rgba(56, 189, 248, 0.16) !important;
  box-shadow: inset 2px 0 0 #38bdf8;
}
.dyn-print-template td.dyn-print-row-hot,
.dyn-print-template th.dyn-print-row-hot {
  background: rgba(56, 189, 248, 0.16) !important;
  box-shadow: inset 0 2px 0 #38bdf8;
}
.dyn-print-template .mp-var {
  background: #dbeafe;
  color: #1e3a8a;
  border-radius: 2px;
  padding: 0 2px;
  cursor: default;
}
.dyn-print-template .mp-var.mp-var-raw {
  background: #eef2ff;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.92em;
}
`;

function normalizeEditorHtml(value: string): string {
  return value.replace(/\u00a0/g, "&nbsp;").trim();
}

function closestTable(node: Node | null, root: HTMLElement): HTMLTableElement | null {
  let cur: Node | null = node;
  while (cur && cur !== root) {
    if (cur instanceof HTMLTableElement) return cur;
    cur = cur.parentNode;
  }
  return null;
}

function cellInTable(table: HTMLTableElement): HTMLTableCellElement | null {
  const sel = document.getSelection();
  const node = sel?.anchorNode ?? null;
  let cur: Node | null = node;
  while (cur && cur !== table) {
    if (cur instanceof HTMLTableCellElement && table.contains(cur)) return cur;
    cur = cur.parentNode;
  }
  const first = table.querySelector("td,th");
  return first instanceof HTMLTableCellElement ? first : null;
}

function colIndexOf(cell: HTMLTableCellElement): number {
  return cell.cellIndex >= 0 ? cell.cellIndex : 0;
}

function cellFromEvent(node: Node | null, root: HTMLElement): HTMLTableCellElement | null {
  let cur: Node | null = node;
  while (cur && cur !== root) {
    if (cur instanceof HTMLTableCellElement) return cur;
    cur = cur.parentNode;
  }
  return null;
}

type ResizeHit =
  | { axis: "col"; index: number }
  | { axis: "row"; row: HTMLTableRowElement };

/** Border under the pointer: a column edge (left/right) or a row edge (up/down). */
function resizeHit(cell: HTMLTableCellElement, clientX: number, clientY: number): ResizeHit | null {
  const rect = cell.getBoundingClientRect();
  const row = cell.parentElement;
  if (!(row instanceof HTMLTableRowElement)) return null;
  const onRight = rect.right - clientX <= 7 && clientX >= rect.right - 7;
  const onLeft = clientX - rect.left <= 7 && cell.cellIndex > 0;
  const onBottom = rect.bottom - clientY <= 7 && clientY >= rect.bottom - 7;
  const onTop = clientY - rect.top <= 7 && row.rowIndex > 0;
  const colDist = onRight ? rect.right - clientX : onLeft ? clientX - rect.left : Infinity;
  const rowDist = onBottom ? rect.bottom - clientY : onTop ? clientY - rect.top : Infinity;
  if (colDist === Infinity && rowDist === Infinity) return null;
  if (colDist <= rowDist) {
    return { axis: "col", index: onRight ? cell.cellIndex : cell.cellIndex - 1 };
  }
  if (onBottom || row.rowIndex <= 0) return { axis: "row", row };
  const table = row.closest("table");
  const above = table instanceof HTMLTableElement ? table.rows[row.rowIndex - 1] : null;
  return { axis: "row", row: above ?? row };
}

function paintColumn(root: HTMLElement | null, table: HTMLTableElement | null, index: number | null) {
  root?.querySelectorAll(".dyn-print-col-hot").forEach((node) => node.classList.remove("dyn-print-col-hot"));
  if (!root || !table || index === null || !root.contains(table)) return;
  Array.from(table.rows).forEach((tr) => tr.cells[index]?.classList.add("dyn-print-col-hot"));
}

function paintRow(root: HTMLElement | null, row: HTMLTableRowElement | null) {
  root?.querySelectorAll(".dyn-print-row-hot").forEach((node) => node.classList.remove("dyn-print-row-hot"));
  if (!root || !row || !root.contains(row)) return;
  Array.from(row.cells).forEach((cell) => cell.classList.add("dyn-print-row-hot"));
}

function lockColumnWidths(table: HTMLTableElement, index: number): number {
  const row = table.rows[0];
  table.style.tableLayout = "fixed";
  if (!table.style.width) {
    table.style.width = `${Math.round(table.getBoundingClientRect().width)}px`;
  }
  let start = 48;
  if (!row) return start;
  Array.from(row.cells).forEach((cell, i) => {
    const width = Math.max(28, Math.round(cell.getBoundingClientRect().width));
    if (i === index) start = width;
    cell.style.width = `${width}px`;
  });
  return start;
}

function chipHtml(token: string, label: string): string {
  const safeToken = token.replace(/&/g, "&amp;").replace(/"/g, "&quot;");
  const safeLabel = label.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/"/g, "&quot;");
  return `<span class="mp-var" contenteditable="false" data-mp-token="${safeToken}" data-mp-label="${safeLabel}">${safeLabel}</span>`;
}

function applyChipDisplay(root: HTMLElement, raw: boolean) {
  root.querySelectorAll(".mp-var").forEach((node) => {
    if (!(node instanceof HTMLElement)) return;
    const token = node.getAttribute("data-mp-token") || "";
    const label = node.getAttribute("data-mp-label") || token;
    node.textContent = raw ? token : label;
    node.classList.toggle("mp-var-raw", raw);
  });
}

const FIELD_TOKEN_RE = /\{\{\s*[A-Za-z_][\w.]*\s*\}\}/g;

function tokenKey(token: string): string {
  return token.replace(/\s+/g, "").toLowerCase();
}

function fallbackFieldLabel(token: string): string {
  const leaf = token.replace(/[{}]/g, "").trim().split(".").pop() || token;
  return leaf.replace(/_/g, " ").replace(/\b\w/g, (ch) => ch.toUpperCase());
}

/** Turn bare {{tokens}} already in the template into field chips so the raw toggle has something to switch. */
function hydratePrintTokens(root: HTMLElement, tokens: PrintTokenOption[]) {
  const lookup = new Map(tokens.map((row) => [tokenKey(row.token), row.label]));
  root.querySelectorAll(".mp-var").forEach((node) => {
    if (!(node instanceof HTMLElement)) return;
    const known = lookup.get(tokenKey(node.getAttribute("data-mp-token") || ""));
    if (known) node.setAttribute("data-mp-label", known);
  });
  const texts: Text[] = [];
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  let current = walker.nextNode();
  while (current) {
    texts.push(current as Text);
    current = walker.nextNode();
  }
  for (const textNode of texts) {
    const parent = textNode.parentElement;
    if (!parent || parent.closest(".mp-var, style, script")) continue;
    const value = textNode.nodeValue ?? "";
    if (!value.includes("{{")) continue;
    FIELD_TOKEN_RE.lastIndex = 0;
    if (!FIELD_TOKEN_RE.test(value)) continue;
    FIELD_TOKEN_RE.lastIndex = 0;
    const frag = document.createDocumentFragment();
    let last = 0;
    let match: RegExpExecArray | null;
    while ((match = FIELD_TOKEN_RE.exec(value))) {
      if (match.index > last) frag.append(value.slice(last, match.index));
      const raw = match[0].replace(/\s+/g, "");
      const label = lookup.get(tokenKey(raw)) || fallbackFieldLabel(raw);
      const span = document.createElement("span");
      span.className = "mp-var";
      span.contentEditable = "false";
      span.setAttribute("data-mp-token", raw);
      span.setAttribute("data-mp-label", label);
      span.textContent = label;
      frag.append(span);
      last = match.index + match[0].length;
    }
    if (last < value.length) frag.append(value.slice(last));
    textNode.replaceWith(frag);
  }
}

function canonicalPrintHtml(value: string): string {
  return normalizeEditorHtml(expandPrintFieldChips(value));
}

function tokenSpanFrom(node: Node | null, root: HTMLElement): HTMLElement | null {
  let cur: Node | null = node;
  while (cur && cur !== root) {
    if (cur instanceof HTMLElement && cur.classList.contains("mp-var")) return cur;
    cur = cur.parentNode;
  }
  return null;
}

function cellFromNode(node: Node | null, table: HTMLTableElement): HTMLTableCellElement | null {
  let cur: Node | null = node;
  while (cur && cur !== table) {
    if (cur instanceof HTMLTableCellElement && table.contains(cur)) return cur;
    cur = cur.parentNode;
  }
  return null;
}

type ContextMenuState = {
  x: number;
  y: number;
  tokenEl: HTMLElement | null;
  cell: HTMLTableCellElement | null;
  table: HTMLTableElement | null;
  submenu: "cell" | "row" | "column" | null;
};

type Clip =
  | { kind: "row"; html: string }
  | { kind: "col"; cells: string[] };

export const DynPrintableWysiwyg = forwardRef<DynPrintableWysiwygHandle, Props>(
  function DynPrintableWysiwyg(
    { html, css, onHtmlChange, onCssChange, orientation = "portrait", insertAsValue = true, tokens = [], className },
    ref,
  ) {
    const [mode, setMode] = useState<Mode>("design");
    const [showGuides, setShowGuides] = useState(true);
    const [tableUi, setTableUi] = useState<TableToolbarState | null>(null);
    const [rawFields, setRawFields] = useState(false);
    const [menu, setMenu] = useState<ContextMenuState | null>(null);
    const [sourceOpen, setSourceOpen] = useState(false);
    const [sourceDraft, setSourceDraft] = useState("");
    const [replaceQuery, setReplaceQuery] = useState("");
    const [replaceEl, setReplaceEl] = useState<HTMLElement | null>(null);
    const [editValue, setEditValue] = useState("");
    const [editEl, setEditEl] = useState<HTMLElement | null>(null);
    const [linkUrl, setLinkUrl] = useState("https://");
    const [linkOpen, setLinkOpen] = useState(false);
    const [cellWidth, setCellWidth] = useState("");
    const [cellAlign, setCellAlign] = useState("left");
    const [cellEl, setCellEl] = useState<HTMLTableCellElement | null>(null);
    const clipRef = useRef<Clip | null>(null);
    const dragRef = useRef<{
      axis: "col" | "row";
      table: HTMLTableElement;
      index: number;
      row: HTMLTableRowElement | null;
      start: number;
      size: number;
    } | null>(null);
    const emitHtmlRef = useRef<() => void>(() => {});
    const rawRef = useRef(rawFields);
    const insertAsValueRef = useRef(insertAsValue);
    const tokensRef = useRef(tokens);
    rawRef.current = rawFields;
    insertAsValueRef.current = insertAsValue;
    tokensRef.current = tokens;
    const editorRef = useRef<HTMLDivElement | null>(null);
    const canvasRef = useRef<HTMLDivElement | null>(null);
    const skipPropSync = useRef(false);
    const modeRef = useRef(mode);
    const htmlRef = useRef(html);
    const cssRef = useRef(css);
    modeRef.current = mode;
    htmlRef.current = html;
    cssRef.current = css;

    const isLandscape = orientation === "landscape";
    const pageWidth = isLandscape ? "11in" : "8.5in";
    const pageMinHeight = isLandscape ? "8.5in" : "11in";

    useLayoutEffect(() => {
      const el = editorRef.current;
      if (!el) return;
      if (skipPropSync.current) {
        skipPropSync.current = false;
        applyChipDisplay(el, rawRef.current);
        return;
      }
      const next = html || "";
      if (canonicalPrintHtml(el.innerHTML) !== canonicalPrintHtml(next)) {
        el.innerHTML = next;
      }
      hydratePrintTokens(el, tokensRef.current);
      applyChipDisplay(el, rawRef.current);
    }, [html, tokens]);

    useEffect(() => {
      if (mode !== "design") {
        clearTableSelection();
        setMenu(null);
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mode]);

    useEffect(() => {
      if (!menu) return;
      function close() {
        setMenu(null);
      }
      function onKey(e: KeyboardEvent) {
        if (e.key === "Escape") setMenu(null);
      }
      window.addEventListener("mousedown", close);
      window.addEventListener("keydown", onKey);
      return () => {
        window.removeEventListener("mousedown", close);
        window.removeEventListener("keydown", onKey);
      };
    }, [menu]);

    function clearTableSelection() {
      const root = editorRef.current;
      root?.querySelectorAll("table.dyn-print-table-selected").forEach((t) => t.classList.remove("dyn-print-table-selected"));
      paintColumn(root, null, null);
      paintRow(root, null);
      setTableUi(null);
    }

    function positionToolbar(table: HTMLTableElement) {
      const canvas = canvasRef.current;
      if (!canvas) return;
      const canvasBox = canvas.getBoundingClientRect();
      const tableBox = table.getBoundingClientRect();
      setTableUi({
        table,
        top: Math.max(4, tableBox.top - canvasBox.top + canvas.scrollTop - 40),
        left: Math.max(120, tableBox.left - canvasBox.left + canvas.scrollLeft + tableBox.width / 2),
      });
    }

    function selectTable(table: HTMLTableElement) {
      clearTableSelection();
      table.classList.add("dyn-print-table-selected");
      positionToolbar(table);
    }

    function exec(command: string, value?: string) {
      editorRef.current?.focus();
      document.execCommand(command, false, value);
      emitHtml();
    }

    function emitHtml() {
      const el = editorRef.current;
      if (!el) return;
      if (rawRef.current) applyChipDisplay(el, false);
      const selected = [...el.querySelectorAll("table.dyn-print-table-selected")];
      const hotCols = [...el.querySelectorAll(".dyn-print-col-hot")];
      const hotRows = [...el.querySelectorAll(".dyn-print-row-hot")];
      selected.forEach((table) => table.classList.remove("dyn-print-table-selected"));
      hotCols.forEach((node) => node.classList.remove("dyn-print-col-hot"));
      hotRows.forEach((node) => node.classList.remove("dyn-print-row-hot"));
      const next = el.innerHTML;
      selected.forEach((table) => table.classList.add("dyn-print-table-selected"));
      hotCols.forEach((node) => node.classList.add("dyn-print-col-hot"));
      hotRows.forEach((node) => node.classList.add("dyn-print-row-hot"));
      if (rawRef.current) applyChipDisplay(el, true);
      if (!normalizeEditorHtml(next) && normalizeEditorHtml(htmlRef.current)) {
        return;
      }
      skipPropSync.current = true;
      onHtmlChange(next);
    }
    emitHtmlRef.current = emitHtml;

    useEffect(() => {
      function onMove(e: MouseEvent) {
        const drag = dragRef.current;
        if (!drag) return;
        if (drag.axis === "col") {
          const width = Math.max(28, Math.round(drag.size + (e.clientX - drag.start)));
          Array.from(drag.table.rows).forEach((tr) => {
            const cell = tr.cells[drag.index];
            if (cell) cell.style.width = `${width}px`;
          });
          return;
        }
        if (!drag.row) return;
        const height = Math.max(18, Math.round(drag.size + (e.clientY - drag.start)));
        drag.row.style.height = `${height}px`;
        Array.from(drag.row.cells).forEach((cell) => {
          cell.style.height = `${height}px`;
        });
      }
      function onUp() {
        const drag = dragRef.current;
        if (!drag) return;
        dragRef.current = null;
        document.body.style.cursor = "";
        if (editorRef.current) editorRef.current.style.cursor = "";
        paintColumn(editorRef.current, null, null);
        paintRow(editorRef.current, null);
        emitHtmlRef.current();
        if (editorRef.current?.contains(drag.table)) positionToolbar(drag.table);
      }
      window.addEventListener("mousemove", onMove);
      window.addEventListener("mouseup", onUp);
      return () => {
        window.removeEventListener("mousemove", onMove);
        window.removeEventListener("mouseup", onUp);
      };
      // positionToolbar reads live layout through refs.
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function insertToken(token: string, label?: string) {
      const currentMode = modeRef.current;
      if (currentMode === "source") {
        const current = htmlRef.current;
        onHtmlChange(`${current}${current && !current.endsWith(" ") ? " " : ""}${token}`);
        return;
      }
      if (currentMode === "styles") {
        const current = cssRef.current;
        onCssChange(`${current}${current ? "\n" : ""}/* ${token} */`);
        return;
      }
      editorRef.current?.focus();
      if (insertAsValueRef.current) {
        document.execCommand("insertHTML", false, chipHtml(token, label?.trim() || token));
      } else {
        document.execCommand("insertText", false, token);
      }
      emitHtml();
    }

    useImperativeHandle(ref, () => ({ insertToken }), [onHtmlChange, onCssChange]);

    function switchMode(next: Mode) {
      if (mode === "design" && next !== "design" && editorRef.current) {
        if (rawRef.current) applyChipDisplay(editorRef.current, false);
        const nextHtml = editorRef.current.innerHTML;
        if (rawRef.current) applyChipDisplay(editorRef.current, true);
        if (normalizeEditorHtml(nextHtml) || !normalizeEditorHtml(htmlRef.current)) {
          skipPropSync.current = true;
          onHtmlChange(nextHtml);
        }
      }
      setMode(next);
    }

    function onEditorMouseDown(e: React.MouseEvent) {
      const root = editorRef.current;
      if (!root || e.button !== 0) return;
      const target = e.target as Node;
      const cell = cellFromEvent(target, root);
      const table = closestTable(target, root);
      if (cell && table) {
        const hit = resizeHit(cell, e.clientX, e.clientY);
        if (hit?.axis === "col") {
          e.preventDefault();
          const startWidth = lockColumnWidths(table, hit.index);
          dragRef.current = { axis: "col", table, index: hit.index, row: null, start: e.clientX, size: startWidth };
          document.body.style.cursor = "col-resize";
          selectTable(table);
          paintColumn(root, table, hit.index);
          return;
        }
        if (hit?.axis === "row") {
          e.preventDefault();
          const startHeight = Math.max(18, Math.round(hit.row.getBoundingClientRect().height));
          dragRef.current = { axis: "row", table, index: hit.row.rowIndex, row: hit.row, start: e.clientY, size: startHeight };
          document.body.style.cursor = "row-resize";
          selectTable(table);
          paintRow(root, hit.row);
          return;
        }
      }
      if (table) {
        window.requestAnimationFrame(() => selectTable(table));
      } else {
        clearTableSelection();
      }
    }

    function onEditorMouseMove(e: React.MouseEvent) {
      if (dragRef.current) return;
      const root = editorRef.current;
      if (!root) return;
      const cell = cellFromEvent(e.target as Node, root);
      const table = cell ? closestTable(cell, root) : null;
      const hit = cell ? resizeHit(cell, e.clientX, e.clientY) : null;
      if (cell && table && hit?.axis === "col") {
        root.style.cursor = "col-resize";
        paintRow(root, null);
        paintColumn(root, table, hit.index);
        return;
      }
      if (hit?.axis === "row") {
        root.style.cursor = "row-resize";
        paintColumn(root, null, null);
        paintRow(root, hit.row);
        return;
      }
      root.style.cursor = "";
      paintColumn(root, null, null);
      paintRow(root, null);
    }

    function mutateSelectedTable(mutator: (table: HTMLTableElement, cell: HTMLTableCellElement) => void) {
      const table = menu?.table ?? tableUi?.table;
      if (!table || !editorRef.current?.contains(table)) return;
      const cell = (menu?.cell && table.contains(menu.cell) ? menu.cell : null) ?? cellInTable(table);
      if (!cell) return;
      mutator(table, cell);
      setMenu(null);
      emitHtml();
      window.requestAnimationFrame(() => {
        if (editorRef.current?.contains(table)) selectTable(table);
      });
    }

    function deleteTable() {
      const table = menu?.table ?? tableUi?.table;
      if (!table) return;
      table.remove();
      setMenu(null);
      clearTableSelection();
      emitHtml();
    }

    function insertRow(where: "before" | "after") {
      mutateSelectedTable((_table, cell) => {
        const tr = cell.parentElement;
        if (!(tr instanceof HTMLTableRowElement)) return;
        const cols = tr.children.length || 1;
        const neu = document.createElement("tr");
        for (let i = 0; i < cols; i++) {
          const td = document.createElement("td");
          td.innerHTML = "&nbsp;";
          neu.appendChild(td);
        }
        if (where === "before") tr.parentElement?.insertBefore(neu, tr);
        else tr.parentElement?.insertBefore(neu, tr.nextSibling);
      });
    }

    function deleteRow() {
      mutateSelectedTable((table, cell) => {
        const tr = cell.parentElement;
        if (!(tr instanceof HTMLTableRowElement)) return;
        const body = tr.parentElement;
        if (!body) return;
        if (body.querySelectorAll("tr").length <= 1) {
          table.remove();
          clearTableSelection();
          return;
        }
        tr.remove();
      });
    }

    function insertColumn(where: "before" | "after") {
      mutateSelectedTable((table, cell) => {
        const idx = colIndexOf(cell);
        table.querySelectorAll("tr").forEach((tr) => {
          const refCell = tr.children[idx] as HTMLElement | undefined;
          const neu = document.createElement(refCell?.tagName === "TH" ? "th" : "td");
          neu.innerHTML = "&nbsp;";
          if (!refCell) {
            tr.appendChild(neu);
            return;
          }
          if (where === "before") tr.insertBefore(neu, refCell);
          else tr.insertBefore(neu, refCell.nextSibling);
        });
      });
    }

    function deleteColumn() {
      mutateSelectedTable((table, cell) => {
        const idx = colIndexOf(cell);
        const rows = Array.from(table.querySelectorAll("tr"));
        if ((rows[0]?.children.length ?? 0) <= 1) {
          table.remove();
          clearTableSelection();
          return;
        }
        rows.forEach((tr) => {
          tr.children[idx]?.remove();
        });
      });
    }

    function openTableProperties() {
      const table = menu?.table ?? tableUi?.table;
      if (!table) return;
      setMenu(null);
      const width = window.prompt("Table width (e.g. 100% or 500px)", table.style.width || "100%");
      if (width === null) return;
      table.style.width = width.trim() || "100%";
      table.style.borderCollapse = table.style.borderCollapse || "collapse";
      emitHtml();
      window.requestAnimationFrame(() => selectTable(table));
    }

    function onEditorContextMenu(e: React.MouseEvent) {
      const root = editorRef.current;
      if (!root || mode !== "design") return;
      const target = e.target as Node;
      const tokenEl = tokenSpanFrom(target, root);
      const table = closestTable(target, root);
      const cell = table ? cellFromNode(target, table) : null;
      if (!tokenEl && !table) return;
      e.preventDefault();
      if (table) selectTable(table);
      setMenu({
        x: Math.min(e.clientX, window.innerWidth - 260),
        y: Math.min(e.clientY, window.innerHeight - 380),
        tokenEl,
        cell,
        table,
        submenu: null,
      });
    }

    function cutCopyRow(cut: boolean) {
      mutateSelectedTable((_table, cell) => {
        const tr = cell.parentElement;
        if (!(tr instanceof HTMLTableRowElement)) return;
        clipRef.current = { kind: "row", html: tr.outerHTML };
        if (cut) tr.remove();
      });
    }

    function pasteRow(where: "before" | "after") {
      const clip = clipRef.current;
      if (!clip || clip.kind !== "row") return;
      mutateSelectedTable((_table, cell) => {
        const tr = cell.parentElement;
        if (!(tr instanceof HTMLTableRowElement)) return;
        const holder = document.createElement("tbody");
        holder.innerHTML = clip.html;
        const neu = holder.firstElementChild;
        if (!(neu instanceof HTMLTableRowElement)) return;
        if (where === "before") tr.parentElement?.insertBefore(neu, tr);
        else tr.parentElement?.insertBefore(neu, tr.nextSibling);
      });
    }

    function cutCopyCol(cut: boolean) {
      mutateSelectedTable((table, cell) => {
        const idx = colIndexOf(cell);
        const cells = Array.from(table.querySelectorAll("tr")).map(
          (tr) => tr.children[idx]?.outerHTML ?? "<td>&nbsp;</td>",
        );
        clipRef.current = { kind: "col", cells };
        if (cut) Array.from(table.querySelectorAll("tr")).forEach((tr) => tr.children[idx]?.remove());
      });
    }

    function pasteCol(where: "before" | "after") {
      const clip = clipRef.current;
      if (!clip || clip.kind !== "col") return;
      mutateSelectedTable((table, cell) => {
        const idx = colIndexOf(cell);
        Array.from(table.querySelectorAll("tr")).forEach((tr, i) => {
          const holder = document.createElement("tr");
          holder.innerHTML = clip.cells[i] ?? "<td>&nbsp;</td>";
          const neu = holder.firstElementChild;
          if (!neu) return;
          const refNode = tr.children[idx] ?? null;
          if (where === "before") tr.insertBefore(neu, refNode);
          else tr.insertBefore(neu, refNode?.nextSibling ?? null);
        });
      });
    }

    function mergeCells() {
      mutateSelectedTable((_table, cell) => {
        const next = cell.nextElementSibling;
        if (!(next instanceof HTMLTableCellElement)) return;
        cell.colSpan = (cell.colSpan || 1) + (next.colSpan || 1);
        if (next.innerHTML.trim() && next.innerHTML !== "&nbsp;") {
          cell.innerHTML = `${cell.innerHTML} ${next.innerHTML}`;
        }
        next.remove();
      });
    }

    function splitCell() {
      mutateSelectedTable((_table, cell) => {
        if ((cell.colSpan || 1) > 1) cell.colSpan = cell.colSpan - 1;
        const neu = document.createElement(cell.tagName.toLowerCase() === "th" ? "th" : "td");
        neu.innerHTML = "&nbsp;";
        cell.parentElement?.insertBefore(neu, cell.nextSibling);
      });
    }

    function openCellProperties() {
      const cell = menu?.cell;
      if (!cell) return;
      setCellEl(cell);
      setCellWidth(cell.style.width || "");
      setCellAlign(cell.style.textAlign || "left");
      setMenu(null);
    }

    function applyCellProperties() {
      if (!cellEl) return;
      if (cellWidth.trim()) cellEl.style.width = cellWidth.trim();
      cellEl.style.textAlign = cellAlign;
      setCellEl(null);
      emitHtml();
    }

    function openSourceDialog() {
      const el = editorRef.current;
      if (mode === "design" && el) {
        if (rawRef.current) applyChipDisplay(el, false);
        setSourceDraft(el.innerHTML);
        if (rawRef.current) applyChipDisplay(el, true);
      } else {
        setSourceDraft(html);
      }
      setSourceOpen(true);
    }

    function saveSourceDialog() {
      onHtmlChange(sourceDraft);
      if (mode === "design" && editorRef.current) {
        skipPropSync.current = true;
        editorRef.current.innerHTML = sourceDraft;
        applyChipDisplay(editorRef.current, rawRef.current);
      }
      setSourceOpen(false);
    }

    function removeToken() {
      menu?.tokenEl?.remove();
      setMenu(null);
      emitHtml();
    }

    function openEditToken() {
      const el = menu?.tokenEl;
      if (!el) return;
      setEditEl(el);
      setEditValue(el.getAttribute("data-mp-token") || "");
      setMenu(null);
    }

    function saveEditToken() {
      if (!editEl) return;
      const next = editValue.trim();
      editEl.setAttribute("data-mp-token", next);
      if (rawRef.current) editEl.textContent = next;
      setEditEl(null);
      emitHtml();
    }

    function openReplace() {
      setReplaceEl(menu?.tokenEl ?? null);
      setReplaceQuery("");
      setMenu(null);
    }

    function applyReplace(opt: PrintTokenOption) {
      if (!replaceEl) return;
      replaceEl.setAttribute("data-mp-token", opt.token);
      replaceEl.setAttribute("data-mp-label", opt.label);
      replaceEl.textContent = rawRef.current ? opt.token : opt.label;
      setReplaceEl(null);
      emitHtml();
    }

    function openLink() {
      setMenu(null);
      setLinkUrl("https://");
      setLinkOpen(true);
    }

    function applyLink() {
      setLinkOpen(false);
      const url = linkUrl.trim();
      if (!url) return;
      exec("createLink", url);
    }

    const previewCss = cssForDesignPreview(css);

    return (
      <div className={cn("flex min-h-0 flex-1 flex-col", className)}>
        <div className="flex flex-wrap items-center gap-1 border-b border-border px-2 py-1.5">
          {(["design", "styles", "source"] as const).map((m) => (
            <button
              key={m}
              type="button"
              onClick={() => switchMode(m)}
              className={cn(
                "rounded-md px-3 py-1.5 text-xs font-medium capitalize",
                mode === m
                  ? "bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100"
                  : "text-muted-foreground hover:bg-muted",
              )}
            >
              {m}
            </button>
          ))}
        </div>

        <div className={cn("flex min-h-0 flex-1 flex-col", mode !== "design" && "hidden")}>
          <div className="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/30 px-2 py-1">
            <ToolBtn label="Undo" onClick={() => exec("undo")}>
              <Undo2 className="size-3.5" />
            </ToolBtn>
            <ToolBtn label="Redo" onClick={() => exec("redo")}>
              <Redo2 className="size-3.5" />
            </ToolBtn>
            <Sep />
            <Select
              className="h-7 w-28 text-[11px]"
              defaultValue="p"
              onChange={(e) => exec("formatBlock", e.target.value)}
              aria-label="Paragraph style"
            >
              <option value="p">Paragraph</option>
              <option value="h2">Heading</option>
              <option value="h3">Subheading</option>
              <option value="pre">Monospace</option>
            </Select>
            <Sep />
            <ToolBtn label="Bold" onClick={() => exec("bold")}>
              <Bold className="size-3.5" />
            </ToolBtn>
            <ToolBtn label="Italic" onClick={() => exec("italic")}>
              <Italic className="size-3.5" />
            </ToolBtn>
            <ToolBtn label="Underline" onClick={() => exec("underline")}>
              <Underline className="size-3.5" />
            </ToolBtn>
            <Sep />
            <ToolBtn label="Align left" onClick={() => exec("justifyLeft")}>
              <AlignLeft className="size-3.5" />
            </ToolBtn>
            <ToolBtn label="Align center" onClick={() => exec("justifyCenter")}>
              <AlignCenter className="size-3.5" />
            </ToolBtn>
            <ToolBtn label="Align right" onClick={() => exec("justifyRight")}>
              <AlignRight className="size-3.5" />
            </ToolBtn>
            <Sep />
            <ToolBtn label="Bullet list" onClick={() => exec("insertUnorderedList")}>
              <List className="size-3.5" />
            </ToolBtn>
            <ToolBtn label="Numbered list" onClick={() => exec("insertOrderedList")}>
              <ListOrdered className="size-3.5" />
            </ToolBtn>
            <Sep />
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-7 px-2 text-[11px]"
              onClick={() =>
                exec(
                  "insertHTML",
                  '<table style="width:100%;border-collapse:collapse;"><tr><td style="padding:4px;">&nbsp;</td><td style="padding:4px;">&nbsp;</td></tr><tr><td style="padding:4px;">&nbsp;</td><td style="padding:4px;">&nbsp;</td></tr></table>',
                )
              }
            >
              Table
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className={cn("h-7 px-2 text-[11px]", showGuides && "bg-muted")}
              onClick={() => setShowGuides((v) => !v)}
              title="Show dashed structure guides (Design only)"
            >
              Guides
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              aria-pressed={rawFields}
              className={cn("h-7 gap-1 px-2 text-[11px]", rawFields && "bg-sky-100 text-sky-900 ring-1 ring-sky-400")}
              onClick={() => {
                const next = !rawRef.current;
                rawRef.current = next;
                setRawFields(next);
                const el = editorRef.current;
                if (!el) return;
                hydratePrintTokens(el, tokensRef.current);
                applyChipDisplay(el, next);
              }}
              title="Show fields as raw {{tokens}}"
            >
              <Grid2x2 className="size-3.5" />
              <ChevronDown className="size-3" />
              Show fields as raw
            </Button>
            <ToolBtn label="Source code" onClick={openSourceDialog}>
              <CodeXml className="size-3.5" />
            </ToolBtn>
            <span className="ml-auto text-[10px] text-muted-foreground">
              {isLandscape ? "Landscape page" : "Portrait page"}
              {previewCss ? " · Styles applied" : ""}
            </span>
          </div>
          <div
            ref={canvasRef}
            className="dyn-print-design-canvas relative min-h-0 flex-1 overflow-auto bg-[#e8e8e8] p-4"
            data-guides={showGuides ? "1" : "0"}
            onScroll={() => {
              if (tableUi?.table && editorRef.current?.contains(tableUi.table)) {
                positionToolbar(tableUi.table);
              }
            }}
          >
            {previewCss ? <style dangerouslySetInnerHTML={{ __html: previewCss }} /> : null}
            <style dangerouslySetInnerHTML={{ __html: DESIGN_STRUCTURE_GUIDES_CSS }} />
            {tableUi ? (
              <div
                className="absolute z-20 flex -translate-x-1/2 items-center gap-0.5 rounded-lg border border-slate-200 bg-white px-1 py-0.5 text-slate-800 shadow-md"
                style={{ top: tableUi.top, left: tableUi.left }}
                onMouseDown={(e) => e.preventDefault()}
              >
                <ToolBtn label="Table properties" onClick={openTableProperties}>
                  <Grid2x2 className="size-3.5" />
                </ToolBtn>
                <ToolBtn label="Delete table" onClick={deleteTable}>
                  <Trash2 className="size-3.5" />
                </ToolBtn>
                <Sep />
                <ToolBtn label="Insert row before" onClick={() => insertRow("before")}>
                  <BetweenHorizontalStart className="size-3.5" />
                </ToolBtn>
                <ToolBtn label="Insert row after" onClick={() => insertRow("after")}>
                  <Rows2 className="size-3.5" />
                </ToolBtn>
                <ToolBtn label="Delete row" onClick={deleteRow}>
                  <Table2 className="size-3.5" />
                </ToolBtn>
                <Sep />
                <ToolBtn label="Insert column before" onClick={() => insertColumn("before")}>
                  <BetweenVerticalStart className="size-3.5" />
                </ToolBtn>
                <ToolBtn label="Insert column after" onClick={() => insertColumn("after")}>
                  <Columns2 className="size-3.5" />
                </ToolBtn>
                <ToolBtn label="Delete column" onClick={deleteColumn}>
                  <Trash2 className="size-3.5" />
                </ToolBtn>
              </div>
            ) : null}
            <div
              ref={editorRef}
              className={cn(
                "dyn-print-template mx-auto bg-white text-[13px] text-slate-900 shadow-sm outline-none",
                previewCss ? "p-0" : "px-6 py-4",
              )}
              style={{
                width: pageWidth,
                maxWidth: "100%",
                minHeight: pageMinHeight,
              }}
              contentEditable
              suppressContentEditableWarning
              onInput={emitHtml}
              onBlur={emitHtml}
              onMouseDown={onEditorMouseDown}
              onMouseMove={onEditorMouseMove}
              onMouseLeave={() => {
                if (dragRef.current) return;
                paintColumn(editorRef.current, null, null);
                paintRow(editorRef.current, null);
                if (editorRef.current) editorRef.current.style.cursor = "";
              }}
              onContextMenu={onEditorContextMenu}
              onKeyDown={(e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
                  e.preventDefault();
                  openLink();
                }
              }}
            />
          </div>
        </div>

        {menu ? (
          <div
            className="fixed z-50 min-w-56 rounded-md border border-border bg-popover py-1 text-[13px] text-popover-foreground shadow-md"
            style={{ top: menu.y, left: menu.x }}
            onMouseDown={(e) => e.stopPropagation()}
          >
            {menu.tokenEl ? (
              <>
                <div className="mx-1 mb-1 rounded bg-primary px-2 py-1 font-mono text-[12px] text-primary-foreground">
                  {menu.tokenEl.getAttribute("data-mp-token")}
                </div>
                <MenuItem onClick={openReplace}>Replace with another field…</MenuItem>
                <MenuItem onClick={openEditToken}>Edit token…</MenuItem>
                <MenuItem onClick={removeToken}>Remove field</MenuItem>
                <div className="my-1 h-px bg-border" />
              </>
            ) : null}
            {menu.table ? (
              <>
                <MenuItem onClick={openLink}>
                  <Link2 className="size-3.5" /> Link… <span className="ml-auto pl-6 text-muted-foreground">Ctrl+K</span>
                </MenuItem>
                <MenuFly label="Cell" open={menu.submenu === "cell"} onOpen={() => setMenu({ ...menu, submenu: "cell" })}>
                  <MenuItem onClick={openCellProperties}>Cell properties</MenuItem>
                  <MenuItem onClick={mergeCells}>Merge cells</MenuItem>
                  <MenuItem onClick={splitCell}>Split cell</MenuItem>
                </MenuFly>
                <MenuFly label="Row" open={menu.submenu === "row"} onOpen={() => setMenu({ ...menu, submenu: "row" })}>
                  <MenuItem onClick={() => insertRow("before")}>Insert row before</MenuItem>
                  <MenuItem onClick={() => insertRow("after")}>Insert row after</MenuItem>
                  <MenuItem onClick={deleteRow}>Delete row</MenuItem>
                  <div className="my-1 h-px bg-border" />
                  <MenuItem onClick={() => cutCopyRow(true)}>Cut row</MenuItem>
                  <MenuItem onClick={() => cutCopyRow(false)}>Copy row</MenuItem>
                  <MenuItem onClick={() => pasteRow("before")}>Paste row before</MenuItem>
                  <MenuItem onClick={() => pasteRow("after")}>Paste row after</MenuItem>
                </MenuFly>
                <MenuFly label="Column" open={menu.submenu === "column"} onOpen={() => setMenu({ ...menu, submenu: "column" })}>
                  <MenuItem onClick={() => insertColumn("before")}>Insert column before</MenuItem>
                  <MenuItem onClick={() => insertColumn("after")}>Insert column after</MenuItem>
                  <MenuItem onClick={deleteColumn}>Delete column</MenuItem>
                  <div className="my-1 h-px bg-border" />
                  <MenuItem onClick={() => cutCopyCol(true)}>Cut column</MenuItem>
                  <MenuItem onClick={() => cutCopyCol(false)}>Copy column</MenuItem>
                  <MenuItem onClick={() => pasteCol("before")}>Paste column before</MenuItem>
                  <MenuItem onClick={() => pasteCol("after")}>Paste column after</MenuItem>
                </MenuFly>
                <div className="my-1 h-px bg-border" />
                <MenuItem onClick={openTableProperties}>Table properties</MenuItem>
                <MenuItem onClick={deleteTable}>Delete table</MenuItem>
              </>
            ) : null}
          </div>
        ) : null}

        <Dialog open={sourceOpen} onOpenChange={setSourceOpen}>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Source Code</DialogTitle>
            </DialogHeader>
            <DialogBody>
            <textarea
              className="min-h-72 w-full resize-y rounded-md border border-border bg-background px-3 py-2 font-mono text-xs"
              value={sourceDraft}
              onChange={(e) => setSourceDraft(e.target.value)}
              spellCheck={false}
              aria-label="Source code"
            />
            </DialogBody>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setSourceOpen(false)}>
                Cancel
              </Button>
              <Button type="button" onClick={saveSourceDialog}>
                Save
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <Dialog open={replaceEl !== null} onOpenChange={(open) => !open && setReplaceEl(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Replace with another field</DialogTitle>
            </DialogHeader>
            <DialogBody className="space-y-3">
            <Input
              value={replaceQuery}
              onChange={(e) => setReplaceQuery(e.target.value)}
              placeholder="Search fields"
              aria-label="Search fields"
            />
            <div className="max-h-64 overflow-auto">
              {tokens
                .filter((t) => {
                  const q = replaceQuery.trim().toLowerCase();
                  return !q || t.label.toLowerCase().includes(q) || t.token.toLowerCase().includes(q);
                })
                .map((t) => (
                  <button
                    key={t.token}
                    type="button"
                    className="flex w-full flex-col items-start px-2 py-1.5 text-left text-sm hover:bg-muted"
                    onClick={() => applyReplace(t)}
                  >
                    <span>{t.label}</span>
                    <span className="font-mono text-[11px] text-muted-foreground">{t.token}</span>
                  </button>
                ))}
            </div>
            </DialogBody>
          </DialogContent>
        </Dialog>

        <Dialog open={editEl !== null} onOpenChange={(open) => !open && setEditEl(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Edit token</DialogTitle>
            </DialogHeader>
            <DialogBody>
            <Input value={editValue} onChange={(e) => setEditValue(e.target.value)} aria-label="Token" />
            </DialogBody>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEditEl(null)}>
                Cancel
              </Button>
              <Button type="button" onClick={saveEditToken}>
                Save
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <Dialog open={linkOpen} onOpenChange={setLinkOpen}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Link</DialogTitle>
            </DialogHeader>
            <DialogBody>
            <Input value={linkUrl} onChange={(e) => setLinkUrl(e.target.value)} aria-label="Link URL" />
            </DialogBody>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setLinkOpen(false)}>
                Cancel
              </Button>
              <Button type="button" onClick={applyLink}>
                Save
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <Dialog open={cellEl !== null} onOpenChange={(open) => !open && setCellEl(null)}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>Cell properties</DialogTitle>
            </DialogHeader>
            <DialogBody className="space-y-3">
            <label className="grid gap-1 text-sm">
              Width
              <Input value={cellWidth} onChange={(e) => setCellWidth(e.target.value)} placeholder="e.g. 20%" />
            </label>
            <label className="grid gap-1 text-sm">
              Align
              <Select className="h-9 w-full" value={cellAlign} onChange={(e) => setCellAlign(e.target.value)} aria-label="Cell alignment">
                <option value="left">Left</option>
                <option value="center">Center</option>
                <option value="right">Right</option>
              </Select>
            </label>
            </DialogBody>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setCellEl(null)}>
                Cancel
              </Button>
              <Button type="button" onClick={applyCellProperties}>
                Save
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <textarea
          className={cn(
            "min-h-[28rem] flex-1 resize-none border-0 bg-slate-950 px-4 py-3 font-mono text-xs text-slate-100 outline-none",
            mode !== "styles" && "hidden",
          )}
          value={css}
          onChange={(e) => onCssChange(e.target.value)}
          spellCheck={false}
          aria-label="Printable CSS"
        />

        <textarea
          className={cn(
            "min-h-[28rem] flex-1 resize-none border-0 bg-slate-950 px-4 py-3 font-mono text-xs text-slate-100 outline-none",
            mode !== "source" && "hidden",
          )}
          value={html}
          onChange={(e) => onHtmlChange(e.target.value)}
          spellCheck={false}
          aria-label="Printable HTML source"
        />
      </div>
    );
  },
);

function ToolBtn({
  label,
  onClick,
  children,
}: {
  label: string;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      onClick={onClick}
      className="inline-flex size-7 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
    >
      {children}
    </button>
  );
}

function Sep() {
  return <span className="mx-1 h-4 w-px bg-border" aria-hidden />;
}

function MenuItem({ onClick, children }: { onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      className="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-primary hover:text-primary-foreground"
      onClick={onClick}
    >
      {children}
    </button>
  );
}

function MenuFly({
  label,
  open,
  onOpen,
  children,
}: {
  label: string;
  open: boolean;
  onOpen: () => void;
  children: React.ReactNode;
}) {
  return (
    <div className="relative" onMouseEnter={onOpen}>
      <button
        type="button"
        className="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-primary hover:text-primary-foreground"
      >
        {label}
        <ChevronRight className="ml-auto size-3.5" />
      </button>
      {open ? (
        <div className="absolute top-0 left-full z-10 min-w-52 rounded-md border border-border bg-popover py-1 shadow-md">
          {children}
        </div>
      ) : null}
    </div>
  );
}
