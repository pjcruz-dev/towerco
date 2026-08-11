"use client";

import Link from "next/link";

import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { WorkspaceAwaitingMeItem } from "@/modules/workspace/types";

const moduleLabels: Record<string, string> = {
  e_approval: "E-Approval",
  project_one: "PROJECT-ONE",
  ticketing: "Ticketing",
  notifications: "Notifications",
};

type Props = {
  total: number;
  items: WorkspaceAwaitingMeItem[];
};

export function AwaitingMeHub({ total, items }: Props) {
  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="text-base font-medium text-foreground">Awaiting you</h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Gate approvals, e-approvals, and tickets assigned to you — one queue across modules.
          </p>
        </div>
        {total > 0 ? (
          <span className="rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-900 dark:bg-amber-950/50 dark:text-amber-100">
            {total} open
          </span>
        ) : null}
      </div>

      <div className="mt-4 space-y-2">
        {items.length === 0 ? (
          <p className="py-4 text-sm text-muted-foreground">Nothing awaiting you right now.</p>
        ) : (
          items.map((item) => (
            <div
              key={item.id}
              className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border p-3"
            >
              <div className="min-w-0">
                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                  {moduleLabels[item.module] ?? item.module}
                </span>
                <p className="mt-1 truncate text-sm font-medium text-foreground">{item.label}</p>
                {item.detail ? (
                  <p className="mt-0.5 truncate text-xs text-muted-foreground">{item.detail}</p>
                ) : null}
              </div>
              <Link
                href={item.href}
                className={cn(buttonVariants({ size: "sm", variant: "outline" }), "shrink-0")}
              >
                Open
              </Link>
            </div>
          ))
        )}
      </div>
    </section>
  );
}
