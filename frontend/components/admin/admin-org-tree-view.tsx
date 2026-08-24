"use client";

import { ChevronDown, ChevronUp } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { OrgPersonCard } from "@/components/admin/admin-org-person-card";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import {
  expandableOrgIds,
  orgChartRoots,
  type OrgChartIndex,
  type OrgChartNode,
} from "@/lib/admin/org-chart";

function OrgChartBranch({
  person,
  index,
  depth,
  expandedIds,
  focusedId,
  ancestors,
  onToggle,
  onSelect,
}: {
  person: OrgChartNode;
  index: OrgChartIndex;
  depth: number;
  expandedIds: Set<string>;
  focusedId: string | null;
  ancestors: Set<string>;
  onToggle: (id: string) => void;
  onSelect: (id: string) => void;
}) {
  if (ancestors.has(person.id) || depth > 12) {
    return null;
  }

  const children = index.reports.get(person.id) ?? [];
  const expanded = expandedIds.has(person.id);
  const showChildren = children.length > 0 && expanded;

  return (
    <div className="flex flex-col items-center">
      <OrgPersonCard
        person={person}
        compact
        emphasis={person.id === focusedId ? "focus" : person.external ? "manager" : "default"}
        onSelect={onSelect}
      />
      {children.length > 0 ? (
        <button
          type="button"
          className="mt-1 inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
          aria-expanded={expanded}
          aria-label={expanded ? `Hide reports of ${person.name}` : `Show reports of ${person.name}`}
          onClick={() => onToggle(person.id)}
        >
          {expanded ? <ChevronUp className="size-4" /> : <ChevronDown className="size-4" />}
        </button>
      ) : null}

      {showChildren ? (
        <>
          <div className="h-4 w-px bg-border" aria-hidden />
          {children.length === 1 ? (
            <OrgChartBranch
              person={children[0]!}
              index={index}
              depth={depth + 1}
              expandedIds={expandedIds}
              focusedId={focusedId}
              ancestors={new Set(ancestors).add(person.id)}
              onToggle={onToggle}
              onSelect={onSelect}
            />
          ) : (
            <div className="flex items-start">
              {children.map((child, offset) => (
                <div key={child.id} className="relative flex flex-col items-center px-2">
                  <div className="absolute inset-x-0 top-0 flex h-px" aria-hidden>
                    <span className={cn("h-px w-1/2", offset === 0 ? "bg-transparent" : "bg-border")} />
                    <span
                      className={cn("h-px w-1/2", offset === children.length - 1 ? "bg-transparent" : "bg-border")}
                    />
                  </div>
                  <div className="h-4 w-px bg-border" aria-hidden />
                  <OrgChartBranch
                    person={child}
                    index={index}
                    depth={depth + 1}
                    expandedIds={expandedIds}
                    focusedId={focusedId}
                    ancestors={new Set(ancestors).add(person.id)}
                    onToggle={onToggle}
                    onSelect={onSelect}
                  />
                </div>
              ))}
            </div>
          )}
        </>
      ) : null}
    </div>
  );
}

export function AdminOrgTreeView({
  index,
  focusedId,
  onSelect,
}: {
  index: OrgChartIndex;
  focusedId: string | null;
  onSelect: (id: string) => void;
}) {
  const roots = useMemo(() => orgChartRoots(index), [index]);
  const trees = useMemo(() => roots.filter((node) => node.direct_report_count > 0), [roots]);
  const unattached = useMemo(() => roots.filter((node) => node.direct_report_count === 0), [roots]);
  const allExpandable = useMemo(() => expandableOrgIds(index), [index]);
  const expandKey = allExpandable.slice().sort().join("|");
  const [expandedIds, setExpandedIds] = useState<Set<string>>(() => new Set(allExpandable));

  useEffect(() => {
    setExpandedIds(new Set(expandKey === "" ? [] : expandKey.split("|")));
  }, [expandKey]);

  const toggle = (id: string) => {
    setExpandedIds((current) => {
      const next = new Set(current);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  if (roots.length === 0) {
    return <p className="px-4 py-10 text-center text-sm text-muted-foreground">No reporting lines to display.</p>;
  }

  return (
    <div className="px-3 py-3">
      <div className="mb-2 flex items-center justify-end gap-1">
        <Button type="button" variant="ghost" size="sm" onClick={() => setExpandedIds(new Set(allExpandable))}>
          Expand all
        </Button>
        <Button type="button" variant="ghost" size="sm" onClick={() => setExpandedIds(new Set())}>
          Collapse all
        </Button>
      </div>
      <div className="max-h-[min(75vh,52rem)] overflow-auto">
        <div className="flex min-w-max flex-col items-center gap-16 px-4 py-4">
          {trees.map((root, offset) => (
            <div key={root.id} className="flex w-full flex-col items-center">
              {trees.length > 1 ? (
                <p className="mb-3 text-center text-[11px] font-medium text-muted-foreground">
                  {offset === 0 ? "Reporting line" : "Separate reporting line"} · {root.name}
                </p>
              ) : null}
              <OrgChartBranch
                person={root}
                index={index}
                depth={0}
                expandedIds={expandedIds}
                focusedId={focusedId}
                ancestors={new Set()}
                onToggle={toggle}
                onSelect={onSelect}
              />
            </div>
          ))}
        </div>
        {unattached.length > 0 ? (
          <div className="border-t border-border px-4 py-6">
            <p className="mb-3 text-center text-[11px] font-medium text-muted-foreground">
              No manager in this workspace ({unattached.length})
            </p>
            <div className="flex flex-wrap justify-center gap-3">
              {unattached.map((person) => (
                <OrgPersonCard
                  key={person.id}
                  person={person}
                  compact
                  emphasis={person.id === focusedId ? "focus" : "default"}
                  onSelect={onSelect}
                />
              ))}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
