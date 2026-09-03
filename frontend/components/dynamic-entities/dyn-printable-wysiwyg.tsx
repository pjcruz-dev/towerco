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
  Columns2,
  Grid2x2,
  Italic,
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
import { Select } from "@/components/ui/select";
import { cn } from "@/lib/utils";

type Mode = "design" | "styles" | "source";

export type DynPrintableWysiwygHandle = {
  insertToken: (token: string) => void;
};

type Props = {
  html: string;
  css: string;
  onHtmlChange: (html: string) => void;
  onCssChange: (css: string) => void;
  orientation?: "portrait" | "landscape" | null;
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
  const tr = cell.parentElement;
  if (!(tr instanceof HTMLTableRowElement)) return 0;
  return Array.from(tr.children).indexOf(cell);
}

export const DynPrintableWysiwyg = forwardRef<DynPrintableWysiwygHandle, Props>(
  function DynPrintableWysiwyg(
    { html, css, onHtmlChange, onCssChange, orientation = "portrait", className },
    ref,
  ) {
    const [mode, setMode] = useState<Mode>("design");
    const [showGuides, setShowGuides] = useState(true);
    const [tableUi, setTableUi] = useState<TableToolbarState | null>(null);
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
        return;
      }
      const next = html || "";
      if (normalizeEditorHtml(el.innerHTML) !== normalizeEditorHtml(next)) {
        el.innerHTML = next;
      }
    }, [html]);

    useEffect(() => {
      if (mode !== "design") clearTableSelection();
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mode]);

    function clearTableSelection() {
      editorRef.current
        ?.querySelectorAll("table.dyn-print-table-selected")
        .forEach((t) => t.classList.remove("dyn-print-table-selected"));
      setTableUi(null);
    }

    function positionToolbar(table: HTMLTableElement) {
      const canvas = canvasRef.current;
      if (!canvas) return;
      const canvasBox = canvas.getBoundingClientRect();
      const tableBox = table.getBoundingClientRect();
      setTableUi({
        table,
        top: Math.max(4, tableBox.top - canvasBox.top + canvas.scrollTop - 36),
        left: Math.max(4, tableBox.left - canvasBox.left + canvas.scrollLeft),
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
      if (!editorRef.current) return;
      const next = editorRef.current.innerHTML;
      if (!normalizeEditorHtml(next) && normalizeEditorHtml(htmlRef.current)) {
        return;
      }
      skipPropSync.current = true;
      onHtmlChange(next);
    }

    function insertToken(token: string) {
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
      document.execCommand("insertText", false, token);
      emitHtml();
    }

    useImperativeHandle(ref, () => ({ insertToken }), [onHtmlChange, onCssChange]);

    function switchMode(next: Mode) {
      if (mode === "design" && next !== "design" && editorRef.current) {
        const nextHtml = editorRef.current.innerHTML;
        if (normalizeEditorHtml(nextHtml) || !normalizeEditorHtml(htmlRef.current)) {
          skipPropSync.current = true;
          onHtmlChange(nextHtml);
        }
      }
      setMode(next);
    }

    function onEditorMouseDown(e: React.MouseEvent) {
      const root = editorRef.current;
      if (!root) return;
      const target = e.target as Node;
      const table = closestTable(target, root);
      if (table) {
        window.requestAnimationFrame(() => selectTable(table));
      } else {
        clearTableSelection();
      }
    }

    function mutateSelectedTable(mutator: (table: HTMLTableElement, cell: HTMLTableCellElement) => void) {
      const table = tableUi?.table;
      if (!table || !editorRef.current?.contains(table)) return;
      const cell = cellInTable(table);
      if (!cell) return;
      mutator(table, cell);
      emitHtml();
      window.requestAnimationFrame(() => {
        if (editorRef.current?.contains(table)) selectTable(table);
      });
    }

    function deleteTable() {
      const table = tableUi?.table;
      if (!table) return;
      table.remove();
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
      const table = tableUi?.table;
      if (!table) return;
      const width = window.prompt("Table width (e.g. 100% or 500px)", table.style.width || "100%");
      if (width === null) return;
      table.style.width = width.trim() || "100%";
      table.style.borderCollapse = table.style.borderCollapse || "collapse";
      emitHtml();
      window.requestAnimationFrame(() => selectTable(table));
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
                className="absolute z-20 flex items-center gap-0.5 rounded-md border border-slate-300 bg-white px-1 py-0.5 shadow-md"
                style={{ top: tableUi.top, left: tableUi.left }}
                onMouseDown={(e) => e.preventDefault()}
              >
                <ToolBtn label="Table properties" onClick={openTableProperties}>
                  <Grid2x2 className="size-3.5" />
                </ToolBtn>
                <ToolBtn label="Delete table" onClick={deleteTable}>
                  <Trash2 className="size-3.5 text-destructive" />
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
            />
          </div>
        </div>

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
