"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  Background,
  Controls,
  MarkerType,
  MiniMap,
  ReactFlow,
  ReactFlowProvider,
  addEdge,
  useEdgesState,
  useNodesState,
  useReactFlow,
  type Connection,
  type Edge,
  type Node,
} from "@xyflow/react";
import "@xyflow/react/dist/style.css";
import { Download, Maximize2, RefreshCw, Trash2 } from "lucide-react";

import {
  DynEntityNode,
  type DynEntityFlowNode,
  type DynEntityNodeData,
} from "@/components/dynamic-entities/dyn-relationship-entity-node";
import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import {
  createDynRelationshipEdge,
  deleteDynRelationshipEdge,
  fetchDynRelationshipGraph,
  saveDynRelationshipGraphLayout,
  updateDynRelationshipEdge,
  type DynRelationshipGraphEdge,
  type DynRelationshipGraphNode,
} from "@/lib/api/modules/dynamic-entities-api";
import { getApiFieldErrors, getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

function downloadBlob(filename: string, blob: Blob) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

async function captureStudioCanvas(el: HTMLElement): Promise<HTMLCanvasElement> {
  const { default: html2canvas } = await import("html2canvas-pro");
  return html2canvas(el, {
    backgroundColor:
      getComputedStyle(document.documentElement).getPropertyValue("--background").trim() ||
      "#ffffff",
    scale: 2,
    useCORS: true,
    logging: false,
    ignoreElements: (node) => {
      if (!(node instanceof HTMLElement)) return false;
      return (
        node.classList.contains("react-flow__controls") ||
        node.classList.contains("react-flow__minimap") ||
        node.classList.contains("react-flow__panel")
      );
    },
  });
}

const nodeTypes = { dynEntity: DynEntityNode };

const PACK_OPTIONS = [
  { value: "all", label: "All packs" },
  { value: "pm", label: "PM / Sites" },
  { value: "procurement", label: "Procurement" },
  { value: "finance", label: "Finance" },
  { value: "ticketing", label: "Ticketing" },
  { value: "reference", label: "Reference" },
  { value: "system", label: "System" },
];

function gridPosition(index: number): { x: number; y: number } {
  const cols = 4;
  const col = index % cols;
  const row = Math.floor(index / cols);
  return { x: col * 280 + 40, y: row * 220 + 40 };
}

function toFlowNodes(
  nodes: DynRelationshipGraphNode[],
  layout: Record<string, { x: number; y: number }>,
  handlers: {
    onOpenFields: (slug: string) => void;
    onOpenRecords: (slug: string) => void;
  },
): DynEntityFlowNode[] {
  return nodes.map((node, index) => {
    const saved = layout[node.id];
    const position = saved ?? gridPosition(index);
    return {
      id: node.id,
      type: "dynEntity",
      position,
      data: {
        slug: node.slug,
        name: node.name,
        module_pack: node.module_pack,
        fields: node.fields,
        onOpenFields: handlers.onOpenFields,
        onOpenRecords: handlers.onOpenRecords,
      } satisfies DynEntityNodeData,
    };
  });
}

function toFlowEdges(edges: DynRelationshipGraphEdge[]): Edge[] {
  return edges.map((edge) => ({
    id: edge.id,
    source: edge.source_entity_id,
    target: edge.target_entity_id,
    label: edge.field_label || edge.field_name || undefined,
    type: "smoothstep",
    animated: edge.kind === "related_tab",
    markerEnd: { type: MarkerType.ArrowClosed, width: 16, height: 16 },
    style: { stroke: "var(--border)", strokeWidth: 1.5 },
    labelStyle: { fontSize: 10, fill: "var(--muted-foreground)" },
    labelBgStyle: { fill: "var(--card)" },
    labelBgPadding: [4, 2] as [number, number],
    data: edge,
  }));
}

function RelationshipStudioCanvas({ modulePack }: { modulePack: string }) {
  const router = useRouter();
  const notify = useNotificationStore((s) => s.push);
  const { fitView, getViewport, setViewport } = useReactFlow();
  const [nodes, setNodes, onNodesChange] = useNodesState<DynEntityFlowNode>([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState<Edge>([]);
  const [loading, setLoading] = useState(true);
  const [savingEdge, setSavingEdge] = useState(false);
  const [selectedEdgeId, setSelectedEdgeId] = useState<string | null>(null);
  const [draftLabel, setDraftLabel] = useState("");
  const [draftTabLabel, setDraftTabLabel] = useState("");
  const [syncRelatedTab, setSyncRelatedTab] = useState(true);
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const graphEdgesRef = useRef<DynRelationshipGraphEdge[]>([]);
  const graphNodesRef = useRef<DynRelationshipGraphNode[]>([]);
  const canvasHostRef = useRef<HTMLDivElement | null>(null);
  const [exporting, setExporting] = useState(false);

  const openFields = useCallback(
    (slug: string) => {
      router.push(`/dynamic-entities/fields?entity=${encodeURIComponent(slug)}`);
    },
    [router],
  );

  const openRecords = useCallback(
    (slug: string) => {
      router.push(`/dynamic-entities/${encodeURIComponent(slug)}`);
    },
    [router],
  );

  const persistLayout = useCallback(
    (nextNodes: Node[], viewport?: { x: number; y: number; zoom: number }) => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
      saveTimer.current = setTimeout(() => {
        const positions: Record<string, { x: number; y: number }> = {};
        for (const node of nextNodes) {
          positions[node.id] = { x: node.position.x, y: node.position.y };
        }
        void saveDynRelationshipGraphLayout({
          positions,
          viewport: viewport ?? getViewport(),
        }).catch(() => {
          // Layout is convenience.
        });
      }, 400);
    },
    [getViewport],
  );

  const loadGraph = useCallback(async () => {
    setLoading(true);
    try {
      const graph = await fetchDynRelationshipGraph({
        module_pack: modulePack === "all" ? undefined : modulePack,
      });
      graphEdgesRef.current = graph.edges;
      graphNodesRef.current = graph.nodes;
      const flowNodes = toFlowNodes(graph.nodes, graph.layout ?? {}, {
        onOpenFields: openFields,
        onOpenRecords: openRecords,
      });
      setNodes(flowNodes);
      setEdges(toFlowEdges(graph.edges));
      if (graph.viewport && typeof graph.viewport.zoom === "number") {
        setViewport({
          x: Number(graph.viewport.x ?? 0),
          y: Number(graph.viewport.y ?? 0),
          zoom: Number(graph.viewport.zoom),
        });
      } else {
        requestAnimationFrame(() => fitView({ padding: 0.2, duration: 200 }));
      }
    } catch (error) {
      notify({
        level: "error",
        title: "Could not load relationship graph",
        message: getErrorMessage(error),
      });
    } finally {
      setLoading(false);
    }
  }, [fitView, modulePack, notify, openFields, openRecords, setEdges, setNodes, setViewport]);

  useEffect(() => {
    void loadGraph();
    return () => {
      if (saveTimer.current) clearTimeout(saveTimer.current);
    };
  }, [loadGraph]);

  const selectedEdge = useMemo(() => {
    if (!selectedEdgeId) return null;
    return graphEdgesRef.current.find((e) => e.id === selectedEdgeId) ?? null;
  }, [selectedEdgeId]);

  useEffect(() => {
    if (!selectedEdge) {
      setDraftLabel("");
      setDraftTabLabel("");
      setSyncRelatedTab(true);
      return;
    }
    setDraftLabel(selectedEdge.field_label || selectedEdge.field_name || "");
    setDraftTabLabel(selectedEdge.related_tab_label || "");
    setSyncRelatedTab(true);
  }, [selectedEdge]);

  const selectedSource = useMemo(() => {
    if (!selectedEdge) return null;
    return nodes.find((n) => n.id === selectedEdge.source_entity_id) ?? null;
  }, [nodes, selectedEdge]);

  const selectedTarget = useMemo(() => {
    if (!selectedEdge) return null;
    return nodes.find((n) => n.id === selectedEdge.target_entity_id) ?? null;
  }, [nodes, selectedEdge]);

  const onConnect = useCallback(
    async (connection: Connection) => {
      if (!connection.source || !connection.target) return;
      if (connection.source === connection.target) {
        notify({
          level: "warning",
          title: "Invalid relationship",
          message: "An entity cannot relate to itself.",
        });
        return;
      }

      const targetNode = graphNodesRef.current.find((n) => n.id === connection.target);
      setSavingEdge(true);
      try {
        const edge = await createDynRelationshipEdge({
          source_entity_id: connection.source,
          target_entity_id: connection.target,
          label: targetNode?.name,
          related_tab_label: graphNodesRef.current.find((n) => n.id === connection.source)?.name,
          sync_related_tab: true,
        });
        setEdges((current) =>
          addEdge(
            {
              id: edge.id,
              source: edge.source_entity_id,
              target: edge.target_entity_id!,
              label: edge.field_label || edge.field_name || undefined,
              type: "smoothstep",
              markerEnd: { type: MarkerType.ArrowClosed, width: 16, height: 16 },
              data: edge,
            },
            current.filter((e) => e.id !== edge.id),
          ),
        );
        graphEdgesRef.current = [
          edge,
          ...graphEdgesRef.current.filter((e) => e.id !== edge.id),
        ];
        setSelectedEdgeId(edge.id);
        notify({
          level: "success",
          title: "Relationship created",
          message: "Field and inverse related tab were synced.",
        });
        await loadGraph();
      } catch (error) {
        notify({
          level: "error",
          title: "Could not create relationship",
          message: getErrorMessage(error),
        });
      } finally {
        setSavingEdge(false);
      }
    },
    [loadGraph, notify, setEdges],
  );

  const saveSelectedEdge = useCallback(async () => {
    if (!selectedEdge?.field_id) {
      notify({
        level: "warning",
        title: "Read-only edge",
        message: "Related-tab-only edges are managed via the relationship field on the source entity.",
      });
      return;
    }
    setSavingEdge(true);
    try {
      const updated = await updateDynRelationshipEdge(selectedEdge.field_id, {
        label: draftLabel.trim() || undefined,
        related_tab_label: draftTabLabel.trim() || undefined,
        sync_related_tab: syncRelatedTab,
      });
      notify({
        level: "success",
        title: "Relationship updated",
        message: syncRelatedTab ? "Inverse related tab synced." : "Field updated without related tab.",
      });
      setSelectedEdgeId(updated.id);
      await loadGraph();
    } catch (error) {
      notify({
        level: "error",
        title: "Could not update relationship",
        message: getErrorMessage(error),
      });
    } finally {
      setSavingEdge(false);
    }
  }, [draftLabel, draftTabLabel, loadGraph, notify, selectedEdge, syncRelatedTab]);

  const softDisableSelectedEdge = useCallback(async () => {
    if (!selectedEdge?.field_id) return;
    if (
      !window.confirm(
        "Soft-disable this relationship? The field stays (values kept) but the target and inverse related tab are detached.",
      )
    ) {
      return;
    }
    setSavingEdge(true);
    try {
      await deleteDynRelationshipEdge(selectedEdge.field_id, { soft_disable: true });
      setSelectedEdgeId(null);
      notify({
        level: "success",
        title: "Relationship soft-disabled",
        message: "Target detached; record values retained on the field.",
      });
      await loadGraph();
    } catch (error) {
      notify({
        level: "error",
        title: "Could not soft-disable relationship",
        message: getErrorMessage(error),
      });
    } finally {
      setSavingEdge(false);
    }
  }, [loadGraph, notify, selectedEdge]);

  const deleteSelectedEdge = useCallback(async () => {
    if (!selectedEdge?.field_id) {
      notify({
        level: "warning",
        title: "Cannot delete",
        message: "This edge is tab-only. Remove it from the parent entity related tabs or create a field edge first.",
      });
      return;
    }
    const usageHint =
      typeof selectedEdge.usage_count === "number" && selectedEdge.usage_count > 0
        ? ` Currently used on ${selectedEdge.usage_count} record(s).`
        : "";
    if (
      !window.confirm(
        `Delete this relationship field and remove the inverse related tab?${usageHint}`,
      )
    ) {
      return;
    }
    setSavingEdge(true);
    try {
      await deleteDynRelationshipEdge(selectedEdge.field_id);
      setSelectedEdgeId(null);
      notify({
        level: "success",
        title: "Relationship deleted",
        message: "Field and inverse related tab were removed.",
      });
      await loadGraph();
    } catch (error) {
      const fieldErrors = getApiFieldErrors(error);
      const usageRaw = fieldErrors.usage_count;
      const usage = usageRaw ? Number(usageRaw) : selectedEdge.usage_count ?? 0;
      const blocked =
        Boolean(fieldErrors.field_id) &&
        (usage > 0 || /force=1|soft_disable=1|used on/i.test(fieldErrors.field_id ?? ""));

      if (blocked) {
        if (
          window.confirm(
            `This relationship is used on ${usage || "some"} record(s).\n\nOK = soft-disable (keep values, detach target)\nCancel = choose force delete or abort`,
          )
        ) {
          try {
            await deleteDynRelationshipEdge(selectedEdge.field_id, { soft_disable: true });
            setSelectedEdgeId(null);
            notify({
              level: "success",
              title: "Relationship soft-disabled",
              message: "Target detached; record values retained.",
            });
            await loadGraph();
          } catch (inner) {
            notify({
              level: "error",
              title: "Could not soft-disable relationship",
              message: getErrorMessage(inner),
            });
          }
        } else if (
          window.confirm(
            `Force-delete this relationship field anyway?\n\nThe field row will be removed. Stored JSON values may become orphaned.`,
          )
        ) {
          try {
            await deleteDynRelationshipEdge(selectedEdge.field_id, { force: true });
            setSelectedEdgeId(null);
            notify({
              level: "success",
              title: "Relationship force-deleted",
              message: "Field and inverse related tab were removed.",
            });
            await loadGraph();
          } catch (inner) {
            notify({
              level: "error",
              title: "Could not force-delete relationship",
              message: getErrorMessage(inner),
            });
          }
        }
      } else {
        notify({
          level: "error",
          title: "Could not delete relationship",
          message: getErrorMessage(error),
        });
      }
    } finally {
      setSavingEdge(false);
    }
  }, [loadGraph, notify, selectedEdge]);

  const exportGraph = useCallback(
    async (format: "png" | "svg") => {
      const host = canvasHostRef.current;
      if (!host) return;
      setExporting(true);
      try {
        const canvas = await captureStudioCanvas(host);
        const stamp = new Date().toISOString().slice(0, 10);
        if (format === "png") {
          const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob((b) => resolve(b), "image/png"),
          );
          if (!blob) throw new Error("PNG export failed");
          downloadBlob(`toweros-relationship-studio-${stamp}.png`, blob);
        } else {
          const dataUrl = canvas.toDataURL("image/png");
          const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${canvas.width}" height="${canvas.height}" viewBox="0 0 ${canvas.width} ${canvas.height}">
  <title>TowerOS Relationship Studio</title>
  <image href="${dataUrl}" width="${canvas.width}" height="${canvas.height}" />
</svg>`;
          downloadBlob(
            `toweros-relationship-studio-${stamp}.svg`,
            new Blob([svg], { type: "image/svg+xml;charset=utf-8" }),
          );
        }
        notify({
          level: "success",
          title: `Exported ${format.toUpperCase()}`,
          message: "Graph snapshot downloaded.",
        });
      } catch (error) {
        notify({
          level: "error",
          title: "Export failed",
          message: getErrorMessage(error),
        });
      } finally {
        setExporting(false);
      }
    },
    [notify],
  );

  return (
    <div className="flex min-h-[70vh] flex-1 flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" size="sm" variant="outline" onClick={() => fitView({ padding: 0.2 })}>
          <Maximize2 className="size-3.5" />
          Fit view
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={async () => {
            await saveDynRelationshipGraphLayout({ positions: {}, reset: true });
            await loadGraph();
            requestAnimationFrame(() => fitView({ padding: 0.2, duration: 200 }));
          }}
        >
          <RefreshCw className="size-3.5" />
          Reset layout
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={exporting || loading}
          onClick={() => void exportGraph("png")}
        >
          <Download className="size-3.5" />
          Export PNG
        </Button>
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={exporting || loading}
          onClick={() => void exportGraph("svg")}
        >
          <Download className="size-3.5" />
          Export SVG
        </Button>
        {loading || savingEdge || exporting ? (
          <span className="text-xs text-muted-foreground">
            {exporting ? "Exporting…" : savingEdge ? "Saving…" : "Loading…"}
          </span>
        ) : null}
        <span className="text-xs text-muted-foreground">
          Drag cards · drag handle A → B to create · pan / zoom
        </span>
      </div>

      <div className="grid min-h-[70vh] flex-1 gap-3 lg:grid-cols-[1fr_18rem]">
        <div
          ref={canvasHostRef}
          className="relative overflow-hidden rounded-xl border border-border bg-background"
        >
          <ReactFlow
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={onConnect}
            nodeTypes={nodeTypes}
            nodesConnectable
            edgesReconnectable={false}
            fitView
            minZoom={0.25}
            maxZoom={1.75}
            proOptions={{ hideAttribution: true }}
            onNodeDragStop={(_, __, nextNodes) => {
              persistLayout(nextNodes);
            }}
            onMoveEnd={(_, viewport) => {
              persistLayout(nodes, viewport);
            }}
            onEdgeClick={(_, edge) => setSelectedEdgeId(edge.id)}
            onPaneClick={() => setSelectedEdgeId(null)}
            defaultEdgeOptions={{ type: "smoothstep" }}
          >
            <Background gap={18} size={1} color="var(--border)" />
            <Controls showInteractive={false} className="!shadow-sm" />
            <MiniMap
              className="!rounded-lg !border !border-border !bg-card"
              maskColor="rgb(15 23 42 / 0.08)"
              nodeColor="var(--muted)"
            />
          </ReactFlow>
        </div>

        <aside className="rounded-xl border border-border bg-card p-3">
          <p className="text-xs font-medium text-foreground">Inspector</p>
          <p className="mt-0.5 text-[11px] text-muted-foreground">
            Edit edges and keep related tabs in sync.
          </p>
          {selectedEdge && selectedSource && selectedTarget ? (
            <div className="mt-3 space-y-3 text-xs">
              <div>
                <p className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                  From → To
                </p>
                <p className="font-medium text-foreground">
                  {selectedSource.data.name} → {selectedTarget.data.name}
                </p>
                <p className="text-muted-foreground">{selectedEdge.kind}</p>
              </div>

              {selectedEdge.field_id ? (
                <>
                  <label className="block space-y-1">
                    <span className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                      Field label
                    </span>
                    <Input
                      value={draftLabel}
                      onChange={(e) => setDraftLabel(e.target.value)}
                      className="h-8 text-xs"
                    />
                  </label>
                  <label className="block space-y-1">
                    <span className="text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                      Related tab label (on target)
                    </span>
                    <Input
                      value={draftTabLabel}
                      onChange={(e) => setDraftTabLabel(e.target.value)}
                      className="h-8 text-xs"
                      placeholder={selectedSource.data.name}
                    />
                  </label>
                  <label className="flex items-center gap-2 text-xs text-foreground">
                    <Checkbox
                      checked={syncRelatedTab}
                      onCheckedChange={(v) => setSyncRelatedTab(v === true)}
                    />
                    Sync inverse related tab
                  </label>
                  {typeof selectedEdge.usage_count === "number" ? (
                    <p className="text-[11px] text-muted-foreground">
                      In use on{" "}
                      <span className="font-medium text-foreground">{selectedEdge.usage_count}</span>{" "}
                      record{selectedEdge.usage_count === 1 ? "" : "s"}
                    </p>
                  ) : null}
                  <div className="flex flex-col gap-1.5">
                    <Button
                      size="sm"
                      disabled={savingEdge}
                      onClick={() => void saveSelectedEdge()}
                    >
                      Save edge
                    </Button>
                    {(selectedEdge.usage_count ?? 0) > 0 ? (
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={savingEdge}
                        onClick={() => void softDisableSelectedEdge()}
                      >
                        Soft-disable
                      </Button>
                    ) : null}
                    <Button
                      size="sm"
                      variant="destructive"
                      disabled={savingEdge}
                      onClick={() => void deleteSelectedEdge()}
                    >
                      <Trash2 className="size-3.5" />
                      Delete relationship
                    </Button>
                  </div>
                </>
              ) : (
                <p className="text-muted-foreground">
                  Tab-only edge. Create or edit the relationship field on the source entity to manage
                  it here.
                </p>
              )}

              <div className="flex flex-col gap-1.5 border-t border-border pt-2">
                <Button
                  size="sm"
                  variant="outline"
                  className="justify-start"
                  render={
                    <Link
                      href={`/dynamic-entities/fields?entity=${encodeURIComponent(selectedSource.data.slug)}`}
                    />
                  }
                >
                  Manage Fields
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  className="justify-start"
                  render={
                    <Link href={`/dynamic-entities/${encodeURIComponent(selectedSource.data.slug)}`} />
                  }
                >
                  Open records
                </Button>
              </div>
            </div>
          ) : (
            <p className="mt-3 text-xs text-muted-foreground">
              Drag from a card handle to another card to create a relationship, or select an edge.
            </p>
          )}
        </aside>
      </div>
    </div>
  );
}

export function DynRelationshipStudioPageClient() {
  const [modulePack, setModulePack] = useState("all");

  return (
    <PermissionGate requiredPermissions={[permissions.dynamicEntitiesFieldsManage]}>
      <div className={cn("flex w-full flex-col gap-4")}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="Relationship Studio"
          description="Draw and edit Dynamic Entity relationships. Creating an edge adds a lookup field and syncs the inverse related tab on the target entity."
          actions={
            <div className="flex flex-wrap items-center gap-2">
              <Select
                value={modulePack}
                onChange={(e) => setModulePack(e.target.value)}
                className="h-8 w-40 text-xs"
                aria-label="Module pack filter"
              >
                {PACK_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </Select>
              <Button size="sm" variant="outline" render={<Link href="/dynamic-entities/fields" />}>
                Manage Fields
              </Button>
            </div>
          }
        />

        <ReactFlowProvider>
          <RelationshipStudioCanvas key={modulePack} modulePack={modulePack} />
        </ReactFlowProvider>
      </div>
    </PermissionGate>
  );
}
