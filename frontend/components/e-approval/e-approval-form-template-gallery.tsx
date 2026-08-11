"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { FileStack } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { createEApprovalFormFromTemplate, fetchEApprovalFormTemplates } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  onCreated: (formId: string) => void;
};

export function EApprovalFormTemplateGallery({ onCreated }: Props) {
  const push = useNotificationStore((s) => s.push);
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

  const templates = data ?? [];

  return (
    <section className="space-y-3 rounded-xl border border-border bg-card p-4">
      <div className="flex items-center gap-2">
        <FileStack className="h-4 w-4 text-primary" />
        <h2 className="text-base font-medium">Start from template</h2>
      </div>
      <p className="text-xs text-muted-foreground">
        Pre-built forms you can customize. Each creates a new draft form in your tenant.
      </p>
      {isLoading ? (
        <p className="text-sm text-muted-foreground">Loading templates…</p>
      ) : templates.length === 0 ? (
        <p className="text-sm text-muted-foreground">No templates configured.</p>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {templates.map((t) => (
            <article
              key={t.id}
              className="flex flex-col rounded-xl border border-border/80 bg-muted/10 p-4 transition-colors hover:border-primary/30"
            >
              <h3 className="flex flex-wrap items-center gap-2 text-sm font-medium text-foreground">
                {t.name}
                <Badge variant="outline" className="text-[10px]">
                  {t.source === "tenant" ? "Tenant" : "System"}
                </Badge>
              </h3>
              <p className="mt-1 flex-1 text-xs text-muted-foreground">{t.description}</p>
              <p className="mt-2 text-[11px] text-muted-foreground">
                {t.field_count} fields · {t.step_count} workflow step{t.step_count === 1 ? "" : "s"}
              </p>
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="mt-3 w-full"
                disabled={createMutation.isPending}
                onClick={() => createMutation.mutate(t.id)}
              >
                Use template
              </Button>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
