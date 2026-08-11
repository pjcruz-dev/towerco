"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Suspense, useMemo, useState } from "react";

import { FileUploadField } from "@/components/forms/file-upload-field";
import { AcronymLabel } from "@/components/help/acronym-label";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { createProjectApproval, fetchProjectOneProjectsIndex } from "@/lib/api/modules/project-one-api";
import { fetchRolloutsIndex } from "@/lib/api/modules/rollout-api";
import { getErrorMessage } from "@/lib/api/error";
import { permissions } from "@/lib/rbac/permissions";
import type { RolloutMediaLink } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

function ProjectOneApprovalNewForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const [approvalType, setApprovalType] = useState("purchase_order");
  const [title, setTitle] = useState("");
  const [requester, setRequester] = useState("");
  const [slaRisk, setSlaRisk] = useState<"low" | "medium" | "high">("medium");
  const [projectId, setProjectId] = useState(() => sanitizeOptionalId(searchParams.get("project_id")));
  const [rolloutId, setRolloutId] = useState(() => sanitizeOptionalId(searchParams.get("rollout_id")));
  const [attachments, setAttachments] = useState<RolloutMediaLink[]>([]);

  const projectsQuery = useQuery({
    queryKey: ["project-one", "projects", "approval-form"],
    queryFn: () => fetchProjectOneProjectsIndex({ per_page: 100 }),
  });

  const rolloutsQuery = useQuery({
    queryKey: ["project-one", "rollouts", "approval-form"],
    queryFn: () => fetchRolloutsIndex({ per_page: 100, status: "all" }),
  });

  const projects = projectsQuery.data?.data ?? [];
  const rollouts = rolloutsQuery.data?.data ?? [];

  const attachmentFileIds = useMemo(() => attachments.map((item) => item.file_id), [attachments]);

  const mutation = useMutation({
    mutationFn: createProjectApproval,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "approvals"] });
      push({ level: "success", title: "Approval submitted", message: "It appears on the Project One approvals queue." });
      router.replace("/project-one/approvals");
    },
    onError: (error) => {
      push({
        level: "error",
        title: "Could not create approval",
        message: getErrorMessage(error),
      });
    },
  });

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneManage]}>
      <div className="space-y-6">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">New approval</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Creates a pending item in the Project One approvals queue. Optionally link a project and/or
            rollout; attach supporting documents when tied to a rollout. This is separate from rollout
            timeline gate approvals.
          </p>
          <p className="mt-2 text-sm font-medium">
            <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/approvals">
              Back to approvals
            </Link>
          </p>
        </header>

        <form
          className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate({
              approval_type: approvalType.trim(),
              title: title.trim(),
              requester: requester.trim(),
              sla_risk: slaRisk,
              project_id: projectId || undefined,
              rollout_program_id: rolloutId || undefined,
              attachment_file_ids: attachmentFileIds.length > 0 ? attachmentFileIds : undefined,
            });
          }}
        >
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-muted-foreground" htmlFor="approval-type">
              Approval type
            </label>
            <Input
              id="approval-type"
              value={approvalType}
              onChange={(e) => setApprovalType(e.target.value)}
              required
              maxLength={64}
              className="h-9"
            />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-muted-foreground" htmlFor="title">
              Title
            </label>
            <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} required maxLength={255} className="h-9" />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-muted-foreground" htmlFor="requester">
              Requester
            </label>
            <Input
              id="requester"
              value={requester}
              onChange={(e) => setRequester(e.target.value)}
              required
              maxLength={255}
              placeholder="Name or email"
              className="h-9"
            />
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-muted-foreground" htmlFor="sla">
              <AcronymLabel term="SLA">SLA risk</AcronymLabel>
            </label>
            <select
              id="sla"
              value={slaRisk}
              onChange={(e) => setSlaRisk(e.target.value as "low" | "medium" | "high")}
              className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-muted-foreground" htmlFor="project-id">
              Project (optional)
            </label>
            <select
              id="project-id"
              value={projectId}
              onChange={(e) => setProjectId(e.target.value)}
              className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
              <option value="">No project link</option>
              {projects.map((project) => (
                <option key={project.id} value={project.id}>
                  {project.name}
                </option>
              ))}
            </select>
          </div>
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-muted-foreground" htmlFor="rollout-id">
              Rollout (optional)
            </label>
            <select
              id="rollout-id"
              value={rolloutId}
              onChange={(e) => {
                setRolloutId(e.target.value);
                if (!e.target.value) {
                  setAttachments([]);
                }
              }}
              className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
              <option value="">No rollout link</option>
              {rollouts.map((rollout) => (
                <option key={rollout.id} value={rollout.id}>
                  {rollout.rollout_ref} · {rollout.status}
                </option>
              ))}
            </select>
          </div>
          {rolloutId ? (
            <FileUploadField
              rolloutId={rolloutId}
              context="approval_attachment"
              label="Supporting documents"
              accept="application/pdf,image/*"
              value={attachments}
              onChange={setAttachments}
            />
          ) : null}
          <div className="flex gap-2 pt-2">
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? "Saving…" : "Submit approval"}
            </Button>
            <Button type="button" variant="outline" render={<Link href="/project-one/approvals" />}>
              Cancel
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}

function sanitizeOptionalId(raw: string | null): string {
  const value = raw?.trim() ?? "";
  if (!value || value === "undefined" || value === "null") {
    return "";
  }

  return value;
}

export function ProjectOneApprovalNewPageClient() {
  return (
    <Suspense fallback={<p className="p-6 text-sm text-muted-foreground">Loading form…</p>}>
      <ProjectOneApprovalNewForm />
    </Suspense>
  );
}
