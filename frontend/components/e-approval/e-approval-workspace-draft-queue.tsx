"use client";

import Link from "next/link";
import { Pencil } from "lucide-react";

import { buttonVariants } from "@/components/ui/button";
import type { EApprovalFormWorkspaceDashboard } from "@/modules/e-approval/form-workspace-types";

type Draft = NonNullable<EApprovalFormWorkspaceDashboard["continue_drafts"]>[number];

type Props = {
  items: Draft[];
  listHref: string;
};

export function EApprovalWorkspaceDraftQueue({ items, listHref }: Props) {
  if (items.length === 0) {
    return null;
  }

  return (
    <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h2 className="text-base font-medium text-foreground">Continue drafts</h2>
          <p className="text-sm text-muted-foreground">Unsubmitted requests in this workspace.</p>
        </div>
        <Link href={listHref} className={buttonVariants({ size: "sm", variant: "outline" })}>
          Show in the list
        </Link>
      </div>
      <ul className="mt-3 divide-y divide-border">
        {items.map((item) => (
          <li key={item.id} className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
            <div className="min-w-0">
              <p className="truncate text-sm font-medium text-foreground">{item.document_no}</p>
              <p className="truncate text-xs text-muted-foreground">
                {item.form_name ? `${item.form_name} · ` : ""}
                {item.requestor_name || "Unknown requestor"}
                {item.created_at ? ` · ${new Date(item.created_at).toLocaleString()}` : ""}
              </p>
            </div>
            <Link href={item.href} className={buttonVariants({ size: "sm", variant: item.can_continue ? "default" : "outline" })}>
              {item.can_continue ? <Pencil className="h-3.5 w-3.5" /> : null}
              {item.can_continue ? "Continue" : "Open"}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
