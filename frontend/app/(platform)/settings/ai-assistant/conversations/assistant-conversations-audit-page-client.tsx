"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { MessageSquareText } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { PaginatedListFooter } from "@/components/registry/paginated-list-footer";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import {
  downloadAssistantConversationCsv,
  exportAssistantConversationJson,
  fetchAssistantConversation,
  fetchAssistantConversations,
  type AssistantConversationDetail,
  type AssistantConversationListRow,
} from "@/lib/api/modules/assistant-api";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

const PER_PAGE = 25;

function formatWhen(iso: string | null): string {
  if (!iso) return "—";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "—";
  return date.toLocaleString();
}

function downloadJson(filename: string, payload: unknown): void {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

function downloadBlob(filename: string, blob: Blob): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

export function AssistantConversationsAuditPageClient() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const debouncedSearch = useDebouncedValue(search, 350, () => setPage(1));

  const listQuery = useQuery({
    queryKey: ["assistant", "conversations-audit", page, debouncedSearch, status],
    queryFn: () =>
      fetchAssistantConversations({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch.trim() || undefined,
        status: status || undefined,
      }),
  });

  const detailQuery = useQuery({
    queryKey: ["assistant", "conversation-detail", selectedId],
    queryFn: () => fetchAssistantConversation(selectedId!),
    enabled: selectedId !== null,
  });

  const rows = listQuery.data?.data ?? [];
  const meta = listQuery.data?.meta;

  const exportRow = async (row: AssistantConversationListRow, format: "json" | "csv") => {
    const slug =
      (row.title || "conversation").replace(/[^a-z0-9_-]+/gi, "-").replace(/^-+|-+$/g, "") || "conversation";
    if (format === "json") {
      const payload = await exportAssistantConversationJson(row.id);
      downloadJson(`assistant-${slug}.json`, payload);
      return;
    }
    const blob = await downloadAssistantConversationCsv(row.id);
    downloadBlob(`assistant-${slug}.csv`, blob);
  };

  return (
    <PermissionGate requiredPermissions={[permissions.aiAssistantConversationsAudit]}>
      <div className="space-y-5 p-4 sm:p-6">
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-muted-foreground">
            <MessageSquareText className="size-5" />
            <Link href="/dashboard" className="text-sm hover:text-foreground">
              Dashboard
            </Link>
          </div>
          <h1 className="text-2xl font-semibold text-foreground">Assistant conversations</h1>
          <p className="text-sm text-muted-foreground">
            Audit tenant assistant chats across users. Search by title, module, or user.
          </p>
        </div>

        <div className="rounded-xl border border-border bg-card shadow-sm">
          <div className="flex flex-wrap items-end gap-3 border-b border-border p-4">
            <div className="min-w-[220px] flex-1">
              <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="assistant-audit-search">
                Search
              </label>
              <Input
                id="assistant-audit-search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Title, user, module…"
              />
            </div>
            <div className="w-full sm:w-40">
              <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="assistant-audit-status">
                Status
              </label>
              <Select
                id="assistant-audit-status"
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
              </Select>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
              <thead className="border-b border-border bg-muted/30 text-xs font-medium text-muted-foreground">
                <tr>
                  <th className="px-4 py-3">Title</th>
                  <th className="px-4 py-3">User</th>
                  <th className="px-4 py-3">Messages</th>
                  <th className="px-4 py-3">Last activity</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {listQuery.isFetching && rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                      Loading conversations…
                    </td>
                  </tr>
                ) : listQuery.isError ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-destructive">
                      Could not load conversations.
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                      No conversations found.
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => (
                    <tr key={row.id} className="border-b border-border/70 last:border-0">
                      <td className="max-w-[240px] truncate px-4 py-3 font-medium text-foreground">
                        {row.title?.trim() || "Untitled chat"}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        <div className="truncate">{row.user_name ?? "—"}</div>
                        {row.user_email ? (
                          <div className="truncate text-xs">{row.user_email}</div>
                        ) : null}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">{row.message_count}</td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {formatWhen(row.last_message_at ?? row.updated_at)}
                      </td>
                      <td className="px-4 py-3">
                        <span
                          className={cn(
                            "inline-flex rounded-full px-2 py-0.5 text-xs font-medium",
                            row.status === "archived"
                              ? "bg-muted text-muted-foreground"
                              : "bg-sky-100 text-sky-900 dark:bg-sky-500/20 dark:text-sky-100",
                          )}
                        >
                          {row.status}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex justify-end gap-1">
                          <Button type="button" size="xs" variant="outline" onClick={() => setSelectedId(row.id)}>
                            View
                          </Button>
                          <Button type="button" size="xs" variant="ghost" onClick={() => void exportRow(row, "json")}>
                            JSON
                          </Button>
                          <Button type="button" size="xs" variant="ghost" onClick={() => void exportRow(row, "csv")}>
                            CSV
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          {meta ? (
            <div className="border-t border-border p-3">
              <PaginatedListFooter
                meta={{ ...meta, current_page: page }}
                onPageChange={setPage}
                isPending={listQuery.isFetching}
              />
            </div>
          ) : null}
        </div>

        <ConversationDetailSheet
          open={selectedId !== null}
          detail={detailQuery.data ?? null}
          loading={detailQuery.isFetching}
          onClose={() => setSelectedId(null)}
        />
      </div>
    </PermissionGate>
  );
}

function ConversationDetailSheet({
  open,
  detail,
  loading,
  onClose,
}: {
  open: boolean;
  detail: AssistantConversationDetail | null;
  loading: boolean;
  onClose: () => void;
}) {
  return (
    <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
      <SheetContent side="right" className="w-full sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>{detail?.title?.trim() || "Conversation"}</SheetTitle>
          <SheetDescription>
            {detail?.module_context ? `Module: ${detail.module_context}` : "Assistant conversation transcript"}
          </SheetDescription>
        </SheetHeader>
        <div className="mt-4 max-h-[calc(100vh-8rem)] space-y-3 overflow-y-auto pr-1">
          {loading ? (
            <p className="text-sm text-muted-foreground">Loading messages…</p>
          ) : detail?.messages?.length ? (
            detail.messages.map((message) => (
              <div
                key={message.id}
                className={cn(
                  "rounded-lg border border-border px-3 py-2 text-sm",
                  message.role === "user" ? "bg-muted/40" : "bg-card",
                )}
              >
                <p className="mb-1 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                  {message.role}
                </p>
                <p className="whitespace-pre-wrap text-foreground">{message.content}</p>
              </div>
            ))
          ) : (
            <p className="text-sm text-muted-foreground">No messages in this conversation.</p>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
