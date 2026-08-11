"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useMemo, useState } from "react";

import { createAssistantKnowledgeTableColumns } from "@/components/assistant/assistant-knowledge-table-columns";
import { PermissionGate } from "@/components/layout/permission-gate";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  archiveAssistantKnowledge,
  createAssistantKnowledge,
  deleteAssistantKnowledge,
  fetchAssistantKnowledge,
  fetchAssistantKnowledgeIndex,
  publishAssistantKnowledge,
  reindexAssistantKnowledge,
  updateAssistantKnowledge,
  type AssistantKnowledgeRow,
} from "@/lib/api/modules/assistant-api";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type KnowledgeFormState = {
  title: string;
  slug: string;
  module_key: string;
  body: string;
  related_routes: string;
};

const emptyForm = (): KnowledgeFormState => ({
  title: "",
  slug: "",
  module_key: "",
  body: "",
  related_routes: "",
});

function parseRoutes(value: string): string[] {
  return value
    .split(/[\n,]/)
    .map((item) => item.trim())
    .filter(Boolean);
}

export function AssistantKnowledgePageClient() {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<string>("");
  const [sheetOpen, setSheetOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<KnowledgeFormState>(emptyForm());

  const listQuery = useQuery({
    queryKey: ["assistant", "knowledge", page, search, status],
    queryFn: () =>
      fetchAssistantKnowledgeIndex({
        page,
        per_page: 25,
        search: search || undefined,
        status: status || undefined,
      }),
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ["assistant", "knowledge"] });
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        title: form.title.trim(),
        body: form.body.trim(),
        slug: form.slug.trim() || null,
        module_key: form.module_key.trim() || null,
        related_routes: parseRoutes(form.related_routes),
      };
      if (editingId) {
        return updateAssistantKnowledge(editingId, payload);
      }
      return createAssistantKnowledge(payload);
    },
    onSuccess: () => {
      invalidate();
      setSheetOpen(false);
      setEditingId(null);
      setForm(emptyForm());
      notify({
        level: "success",
        title: editingId ? "Article updated" : "Article created",
        message: editingId
          ? "Draft saved. Publish again to re-index for the assistant."
          : "Draft ready. Publish when content is approved.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Could not save article",
        message: getErrorMessage(error),
      }),
  });

  const publishMutation = useMutation({
    mutationFn: (id: string) => publishAssistantKnowledge(id),
    onSuccess: () => {
      invalidate();
      notify({
        level: "success",
        title: "Published",
        message: "Article queued for indexing. Ask TowerOS can use it after ingest completes.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Publish failed",
        message: getErrorMessage(error),
      }),
  });

  const archiveMutation = useMutation({
    mutationFn: (id: string) => archiveAssistantKnowledge(id),
    onSuccess: () => {
      invalidate();
      notify({
        level: "success",
        title: "Archived",
        message: "Article removed from assistant retrieval.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Archive failed",
        message: getErrorMessage(error),
      }),
  });

  const reindexMutation = useMutation({
    mutationFn: (id: string) => reindexAssistantKnowledge(id),
    onSuccess: () => {
      invalidate();
      notify({
        level: "success",
        title: "Re-index queued",
        message: "Chunks will refresh for this article.",
      });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Re-index failed",
        message: getErrorMessage(error),
      }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteAssistantKnowledge(id),
    onSuccess: () => {
      invalidate();
      notify({ level: "success", title: "Deleted", message: "Knowledge article removed." });
    },
    onError: (error) =>
      notify({
        level: "error",
        title: "Delete failed",
        message: getErrorMessage(error),
      }),
  });

  const actionPending =
    saveMutation.isPending ||
    publishMutation.isPending ||
    archiveMutation.isPending ||
    reindexMutation.isPending ||
    deleteMutation.isPending;

  const openCreate = () => {
    setEditingId(null);
    setForm(emptyForm());
    setSheetOpen(true);
  };

  const openEdit = async (row: AssistantKnowledgeRow) => {
    try {
      const detail = await fetchAssistantKnowledge(row.id);
      setEditingId(detail.id);
      setForm({
        title: detail.title,
        slug: detail.slug ?? "",
        module_key: detail.module_key ?? "",
        body: detail.body ?? "",
        related_routes: (detail.related_routes ?? []).join("\n"),
      });
      setSheetOpen(true);
    } catch (error) {
      notify({
        level: "error",
        title: "Could not load article",
        message: getErrorMessage(error),
      });
    }
  };

  const columns = useMemo(
    () =>
      createAssistantKnowledgeTableColumns({
        onEdit: (row) => void openEdit(row),
        onPublish: (row) => publishMutation.mutate(row.id),
        onArchive: (row) => {
          if (window.confirm(`Archive "${row.title}"? It will leave assistant answers.`)) {
            archiveMutation.mutate(row.id);
          }
        },
        onReindex: (row) => reindexMutation.mutate(row.id),
        onDelete: (row) => {
          if (window.confirm(`Delete "${row.title}"? This cannot be undone.`)) {
            deleteMutation.mutate(row.id);
          }
        },
        actionPending,
      }),
    [actionPending, archiveMutation, deleteMutation, publishMutation, reindexMutation],
  );

  const rows = listQuery.data?.data ?? [];
  const meta = listQuery.data?.meta;

  return (
    <PermissionGate requiredPermissions={[permissions.aiAssistantKnowledgeManage]}>
      <div className="space-y-5">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm">
              <Link className="text-primary hover:underline" href="/settings">
                Settings
              </Link>
            </p>
            <h1 className="mt-2 text-2xl font-semibold tracking-tight text-foreground">
              Assistant knowledge
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Tenant SOPs and internal processes used only by Ask TowerOS inside this tenant.
              Publish to index; drafts and archived articles are not retrieved.
            </p>
          </div>
          <Button size="sm" onClick={openCreate}>
            New article
          </Button>
        </header>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <Input
              className="h-9 max-w-xs"
              placeholder="Search title or slug…"
              value={search}
              onChange={(e) => {
                setPage(1);
                setSearch(e.target.value);
              }}
            />
            <Select
              className="h-9 w-[160px]"
              value={status}
              onChange={(e) => {
                setPage(1);
                setStatus(e.target.value);
              }}
            >
              <option value="">All statuses</option>
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </Select>
          </div>

          <RegistryDataTableView
            columns={columns}
            data={rows}
            getRowId={(row) => row.id}
            isLoading={listQuery.isLoading}
            isEmpty={!listQuery.isLoading && rows.length === 0}
            emptyMessage="No tenant knowledge articles yet."
            columnVisibilityStorageKey="toweros.table.columns.ai_assistant.knowledge"
          />

          {meta && meta.last_page > 1 ? (
            <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
              <span>
                Page {meta.current_page} of {meta.last_page} · {meta.total} articles
              </span>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  disabled={page >= meta.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </Button>
              </div>
            </div>
          ) : null}
        </section>

        <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
          <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
            <SheetHeader>
              <SheetTitle>{editingId ? "Edit article" : "New article"}</SheetTitle>
              <SheetDescription>
                Markdown supported. Publishing indexes content for Ask TowerOS in this tenant only.
              </SheetDescription>
            </SheetHeader>

            <div className="mt-6 space-y-4">
              <div className="space-y-1.5">
                <Label htmlFor="knowledge-title">Title</Label>
                <Input
                  id="knowledge-title"
                  value={form.title}
                  onChange={(e) => setForm((prev) => ({ ...prev, title: e.target.value }))}
                  placeholder="Night shift escalation"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="knowledge-slug">Slug (optional)</Label>
                <Input
                  id="knowledge-slug"
                  value={form.slug}
                  onChange={(e) => setForm((prev) => ({ ...prev, slug: e.target.value }))}
                  placeholder="night-shift-escalation"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="knowledge-module">Module key (optional)</Label>
                <Input
                  id="knowledge-module"
                  value={form.module_key}
                  onChange={(e) => setForm((prev) => ({ ...prev, module_key: e.target.value }))}
                  placeholder="ticketing"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="knowledge-routes">Related routes (optional)</Label>
                <Textarea
                  id="knowledge-routes"
                  value={form.related_routes}
                  onChange={(e) => setForm((prev) => ({ ...prev, related_routes: e.target.value }))}
                  placeholder={"/ticketing\n/dashboard"}
                  className="min-h-[72px]"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="knowledge-body">Body</Label>
                <Textarea
                  id="knowledge-body"
                  value={form.body}
                  onChange={(e) => setForm((prev) => ({ ...prev, body: e.target.value }))}
                  placeholder={"## Steps\n1. Describe the process…"}
                  className="min-h-[240px] font-mono text-xs"
                />
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setSheetOpen(false)}
                  disabled={saveMutation.isPending}
                >
                  Cancel
                </Button>
                <Button
                  type="button"
                  disabled={
                    saveMutation.isPending || !form.title.trim() || !form.body.trim()
                  }
                  onClick={() => saveMutation.mutate()}
                >
                  {editingId ? "Save draft" : "Create draft"}
                </Button>
              </div>
            </div>
          </SheetContent>
        </Sheet>
      </div>
    </PermissionGate>
  );
}
