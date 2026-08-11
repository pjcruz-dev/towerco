import Link from "next/link";

import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { ProjectOneActionWidget } from "@/modules/project-one/types";

export function ActionableWidgets({ items }: { items: ProjectOneActionWidget[] }) {
  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <h2 className="text-base font-medium text-foreground">Action queue</h2>
      <p className="text-xs text-muted-foreground">
        Prioritized follow-ups to keep approvals and milestones on track.
      </p>

      <div className="mt-4 space-y-2">
        {items.length === 0 ? (
          <p className="text-sm text-muted-foreground">No pending actions.</p>
        ) : (
          items.map((item) => (
            <div key={item.id} className="rounded-lg border p-3">
              <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-medium">{item.label}</p>
                <span
                  className={`rounded px-2 py-0.5 text-[11px] ${
                    item.priority === "high"
                      ? "bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"
                      : "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                  }`}
                >
                  {item.priority === "high" ? "High" : "Normal"}
                </span>
              </div>
              <div className="mt-2 flex items-center justify-between">
                <span className="text-sm text-muted-foreground">{item.count} items</span>
                <Link
                  href={item.href}
                  className={cn(buttonVariants({ size: "sm", variant: "outline" }))}
                >
                  Open
                </Link>
              </div>
            </div>
          ))
        )}
      </div>
    </section>
  );
}
