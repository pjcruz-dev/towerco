"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { createProjectOneProject } from "@/lib/api/modules/project-one-api";
import { fetchSitesIndex } from "@/lib/api/modules/sites-api";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const statusOptions = [
  { value: "planning", label: "Planning" },
  { value: "active", label: "Active" },
  { value: "on_hold", label: "On hold" },
  { value: "completed", label: "Completed" },
] as const;

export function ProjectNewPageClient() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const [name, setName] = useState("");
  const [siteId, setSiteId] = useState("");
  const [status, setStatus] = useState<(typeof statusOptions)[number]["value"]>("planning");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");

  const sitesQuery = useQuery({
    queryKey: ["sites", "index", "project-new"],
    queryFn: () => fetchSitesIndex({ page: 1, per_page: 100 }),
  });
  const sites = sitesQuery.data?.data ?? [];

  const mutation = useMutation({
    mutationFn: () =>
      createProjectOneProject({
        name: name.trim(),
        site_id: siteId || null,
        status,
        start_date: startDate || null,
        end_date: endDate || null,
      }),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "projects"] });
      push({ level: "success", title: "Project created", message: data.name });
      router.push(`/project-one/projects/${data.id}`);
    },
    onError: (error) => {
      push({ level: "error", title: "Could not create project", message: getErrorMessage(error) });
    },
  });

  return (
    <PermissionGate requiredPermissions={[permissions.projectOneManage]}>
      <div className="space-y-5">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">New project</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            QMS program record — link rollouts and track milestones on a site.
          </p>
          <p className="mt-2 text-xs font-medium">
            <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/projects">
              Back to projects
            </Link>
          </p>
        </header>

        <form
          className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate();
          }}
        >
          <FormInput label="Project name" value={name} onChange={(e) => setName(e.target.value)} required />

          <label className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Site</span>
            <select
              className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={siteId}
              onChange={(e) => setSiteId(e.target.value)}
            >
              <option value="">No site yet</option>
              {sites.map((site) => (
                <option key={site.id} value={site.id}>
                  {site.site_code} · {site.name}
                </option>
              ))}
            </select>
          </label>

          <label className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Status</span>
            <select
              className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
              value={status}
              onChange={(e) => setStatus(e.target.value as typeof status)}
            >
              {statusOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <div className="grid gap-4 sm:grid-cols-2">
            <FormInput label="Start date" date value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            <FormInput label="End date" date value={endDate} onChange={(e) => setEndDate(e.target.value)} />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Link href="/project-one/projects" className={buttonVariants({ variant: "outline" })}>
              Cancel
            </Link>
            <Button type="submit" disabled={mutation.isPending || !name.trim()}>
              {mutation.isPending ? "Creating…" : "Create project"}
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}
