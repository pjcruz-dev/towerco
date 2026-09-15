"use client";

import {
  Download,
  History,
  Loader2,
  MessageSquarePlus,
  MoreHorizontal,
  Pencil,
  Search,
  Trash2,
  XIcon,
} from "lucide-react";
import { useCallback, useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import {
  deleteAssistantConversation,
  downloadAssistantConversationCsv,
  exportAssistantConversationJson,
  fetchAssistantConversations,
  updateAssistantConversation,
  type AssistantConversationListRow,
} from "@/lib/api/modules/assistant-api";
import { getErrorMessage } from "@/lib/api/error";
import { cn } from "@/lib/utils";

type Props = {
  open: boolean;
  activeConversationId: string | null;
  onClose: () => void;
  onSelect: (conversationId: string) => void;
  onNewChat: () => void;
  onArchived?: (conversationId: string) => void;
};

function formatWhen(iso: string | null): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  return date.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
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

export function AssistantHistoryPanel({
  open,
  activeConversationId,
  onClose,
  onSelect,
  onNewChat,
  onArchived,
}: Props) {
  const [rows, setRows] = useState<AssistantConversationListRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [renamingId, setRenamingId] = useState<string | null>(null);
  const [renameValue, setRenameValue] = useState("");
  const [busyId, setBusyId] = useState<string | null>(null);
  const debouncedSearch = useDebouncedValue(search, 350);

  const loadRows = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await fetchAssistantConversations({
        page: 1,
        per_page: 50,
        search: debouncedSearch.trim() || undefined,
      });
      setRows(result.data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch]);

  useEffect(() => {
    if (!open) return;
    void loadRows();
  }, [open, loadRows]);

  const startRename = (row: AssistantConversationListRow) => {
    setRenamingId(row.id);
    setRenameValue(row.title?.trim() || "");
  };

  const cancelRename = () => {
    setRenamingId(null);
    setRenameValue("");
  };

  const saveRename = async (conversationId: string) => {
    const title = renameValue.trim();
    if (!title) return;

    setBusyId(conversationId);
    try {
      const updated = await updateAssistantConversation(conversationId, { title });
      setRows((current) => current.map((row) => (row.id === updated.id ? updated : row)));
      cancelRename();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  };

  const archiveConversation = async (conversationId: string) => {
    if (!window.confirm("Delete this conversation? It will be archived and hidden from your history.")) {
      return;
    }

    setBusyId(conversationId);
    try {
      await deleteAssistantConversation(conversationId);
      setRows((current) => current.filter((row) => row.id !== conversationId));
      onArchived?.(conversationId);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  };

  const exportJson = async (row: AssistantConversationListRow) => {
    setBusyId(row.id);
    try {
      const payload = await exportAssistantConversationJson(row.id);
      const slug = (row.title || "conversation").replace(/[^a-z0-9_-]+/gi, "-").replace(/^-+|-+$/g, "") || "conversation";
      downloadJson(`assistant-${slug}.json`, payload);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  };

  const exportCsv = async (row: AssistantConversationListRow) => {
    setBusyId(row.id);
    try {
      const blob = await downloadAssistantConversationCsv(row.id);
      const slug = (row.title || "conversation").replace(/[^a-z0-9_-]+/gi, "-").replace(/^-+|-+$/g, "") || "conversation";
      downloadBlob(`assistant-${slug}.csv`, blob);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  };

  if (!open) {
    return null;
  }

  return (
    <div className="absolute inset-0 z-10 flex flex-col bg-background">
      <div className="flex items-center justify-between gap-2 border-b border-border px-4 py-2.5">
        <div className="flex items-center gap-2 text-sm font-medium text-foreground">
          <History className="size-4 text-muted-foreground" />
          Conversations
        </div>
        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant="ghost"
            size="xs"
            className="h-7 gap-1 px-2 text-[11px]"
            onClick={() => {
              onNewChat();
              onClose();
            }}
          >
            <MessageSquarePlus className="size-3.5" />
            New chat
          </Button>
          <Button type="button" variant="ghost" size="icon-sm" aria-label="Close history" onClick={onClose}>
            <XIcon className="size-4" />
          </Button>
        </div>
      </div>

      <div className="border-b border-border px-3 py-2">
        <div className="relative">
          <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search conversations…"
            className="h-8 pl-8 text-xs"
            aria-label="Search conversations"
          />
        </div>
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto px-2 py-2">
        {loading ? (
          <div className="flex items-center gap-2 px-2 py-6 text-sm text-muted-foreground">
            <Loader2 className="size-4 animate-spin" />
            Loading history…
          </div>
        ) : error ? (
          <p className="px-2 py-4 text-xs text-destructive" role="alert">
            {error}
          </p>
        ) : rows.length === 0 ? (
          <p className="px-2 py-6 text-sm text-muted-foreground">
            {debouncedSearch.trim() ? "No conversations match your search." : "No saved conversations yet."}
          </p>
        ) : (
          <ul className="space-y-1">
            {rows.map((row) => {
              const active = row.id === activeConversationId;
              const isRenaming = renamingId === row.id;
              const isBusy = busyId === row.id;

              return (
                <li key={row.id}>
                  <div
                    className={cn(
                      "group flex items-start gap-1 rounded-lg px-2 py-1.5 transition-colors",
                      active ? "bg-muted" : "hover:bg-muted/60",
                    )}
                  >
                    <div className="min-w-0 flex-1">
                      {isRenaming ? (
                        <form
                          className="flex items-center gap-1"
                          onSubmit={(e) => {
                            e.preventDefault();
                            void saveRename(row.id);
                          }}
                        >
                          <Input
                            value={renameValue}
                            onChange={(e) => setRenameValue(e.target.value)}
                            className="h-7 text-xs"
                            autoFocus
                            disabled={isBusy}
                          />
                          <Button type="submit" size="xs" disabled={isBusy || !renameValue.trim()}>
                            Save
                          </Button>
                          <Button type="button" size="xs" variant="ghost" onClick={cancelRename} disabled={isBusy}>
                            Cancel
                          </Button>
                        </form>
                      ) : (
                        <button
                          type="button"
                          className="w-full px-1 py-1 text-left"
                          onClick={() => onSelect(row.id)}
                        >
                          <p className="truncate text-sm font-medium text-foreground">
                            {row.title?.trim() || "Untitled chat"}
                          </p>
                          <p className="mt-0.5 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                            <span>{formatWhen(row.last_message_at ?? row.updated_at)}</span>
                            <span aria-hidden>·</span>
                            <span>
                              {row.message_count} message{row.message_count === 1 ? "" : "s"}
                            </span>
                          </p>
                        </button>
                      )}
                    </div>

                    {!isRenaming ? (
                      <DropdownMenu>
                        <DropdownMenuTrigger
                          disabled={isBusy}
                          render={
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon-sm"
                              className="mt-0.5 shrink-0 opacity-0 group-hover:opacity-100 data-popup-open:opacity-100"
                              aria-label="Conversation actions"
                            >
                              {isBusy ? (
                                <Loader2 className="size-3.5 animate-spin" />
                              ) : (
                                <MoreHorizontal className="size-3.5" />
                              )}
                            </Button>
                          }
                        />
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onClick={() => startRename(row)}>
                            <Pencil className="size-3.5" />
                            Rename
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => void exportJson(row)}>
                            <Download className="size-3.5" />
                            Export JSON
                          </DropdownMenuItem>
                          <DropdownMenuItem onClick={() => void exportCsv(row)}>
                            <Download className="size-3.5" />
                            Export CSV
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            destructive
                            onClick={() => void archiveConversation(row.id)}
                          >
                            <Trash2 className="size-3.5" />
                            Delete
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    ) : null}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </div>
  );
}
