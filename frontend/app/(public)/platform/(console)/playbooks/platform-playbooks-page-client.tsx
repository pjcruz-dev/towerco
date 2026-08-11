"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";
import Link from "next/link";
import { CheckCircle2, Clock, Package, UploadCloud } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { buttonVariants } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformListRolloutPlaybooks,
  platformPublishRolloutPlaybook,
} from "@/lib/api/modules/platform-api";
import { platformHasPermission, PLATFORM_PERMS } from "@/lib/platform/platform-permissions";
import { usePlatformAuthStore } from "@/stores/platform-auth-store";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

export function PlatformPlaybooksPageClient() {
  const user = usePlatformAuthStore((s) => s.user);
  const notify = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const canManage = platformHasPermission(user, PLATFORM_PERMS.playbooksManage);

  const [publishingVersion, setPublishingVersion] = useState<string | null>(null);

  const playbooksQuery = useQuery({
    queryKey: ["platform", "rollout-playbooks"],
    queryFn: platformListRolloutPlaybooks,
  });

  const versions = playbooksQuery.data?.versions ?? [];
  const registryVersions = playbooksQuery.data?.registry_versions ?? [];

  // Map of version string → published DB record
  const publishedMap = useMemo(() => {
    const map = new Map<string, (typeof versions)[number]>();
    for (const v of versions) {
      if (v.published_at) {
        map.set(v.version, v);
      }
    }
    return map;
  }, [versions]);

  const publishMutation = useMutation({
    mutationFn: (version: string) => platformPublishRolloutPlaybook(version),
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "rollout-playbooks"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      notify({
        level: "success",
        title: "Playbook published",
        message: `Version ${data.version} is now published.`,
      });
      setPublishingVersion(null);
    },
    onError: (error) => {
      notify({ level: "error", title: "Publish failed", message: getErrorMessage(error) });
      setPublishingVersion(null);
    },
  });

  const handlePublish = (version: string) => {
    setPublishingVersion(version);
    publishMutation.mutate(version);
  };

  const allPublished = registryVersions.length > 0 && registryVersions.every((v) => publishedMap.has(v));

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold text-foreground">Rollout playbooks</h1>
        <p className="text-sm text-muted-foreground">
          Publish playbook versions and policy bundles here. Assign, upgrade, or downgrade per tenant from
          Tenant 360 or the tenant directory actions menu.
        </p>
      </header>

      {/* Registry versions */}
      <div className="rounded-xl border border-border bg-card shadow-sm">
        <div className="flex items-center justify-between border-b border-border px-5 py-4">
          <div>
            <p className="text-base font-medium text-foreground">Registry versions</p>
            <p className="mt-0.5 text-xs text-muted-foreground">
              Versions defined in the platform codebase. Publish to make them available to tenants.
            </p>
          </div>
          <Package className="h-5 w-5 text-muted-foreground" />
        </div>

        {playbooksQuery.isLoading ? (
          <div className="space-y-3 p-5">
            {[1, 2].map((i) => (
              <div key={i} className="h-16 animate-pulse rounded-lg bg-muted" />
            ))}
          </div>
        ) : registryVersions.length === 0 ? (
          <div className="px-5 py-8 text-center">
            <p className="text-sm text-muted-foreground">No registry versions found.</p>
          </div>
        ) : (
          <div className="divide-y divide-border">
            {registryVersions.map((version) => {
              const published = publishedMap.get(version);
              const isPublishing = publishingVersion === version && publishMutation.isPending;

              return (
                <div
                  key={version}
                  className="flex flex-wrap items-center justify-between gap-3 px-5 py-4"
                >
                  <div className="flex items-center gap-3">
                    <div className={cn(
                      "flex h-9 w-9 items-center justify-center rounded-lg",
                      published ? "bg-emerald-50 dark:bg-emerald-950/30" : "bg-muted",
                    )}>
                      {published ? (
                        <CheckCircle2 className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                      ) : (
                        <Clock className="h-5 w-5 text-muted-foreground" />
                      )}
                    </div>
                    <div>
                      <p className="text-sm font-semibold text-foreground">
                        v{version}
                        {published?.name ? (
                          <span className="ml-1.5 font-normal text-muted-foreground">
                            — {published.name}
                          </span>
                        ) : null}
                      </p>
                      <p className="mt-0.5 text-xs text-muted-foreground">
                        {published?.published_at
                          ? `Published ${new Date(published.published_at).toLocaleString()}`
                          : "Not yet published"}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    {published ? (
                      <Badge
                        variant="outline"
                        className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400"
                      >
                        Published
                      </Badge>
                    ) : (
                      <Badge variant="outline" className="text-muted-foreground">
                        Not published
                      </Badge>
                    )}

                    {canManage ? (
                      <Button
                        type="button"
                        size="sm"
                        variant={published ? "outline" : "default"}
                        className="gap-1.5"
                        disabled={isPublishing || publishMutation.isPending}
                        onClick={() => handlePublish(version)}
                      >
                        <UploadCloud className="h-3.5 w-3.5" />
                        {isPublishing
                          ? "Publishing…"
                          : published
                            ? "Republish"
                            : "Publish"}
                      </Button>
                    ) : null}
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {allPublished && canManage ? (
          <div className="border-t border-border bg-muted/20 px-5 py-3">
            <p className="text-xs text-muted-foreground">
              All registry versions are published. Use <strong>Republish</strong> to re-sync a version to tenants.
            </p>
          </div>
        ) : null}
      </div>

      {/* Published versions history */}
      {versions.length > 0 ? (
        <div className="rounded-xl border border-border bg-card shadow-sm">
          <div className="border-b border-border px-5 py-4">
            <p className="text-base font-medium text-foreground">Published history</p>
            <p className="mt-0.5 text-xs text-muted-foreground">
              All playbook versions that have been published and synced to tenants.
            </p>
          </div>
          <div className="divide-y divide-border">
            {versions.map((row) => (
              <div
                key={row.id}
                className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 text-sm"
              >
                <div>
                  <p className="font-medium text-foreground">
                    v{row.version}
                    {row.name ? (
                      <span className="ml-1.5 font-normal text-muted-foreground">— {row.name}</span>
                    ) : null}
                  </p>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {row.published_at
                      ? `Published ${new Date(row.published_at).toLocaleString()}`
                      : "Draft"}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {row.sla_working_days_only ? (
                    <Badge variant="outline" className="text-xs text-muted-foreground">
                      Working days SLA
                    </Badge>
                  ) : null}
                  {row.published_at ? (
                    <Badge
                      variant="outline"
                      className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-400"
                    >
                      Published
                    </Badge>
                  ) : (
                    <Badge variant="outline">Draft</Badge>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      ) : null}

      <div>
        <Link
          href="/platform#tenant-directory"
          className={buttonVariants({ variant: "outline", size: "sm" })}
        >
          Back to tenant directory
        </Link>
      </div>
    </div>
  );
}
