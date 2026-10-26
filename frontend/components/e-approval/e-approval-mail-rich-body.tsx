"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import {
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Bold,
  ListIndentDecrease,
  ListIndentIncrease,
  Italic,
  Link2,
  List,
  ListOrdered,
  Plus,
  Redo2,
  Table,
  Underline,
  Undo2,
  Unlink,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { isMailHtml, mailTokenQuery, sanitizeMailUrl } from "@/modules/e-approval/form-mail-config";

type MailTokenOption = { token: string; label: string };

const SYSTEM_TOKENS = new Set(["document_no", "form_name", "step_label", "requestor_name", "actor_name"]);
const ALLOWED_TAGS = new Set([
  "p", "br", "div", "span", "strong", "b", "em", "i", "u", "s", "h1", "h2", "h3", "h4",
  "ul", "ol", "li", "a", "blockquote", "table", "thead", "tbody", "tr", "th", "td",
]);

const FONTS = ["Arial", "Verdana", "Georgia", "Times New Roman", "Tahoma", "Courier New"];
const SIZES = ["10pt", "11pt", "12pt", "14pt", "18pt", "24pt"];

function escapeHtml(value: string): string {
  return value.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function allowedColor(value: string): boolean {
  return /^#(?:[0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(value)
    || /^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/i.test(value);
}

function allowedStyle(style: string): string {
  const kept: string[] = [];
  for (const rule of style.split(";")) {
    const parts = rule.split(":");
    if (parts.length < 2) {
      continue;
    }
    const property = parts[0]?.trim().toLowerCase() ?? "";
    const value = parts.slice(1).join(":").trim();
    if (value === "" || /url\s*\(|expression|javascript:/i.test(value)) {
      continue;
    }
    const ok = property === "font-family"
      ? /^[A-Za-z0-9 ,'-]+$/.test(value)
      : property === "font-size"
        ? /^\d+(?:\.\d+)?(?:px|pt|em|rem|%)$/.test(value)
        : property === "color" || property === "background-color"
          ? allowedColor(value)
          : property === "text-align"
            ? ["left", "center", "right", "justify"].includes(value.toLowerCase())
            : property === "font-weight"
              ? /^(?:normal|bold|[1-9]00)$/.test(value)
              : property === "font-style"
                ? ["normal", "italic"].includes(value.toLowerCase())
                : property === "text-decoration"
                  ? ["none", "underline", "line-through"].includes(value.toLowerCase())
                  : property === "width"
                    ? /^\d+(?:\.\d+)?(?:%|px)$/.test(value)
                    : property === "border-collapse"
                      ? value.toLowerCase() === "collapse"
                      : property === "border"
                        ? /^\d+px solid rgb\(\d{1,3}, \d{1,3}, \d{1,3}\)$/.test(value)
                        : property === "padding"
                          ? /^\d+px(?: \d+px){0,3}$/.test(value)
                          : false;
    if (ok) {
      kept.push(`${property}: ${value}`);
    }
  }

  return kept.join("; ");
}

function safeHref(value: string): string | null {
  const trimmed = value.trim();
  const mail = trimmed.match(/^mailto:([^\s<>]+@[^\s<>]+)$/i);
  if (mail) {
    return trimmed;
  }

  return sanitizeMailUrl(trimmed);
}

function sanitizeChildren(node: HTMLElement): void {
  for (const child of [...node.childNodes]) {
    if (child.nodeType === Node.TEXT_NODE) {
      continue;
    }
    if (!(child instanceof HTMLElement)) {
      child.remove();
      continue;
    }
    const tag = child.tagName.toLowerCase();
    if (["script", "style", "iframe", "object", "embed", "link", "meta", "img"].includes(tag)) {
      child.remove();
      continue;
    }
    const token = child.getAttribute("data-mail-token");
    if (token !== null) {
      child.replaceWith(document.createTextNode(/^[A-Za-z0-9_]+$/.test(token) ? `{{${token}}}` : child.textContent ?? ""));
      continue;
    }
    if (!ALLOWED_TAGS.has(tag)) {
      sanitizeChildren(child);
      child.replaceWith(...child.childNodes);
      continue;
    }
    const href = tag === "a" ? safeHref(child.getAttribute("href") ?? "") : null;
    let style = allowedStyle(child.getAttribute("style") ?? "");
    if (tag === "table") {
      style = style === "" ? "width:100%;border-collapse:collapse" : `${style}; width:100%;border-collapse:collapse`;
    }
    if (tag === "td" || tag === "th") {
      const border = "border:1px solid rgb(203, 213, 225); padding:4px 8px";
      style = style === "" ? border : `${style}; ${border}`;
    }
    const colspan = child.getAttribute("colspan") ?? "";
    const rowspan = child.getAttribute("rowspan") ?? "";
    for (const attribute of [...child.attributes]) {
      child.removeAttribute(attribute.name);
    }
    if (href) {
      child.setAttribute("href", href);
    }
    if (style !== "") {
      child.setAttribute("style", style);
    }
    if (/^[1-9]\d?$/.test(colspan)) {
      child.setAttribute("colspan", colspan);
    }
    if (/^[1-9]\d?$/.test(rowspan)) {
      child.setAttribute("rowspan", rowspan);
    }
    sanitizeChildren(child);
  }
}

export function prepareMailHtml(source: string): string {
  if (typeof DOMParser === "undefined" || source.trim() === "") {
    return source.trim();
  }
  const doc = new DOMParser().parseFromString(`<div id="mail-root">${source}</div>`, "text/html");
  const root = doc.getElementById("mail-root");
  if (!root) {
    return "";
  }
  sanitizeChildren(root);

  return root.innerHTML.trim();
}

function chipHtml(token: string, label: string, deleted: boolean): string {
  const flag = deleted ? ' data-deleted="1"' : "";

  return `<span data-mail-token="${token}" contenteditable="false"${flag}>${escapeHtml(label)}</span>`;
}

function withChips(html: string, tokens: MailTokenOption[]): string {
  return html.replace(/\{\{([A-Za-z0-9_]+)\}\}/g, (_match, token: string) => {
    const known = tokens.find((item) => item.token === token);

    return chipHtml(token, known?.label ?? "Deleted field", !known);
  });
}

function markdownToHtml(markdown: string): string {
  const inline = (source: string) => escapeHtml(source)
    .replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2">$1</a>')
    .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
    .replace(/(^|[^*])\*(.+?)\*/g, "$1<em>$2</em>");
  if (markdown.trim() === "") {
    return "";
  }

  return markdown.split(/\n{2,}/).map((block) => {
    const lines = block.split("\n");
    if (lines.every((line) => line.startsWith("### "))) {
      return lines.map((line) => `<h3>${inline(line.slice(4))}</h3>`).join("");
    }
    if (lines.every((line) => line.startsWith("- "))) {
      return `<ul>${lines.map((line) => `<li>${inline(line.slice(2))}</li>`).join("")}</ul>`;
    }
    if (lines.every((line) => /^\d+\. /.test(line))) {
      return `<ol>${lines.map((line) => `<li>${inline(line.replace(/^\d+\. /, ""))}</li>`).join("")}</ol>`;
    }
    if (lines.every((line) => line.startsWith("> "))) {
      return `<blockquote>${lines.map((line) => inline(line.slice(2))).join("<br>")}</blockquote>`;
    }

    return `<p>${lines.map((line) => inline(line)).join("<br>")}</p>`;
  }).join("");
}

function hydrate(value: string, tokens: MailTokenOption[]): string {
  if (value.trim() === "") {
    return "";
  }
  const html = isMailHtml(value) ? value : markdownToHtml(value);

  return withChips(html, tokens);
}

function ToolButton({
  label,
  disabled,
  onClick,
  children,
}: {
  label: string;
  disabled?: boolean;
  onClick: () => void;
  children: ReactNode;
}) {
  return (
    <Button
      type="button"
      size="icon-xs"
      variant="ghost"
      aria-label={label}
      title={label}
      disabled={disabled}
      onMouseDown={(event) => event.preventDefault()}
      onClick={onClick}
    >
      {children}
    </Button>
  );
}

type BodyProps = {
  value: string;
  disabled?: boolean;
  tokens: MailTokenOption[];
  onChange: (value: string) => void;
};

export function EApprovalMailBodyField({ value, disabled, tokens, onChange }: BodyProps) {
  const editorRef = useRef<HTMLDivElement>(null);
  const savedRange = useRef<Range | null>(null);
  const focused = useRef(false);
  const written = useRef(value);
  const tokenKey = tokens.map((item) => `${item.token}:${item.label}`).join("|");
  const hydratedKey = useRef("");
  const [query, setQuery] = useState<string | null>(null);
  const [menuBox, setMenuBox] = useState<{ top: number; left: number } | null>(null);
  const [fieldsOpen, setFieldsOpen] = useState(false);
  const [linkOpen, setLinkOpen] = useState(false);
  const [linkUrl, setLinkUrl] = useState("https://");
  const [linkInvalid, setLinkInvalid] = useState(false);

  useEffect(() => {
    const editor = editorRef.current;
    if (!editor || focused.current || (written.current === value && hydratedKey.current === tokenKey)) {
      return;
    }
    editor.innerHTML = hydrate(value, tokens);
    written.current = value;
    hydratedKey.current = tokenKey;
  }, [tokenKey, tokens, value]);

  const publish = () => {
    const editor = editorRef.current;
    if (!editor) {
      return;
    }
    const html = prepareMailHtml(editor.innerHTML);
    written.current = html;
    onChange(html);
  };

  const rememberRange = () => {
    const editor = editorRef.current;
    const selection = window.getSelection();
    if (editor && selection && selection.rangeCount > 0 && editor.contains(selection.anchorNode)) {
      savedRange.current = selection.getRangeAt(0).cloneRange();
    }
  };

  const restoreRange = () => {
    const editor = editorRef.current;
    const selection = window.getSelection();
    if (!editor || !selection) {
      return;
    }
    editor.focus();
    if (savedRange.current) {
      selection.removeAllRanges();
      selection.addRange(savedRange.current);
    }
  };

  const command = (name: string, argument?: string) => {
    const editor = editorRef.current;
    if (!editor || disabled) {
      return;
    }
    editor.focus();
    document.execCommand("styleWithCSS", false, "true");
    document.execCommand(name, false, argument);
    publish();
  };

  const applyStyle = (property: string, styleValue: string) => {
    restoreRange();
    const editor = editorRef.current;
    const selection = window.getSelection();
    if (!editor || !selection || selection.rangeCount === 0 || disabled) {
      return;
    }
    if (selection.isCollapsed) {
      const node = selection.anchorNode instanceof HTMLElement ? selection.anchorNode : selection.anchorNode?.parentElement;
      const block = node?.closest("p, h1, h2, h3, li, td, th, blockquote, div");
      if (block instanceof HTMLElement && editor.contains(block)) {
        block.style.setProperty(property, styleValue);
      }
      publish();
      return;
    }
    const span = document.createElement("span");
    span.style.setProperty(property, styleValue);
    const range = selection.getRangeAt(0);
    try {
      range.surroundContents(span);
    } catch {
      span.appendChild(range.extractContents());
      range.insertNode(span);
    }
    publish();
  };

  const textBeforeCaret = (editor: HTMLElement): string => {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0 || !selection.anchorNode || !editor.contains(selection.anchorNode)) {
      return "";
    }
    const range = selection.getRangeAt(0);
    const startNode = range.startContainer instanceof Element ? range.startContainer : range.startContainer.parentElement;
    const block = startNode?.closest("td, th, p, li, h1, h2, h3, h4, blockquote");
    const scope = block && editor.contains(block) ? block : editor;
    try {
      const before = range.cloneRange();
      before.selectNodeContents(scope);
      before.setEnd(range.endContainer, range.endOffset);

      return before.toString();
    } catch {
      return "";
    }
  };

  const refreshQuery = () => {
    const editor = editorRef.current;
    const selection = window.getSelection();
    if (!editor || !selection || selection.rangeCount === 0 || !selection.anchorNode || !editor.contains(selection.anchorNode)) {
      setQuery(null);
      setMenuBox(null);
      return;
    }
    const next = mailTokenQuery(textBeforeCaret(editor));
    setQuery(next);
    if (next === null) {
      setMenuBox(null);
      return;
    }
    const range = selection.getRangeAt(0);
    const rects = range.getClientRects();
    const caret = rects.length > 0 ? rects[rects.length - 1] : range.getBoundingClientRect();
    const host = (range.startContainer instanceof Element ? range.startContainer : range.startContainer.parentElement)?.getBoundingClientRect();
    const caretBottom = caret.height > 0 ? caret.bottom : (host?.bottom ?? caret.bottom);
    const caretTop = caret.height > 0 ? caret.top : (host?.top ?? caret.top);
    let top = caretBottom + 4;
    if (top + 200 > window.innerHeight) {
      top = Math.max(8, caretTop - 200);
    }
    const rawLeft = caret.width > 0 || caret.left > 0 ? caret.left : (host?.left ?? caret.left);
    const left = Math.min(Math.max(8, rawLeft), window.innerWidth - 328);
    setMenuBox({ top, left });
  };

  const clearTypedToken = (editor: HTMLElement) => {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) {
      return;
    }
    const typed = textBeforeCaret(editor).match(/\{\{[A-Za-z0-9_]*$/);
    if (!typed) {
      return;
    }
    const range = selection.getRangeAt(0);
    const length = typed[0].length;
    const node = range.startContainer;
    const offset = range.startOffset;
    if (node.nodeType === Node.TEXT_NODE && offset >= length) {
      const text = node.textContent ?? "";
      if (text.slice(offset - length, offset) !== typed[0]) {
        return;
      }
      node.textContent = text.slice(0, offset - length) + text.slice(offset);
      const next = document.createRange();
      next.setStart(node, offset - length);
      next.collapse(true);
      selection.removeAllRanges();
      selection.addRange(next);
      return;
    }
    if (node instanceof HTMLElement) {
      const child = node.childNodes[offset - 1];
      if (child?.nodeType !== Node.TEXT_NODE) {
        return;
      }
      const text = child.textContent ?? "";
      if (!text.endsWith(typed[0])) {
        return;
      }
      child.textContent = text.slice(0, text.length - length);
      const next = document.createRange();
      next.setStart(child, child.textContent?.length ?? 0);
      next.collapse(true);
      selection.removeAllRanges();
      selection.addRange(next);
    }
  };

  const insertChip = (token: string, useSavedRange: boolean) => {
    const editor = editorRef.current;
    const known = tokens.find((item) => item.token === token);
    if (!editor || !known) {
      return;
    }
    if (useSavedRange) {
      restoreRange();
    } else {
      editor.focus();
      clearTypedToken(editor);
    }
    document.execCommand("insertHTML", false, `${chipHtml(token, known.label, false)}&nbsp;`);
    setQuery(null);
    setMenuBox(null);
    setFieldsOpen(false);
    publish();
  };

  const insertTable = () => {
    const editor = editorRef.current;
    editor?.focus();
    const fields = tokens.filter((item) => !SYSTEM_TOKENS.has(item.token)).slice(0, 8);
    const rows = fields.length > 0
      ? fields.map((field) => `<tr><td>${escapeHtml(field.label)}</td><td>${chipHtml(field.token, field.label, false)}</td></tr>`).join("")
      : "<tr><td><br></td><td><br></td></tr><tr><td><br></td><td><br></td></tr>";
    document.execCommand(
      "insertHTML",
      false,
      `<table><thead><tr><th colspan="2">Form summary</th></tr></thead><tbody>${rows}</tbody></table><p><br></p>`,
    );
    publish();
  };

  const openLink = () => {
    rememberRange();
    setLinkUrl("https://");
    setLinkInvalid(false);
    setLinkOpen(true);
  };

  const applyLink = () => {
    const url = sanitizeMailUrl(linkUrl);
    if (!url) {
      setLinkInvalid(true);
      return;
    }
    restoreRange();
    const selection = window.getSelection();
    if (!selection || selection.isCollapsed) {
      const label = url.replace(/^https?:\/\//, "");
      document.execCommand("insertHTML", false, `<a href="${escapeHtml(url)}">${escapeHtml(label)}</a>`);
    } else {
      document.execCommand("createLink", false, url);
    }
    setLinkOpen(false);
    publish();
  };

  const matches = query === null
    ? []
    : tokens.filter((item) => item.token.toLowerCase().includes(query.toLowerCase()) || item.label.toLowerCase().includes(query.toLowerCase()));

  return (
    <div className="relative rounded-lg border border-input bg-background">
      <div className="flex flex-wrap items-center gap-1 border-b border-border bg-muted/60 px-1.5 py-1">
        <Select
          aria-label="Font"
          disabled={disabled}
          className="h-7 w-28 text-xs"
          onMouseDown={rememberRange}
          defaultValue=""
          onChange={(event) => {
            if (event.target.value) {
              applyStyle("font-family", event.target.value);
            }
          }}
        >
          <option value="">Font</option>
          {FONTS.map((font) => (
            <option key={font} value={font}>{font}</option>
          ))}
        </Select>
        <Select
          aria-label="Font size"
          disabled={disabled}
          className="h-7 w-20 text-xs"
          onMouseDown={rememberRange}
          defaultValue=""
          onChange={(event) => {
            if (event.target.value) {
              applyStyle("font-size", event.target.value);
            }
          }}
        >
          <option value="">Size</option>
          {SIZES.map((size) => (
            <option key={size} value={size}>{size}</option>
          ))}
        </Select>
        <Select
          aria-label="Formats"
          disabled={disabled}
          className="h-7 w-28 text-xs"
          onMouseDown={rememberRange}
          defaultValue=""
          onChange={(event) => {
            const block = event.target.value;
            event.target.value = "";
            if (block) {
              restoreRange();
              command("formatBlock", block);
            }
          }}
        >
          <option value="">Formats</option>
          <option value="p">Paragraph</option>
          <option value="h2">Heading</option>
          <option value="h3">Subheading</option>
          <option value="blockquote">Quote</option>
        </Select>
        <ToolButton label="Bold" disabled={disabled} onClick={() => command("bold")}><Bold /></ToolButton>
        <ToolButton label="Italic" disabled={disabled} onClick={() => command("italic")}><Italic /></ToolButton>
        <ToolButton label="Underline" disabled={disabled} onClick={() => command("underline")}><Underline /></ToolButton>
        <label className="inline-flex h-7 items-center" title="Text color">
          <span className="sr-only">Text color</span>
          <input
            type="color"
            aria-label="Text color"
            disabled={disabled}
            className="h-7 w-7 cursor-pointer rounded-md border border-border bg-background p-0.5"
            onMouseDown={rememberRange}
            onChange={(event) => applyStyle("color", event.target.value)}
          />
        </label>
      </div>
      <div className="flex flex-wrap items-center gap-0.5 border-b border-border bg-muted/60 px-1.5 py-1">
        <ToolButton label="Align left" disabled={disabled} onClick={() => command("justifyLeft")}><AlignLeft /></ToolButton>
        <ToolButton label="Align center" disabled={disabled} onClick={() => command("justifyCenter")}><AlignCenter /></ToolButton>
        <ToolButton label="Align right" disabled={disabled} onClick={() => command("justifyRight")}><AlignRight /></ToolButton>
        <ToolButton label="Justify" disabled={disabled} onClick={() => command("justifyFull")}><AlignJustify /></ToolButton>
        <ToolButton label="Bullet list" disabled={disabled} onClick={() => command("insertUnorderedList")}><List /></ToolButton>
        <ToolButton label="Numbered list" disabled={disabled} onClick={() => command("insertOrderedList")}><ListOrdered /></ToolButton>
        <ToolButton label="Decrease indent" disabled={disabled} onClick={() => command("outdent")}><ListIndentDecrease /></ToolButton>
        <ToolButton label="Increase indent" disabled={disabled} onClick={() => command("indent")}><ListIndentIncrease /></ToolButton>
        <ToolButton label="Insert table" disabled={disabled} onClick={insertTable}><Table /></ToolButton>
        <ToolButton label="Link" disabled={disabled} onClick={openLink}><Link2 /></ToolButton>
        <ToolButton label="Remove link" disabled={disabled} onClick={() => command("unlink")}><Unlink /></ToolButton>
        <ToolButton label="Undo" disabled={disabled} onClick={() => command("undo")}><Undo2 /></ToolButton>
        <ToolButton label="Redo" disabled={disabled} onClick={() => command("redo")}><Redo2 /></ToolButton>
        <ToolButton
          label="Insert field"
          disabled={disabled}
          onClick={() => {
            rememberRange();
            setFieldsOpen((open) => !open);
          }}
        >
          <Plus />
        </ToolButton>
      </div>
      {linkOpen && !disabled ? (
        <div className="flex flex-wrap items-center gap-1 border-b border-border px-2 py-1">
          <Input
            value={linkUrl}
            aria-label="Link address"
            placeholder="https://"
            className="h-8 max-w-sm"
            onChange={(event) => {
              setLinkUrl(event.target.value);
              setLinkInvalid(false);
            }}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                applyLink();
              }
            }}
          />
          <Button type="button" size="sm" variant="outline" onClick={applyLink}>Add link</Button>
          <Button type="button" size="sm" variant="ghost" onClick={() => setLinkOpen(false)}>Cancel</Button>
          {linkInvalid ? <span className="text-xs text-destructive">Use an http or https address.</span> : null}
        </div>
      ) : null}
      {fieldsOpen && !disabled ? (
        <ul className="max-h-40 overflow-y-auto border-b border-border bg-popover p-1 text-sm">
          {tokens.map((item) => (
            <li key={item.token}>
              <button
                type="button"
                className="flex w-full items-baseline justify-between gap-3 rounded-md px-2 py-1.5 text-left hover:bg-muted"
                onMouseDown={(event) => {
                  event.preventDefault();
                  insertChip(item.token, true);
                }}
              >
                <span className="font-medium text-foreground">{item.label}</span>
                <span className="font-mono text-xs text-muted-foreground">{`{{${item.token}}}`}</span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}
      <div
        ref={editorRef}
        role="textbox"
        aria-label="Body"
        aria-multiline="true"
        contentEditable={!disabled}
        suppressContentEditableWarning
        data-placeholder="Write the email. Type {{ to insert a field."
        className="min-h-36 px-3 py-2 text-sm text-foreground outline-none empty:before:text-muted-foreground empty:before:content-[attr(data-placeholder)] [&_a]:underline [&_blockquote]:my-1 [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_h2]:text-base [&_h2]:font-semibold [&_h3]:text-sm [&_h3]:font-semibold [&_li]:my-0.5 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:mb-2 [&_[data-deleted]]:rounded [&_[data-deleted]]:bg-destructive/15 [&_[data-deleted]]:px-1 [&_[data-deleted]]:text-destructive [&_[data-deleted]]:line-through [&_[data-mail-token]]:mx-0.5 [&_[data-mail-token]]:inline-flex [&_[data-mail-token]]:rounded [&_[data-mail-token]]:bg-muted [&_[data-mail-token]]:px-1.5 [&_[data-mail-token]]:py-0.5 [&_[data-mail-token]]:text-xs [&_[data-mail-token]]:font-medium [&_table]:my-2 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-border [&_th]:bg-muted/40 [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5"
        onInput={() => {
          publish();
          refreshQuery();
        }}
        onKeyUp={refreshQuery}
        onClick={refreshQuery}
        onFocus={() => {
          focused.current = true;
        }}
        onBlur={() => {
          focused.current = false;
          setQuery(null);
          setMenuBox(null);
        }}
      />
      {query !== null && menuBox && !disabled ? (
        <ul
          className="fixed z-50 max-h-48 w-80 overflow-y-auto rounded-lg border border-border bg-popover p-1 text-sm shadow-md"
          style={{ top: menuBox.top, left: menuBox.left }}
        >
          {matches.length === 0 ? (
            <li className="px-2 py-1.5 text-xs text-muted-foreground">No matching fields</li>
          ) : (
            matches.map((item) => (
              <li key={item.token}>
                <button
                  type="button"
                  className="flex w-full items-baseline justify-between gap-3 rounded-md px-2 py-1.5 text-left hover:bg-muted"
                  onMouseDown={(event) => {
                    event.preventDefault();
                    insertChip(item.token, false);
                  }}
                >
                  <span className="font-medium text-foreground">{item.label}</span>
                  <span className="font-mono text-xs text-muted-foreground">{`{{${item.token}}}`}</span>
                </button>
              </li>
            ))
          )}
        </ul>
      ) : null}
    </div>
  );
}
