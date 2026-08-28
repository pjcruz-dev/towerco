"use client";

import Link from "next/link";
import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Paperclip } from "lucide-react";

import { TicketingPriorityBadge, TicketingStatusBadge } from "@/components/ticketing/ticketing-badges";
import { TicketingSlaBadge } from "@/components/ticketing/ticketing-sla-badge";
import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { formatFileSize, formatTicketingDate, ticketingCategoryLabel } from "@/components/ticketing/ticketing-utils";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { WorkspaceEntityActivityPanel } from "@/components/governance/workspace-entity-activity-panel";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import {
  addTicketingComment,
  downloadTicketingAttachment,
  fetchTicketingAssignableUsers,
  fetchTicketingMetadata,
  fetchTicketingTicket,
  updateTicketingTicket,
  uploadTicketingAttachment,
} from "@/lib/api/modules/ticketing-api";
import { ticketingLinkHref } from "@/lib/ticketing/link-href";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

type Props = {
  ticketId: string;
};

export function TicketingTicketDetailPageClient({ ticketId }: Props) {
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const user = useAuthStore((s) => s.user);
  const canManage = user?.permissions.includes(permissions.ticketingTicketsManage) ?? false;

  const [comment, setComment] = useState("");
  const [isInternalComment, setIsInternalComment] = useState(false);
  const [status, setStatus] = useState("");
  const [priority, setPriority] = useState("");
  const [category, setCategory] = useState("");
  const [assigneeId, setAssigneeId] = useState("");
  const [resolutionComment, setResolutionComment] = useState("");
  const [manageError, setManageError] = useState<string | null>(null);

  const ticketQuery = useQuery({
    queryKey: ["ticketing", "ticket", ticketId],
    queryFn: () => fetchTicketingTicket(ticketId),
    enabled: Boolean(ticketId),
  });

  const { data: metadata } = useQuery({
    queryKey: ["ticketing", "metadata"],
    queryFn: fetchTicketingMetadata,
    staleTime: 300_000,
  });

  const { data: assignableUsers } = useQuery({
    queryKey: ["ticketing", "assignable-users"],
    queryFn: fetchTicketingAssignableUsers,
    enabled: canManage,
  });

  const ticket = ticketQuery.data;
  const showSkeleton = ticketQuery.isLoading;

  const updateMutation = useMutation({
    mutationFn: (payload: {
      status?: string;
      priority?: string;
      category?: string | null;
      assignee_id?: string | null;
      resolution_comment?: string;
    }) => updateTicketingTicket(ticketId, payload),
    onSuccess: () => {
      setResolutionComment("");
      setManageError(null);
      queryClient.invalidateQueries({ queryKey: ["ticketing", "ticket", ticketId] });
      queryClient.invalidateQueries({ queryKey: ["ticketing", "dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["ticketing", "tickets"] });
    },
    onError: () => {
      setManageError("Could not update ticket. Check required fields and try again.");
    },
  });

  const commentMutation = useMutation({
    mutationFn: () =>
      addTicketingComment(ticketId, {
        body: comment.trim(),
        is_internal: canManage && isInternalComment,
      }),
    onSuccess: () => {
      setComment("");
      setIsInternalComment(false);
      queryClient.invalidateQueries({ queryKey: ["ticketing", "ticket", ticketId] });
    },
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadTicketingAttachment(ticketId, file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["ticketing", "ticket", ticketId] });
    },
  });

  return (
    <PermissionGate requiredPermissions={[permissions.ticketingView]}>
      <div className="space-y-6">
        <LiveProductTourHost />
        <TicketingPageHeader
          eyebrow={
            <>
              <Link href="/ticketing" className="hover:text-primary">
                Ticketing
              </Link>
              {" / "}
              <Link href="/ticketing/tickets" className="hover:text-primary">
                Tickets
              </Link>
              {ticket?.ticket_number ? ` / ${ticket.ticket_number}` : null}
            </>
          }
          title={ticket?.title ?? "Ticket"}
          actions={
            <Button size="sm" variant="outline" type="button" onClick={() => ticketQuery.refetch()}>
              Refresh
            </Button>
          }
        />

        {showSkeleton ? (
          <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
            <SectionCardSkeleton fields={4} />
            <SectionCardSkeleton fields={3} />
          </div>
        ) : null}

        {ticketQuery.isError ? (
          <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
            Could not load ticket.
          </div>
        ) : null}

        {ticket ? (
          <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
            <div className="space-y-6">
              <section
                data-help="tk-detail-header"
                className="rounded-xl border border-border bg-card p-5 shadow-sm"
              >
                <div className="flex flex-wrap items-center gap-2">
                  <TicketingStatusBadge status={ticket.status} />
                  <TicketingPriorityBadge priority={ticket.priority} />
                  <TicketingSlaBadge status={ticket.sla_status} />
                  {ticket.category ? (
                    <span className="rounded-md bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                      {ticketingCategoryLabel(ticket.category, metadata?.category_options)}
                    </span>
                  ) : null}
                </div>
                <div data-help="tk-detail-description">
                  <h2 className="mt-4 text-sm font-medium text-foreground">Description</h2>
                  <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">
                    {ticket.description?.trim() || "No description provided."}
                  </p>
                </div>
              </section>
              <section className="rounded-xl border border-border bg-card p-5 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h2 className="text-sm font-medium text-foreground">Attachments</h2>
                  <button
                    type="button"
                    className={cn(buttonVariants({ variant: "outline", size: "sm" }), "inline-flex")}
                    onClick={() => fileInputRef.current?.click()}
                    disabled={uploadMutation.isPending}
                  >
                    <Paperclip className="mr-1.5 h-4 w-4" aria-hidden />
                    {uploadMutation.isPending ? "Uploading…" : "Upload"}
                  </button>
                  <input
                    ref={fileInputRef}
                    type="file"
                    className="sr-only"
                    accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) uploadMutation.mutate(file);
                      e.target.value = "";
                    }}
                  />
                </div>
                <ul className="mt-3 space-y-2">
                  {ticket.attachments.length === 0 ? (
                    <li className="text-sm text-muted-foreground">No attachments.</li>
                  ) : (
                    ticket.attachments.map((attachment) => (
                      <li
                        key={attachment.id}
                        className="flex items-center justify-between gap-2 rounded-lg border border-border bg-muted/20 px-3 py-2.5 text-sm"
                      >
                        <div className="min-w-0">
                          <p className="truncate font-medium text-foreground">{attachment.file_name}</p>
                          <p className="text-xs text-muted-foreground">{formatFileSize(attachment.size_bytes)}</p>
                        </div>
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          onClick={() => downloadTicketingAttachment(attachment.id, attachment.file_name)}
                        >
                          <Download className="h-4 w-4" aria-hidden />
                        </Button>
                      </li>
                    ))
                  )}
                </ul>
              </section>

              <section
                data-help="tk-detail-activity"
                className="rounded-xl border border-border bg-card p-5 shadow-sm"
              >
                <h2 className="text-sm font-medium text-foreground">Activity</h2>                <ul className="mt-3 space-y-3">
                  {ticket.comments.length === 0 ? (
                    <li className="text-sm text-muted-foreground">No comments yet.</li>
                  ) : (
                    ticket.comments.map((row) => (
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
                    ))
                  )}
                </ul>
                <form
                  className="mt-4 space-y-2"
                  onSubmit={(e) => {
                    e.preventDefault();
                    if (!comment.trim()) return;
                    commentMutation.mutate();
                  }}
                >
                  <Textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    placeholder={canManage ? "Add a public or internal note…" : "Add a comment…"}
                    rows={3}
                  />
                  {canManage ? (
                    <label className="flex items-center gap-2 text-xs text-muted-foreground">
                      <Checkbox
                        checked={isInternalComment}
                        onCheckedChange={(v) => setIsInternalComment(v === true)}
                      />
                      Internal note (visible to IT only)
                    </label>
                  ) : null}
                  <Button type="submit" size="sm" disabled={commentMutation.isPending || !comment.trim()}>
                    {isInternalComment && canManage ? "Post internal note" : "Post comment"}
                  </Button>
                </form>
              </section>

              <WorkspaceEntityActivityPanel
                entityType="ticket"
                entityId={ticket.id}
                title="Audit activity"
              />
            </div>

            <aside className="space-y-4">
              <div className="rounded-xl border border-border bg-card p-4 shadow-sm text-sm">
                <h3 className="text-sm font-medium text-foreground">Details</h3>
                <dl className="mt-3 space-y-3">
                  <div>
                    <dt className="text-xs text-muted-foreground">Ticket</dt>
                    <dd className="mt-0.5 font-mono text-xs text-foreground">{ticket.ticket_number}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">Source</dt>
                    <dd className="mt-0.5 text-foreground">{ticket.source_label ?? ticket.source_module}</dd>
                  </div>
                  {ticket.links.length > 0 ? (
                    <div>
                      <dt className="text-xs text-muted-foreground">Linked records</dt>
                      <dd className="mt-1 space-y-1">
                        {ticket.links.map((link) => {
                          const href = ticketingLinkHref(link);
                          const label = link.link_label ?? `${link.link_module} / ${link.link_type}`;
                          return href ? (
                            <Link key={link.id} href={href} className="block text-primary hover:underline">
                              {label}
                            </Link>
                          ) : (
                            <span key={link.id} className="block text-foreground">
                              {label}
                            </span>
                          );
                        })}
                      </dd>
                    </div>
                  ) : null}
                  <div>
                    <dt className="text-xs text-muted-foreground">Requester</dt>
                    <dd className="mt-0.5 text-foreground">{ticket.requester?.name ?? "—"}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">Assignee</dt>
                    <dd className="mt-0.5 text-foreground">{ticket.assignee?.name ?? "Unassigned"}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">SLA due</dt>
                    <dd className="mt-0.5 text-foreground">
                      {ticket.sla_due_at ? formatTicketingDate(ticket.sla_due_at) : "—"}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">Created</dt>
                    <dd className="mt-0.5 text-foreground">{formatTicketingDate(ticket.created_at)}</dd>
                  </div>
                  <div>
                    <dt className="text-xs text-muted-foreground">Updated</dt>
                    <dd className="mt-0.5 text-foreground">{formatTicketingDate(ticket.updated_at)}</dd>
                  </div>
                </dl>
              </div>

              {ticket.can_reopen ? (
                <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                  <h3 className="text-sm font-medium text-foreground">Reopen ticket</h3>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Send this ticket back to IT for follow-up. Status will return to open.
                  </p>
                  <Button
                    type="button"
                    size="sm"
                    className="mt-3 w-full"
                    disabled={updateMutation.isPending}
                    onClick={() => updateMutation.mutate({ status: "open" })}
                  >
                    Reopen ticket
                  </Button>
                </div>
              ) : null}

              {canManage ? (
                <div
                  data-help="tk-detail-manage"
                  className="rounded-xl border border-border bg-card p-4 shadow-sm space-y-3"
                >
                  <h3 className="text-sm font-medium text-foreground">Manage</h3>                  <div className="space-y-2">
                    <Label htmlFor="status-update">Status</Label>
                    <Select
                      id="status-update"
                      className="h-9"
                      value={status || ticket.status}
                      onChange={(e) => setStatus(e.target.value)}
                    >
                      {(metadata?.statuses ?? []).map((item) => (
                        <option key={item} value={item}>
                          {item.replace(/_/g, " ")}
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="priority-update">Priority</Label>
                    <Select
                      id="priority-update"
                      className="h-9"
                      value={priority || ticket.priority}
                      onChange={(e) => setPriority(e.target.value)}
                    >
                      {(metadata?.priorities ?? []).map((item) => (
                        <option key={item} value={item}>
                          {item.replace(/_/g, " ")}
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="category-update">Category</Label>
                    <Select
                      id="category-update"
                      className="h-9"
                      value={category || ticket.category || ""}
                      onChange={(e) => setCategory(e.target.value)}
                    >
                      <option value="">Uncategorized</option>
                      {(metadata?.category_options ?? []).map((item) => (
                        <option key={item.id} value={item.id}>
                          {item.label}
                        </option>
                      ))}
                    </Select>
                  </div>
                  {(status || ticket.status) === "resolved" ? (
                    <div className="space-y-2">
                      <Label htmlFor="resolution-comment">Resolution comment</Label>
                      <Textarea
                        id="resolution-comment"
                        value={resolutionComment}
                        onChange={(e) => setResolutionComment(e.target.value)}
                        placeholder="Explain how the issue was resolved…"
                        rows={3}
                      />
                    </div>
                  ) : null}
                  <div className="space-y-2">
                    <Label htmlFor="assignee">Assignee</Label>
                    <Select
                      id="assignee"
                      className="h-9"
                      value={assigneeId || ticket.assignee?.id || ""}
                      onChange={(e) => setAssigneeId(e.target.value)}
                    >
                      <option value="">Unassigned</option>
                      {(assignableUsers ?? []).map((u) => (
                        <option key={u.id} value={u.id}>
                          {u.name}
                        </option>
                      ))}
                    </Select>
                  </div>
                  {manageError ? <p className="text-xs text-destructive">{manageError}</p> : null}
                  <Button
                    type="button"
                    size="sm"
                    className="w-full"
                    disabled={updateMutation.isPending}
                    onClick={() => {
                      const nextStatus = status || ticket.status;
                      if (nextStatus === "resolved" && !resolutionComment.trim()) {
                        setManageError("A resolution comment is required when resolving a ticket.");
                        return;
                      }
                      updateMutation.mutate({
                        status: nextStatus,
                        priority: priority || ticket.priority,
                        category: (category || ticket.category || "") || null,
                        assignee_id: (assigneeId || ticket.assignee?.id || "") || null,
                        resolution_comment: nextStatus === "resolved" ? resolutionComment.trim() : undefined,
                      });
                    }}
                  >
                    Save changes
                  </Button>
                </div>
              ) : null}
            </aside>
          </div>
        ) : null}
      </div>
    </PermissionGate>
  );
}
