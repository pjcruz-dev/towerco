"use client";

import { memo } from "react";
import { Handle, Position, type Node, type NodeProps } from "@xyflow/react";
import { KeyRound, Link2 } from "lucide-react";

import { cn } from "@/lib/utils";

export type DynEntityNodeData = {
  slug: string;
  name: string;
  module_pack: string;
  fields: Array<{
    id: string;
    name: string;
    label: string;
    type: string;
    is_key: boolean;
    target_entity_id: string | null;
  }>;
  onOpenFields?: (slug: string) => void;
  onOpenRecords?: (slug: string) => void;
};

export type DynEntityFlowNode = Node<DynEntityNodeData, "dynEntity">;

function DynEntityNodeComponent({ data, selected }: NodeProps<DynEntityFlowNode>) {
  return (
    <div
      className={cn(
        "w-56 overflow-hidden rounded-xl border bg-card shadow-sm",
        selected ? "border-foreground/40 ring-2 ring-foreground/10" : "border-border",
      )}
    >
      <Handle
        type="target"
        position={Position.Left}
        className="!size-2.5 !border-border !bg-muted-foreground/40"
      />
      <div className="flex items-center justify-between gap-2 border-b border-border bg-muted/40 px-3 py-2">
        <div className="min-w-0">
          <p className="truncate text-xs font-medium text-foreground">{data.name}</p>
          <p className="truncate text-[10px] text-muted-foreground">{data.module_pack}</p>
        </div>
      </div>
      <ul className="max-h-40 space-y-0.5 overflow-y-auto px-2 py-1.5">
        {data.fields.length === 0 ? (
          <li className="px-1 py-1 text-[10px] text-muted-foreground">No key / relation fields</li>
        ) : (
          data.fields.map((field) => (
            <li
              key={field.id}
              className="flex items-center gap-1.5 rounded-md px-1 py-0.5 text-[11px] text-muted-foreground"
            >
              {field.type === "relationship" ? (
                <Link2 className="size-3 shrink-0 text-sky-600 dark:text-sky-400" aria-hidden />
              ) : field.is_key ? (
                <KeyRound className="size-3 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden />
              ) : (
                <span className="size-3 shrink-0" />
              )}
              <span className="min-w-0 flex-1 truncate text-foreground">{field.label}</span>
              <span className="shrink-0 font-mono text-[9px] uppercase opacity-70">{field.type}</span>
            </li>
          ))
        )}
      </ul>
      <div className="flex border-t border-border">
        <button
          type="button"
          className="flex-1 px-2 py-1.5 text-[10px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
          onClick={(e) => {
            e.stopPropagation();
            data.onOpenRecords?.(data.slug);
          }}
        >
          Records
        </button>
        <button
          type="button"
          className="flex-1 border-l border-border px-2 py-1.5 text-[10px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
          onClick={(e) => {
            e.stopPropagation();
            data.onOpenFields?.(data.slug);
          }}
        >
          Fields
        </button>
      </div>
      <Handle
        type="source"
        position={Position.Right}
        className="!size-2.5 !border-border !bg-muted-foreground/40"
      />
    </div>
  );
}

export const DynEntityNode = memo(DynEntityNodeComponent);
