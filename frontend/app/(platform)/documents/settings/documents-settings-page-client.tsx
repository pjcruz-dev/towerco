"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  fetchDocumentBinderTemplate,
  resetDocumentBinderTemplate,
  updateDocumentBinderTemplate,
  type BinderTemplateNode,
} from "@/lib/api/modules/documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

function cloneTree(nodes: BinderTemplateNode[]): BinderTemplateNode[] {
  return nodes.map((node) => ({
    ...node,
    children: node.children ? cloneTree(node.children) : undefined,
  }));
}

function updateNodeLabel(
  nodes: BinderTemplateNode[],
  key: string,
  label: string,
): BinderTemplateNode[] {
  return nodes.map((node) => {
    if (node.key === key) {
      return { ...node, label };
    }
    if (node.children?.length) {
      return { ...node, children: updateNodeLabel(node.children, key, label) };
    }
    return node;
  });
}

function TemplateEditor({
  nodes,
  depth,
  onLabelChange,
}: {
  nodes: BinderTemplateNode[];
  depth: number;
  onLabelChange: (key: string, label: string) => void;
}) {
  return nodes.map((node) => (
    <div key={node.key}>
      <div
        className="grid gap-2 py-2 sm:grid-cols-[160px_1fr_120px]"
        style={{ paddingLeft: `${depth * 12}px` }}
      >
        <span className="font-mono text-xs text-muted-foreground">{node.key}</span>
        <Input
          className="h-8 text-sm"
          value={node.label}
          onChange={(e) => onLabelChange(node.key, e.target.value)}
        />
        <span className="text-xs text-muted-foreground">{node.type}</span>
      </div>
      {node.children?.length ? (
        <TemplateEditor nodes={node.children} depth={depth + 1} onLabelChange={onLabelChange} />
      ) : null}
    </div>
  ));
}

export function DocumentsSettingsPageClient() {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const query = useQuery({
    queryKey: ["documents", "binder-template"],
    queryFn: fetchDocumentBinderTemplate,
  });

  const [draft, setDraft] = useState<BinderTemplateNode[] | null>(null);
  const tree = draft ?? query.data?.tree ?? [];

  const dirty = useMemo(() => {
    if (!draft || !query.data?.tree) return false;
    return JSON.stringify(draft) !== JSON.stringify(query.data.tree);
  }, [draft, query.data?.tree]);

  const saveMutation = useMutation({
    mutationFn: () => updateDocumentBinderTemplate(tree),
    onSuccess: (data) => {
      setDraft(null);
      queryClient.setQueryData(["documents", "binder-template"], data);
      push({ level: "success", title: "Binder template saved" });
    },
    onError: (e) =>
      push({ level: "error", title: "Could not save template", message: getErrorMessage(e) }),
  });

  const resetMutation = useMutation({
    mutationFn: resetDocumentBinderTemplate,
    onSuccess: (data) => {
      setDraft(null);
      queryClient.setQueryData(["documents", "binder-template"], data);
      push({ level: "success", title: "Template reset to platform default" });
    },
    onError: (e) =>
      push({ level: "error", title: "Could not reset template", message: getErrorMessage(e) }),
  });

  return (
    <PermissionGate requiredPermissions={[permissions.documentsTemplateManage]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p className="text-sm">
              <Link className="text-primary hover:underline" href="/documents">
                Documents
              </Link>
            </p>
            <h1 className="mt-2 text-2xl font-semibold tracking-tight text-foreground">
              Binder template
            </h1>
            <p className="mt-1 text-sm text-muted-foreground">
              Folder labels for new site binders. Existing sites keep their current folders.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={resetMutation.isPending || query.data?.source === "platform_default"}
              onClick={() => resetMutation.mutate()}
            >
              Reset to default
            </Button>
            <Button
              type="button"
              size="sm"
              disabled={!dirty || saveMutation.isPending}
              onClick={() => saveMutation.mutate()}
            >
              Save template
            </Button>
          </div>
        </header>

        <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
          {query.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading template…</p>
          ) : query.isError ? (
            <p className="text-sm text-destructive">Could not load binder template.</p>
          ) : (
            <>
              <p className="mb-3 text-sm text-muted-foreground">{query.data?.note}</p>
              <p className="mb-3 text-xs text-muted-foreground">
                Source: {query.data?.source === "tenant_custom" ? "Tenant custom" : "Platform default"}
                {query.data?.updated_by?.name
                  ? ` · Updated by ${query.data.updated_by.name}`
                  : ""}
              </p>
              <div className="rounded-lg border border-border bg-muted/20 p-3">
                <div className="mb-2 hidden gap-2 text-xs font-medium text-muted-foreground sm:grid sm:grid-cols-[160px_1fr_120px]">
                  <span>Key</span>
                  <span>Label</span>
                  <span>Type</span>
                </div>
                <TemplateEditor
                  nodes={tree}
                  depth={0}
                  onLabelChange={(key, label) => {
                    setDraft((current) =>
                      updateNodeLabel(cloneTree(current ?? query.data?.tree ?? []), key, label),
                    );
                  }}
                />
              </div>
            </>
          )}
        </section>
      </div>
    </PermissionGate>
  );
}
