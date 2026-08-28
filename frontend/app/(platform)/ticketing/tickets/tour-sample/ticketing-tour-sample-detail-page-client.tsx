"use client";

import Link from "next/link";
import { Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { Download, Paperclip } from "lucide-react";

import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import { TicketingSlaBadge } from "@/components/ticketing/ticketing-sla-badge";
import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { formatFileSize, formatTicketingDate } from "@/components/ticketing/ticketing-utils";
import {
  TicketingTourSampleNotice,
} from "@/components/help/ticketing-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { usePermission } from "@/hooks/use-permission";
import {
  isTicketingTourActive,
  ticketingTourSampleDetail,
} from "@/lib/help/ticketing-tour-fixtures";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

function TicketingTourSampleDetailInner() {
  const searchParams = useSearchParams();
  const tourActive = isTicketingTourActive(searchParams);
  const canManage = usePermission([permissions.ticketingTicketsManage]);
  const ticket = ticketingTourSampleDetail;

  if (!tourActive) {
    return (
      <PermissionGate requiredPermissions={[permissions.ticketingView]}>
        <div className="space-y-4">
          <TicketingPageHeader title="Sample ticket" description="This page is only available during the Ticketing product tour." />
          <p className="text-sm text-muted-foreground">
            <Link href="/ticketing" className="text-primary hover:underline">
              Back to Ticketing
            </Link>
          </p>
        </div>
      </PermissionGate>
    );
  }

  return (
    <PermissionGate requiredPermissions={[permissions.ticketingView]}>
      <div className="space-y-6">
        <LiveProductTourHost />
        <TicketingTourSampleNotice />
        <TicketingPageHeader
          eyebrow={
            <>
              <Link href={`/ticketing?${searchParams.toString()}`} className="hover:text-primary">
                Ticketing
              </Link>
              {" / "}
              <Link href={`/ticketing/tickets?${searchParams.toString()}`} className="hover:text-primary">
                Tickets
              </Link>
              {` / ${ticket.ticket_number}`}
            </>
          }
          title={ticket.title}
          actions={
            <Button size="sm" variant="outline" type="button" disabled>
              Refresh
            </Button>
          }
        />

        <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
          <div className="space-y-6">
            <section
              data-help="tk-detail-header"
              className="rounded-xl border border-dashed border-sky-300/80 bg-card p-5 shadow-sm dark:border-sky-800"
            >
              <div className="flex flex-wrap items-center gap-2">
                <TicketingStatusBadge status={ticket.status} />
                <TicketingPriorityBadge priority={ticket.priority} />
                <TicketingSlaBadge status={ticket.sla_status} />
                <span className="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">Access</span>
              </div>
              <div data-help="tk-detail-description">
                <h2 className="mt-4 text-sm font-medium text-foreground">Description</h2>
                <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">
                  {ticket.description}
                </p>
              </div>
            </section>

            <section className="rounded-xl border border-dashed border-sky-300/80 bg-card p-5 shadow-sm dark:border-sky-800">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-medium text-foreground">Attachments</h2>
                <span className={cn(buttonVariants({ variant: "outline", size: "sm" }), "inline-flex opacity-60")}>
                  <Paperclip className="mr-1.5 h-4 w-4" aria-hidden />
                  Upload
                </span>
              </div>
              <ul className="mt-3 space-y-2">
                {ticket.attachments.map((attachment) => (
                  <li
                    key={attachment.id}
                    className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted/20 px-3 py-2.5 text-sm"
                  >
                    <div className="min-w-0">
                      <p className="truncate font-medium text-foreground">{attachment.file_name}</p>
                      <p className="text-xs text-muted-foreground">{formatFileSize(attachment.size_bytes)}</p>
                    </div>
                    <Button type="button" size="sm" variant="ghost" disabled>
                      <Download className="h-4 w-4" aria-hidden />
                    </Button>
                  </li>
                ))}
              </ul>
            </section>

            <section
              data-help="tk-detail-activity"
              className="rounded-xl border border-dashed border-sky-300/80 bg-card p-5 shadow-sm dark:border-sky-800"
            >
              <h2 className="text-sm font-medium text-foreground">Activity</h2>
              <ul className="mt-3 space-y-3">
                {ticket.comments
                  .filter((row) => canManage || !row.is_internal)
                  .map((row) => (
                    <li
                      key={row.id}
                      className={`rounded-lg border px-3 py-2.5 ${
                        row.is_internal
                          ? "border-amber-200/80 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20"
                          : "border-border bg-muted/20"
                      }`}
                    >
                      <p className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>
                          {row.author?.name ?? "User"} · {formatTicketingDate(row.created_at)}
                        </span>
                        {row.is_internal ? (
                          <Badge variant="outline" className="h-5 text-[10px]">
                            Internal
                          </Badge>
                        ) : null}
                      </p>
                      <p className="mt-1 whitespace-pre-wrap text-sm text-foreground">{row.body}</p>
                    </li>
                  ))}
              </ul>
              <form className="mt-4 space-y-2" onSubmit={(e) => e.preventDefault()}>
                <Textarea
                  value=""
                  readOnly
                  placeholder={canManage ? "Add a public or internal note…" : "Add a comment…"}
                  rows={3}
                />
                {canManage ? (
                  <label className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Checkbox disabled checked={false} />
                    Internal note (visible to IT only)
                  </label>
                ) : null}
                <Button type="button" size="sm" disabled>
                  Post comment
                </Button>
              </form>
            </section>
          </div>

          <aside className="space-y-4">
            <div className="rounded-xl border border-dashed border-sky-300/80 bg-card p-4 text-sm shadow-sm dark:border-sky-800">
              <h3 className="text-sm font-medium text-foreground">Details</h3>
              <dl className="mt-3 space-y-3">
                <div>
                  <dt className="text-xs text-muted-foreground">Ticket</dt>
                  <dd className="mt-0.5 font-mono text-xs text-foreground">{ticket.ticket_number}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Source</dt>
                  <dd className="mt-0.5 text-foreground">{ticket.source_label}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Requester</dt>
                  <dd className="mt-0.5 text-foreground">{ticket.requester?.name}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Assignee</dt>
                  <dd className="mt-0.5 text-foreground">{ticket.assignee?.name}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">SLA due</dt>
                  <dd className="mt-0.5 text-foreground">{formatTicketingDate(ticket.sla_due_at)}</dd>
                </div>
              </dl>
            </div>

            {canManage ? (
              <div
                data-help="tk-detail-manage"
                className="space-y-3 rounded-xl border border-dashed border-sky-300/80 bg-card p-4 shadow-sm dark:border-sky-800"
              >
                <h3 className="text-sm font-medium text-foreground">Manage</h3>
                <div className="space-y-2">
                  <Label htmlFor="tour-status">Status</Label>
                  <Select id="tour-status" className="h-9" value={ticket.status} disabled>
                    <option value="open">open</option>
                    <option value="in_progress">in progress</option>
                    <option value="resolved">resolved</option>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="tour-priority">Priority</Label>
                  <Select id="tour-priority" className="h-9" value={ticket.priority} disabled>
                    <option value="high">high</option>
                    <option value="normal">normal</option>
                    <option value="urgent">urgent</option>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="tour-assignee">Assignee</Label>
                  <Select id="tour-assignee" className="h-9" value={ticket.assignee?.id ?? ""} disabled>
                    <option value={ticket.assignee?.id ?? ""}>{ticket.assignee?.name}</option>
                  </Select>
                </div>
                <Button type="button" size="sm" className="w-full" disabled>
                  Save changes
                </Button>
              </div>
            ) : null}
          </aside>
        </div>
      </div>
    </PermissionGate>
  );
}

export function TicketingTourSampleDetailPageClient() {
  return (
    <Suspense fallback={null}>
      <TicketingTourSampleDetailInner />
    </Suspense>
  );
}
