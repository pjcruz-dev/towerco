"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useRef, useState, useEffect } from "react";
import { CheckCircle2, Circle, Download, FileText, FolderOpen, Link2, Upload } from "lucide-react";

import { DocumentDetailDrawer } from "@/components/documents/document-detail-drawer";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { usePermission } from "@/hooks/use-permission";
import {
  addSiteDocumentLessor,
  fetchPublishedEApprovalForms,
  fetchRolloutProgramOptions,
  fetchSiteDocumentFiles,
  fetchSiteDocumentGateChecklist,
  fetchSiteDocumentWorkspace,
  getDocumentDownloadUrl,
  migrateRolloutLeasePackage,
  requestDocumentApproval,
  updateSiteDocumentMetadata,
  updateSiteDocumentWorkspace,
  uploadSiteDocumentSmart,
  type DocumentFileRow,
  type DocumentSiteNode,
} from "@/lib/api/modules/documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { isTenantModuleEnabled, resolveEnabledModulesForUser } from "@/lib/tenant/enabled-modules";
import { permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";
import { useNotificationStore } from "@/stores/notification-store";

type TreeNode = DocumentSiteNode & { children: TreeNode[] };

function buildTree(nodes: DocumentSiteNode[]): TreeNode[] {
  const byId = new Map(nodes.map((n) => [n.id, { ...n, children: [] as TreeNode[] }]));
  const roots: TreeNode[] = [];
  for (const node of byId.values()) {
    if (node.parent_id && byId.has(node.parent_id)) {
      byId.get(node.parent_id)!.children.push(node);
    } else {
      roots.push(node);
    }
  }
  const sortRec = (list: TreeNode[]) => {
    list.sort((a, b) => a.sort_order - b.sort_order);
    list.forEach((n) => sortRec(n.children));
  };
  sortRec(roots);
  return roots;
}

function isUploadTarget(node: DocumentSiteNode): boolean {
  return !["binder", "folder", "repeatable_container", "repeatable_instance"].includes(node.node_type);
}

function formatRelative(iso: string | null | undefined): string {
  if (!iso) return "—";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  const diff = Date.now() - date.getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 48) return `${hours}h ago`;
  return date.toLocaleDateString();
}

type Props = { siteId: string; siteCode?: string; initialDocumentId?: string | null };

