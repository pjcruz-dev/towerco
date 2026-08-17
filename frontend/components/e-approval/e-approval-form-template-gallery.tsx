"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { ChevronDown, FileStack } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useLocalStorageJsonState, isVisibilityState } from "@/hooks/use-local-storage-json-state";
import { useLocalStorageState } from "@/hooks/use-local-storage-state";
import { createEApprovalFormFromTemplate, fetchEApprovalFormTemplates } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { cn } from "@/lib/utils";
import { groupFormTemplates } from "@/modules/e-approval/form-template-groups";
import type { FormTemplateGroupId } from "@/modules/e-approval/form-template-groups";
import type { EApprovalFormTemplate } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";

const GALLERY_MINIMIZE_KEY = "e-approval-form-templates-minimized";
const GROUP_COLLAPSE_KEY = "e-approval-form-templates-groups";
const MINIMIZE_VALUES = ["0", "1"] as const;

type Props = {
  onCreated: (formId: string) => void;
  /** Persist minimize on this browser. Off for the create wizard so templates stay visible. */
  persistMinimize?: boolean;
};

function TemplateCard({
  template,
  pending,
  onUse,
}: {
  template: EApprovalFormTemplate;
  pending: boolean;
  onUse: (id: string) => void;
}) {
  return (
    <article className="flex flex-col rounded-xl border border-border/80 bg-muted/10 p-4 transition-colors hover:border-foreground/20">
      <h3 className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground">
        {template.name}
        <Badge variant="outline" className="text-[10px] font-medium">
          {template.source === "tenant" ? "Tenant" : "System"}
        </Badge>
      </h3>
      <p className="mt-1 flex-1 text-xs text-muted-foreground">{template.description}</p>
      <p className="mt-2 text-[11px] text-muted-foreground">
        {template.field_count} fields · {template.step_count} workflow step
        {template.step_count === 1 ? "" : "s"}
      </p>
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="mt-3 w-full"
        disabled={pending}
        onClick={() => onUse(template.id)}
      >
        Use template
      </Button>
    </article>
  );
}

export function EApprovalFormTemplateGallery({ onCreated, persistMinimize = true }: Props) {
  const push = useNotificationStore((s) => s.push);
  const [sessionMinimized, setSessionMinimized] = useState(false);
  const [storedMinimized, setStoredMinimized] = useLocalStorageState(GALLERY_MINIMIZE_KEY, "0", MINIMIZE_VALUES);
  const [collapsedGroups, setCollapsedGroups] = useLocalStorageJsonState<Record<string, boolean>>(
    persistMinimize ? GROUP_COLLAPSE_KEY : null,
    {},
    isVisibilityState,
  );
  const minimized = persistMinimize ? storedMinimized === "1" : sessionMinimized;
  const { data, isLoading } = useQuery({
    queryKey: ["e-approval", "form-templates"],
    queryFn: fetchEApprovalFormTemplates,
  });

  const createMutation = useMutation({
    mutationFn: (templateId: string) => createEApprovalFormFromTemplate(templateId),
    onSuccess: (form) => {
      push({ level: "success", title: "Form created from template" });
      onCreated(form.id);
    },
    onError: (e) => push({ level: "error", title: "Could not create form", message: getErrorMessage(e) }),
  });

  const groups = useMemo(() => groupFormTemplates(data ?? []), [data]);
  const templateCount = data?.length ?? 0;

  const setMinimized = (next: boolean) => {
    if (persistMinimize) {
      setStoredMinimized(next ? "1" : "0");
      return;
    }
    setSessionMinimized(next);
  };

  const toggleGroup = (id: FormTemplateGroupId) => {
    setCollapsedGroups((current) => ({ ...current, [id]: !current[id] }));
  };

  return (
    <section className="space-y-4 rounded-xl border border-border bg-card p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <FileStack className="h-4 w-4 text-muted-foreground" />
            <h2 className="text-base font-medium">Start from template</h2>
          </div>
          <p className="mt-1 text-xs text-muted-foreground">
            {minimized
              ? `${templateCount} template${templateCount === 1 ? "" : "s"} hidden. Expand to start a draft from a pre-built form.`
              : "Pre-built forms grouped by function. Each creates a new draft in this workspace."}
          </p>
        </div>
        <Button
          type="button"
          size="sm"
          variant="outline"
          aria-expanded={!minimized}
          onClick={() => setMinimized(!minimized)}
        >
          {minimized ? "Show templates" : "Minimize templates"}
        </Button>
      </div>
      {minimized ? null : isLoading ? (
        <p className="text-sm text-muted-foreground">Loading templates…</p>
      ) : groups.length === 0 ? (
        <p className="text-sm text-muted-foreground">No templates configured.</p>
      ) : (
        <div className="space-y-6">
          {groups.map((group) => {
            const collapsed = Boolean(collapsedGroups[group.id]);
            return (
              <section key={group.id} className="space-y-3">
                <button
                  type="button"
                  className="flex w-full items-start justify-between gap-2 border-b border-border pb-2 text-left"
                  aria-expanded={!collapsed}
                  onClick={() => toggleGroup(group.id)}
                >
                  <div>
                    <h3 className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                      <ChevronDown
                        className={cn("h-3.5 w-3.5 text-muted-foreground transition-transform", collapsed && "-rotate-90")}
                      />
                      {group.label}
                    </h3>
                    <p className="mt-0.5 pl-5 text-xs text-muted-foreground">{group.description}</p>
                  </div>
                  <p className="shrink-0 pt-0.5 text-[11px] text-muted-foreground">
                    {group.templates.length} template{group.templates.length === 1 ? "" : "s"}
                  </p>
                </button>
                {collapsed ? null : (
                  <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {group.templates.map((template) => (
                      <TemplateCard
                        key={template.id}
                        template={template}
                        pending={createMutation.isPending}
                        onUse={(id) => createMutation.mutate(id)}
                      />
                    ))}
                  </div>
                )}
              </section>
            );
          })}
        </div>
      )}
    </section>
  );
}
