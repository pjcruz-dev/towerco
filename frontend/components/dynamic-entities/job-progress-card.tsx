"use client";

import { Button } from "@/components/ui/button";

export function JobProgressCard({
  title,
  label,
  done,
  total,
  onCancel,
}: {
  title: string;
  label: string;
  done: number;
  total: number;
  onCancel: () => void;
}) {
  const percent = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;

  return (
    <div
      className="fixed bottom-24 right-4 z-[80] w-[min(calc(100vw-2rem),22rem)] rounded-xl border border-border bg-card p-4 shadow-sm"
      role="status"
      aria-live="polite"
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-foreground">{title}</p>
          <p className="mt-0.5 text-sm text-muted-foreground">{label}</p>
        </div>
        <p className="shrink-0 text-lg font-medium leading-none tabular-nums text-foreground">{percent}%</p>
      </div>
      <div
        className="mt-3 h-2 overflow-hidden rounded-full bg-muted"
        role="progressbar"
        aria-valuenow={percent}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={title}
      >
        <div
          className="h-full bg-primary transition-[width] duration-150"
          style={{ width: `${percent}%` }}
        />
      </div>
      <div className="mt-3 flex justify-end">
        <Button type="button" variant="outline" size="sm" onClick={onCancel}>
          Cancel
        </Button>
      </div>
    </div>
  );
}
