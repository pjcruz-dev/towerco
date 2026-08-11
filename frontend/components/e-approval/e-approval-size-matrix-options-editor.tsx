"use client";

import { Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  sizeMatrixRowInput,
  type SizeMatrixRow,
  type SizeMatrixRowInput,
} from "@/modules/e-approval/field-size-matrix";
import { cn } from "@/lib/utils";

type Props = {
  rows: SizeMatrixRow[];
  onChange: (rows: SizeMatrixRow[]) => void;
  disabled?: boolean;
};

function slugifyValue(label: string, fallback: string): string {
  const slug = label
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "_")
    .replace(/^_+|_+$/g, "");

  return slug !== "" ? slug.slice(0, 64) : fallback;
}

function uniqueValue(base: string, used: Set<string>): string {
  if (!used.has(base)) {
    return base;
  }
  let n = 2;
  while (used.has(`${base}_${n}`)) {
    n += 1;
  }

  return `${base}_${n}`;
}

export function EApprovalSizeMatrixOptionsEditor({ rows, onChange, disabled }: Props) {
  const updateRow = (index: number, patch: Partial<SizeMatrixRow>) => {
    const next = [...rows];
    const current = next[index];
    if (!current) {
      return;
    }
    next[index] = { ...current, ...patch };
    onChange(next);
  };

  const addRow = (input: SizeMatrixRowInput = "size") => {
    const used = new Set(rows.map((r) => r.value));
    const n = rows.length + 1;
    const label = input === "text" ? `Notes ${n}` : `Item ${n}`;
    onChange([
      ...rows,
      {
        value: uniqueValue(slugifyValue(label, `row_${n}`), used),
        label,
        input,
      },
    ]);
  };

  const removeRow = (index: number) => {
    if (rows.length <= 1) {
      return;
    }
    onChange(rows.filter((_, i) => i !== index));
  };

  return (
    <div className="min-w-0 space-y-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Label>Rows</Label>
        <div className="flex flex-wrap gap-1.5">
          <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => addRow("size")}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Size row
          </Button>
          <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={() => addRow("text")}>
            <Plus className="mr-1 h-3.5 w-3.5" />
            Text row
          </Button>
        </div>
      </div>
      <p className="text-[11px] leading-relaxed text-muted-foreground">
        Size rows: W × H + NA. Text rows: free line (Other / Existing Utilities).
      </p>
      <ul className="min-w-0 space-y-2">
        {rows.map((row, index) => {
          const input = sizeMatrixRowInput(row);
          return (
            <li
              key={`size-row-${index}`}
              className="min-w-0 space-y-1.5 rounded-lg border border-border/60 bg-muted/20 p-2"
            >
              <div className="flex min-w-0 items-center gap-1.5">
                <Input
                  disabled={disabled}
                  value={row.label}
                  onChange={(e) => updateRow(index, { label: e.target.value })}
                  placeholder="Row label"
                  className="h-8 min-w-0 flex-1 text-sm"
                />
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  className="h-8 w-8 shrink-0 text-destructive"
                  disabled={disabled || rows.length <= 1}
                  onClick={() => removeRow(index)}
                  aria-label="Remove row"
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
              <div className="flex min-w-0 gap-1">
                {(
                  [
                    { value: "size", label: "Size (W × H)" },
                    { value: "text", label: "Text line" },
                  ] as const
                ).map((option) => (
                  <button
                    key={option.value}
                    type="button"
                    disabled={disabled}
                    onClick={() => updateRow(index, { input: option.value })}
                    className={cn(
                      "h-7 flex-1 rounded-md border px-2 text-[11px] font-medium transition-colors",
                      input === option.value
                        ? "border-foreground/20 bg-background text-foreground shadow-sm"
                        : "border-transparent bg-transparent text-muted-foreground hover:bg-background/60",
                      disabled && "opacity-60",
                    )}
                  >
                    {option.label}
                  </button>
                ))}
              </div>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
