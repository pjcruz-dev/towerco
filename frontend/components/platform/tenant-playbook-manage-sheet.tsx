"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";
import { AlertTriangle } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import {
  platformAssignTenantPlaybook,
  platformListRolloutPlaybooks,
  platformListRolloutPolicies,
  type PlatformTenantRow,
} from "@/lib/api/modules/platform-api";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenant: PlatformTenantRow;
};

type AssignmentMode = "policy" | "version";

function compareVersions(left: string, right: string): number {
  const leftParts = left.split(".").map((part) => Number.parseInt(part, 10) || 0);
  const rightParts = right.split(".").map((part) => Number.parseInt(part, 10) || 0);
  const length = Math.max(leftParts.length, rightParts.length);

  for (let index = 0; index < length; index += 1) {
    const diff = (leftParts[index] ?? 0) - (rightParts[index] ?? 0);
    if (diff !== 0) {
      return diff;
    }
  }

  return 0;
}

export function TenantPlaybookManageSheet({ open, onOpenChange, tenant }: Props) {
  const notify = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const [mode, setMode] = useState<AssignmentMode>("policy");
  const [policyId, setPolicyId] = useState("");
  const [versionId, setVersionId] = useState("");
  const [upgradePolicy, setUpgradePolicy] = useState<"new_rollouts_only" | "include_draft_rollouts">(
    "new_rollouts_only",
  );

  const playbooksQuery = useQuery({
    queryKey: ["platform", "rollout-playbooks"],
    queryFn: platformListRolloutPlaybooks,
    enabled: open,
  });

  const policiesQuery = useQuery({
    queryKey: ["platform", "rollout-policies", "published"],
    queryFn: () => platformListRolloutPolicies("published"),
    enabled: open,
  });

  const publishedVersions = useMemo(
    () => (playbooksQuery.data?.versions ?? []).filter((row) => row.published_at),
    [playbooksQuery.data],
  );

  const publishedPolicies = useMemo(
    () => (policiesQuery.data ?? []).filter((policy) => policy.status === "published"),
    [policiesQuery.data],
  );

  const assignedVersion = tenant.assigned_playbook_version ?? null;
  const assignedPolicyCode = tenant.assigned_rollout_policy_code ?? null;

  const selectedVersion = useMemo(() => {
    if (mode === "version") {
      return publishedVersions.find((row) => row.id === versionId)?.version ?? null;
    }

    return publishedPolicies.find((row) => row.id === policyId)?.playbook_version ?? null;
  }, [mode, policyId, publishedPolicies, publishedVersions, versionId]);

  const changeKind = useMemo(() => {
    if (!selectedVersion || !assignedVersion) {
      return "assign";
    }

    const diff = compareVersions(selectedVersion, assignedVersion);
    if (diff > 0) {
      return "upgrade";
    }
    if (diff < 0) {
      return "downgrade";
    }

    if (mode === "policy" && policyId && tenant.rollout_policy_bundle_id !== policyId) {
      return "lateral";
    }

    return "unchanged";
  }, [assignedVersion, mode, policyId, selectedVersion, tenant.rollout_policy_bundle_id]);

  useEffect(() => {
    if (!open) {
      return;
    }

    setMode(tenant.rollout_policy_bundle_id ? "policy" : assignedVersion ? "version" : "policy");
    setPolicyId(tenant.rollout_policy_bundle_id ?? publishedPolicies[0]?.id ?? "");
    setVersionId(
      publishedVersions.find((row) => row.version === assignedVersion)?.id ??
        publishedVersions[0]?.id ??
        "",
    );
    setUpgradePolicy("new_rollouts_only");
  }, [assignedVersion, open, publishedPolicies, publishedVersions, tenant.rollout_policy_bundle_id]);

  const mutation = useMutation({
    mutationFn: () => {
      if (mode === "policy") {
        return platformAssignTenantPlaybook(tenant.id, {
          rollout_policy_bundle_id: policyId,
          sync_tenant_database: true,
          upgrade_policy: upgradePolicy,
        });
      }

      return platformAssignTenantPlaybook(tenant.id, {
        playbook_version_id: versionId,
        sync_tenant_database: true,
        upgrade_policy: upgradePolicy,
      });
    },
    onSuccess: (data) => {
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenant.id] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "tenants", tenant.id, "audit"] });
      void queryClient.invalidateQueries({ queryKey: ["platform", "dashboard"] });

      const policyLabel = data.assigned_policy_code ? ` · ${data.assigned_policy_code}` : "";
      notify({
        level: "success",
        title: "Rollout policy updated",
        message: `Assigned v${data.assigned_version}${policyLabel} to ${tenant.slug ?? tenant.id}.`,
      });
      onOpenChange(false);
    },
    onError: (error) =>
      notify({ level: "error", title: "Could not update rollout policy", message: getErrorMessage(error) }),
  });

  const canSubmit =
    !mutation.isPending &&
    changeKind !== "unchanged" &&
    ((mode === "policy" && Boolean(policyId)) || (mode === "version" && Boolean(versionId)));

  const submitLabel =
    changeKind === "downgrade"
      ? "Confirm downgrade"
      : changeKind === "upgrade"
        ? "Confirm upgrade"
        : changeKind === "lateral"
          ? "Apply change"
          : "Apply assignment";

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Manage rollout policy</SheetTitle>
          <SheetDescription>
            {tenant.slug ?? tenant.id}
            {assignedVersion ? ` · currently on playbook v${assignedVersion}` : " · no playbook assigned yet"}
            {assignedPolicyCode ? ` · policy ${assignedPolicyCode}` : ""}.
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 px-4 py-2 text-sm">
          <div className="space-y-2">
            <Label htmlFor="assignment-mode">Assignment mode</Label>
            <Select
              id="assignment-mode"
              className="h-9"
              value={mode}
              onChange={(event) => setMode(event.target.value as AssignmentMode)}
              disabled={mutation.isPending}
            >
              <option value="policy">Policy bundle (recommended)</option>
              <option value="version">Playbook version only (advanced)</option>
            </Select>
            <p className="text-xs text-muted-foreground">
              Policy bundles include gate chains, SLA windows, and timeline defaults for a playbook version.
            </p>
          </div>

          {mode === "policy" ? (
            <div className="space-y-2">
              <Label htmlFor="policy-bundle">Published policy bundle</Label>
              <Select
                id="policy-bundle"
                className="h-9"
                value={policyId}
                onChange={(event) => setPolicyId(event.target.value)}
                disabled={policiesQuery.isLoading || mutation.isPending || publishedPolicies.length === 0}
              >
                <option value="">
                  {publishedPolicies.length === 0 ? "No published policies" : "Select policy bundle"}
                </option>
                {publishedPolicies.map((policy) => (
                  <option key={policy.id} value={policy.id}>
                    {policy.code} — {policy.name}
                    {policy.playbook_version ? ` (v${policy.playbook_version})` : ""}
                  </option>
                ))}
              </Select>
            </div>
          ) : (
            <div className="space-y-2">
              <Label htmlFor="playbook-version">Published playbook version</Label>
              <Select
                id="playbook-version"
                className="h-9"
                value={versionId}
                onChange={(event) => setVersionId(event.target.value)}
                disabled={playbooksQuery.isLoading || mutation.isPending}
              >
                <option value="">Select published version</option>
                {publishedVersions.map((row) => (
                  <option key={row.id} value={row.id}>
                    v{row.version} — {row.name}
                    {row.version === assignedVersion ? " (current)" : ""}
                  </option>
                ))}
              </Select>
              <p className="text-xs text-muted-foreground">
                Version-only assignment clears the tenant&apos;s rollout policy bundle binding.
              </p>
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="upgrade-policy">Rollout scope</Label>
            <Select
              id="upgrade-policy"
              className="h-9"
              value={upgradePolicy}
              onChange={(event) =>
                setUpgradePolicy(event.target.value as "new_rollouts_only" | "include_draft_rollouts")
              }
              disabled={mutation.isPending}
            >
              <option value="new_rollouts_only">New rollouts only (recommended)</option>
              <option value="include_draft_rollouts">Include draft rollouts</option>
            </Select>
            <p className="text-xs text-muted-foreground">
              In-flight rollouts keep their original snapshot unless you include drafts.
            </p>
          </div>

          {changeKind === "downgrade" ? (
            <div
              className={cn(
                "flex gap-2 rounded-lg border border-amber-300/60 bg-amber-500/10 px-3 py-2 text-xs text-amber-900 dark:text-amber-200",
              )}
            >
              <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
              <p>
                You are downgrading from v{assignedVersion} to v{selectedVersion}. Existing rollouts are
                unaffected, but new rollouts will use the older playbook snapshot.
              </p>
            </div>
          ) : null}

          {changeKind === "unchanged" ? (
            <p className="text-xs text-muted-foreground">Select a different policy or version to apply a change.</p>
          ) : null}
        </div>

        <SheetFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!canSubmit} onClick={() => mutation.mutate()}>
            {mutation.isPending ? "Applying…" : submitLabel}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

/** @deprecated Use TenantPlaybookManageSheet */
export const TenantPlaybookUpgradeSheet = TenantPlaybookManageSheet;