export function SiteDocumentsPanel({ siteId, siteCode, initialDocumentId }: Props) {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
  const [lessorName, setLessorName] = useState("");
  const [lessorContact, setLessorContact] = useState("");
  const [approvalDocId, setApprovalDocId] = useState<string | null>(null);
  const [approvalFormId, setApprovalFormId] = useState("");
  const [detailDocumentId, setDetailDocumentId] = useState<string | null>(null);

  useEffect(() => {
    if (!initialDocumentId) {
      return;
    }

    setDetailDocumentId(initialDocumentId);
    window.setTimeout(() => {
      document.getElementById("site-documents-panel")?.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }, 150);
  }, [initialDocumentId]);

  const canManage = usePermission([permissions.documentsManage]);
  const canUpload = usePermission([permissions.documentsUpload]);
  const canRequestApproval = usePermission([permissions.eApprovalSubmissionsCreate]);
  const canLinkRollout = usePermission([permissions.rolloutView]);
  const user = useAuthStore((s) => s.user);
  const activeTenantId = useAuthStore((s) => s.activeTenantId);
  const enabledModules = resolveEnabledModulesForUser(user, activeTenantId);
  const eApprovalOn = isTenantModuleEnabled(enabledModules, "e_approval");

  const workspaceQuery = useQuery({
    queryKey: ["documents", "workspace", siteId],
    queryFn: () => fetchSiteDocumentWorkspace(siteId),
  });

  const gateQuery = useQuery({
    queryKey: ["documents", "gate-checklist", siteId],
    queryFn: () => fetchSiteDocumentGateChecklist(siteId),
    enabled: canManage,
  });

  const rolloutsQuery = useQuery({
    queryKey: ["documents", "rollout-options", siteId],
    queryFn: () => fetchRolloutProgramOptions(siteId),
    enabled: canManage && canLinkRollout && !!siteId,
  });

  const formsQuery = useQuery({
    queryKey: ["e-approval", "forms", "published"],
    queryFn: fetchPublishedEApprovalForms,
    enabled: eApprovalOn && canRequestApproval && approvalDocId !== null,
  });

  const defaultApprovalFormId = useMemo(() => {
    const forms = formsQuery.data ?? [];
    const siteReview = forms.find((form) => form.name === "Site document review");
    return siteReview?.id ?? forms[0]?.id ?? "";
  }, [formsQuery.data]);

  useEffect(() => {
    if (!approvalDocId || approvalFormId) {
      return;
    }
    if (defaultApprovalFormId) {
      setApprovalFormId(defaultApprovalFormId);
    }
  }, [approvalDocId, approvalFormId, defaultApprovalFormId]);

  const tree = useMemo(
    () => buildTree(workspaceQuery.data?.nodes ?? []),
    [workspaceQuery.data?.nodes],
  );

  const selectedNode = workspaceQuery.data?.nodes.find((n) => n.id === selectedNodeId) ?? null;
  const canUploadSelected = selectedNode ? isUploadTarget(selectedNode) : false;
  const linkedRolloutId = workspaceQuery.data?.workspace.rollout_program_id ?? "";

  const filesQuery = useQuery({
    queryKey: ["documents", "files", siteId, selectedNodeId],
    queryFn: () => fetchSiteDocumentFiles(siteId, selectedNodeId!),
    enabled: !!selectedNodeId && canUploadSelected,
  });

  const uploadMutation = useMutation({
    mutationFn: (file: File) =>
      uploadSiteDocumentSmart(siteId, { site_node_id: selectedNodeId!, file }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["documents", "files", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "workspace", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "gate-checklist", siteId] });
      push({ level: "success", title: "Document uploaded" });
    },
    onError: (e) => push({ level: "error", title: "Upload failed", message: getErrorMessage(e) }),
  });

  const lessorMutation = useMutation({
    mutationFn: () =>
      addSiteDocumentLessor(siteId, {
        lessor_name: lessorName.trim(),
        lessor_contact: lessorContact.trim() || undefined,
      }),
    onSuccess: (data) => {
      setLessorName("");
      setLessorContact("");
      queryClient.invalidateQueries({ queryKey: ["documents", "workspace", siteId] });
      setSelectedNodeId(data.instance.upload_node_id);
      push({ level: "success", title: "Lessor folder created" });
    },
    onError: (e) => push({ level: "error", title: "Could not add lessor", message: getErrorMessage(e) }),
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: string; status: string }) =>
      updateSiteDocumentMetadata(id, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["documents", "files", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "workspace", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "gate-checklist", siteId] });
    },
  });

  const rolloutMutation = useMutation({
    mutationFn: (rolloutProgramId: string | null) =>
      updateSiteDocumentWorkspace(siteId, { rollout_program_id: rolloutProgramId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["documents", "workspace", siteId] });
      push({ level: "success", title: "Rollout link updated" });
    },
    onError: (e) =>
      push({ level: "error", title: "Could not link rollout", message: getErrorMessage(e) }),
  });

  const leaseMigrateMutation = useMutation({
    mutationFn: () => migrateRolloutLeasePackage(linkedRolloutId),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["documents", "files", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "workspace", siteId] });
      queryClient.invalidateQueries({ queryKey: ["documents", "gate-checklist", siteId] });
      if (data.migrated > 0) {
        push({
          level: "success",
          title: "Lease package migrated",
          message: `${data.migrated} file(s) copied to binder`,
        });
        return;
      }

      push({
        level: "warning",
        title: "No lease files copied",
        message:
          data.skipped > 0
            ? `${data.skipped} file(s) were already in the binder.`
            : "Select a rollout candidate with lease documents first, then import again.",
      });
    },
    onError: (e) =>
      push({ level: "error", title: "Migration failed", message: getErrorMessage(e) }),
  });

  const approvalMutation = useMutation({
    mutationFn: ({ documentId, formId }: { documentId: string; formId: string }) =>
      requestDocumentApproval(documentId, { form_id: formId }),
    onSuccess: (data) => {
      setApprovalDocId(null);
      setApprovalFormId("");
      queryClient.invalidateQueries({ queryKey: ["documents", "files", siteId] });
      push({
        level: "success",
        title: "Approval requested",
        message: data.submission.document_no,
      });
    },
    onError: (e) =>
      push({ level: "error", title: "Approval request failed", message: getErrorMessage(e) }),
  });

  const renderTree = (nodes: TreeNode[], depth = 0) =>
    nodes.map((node) => (
      <div key={node.id}>
        <button
          type="button"
          className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-muted ${
            selectedNodeId === node.id ? "bg-muted font-medium" : ""
          }`}
          style={{ paddingLeft: `${8 + depth * 12}px` }}
          onClick={() => setSelectedNodeId(node.id)}
        >
          <FolderOpen className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
          <span className="truncate">{node.label}</span>
          {node.document_count > 0 ? (
            <span className="ml-auto text-xs text-muted-foreground">{node.document_count}</span>
          ) : null}
        </button>
        {node.children.length > 0 ? renderTree(node.children, depth + 1) : null}
      </div>
    ));

  const lastActivity = workspaceQuery.data?.last_activity;
  const gateSummary = gateQuery.data?.summary;

  return (
    <section id="site-documents-panel" className="rounded-xl border border-border bg-card shadow-sm">
      <div className="border-b border-border px-4 py-3">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="text-base font-medium text-foreground">Site binder</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {siteCode ? `${siteCode} — ` : ""}
              eSite Binder &amp; Engineering folders
            </p>
          </div>
          {canManage && canLinkRollout ? (
            <div className="flex flex-wrap items-end gap-2">
              <div className="min-w-[200px]">
                <Label className="text-xs text-muted-foreground">Linked rollout</Label>
                <Select
                  className="mt-1 h-8 text-xs"
                  value={linkedRolloutId}
                  onChange={(e) => {
                    const value = e.target.value;
                    rolloutMutation.mutate(value === "" ? null : value);
                  }}
                  disabled={rolloutMutation.isPending || rolloutsQuery.isLoading}
                >
                  <option value="">None</option>
                  {(rolloutsQuery.data ?? []).map((r) => (
                    <option key={r.id} value={r.id}>
                      {r.rollout_ref} ({r.status})
                    </option>
                  ))}
                </Select>
              </div>
              {linkedRolloutId ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={leaseMigrateMutation.isPending}
                  onClick={() => leaseMigrateMutation.mutate()}
                >
                  Import lease package
                </Button>
              ) : null}
            </div>
          ) : null}
        </div>
        {linkedRolloutId ? (
          <p className="mt-2 text-xs text-muted-foreground">
            Import copies SAQ lease documents into this binder (Documents / COL). Candidate photos and
            other gate uploads stay on the rollout and do not complete the gate checklist below.
          </p>
        ) : null}
        {lastActivity ? (
          <p className="mt-2 text-xs text-muted-foreground">
            Last activity: {lastActivity.title} · {formatRelative(lastActivity.at)}
            {lastActivity.by ? ` · ${lastActivity.by.name}` : ""}
          </p>
        ) : null}
        {canManage && gateSummary ? (
          <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
            <Link2 className="h-3.5 w-3.5" />
            <span>
              Gate checklist: {gateSummary.met}/{gateSummary.required} required folders with final
              documents
            </span>
            {gateQuery.data?.items.map((item) => (
              <span
                key={item.node_key}
                className="inline-flex items-center gap-1 rounded-md border border-border px-2 py-0.5"
              >
                {item.met ? (
                  <CheckCircle2 className="h-3 w-3 text-success" />
                ) : (
                  <Circle className="h-3 w-3" />
                )}
                {item.label}
              </span>
            ))}
          </div>
        ) : null}
      </div>

      <div className="grid gap-0 lg:grid-cols-[240px_1fr]">
        <div className="border-b border-border p-3 lg:border-b-0 lg:border-r">
          <p className="mb-2 text-xs font-medium text-muted-foreground">Binders</p>
          {workspaceQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : (
            renderTree(tree)
          )}
        </div>

        <div className="p-4">
          {selectedNode ? (
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
              <p className="text-sm font-medium">{selectedNode.label}</p>
              {canUploadSelected && canUpload ? (
                <div className="flex gap-2">
                  <input
                    ref={fileInputRef}
                    type="file"
                    className="sr-only"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) uploadMutation.mutate(file);
                      e.target.value = "";
                    }}
                  />
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={uploadMutation.isPending}
                    onClick={() => fileInputRef.current?.click()}
                  >
                    <Upload className="mr-1.5 h-3.5 w-3.5" />
                    Upload
                  </Button>
                </div>
              ) : null}
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">Select a folder to view or upload files.</p>
          )}

          {selectedNode?.node_key === "lessors" ? (
            <div className="mb-4 rounded-lg border border-border bg-muted/30 p-3">
              <p className="text-sm font-medium">Add lessor</p>
              <div className="mt-2 grid gap-2 sm:grid-cols-2">
                <div>
                  <Label className="text-xs">Lessor name</Label>
                  <Input value={lessorName} onChange={(e) => setLessorName(e.target.value)} />
                </div>
                <div>
                  <Label className="text-xs">Contact (optional)</Label>
                  <Input value={lessorContact} onChange={(e) => setLessorContact(e.target.value)} />
                </div>
              </div>
              <Button
                type="button"
                size="sm"
                className="mt-2"
                disabled={!lessorName.trim() || lessorMutation.isPending}
                onClick={() => lessorMutation.mutate()}
              >
                Add lessor
              </Button>
            </div>
          ) : null}

          {canUploadSelected ? (
            <div className="overflow-x-auto">
              <table className="w-full text-[13px]">
                <thead>
                  <tr className="border-b border-border text-left text-xs text-muted-foreground">
                    <th className="py-2 pr-2 font-medium">Name</th>
                    <th className="py-2 pr-2 font-medium">Status</th>
                    <th className="py-2 pr-2 font-medium">Approval</th>
                    <th className="py-2 pr-2 font-medium">Expires</th>
                    <th className="py-2 pr-2 font-medium">Updated</th>
                    <th className="py-2 font-medium">Last touch</th>
                  </tr>
                </thead>
                <tbody>
                  {(filesQuery.data ?? []).map((file) => (
                    <DocumentFileTableRow
                      key={file.id}
                      file={file}
                      canUpload={canUpload}
                      canRequestApproval={eApprovalOn && canRequestApproval}
                      onStatusChange={(status) => statusMutation.mutate({ id: file.id, status })}
                      onRequestApproval={() => {
                        setApprovalDocId(file.id);
                        setApprovalFormId("");
                      }}
                      onOpenDetail={() => setDetailDocumentId(file.id)}
                      onDownload={async () => {
                        try {
                          const url = await getDocumentDownloadUrl(file.id);
                          window.open(url, "_blank", "noopener,noreferrer");
                        } catch (e) {
                          push({
                            level: "error",
                            title: "Download failed",
                            message: getErrorMessage(e),
                          });
                        }
                      }}
                    />
                  ))}
                </tbody>
              </table>
              {filesQuery.isLoading ? (
                <p className="py-4 text-sm text-muted-foreground">Loading files…</p>
              ) : null}
              {!filesQuery.isLoading && (filesQuery.data?.length ?? 0) === 0 ? (
                <p className="py-4 text-sm text-muted-foreground">No files in this folder yet.</p>
              ) : null}
            </div>
          ) : null}
        </div>
      </div>

      {approvalDocId ? (
        <div className="border-t border-border bg-muted/20 px-4 py-3">
          <p className="text-sm font-medium">Request E-Approval</p>
          <p className="mt-1 text-xs text-muted-foreground">
            Document fields are auto-filled when the form has matching field names (e.g. document_title,
            site_code).
          </p>
          <div className="mt-2 flex flex-wrap items-end gap-2">
            <div className="min-w-[220px] flex-1">
              <Label className="text-xs">Approval form</Label>
              <Select
                className="mt-1 h-9 text-sm"
                value={approvalFormId}
                onChange={(e) => setApprovalFormId(e.target.value)}
              >
                <option value="">Select form…</option>
                {(formsQuery.data ?? []).map((form) => (
                  <option key={form.id} value={form.id}>
                    {form.name}
                  </option>
                ))}
              </Select>
            </div>
            <Button
              type="button"
              size="sm"
              disabled={!approvalFormId || approvalMutation.isPending}
              onClick={() =>
                approvalMutation.mutate({ documentId: approvalDocId, formId: approvalFormId })
              }
            >
              Submit for approval
            </Button>
            <Button type="button" size="sm" variant="ghost" onClick={() => setApprovalDocId(null)}>
              Cancel
            </Button>
          </div>
        </div>
      ) : null}

      <DocumentDetailDrawer
        documentId={detailDocumentId}
        open={detailDocumentId !== null}
        onOpenChange={(open) => {
          if (!open) setDetailDocumentId(null);
        }}
        siteId={siteId}
        canUpload={canUpload}
        canRequestApproval={eApprovalOn && canRequestApproval}
        onRequestApproval={(id) => {
          setDetailDocumentId(null);
          setApprovalDocId(id);
          setApprovalFormId("");
        }}
      />
    </section>
  );
}

function DocumentFileTableRow({
  file,
  canUpload,
  canRequestApproval,
  onStatusChange,
  onRequestApproval,
  onOpenDetail,
  onDownload,
}: {
  file: DocumentFileRow;
  canUpload: boolean;
  canRequestApproval: boolean;
  onStatusChange: (status: string) => void;
  onRequestApproval: () => void;
  onOpenDetail: () => void;
  onDownload: () => void;
}) {
  const approvalLabel = file.e_approval_submission?.document_no ?? file.approval_status ?? "none";
  const showRequest =
    canRequestApproval &&
    file.approval_status !== "pending" &&
    file.approval_status !== "approved";

  return (
    <tr className="border-b border-border/60">
      <td className="py-2 pr-2">
        <div className="flex items-center gap-1">
          <button
            type="button"
            className="inline-flex min-w-0 flex-1 items-center gap-1.5 text-left hover:text-primary"
            onClick={onOpenDetail}
          >
            <FileText className="h-3.5 w-3.5 shrink-0" />
            <span className="truncate">{file.title}</span>
            <span className="text-xs text-muted-foreground">v{file.version}</span>
          </button>
          <button
            type="button"
            className="shrink-0 rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
            title="Download"
            onClick={onDownload}
          >
            <Download className="h-3.5 w-3.5" />
          </button>
        </div>
      </td>
      <td className="py-2 pr-2">
        {canUpload ? (
          <Select
            className="h-8 text-xs"
            value={file.status}
            onChange={(e) => onStatusChange(e.target.value)}
          >
            <option value="draft">Draft</option>
            <option value="final">Final</option>
            <option value="superseded">Superseded</option>
          </Select>
        ) : (
          <span className="text-muted-foreground">{file.status}</span>
        )}
      </td>
      <td className="py-2 pr-2">
        <div className="flex flex-col gap-1">
          {file.e_approval_submission ? (
            <Link
              href={file.e_approval_submission.href}
              className="text-xs text-primary hover:underline"
            >
              {approvalLabel}
            </Link>
          ) : (
            <span className="text-xs text-muted-foreground">{approvalLabel}</span>
          )}
          {showRequest ? (
            <button
              type="button"
              className="text-left text-xs text-primary hover:underline"
              onClick={onRequestApproval}
            >
              Request approval
            </button>
          ) : null}
        </div>
      </td>
      <td className="py-2 pr-2 text-muted-foreground">
        {file.expires_at ? new Date(file.expires_at).toLocaleDateString() : "—"}
      </td>
      <td className="py-2 pr-2 text-muted-foreground">{formatRelative(file.last_touched_at)}</td>
      <td className="py-2 text-muted-foreground">{file.last_touched_by?.name ?? "—"}</td>
    </tr>
  );
}
