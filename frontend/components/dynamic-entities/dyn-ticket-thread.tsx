"use client";

import { Send } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  createDynRecord,
  fetchDynRecords,
  type DynRecordListRow,
} from "@/lib/api/modules/dynamic-entities-api";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { cn } from "@/lib/utils";

type Props = {
  parentRecordId: string;
  foreignField?: string | null;
  canCreate: boolean;
  onCountChange?: (count: number) => void;
};

type Reply = {
  id: string;
  body: string;
  author: string;
  at: string | null;
  internal: boolean;
  mine: boolean;
};

function cellText(value: unknown): string {
  if (value === null || value === undefined) return "";
  return String(value).trim();
}

function replyFromRow(row: DynRecordListRow, myId: string, myName: string): Reply | null {
  const body = cellText(row.columns.notes) || cellText(row.title);
  if (!body) return null;
  const stored = cellText(row.columns.performed_by);
  const author = (stored && stored !== "Someone" ? stored : "") || row.created_by_name || stored || "Someone";
  const at = cellText(row.columns.activity_at) || row.created_at;
  const type = cellText(row.columns.activity_type) || cellText(row.status);
  return {
    id: row.id,
    body,
    author,
    at: at || null,
    internal: type.toLowerCase() === "internal",
    mine:
      (myId !== "" && row.created_by === myId) ||
      (myName !== "" && author.toLowerCase() === myName.toLowerCase()),
  };
}

function formatWhen(value: string | null): string {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

export function DynTicketThread({
  parentRecordId,
  foreignField,
  canCreate,
  onCountChange,
}: Props) {
  const user = useAuthStore((state) => state.user);
  const canManage = user?.permissions.includes(permissions.ticketingTicketsManage) ?? false;
  const myId = user?.id ?? "";
  const myName = user?.name ?? "";
  const [rows, setRows] = useState<DynRecordListRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState("");
  const [internal, setInternal] = useState(false);
  const [sending, setSending] = useState(false);
  const logRef = useRef<HTMLDivElement>(null);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const page = await fetchDynRecords("helpdesk_ticket_activities", {
        per_page: 100,
        parent_record_id: parentRecordId,
        foreign_field: foreignField || "helpdesk_ticket_id",
        sort: "created_at",
      });
      setRows(page.data);
    } catch (err) {
      setRows([]);
      setError(getErrorMessage(err) || "Unable to load the thread.");
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- reload when the ticket changes
  }, [parentRecordId, foreignField]);

  const replies = useMemo(() => {
    const mapped = rows
      .map((row) => replyFromRow(row, myId, myName))
      .filter((row): row is Reply => row !== null)
      .filter((row) => canManage || !row.internal);
    mapped.sort((a, b) => {
      const left = a.at ? Date.parse(a.at) : 0;
      const right = b.at ? Date.parse(b.at) : 0;
      return left - right;
    });
    return mapped;
  }, [rows, myId, myName, canManage]);

  const onCountChangeRef = useRef(onCountChange);
  onCountChangeRef.current = onCountChange;
  useEffect(() => {
    onCountChangeRef.current?.(replies.length);
  }, [replies.length]);

  useEffect(() => {
    const node = logRef.current;
    if (!node) return;
    node.scrollTop = node.scrollHeight;
  }, [replies.length, loading]);

  async function onSend() {
    const body = draft.trim();
    if (!body || sending || !canCreate) return;
    setSending(true);
    setError(null);
    try {
      await createDynRecord("helpdesk_ticket_activities", {
        title: body.slice(0, 120),
        status: internal && canManage ? "Internal" : "Comment",
        parent_record_id: parentRecordId,
        values: {
          helpdesk_ticket_id: parentRecordId,
          activity_type: internal && canManage ? "Internal" : "Comment",
          notes: body,
          activity_at: new Date().toISOString(),
          ...(myName ? { performed_by: myName } : {}),
        },
      });
      setDraft("");
      setInternal(false);
      await load();
    } catch (err) {
      setError(getErrorMessage(err) || "Could not send that reply.");
    } finally {
      setSending(false);
    }
  }

  return (
    <section className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
      <header className="border-b border-border px-4 py-3">
        <h2 className="text-base font-medium text-foreground">Activity</h2>
        <p className="mt-0.5 text-xs text-muted-foreground">
          Replies between the person who raised this and the person fixing it.
        </p>
      </header>

      <div
        ref={logRef}
        className="flex max-h-[28rem] min-h-48 flex-col gap-3 overflow-y-auto px-4 py-4"
        role="log"
        aria-live="polite"
        aria-relevant="additions"
        aria-label="Ticket replies"
      >
        {loading && replies.length === 0 ? (
          <p className="text-sm text-muted-foreground">Loading replies…</p>
        ) : null}
        {!loading && replies.length === 0 ? (
          <p className="text-sm text-muted-foreground">No replies yet. Write the first message below.</p>
        ) : null}
        {replies.map((reply) => (
          <article
            key={reply.id}
            className={cn("flex max-w-[85%] flex-col gap-1", reply.mine ? "ms-auto items-end" : "me-auto items-start")}
          >
            <p className="flex flex-wrap items-center gap-2 px-1 text-xs text-muted-foreground">
              <span className="font-medium text-foreground">{reply.mine ? "You" : reply.author}</span>
              {reply.internal ? (
                <Badge variant="outline" className="h-5 text-[10px]">
                  Internal
                </Badge>
              ) : null}
              {reply.at ? <time dateTime={reply.at}>{formatWhen(reply.at)}</time> : null}
            </p>
            <div
              className={cn(
                "rounded-xl px-3 py-2 text-sm whitespace-pre-wrap",
                reply.mine ? "bg-primary text-primary-foreground" : "bg-muted text-foreground",
              )}
            >
              {reply.body}
            </div>
          </article>
        ))}
      </div>

      {error ? <p className="px-4 pb-2 text-sm text-destructive">{error}</p> : null}

      {canCreate ? (
        <form
          className="space-y-2 border-t border-border p-4"
          onSubmit={(event) => {
            event.preventDefault();
            void onSend();
          }}
        >
          <Textarea
            value={draft}
            onChange={(event) => setDraft(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                void onSend();
              }
            }}
            rows={3}
            placeholder={canManage ? "Reply, or leave an internal note for the fixer…" : "Reply on this ticket…"}
            aria-label="Reply"
          />
          <div className="flex flex-wrap items-center justify-between gap-2">
            {canManage ? (
              <label className="flex items-center gap-2 text-xs text-muted-foreground">
                <Checkbox
                  checked={internal}
                  onCheckedChange={(value) => setInternal(value === true)}
                />
                Internal note, visible to fixers only
              </label>
            ) : (
              <span className="text-xs text-muted-foreground">Enter to send. Shift+Enter for a new line.</span>
            )}
            <Button type="submit" size="sm" className="gap-1.5" disabled={sending || !draft.trim()}>
              <Send className="size-3.5" />
              {internal && canManage ? "Send note" : "Send"}
            </Button>
          </div>
        </form>
      ) : (
        <p className="border-t border-border px-4 py-3 text-xs text-muted-foreground">
          You can read this thread. Your role cannot post a reply.
        </p>
      )}
    </section>
  );
}
