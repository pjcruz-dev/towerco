"use client";

import { useMemo, type ReactNode } from "react";

type HelpMarkdownProps = {
  content: string;
  className?: string;
};

/**
 * Lightweight markdown renderer for user guides (no extra dependency).
 * Supports headings, paragraphs, lists, tables, bold, inline code, and links.
 */
export function HelpMarkdown({ content, className }: HelpMarkdownProps) {
  const blocks = useMemo(() => parseBlocks(content), [content]);

  return (
    <div className={className ?? "space-y-3 text-sm leading-relaxed text-foreground"}>
      {blocks.map((block, index) => {
        switch (block.type) {
          case "h1":
            return (
              <h1 key={index} className="text-2xl font-semibold tracking-tight text-foreground">
                {renderInline(block.text)}
              </h1>
            );
          case "h2":
            return (
              <h2 key={index} className="mt-6 text-xl font-semibold text-foreground">
                {renderInline(block.text)}
              </h2>
            );
          case "h3":
            return (
              <h3 key={index} className="mt-4 text-base font-medium text-foreground">
                {renderInline(block.text)}
              </h3>
            );
          case "ul":
            return (
              <ul key={index} className="list-disc space-y-1 pl-5 text-muted-foreground">
                {block.items.map((item, i) => (
                  <li key={i} className="text-foreground">
                    {renderInline(item)}
                  </li>
                ))}
              </ul>
            );
          case "ol":
            return (
              <ol key={index} className="list-decimal space-y-1 pl-5 text-muted-foreground">
                {block.items.map((item, i) => (
                  <li key={i} className="text-foreground">
                    {renderInline(item)}
                  </li>
                ))}
              </ol>
            );
          case "table":
            return (
              <div key={index} className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full min-w-[28rem] border-collapse text-left text-sm">
                  <thead className="bg-muted/40">
                    <tr>
                      {block.headers.map((header, i) => (
                        <th key={i} className="border-b border-border px-3 py-2 font-medium">
                          {renderInline(header)}
                        </th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {block.rows.map((row, ri) => (
                      <tr key={ri} className="odd:bg-card even:bg-muted/20">
                        {row.map((cell, ci) => (
                          <td key={ci} className="border-b border-border px-3 py-2 align-top text-muted-foreground">
                            {renderInline(cell)}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            );
          case "hr":
            return <hr key={index} className="border-border" />;
          case "p":
          default:
            return (
              <p key={index} className="text-muted-foreground">
                {renderInline(block.text)}
              </p>
            );
        }
      })}
    </div>
  );
}

type Block =
  | { type: "h1" | "h2" | "h3" | "p"; text: string }
  | { type: "ul" | "ol"; items: string[] }
  | { type: "table"; headers: string[]; rows: string[][] }
  | { type: "hr" };

function parseBlocks(markdown: string): Block[] {
  const lines = markdown.replace(/\r\n/g, "\n").split("\n");
  const blocks: Block[] = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i] ?? "";
    const trimmed = line.trim();

    if (trimmed === "") {
      i += 1;
      continue;
    }

    if (trimmed === "---") {
      blocks.push({ type: "hr" });
      i += 1;
      continue;
    }

    if (trimmed.startsWith("# ")) {
      blocks.push({ type: "h1", text: trimmed.slice(2) });
      i += 1;
      continue;
    }
    if (trimmed.startsWith("## ")) {
      blocks.push({ type: "h2", text: trimmed.slice(3) });
      i += 1;
      continue;
    }
    if (trimmed.startsWith("### ")) {
      blocks.push({ type: "h3", text: trimmed.slice(4) });
      i += 1;
      continue;
    }

    if (trimmed.startsWith("|") && (lines[i + 1] ?? "").includes("---")) {
      const headers = splitTableRow(trimmed);
      i += 2;
      const rows: string[][] = [];
      while (i < lines.length && (lines[i] ?? "").trim().startsWith("|")) {
        rows.push(splitTableRow((lines[i] ?? "").trim()));
        i += 1;
      }
      blocks.push({ type: "table", headers, rows });
      continue;
    }

    if (/^[-*]\s+/.test(trimmed) || /^\d+\.\s+/.test(trimmed)) {
      const ordered = /^\d+\.\s+/.test(trimmed);
      const items: string[] = [];
      while (i < lines.length) {
        const itemLine = (lines[i] ?? "").trim();
        if (ordered) {
          const match = itemLine.match(/^\d+\.\s+(.*)$/);
          if (!match) break;
          items.push(match[1] ?? "");
        } else {
          const match = itemLine.match(/^[-*]\s+(.*)$/);
          if (!match) break;
          items.push(match[1] ?? "");
        }
        i += 1;
      }
      blocks.push({ type: ordered ? "ol" : "ul", items });
      continue;
    }

    const paragraph: string[] = [trimmed];
    i += 1;
    while (i < lines.length) {
      const next = (lines[i] ?? "").trim();
      if (
        next === "" ||
        next.startsWith("#") ||
        next.startsWith("|") ||
        next === "---" ||
        /^[-*]\s+/.test(next) ||
        /^\d+\.\s+/.test(next)
      ) {
        break;
      }
      paragraph.push(next);
      i += 1;
    }
    blocks.push({ type: "p", text: paragraph.join(" ") });
  }

  return blocks;
}

function splitTableRow(row: string): string[] {
  return row
    .replace(/^\|/, "")
    .replace(/\|$/, "")
    .split("|")
    .map((cell) => cell.trim());
}

function renderInline(text: string): ReactNode[] {
  const nodes: ReactNode[] = [];
  const pattern = /(\*\*[^*]+\*\*|`[^`]+`|\[[^\]]+\]\([^)]+\))/g;
  let lastIndex = 0;
  let match: RegExpExecArray | null;
  let key = 0;

  while ((match = pattern.exec(text)) !== null) {
    if (match.index > lastIndex) {
      nodes.push(text.slice(lastIndex, match.index));
    }
    const token = match[0];
    if (token.startsWith("**")) {
      nodes.push(
        <strong key={key++} className="font-medium text-foreground">
          {token.slice(2, -2)}
        </strong>,
      );
    } else if (token.startsWith("`")) {
      nodes.push(
        <code key={key++} className="rounded bg-muted px-1 py-0.5 text-xs text-foreground">
          {token.slice(1, -1)}
        </code>,
      );
    } else {
      const linkMatch = token.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
      if (linkMatch) {
        nodes.push(
          <a
            key={key++}
            href={linkMatch[2]}
            className="text-sky-700 underline-offset-2 hover:underline dark:text-sky-400"
          >
            {linkMatch[1]}
          </a>,
        );
      }
    }
    lastIndex = match.index + token.length;
  }

  if (lastIndex < text.length) {
    nodes.push(text.slice(lastIndex));
  }

  return nodes;
}
