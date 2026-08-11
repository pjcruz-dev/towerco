"use client";

import Link from "next/link";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import { AcronymLabel } from "@/components/help/acronym-label";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { usePermission } from "@/hooks/use-permission";
import { getErrorMessage } from "@/lib/api/error";
import { resolveProjectApproval } from "@/lib/api/modules/project-one-api";
import { permissions } from "@/lib/rbac/permissions";
import type { ProjectOneApproval } from "@/modules/project-one/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

const pageSize = 8;

function formatSubmittedAt(iso: string): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" });
}

export function PendingApprovalsTable({ approvals }: { approvals: ProjectOneApproval[] }) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const canResolve = usePermission([permissions.projectOneManage]);

  const [query, setQuery] = useState("");
  const [page, setPage] = useState(1);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [active, setActive] = useState<ProjectOneApproval | null>(null);
  const [rejectNotes, setRejectNotes] = useState("");

  const mutation = useMutation({
    mutationFn: ({ id, status, resolution_notes }: { id: string; status: "approved" | "rejected"; resolution_notes?: string }) =>
      resolveProjectApproval(id, { status, resolution_notes }),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "approvals"] });
      setSheetOpen(false);
      setActive(null);
      setRejectNotes("");
      push({
        level: "success",
        title: variables.status === "approved" ? "Approval granted" : "Approval rejected",
        message: "The queue has been updated.",
      });
    },
    onError: (error) => {
      push({
        level: "error",
        title: "Could not update approval",
        message: getErrorMessage(error),
      });
    },
  });

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) return approvals;
    return approvals.filter((item) =>
      `${item.type} ${item.title} ${item.requester}`.toLowerCase().includes(needle),
    );
  }, [approvals, query]);

  const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
  const safePage = Math.min(page, pageCount);
  const currentRows = filtered.slice((safePage - 1) * pageSize, safePage * pageSize);

  function openReview(item: ProjectOneApproval) {
    setActive(item);
    setRejectNotes("");
    setSheetOpen(true);
  }

  return (
    <>
      <section className="rounded-xl border bg-card p-4 shadow-sm">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold">Pending Approvals</h2>
            <p className="text-xs text-muted-foreground">
              Approval workload with <AcronymLabel term="SLA" /> risk and quick triage support.{" "}
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/approvals">
                View all
              </Link>
            </p>
          </div>
          <input
            value={query}
            onChange={(event) => {
              setQuery(event.target.value);
              setPage(1);
            }}
            placeholder="Search approvals"
            className="h-9 w-full rounded-md border bg-background px-3 text-sm sm:w-64"
          />
        </div>

        <div className="overflow-hidden rounded-lg border">
          <div className="max-h-[360px] overflow-auto">
            <table className="w-full min-w-[720px] text-[13px]">
              <thead className="sticky top-0 z-10 bg-muted/60">
                <tr className="text-left">
                  <th className="px-3 py-2 font-medium">Type</th>
                  <th className="px-3 py-2 font-medium">Title</th>
                  <th className="px-3 py-2 font-medium">Requester</th>
                  <th className="px-3 py-2 font-medium">Submitted</th>
                  <th className="px-3 py-2 font-medium">
                    <AcronymLabel term="SLA">SLA Risk</AcronymLabel>
                  </th>
                  <th className="px-3 py-2 font-medium text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                {currentRows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-3 py-8 text-center text-sm text-muted-foreground">
                      No approvals found.
                    </td>
                  </tr>
                ) : (
                  currentRows.map((item) => (
                    <tr key={item.id} className="border-t">
                      <td className="px-3 py-2">{item.type}</td>
                      <td className="px-3 py-2">{item.title}</td>
                      <td className="px-3 py-2 text-muted-foreground">{item.requester}</td>
                      <td className="px-3 py-2 text-muted-foreground">{formatSubmittedAt(item.submittedAt)}</td>
                      <td className="px-3 py-2">
                        <span
                          className={cn(
                            "rounded px-2 py-0.5 text-xs",
                            item.slaRisk === "high"
                              ? "bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300"
                              : item.slaRisk === "medium"
                                ? "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300"
                                : "bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300",
                          )}
                        >
                          {item.slaRisk.toUpperCase()}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-right">
                        {canResolve ? (
                          <Button size="sm" variant="outline" type="button" onClick={() => openReview(item)}>
                            Review
                          </Button>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
          <span>
            Showing {currentRows.length} of {filtered.length}
          </span>
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant="outline"
              disabled={safePage <= 1}
              onClick={() => setPage((prev) => Math.max(1, prev - 1))}
            >
              Previous
            </Button>
            <span>
              Page {safePage} / {pageCount}
            </span>
            <Button
              size="sm"
              variant="outline"
              disabled={safePage >= pageCount}
              onClick={() => setPage((prev) => Math.min(pageCount, prev + 1))}
            >
              Next
            </Button>
          </div>
        </div>
      </section>

      <Sheet
        open={sheetOpen}
        onOpenChange={(open) => {
          setSheetOpen(open);
          if (!open) {
            setActive(null);
            setRejectNotes("");
          }
        }}
      >
        <SheetContent side="right" className="flex w-full flex-col sm:max-w-md">
          <SheetHeader>
            <SheetTitle>Review approval</SheetTitle>
            <SheetDescription>
              {active ? (
                <>
                  <span className="font-medium text-foreground">{active.title}</span>
                  <span className="block text-xs text-muted-foreground">
                    {active.type} · {active.requester}
                  </span>
                </>
              ) : null}
            </SheetDescription>
          </SheetHeader>

          <div className="flex flex-1 flex-col gap-4 px-4">
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-muted-foreground" htmlFor="reject-notes">
                Notes (optional, shown on reject)
              </label>
              <textarea
                id="reject-notes"
                value={rejectNotes}
                onChange={(e) => setRejectNotes(e.target.value)}
                rows={4}
                maxLength={2000}
                placeholder="Reason or context for the decision…"
                className="w-full resize-y rounded-lg border border-input bg-transparent px-2.5 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
              />
            </div>
          </div>

          <SheetFooter className="flex-row flex-wrap gap-2 border-t border-border pt-4 sm:justify-end">
            <Button
              type="button"
              variant="outline"
              disabled={mutation.isPending || !active}
              onClick={() => setSheetOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={mutation.isPending || !active}
              onClick={() => {
                if (!active) return;
                mutation.mutate({
                  id: active.id,
                  status: "rejected",
                  resolution_notes: rejectNotes.trim() || undefined,
                });
              }}
            >
              Reject
            </Button>
            <Button
              type="button"
              disabled={mutation.isPending || !active}
              onClick={() => {
                if (!active) return;
                mutation.mutate({
                  id: active.id,
                  status: "approved",
                  resolution_notes: rejectNotes.trim() || undefined,
                });
              }}
            >
              Approve
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </>
  );
}
