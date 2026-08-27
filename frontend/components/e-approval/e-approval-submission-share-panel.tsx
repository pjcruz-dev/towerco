"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Copy, Link2, Share2 } from "lucide-react";
import { useState } from "react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { getErrorMessage } from "@/lib/api/error";
import {
  createEApprovalSubmissionShareLink,
  fetchEApprovalSubmissionShareLinks,
  revokeEApprovalSubmissionShareLink,
} from "@/lib/api/modules/e-approval-api";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  submissionId: string;
  enabled?: boolean;
};

export function EApprovalSubmissionSharePanel({ submissionId, enabled = true }: Props) {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const [label, setLabel] = useState("");
  const [ttlDays, setTtlDays] = useState("14");
  const [lastUrl, setLastUrl] = useState<string | null>(null);
  const [copied, setCopied] = useState(false);

  const linksQuery = useQuery({
    queryKey: ["e-approval", "submission", submissionId, "share-links"],
    queryFn: () => fetchEApprovalSubmissionShareLinks(submissionId),
    enabled,
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createEApprovalSubmissionShareLink(submissionId, {
        label: label.trim() || undefined,
        ttl_days: Number.parseInt(ttlDays, 10) || 14,
      }),
    onSuccess: (data) => {
      setLastUrl(data.url);
      setCopied(false);
      setLabel("");
      void queryClient.invalidateQueries({
        queryKey: ["e-approval", "submission", submissionId, "share-links"],
      });
      push({ level: "success", title: "Share link created" });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not create share link", message: getErrorMessage(error) }),
  });

  const revokeMutation = useMutation({
    mutationFn: (id: string) => revokeEApprovalSubmissionShareLink(id),
    onSuccess: () => {
      void queryClient.invalidateQueries({
        queryKey: ["e-approval", "submission", submissionId, "share-links"],
      });
      push({ level: "success", title: "Share link revoked" });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not revoke link", message: getErrorMessage(error) }),
  });

  const copyUrl = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      push({ level: "success", title: "Link copied" });
    } catch {
      push({ level: "error", title: "Copy failed", message: "Select the URL and copy manually." });
    }
  };

  const activeLinks = (linksQuery.data ?? []).filter((link) => link.is_active);

  return (
    <EApprovalSectionCard
      title="Share approved request"
      description="Create a read-only link for people outside TowerOS. Links only work while the request stays approved and before expiry."
    >
      <div className="mt-3 space-y-4">
        <div className="grid gap-3 sm:grid-cols-[1fr_7rem_auto] sm:items-end">
          <div className="space-y-1.5">
            <Label htmlFor="ea-share-label">Label (optional)</Label>
            <Input
              id="ea-share-label"
              value={label}
              onChange={(e) => setLabel(e.target.value)}
              placeholder="e.g. Vendor copy"
              disabled={createMutation.isPending}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="ea-share-ttl">Days valid</Label>
            <Input
              id="ea-share-ttl"
              type="number"
              min={1}
              max={90}
              value={ttlDays}
              onChange={(e) => setTtlDays(e.target.value)}
              disabled={createMutation.isPending}
            />
          </div>
          <Button
            size="sm"
            className="gap-1.5"
            onClick={() => createMutation.mutate()}
            disabled={createMutation.isPending}
          >
            <Share2 className="h-3.5 w-3.5" />
            {createMutation.isPending ? "Creating…" : "Create link"}
          </Button>
        </div>

        {lastUrl ? (
          <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
            <p className="text-xs font-medium text-foreground">New link (copy now — shown once)</p>
            <div className="flex flex-wrap gap-2">
              <Input readOnly value={lastUrl} className="min-w-0 flex-1 font-mono text-xs" />
              <Button size="sm" variant="outline" className="gap-1.5" onClick={() => void copyUrl(lastUrl)}>
                {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                Copy
              </Button>
            </div>
          </div>
        ) : null}

        {activeLinks.length > 0 ? (
          <div className="space-y-2">
            <p className="text-xs font-medium text-muted-foreground">Active links</p>
            <ul className="space-y-2">
              {activeLinks.map((link) => (
                <li
                  key={link.id}
                  className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border px-3 py-2 text-sm"
                >
                  <div className="min-w-0 space-y-0.5">
                    <p className="font-medium text-foreground">
                      {link.label?.trim() || "Share link"}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      Expires {link.expires_at ? new Date(link.expires_at).toLocaleString() : "—"}
                      {link.access_count > 0 ? ` · Opened ${link.access_count}×` : ""}
                    </p>
                  </div>
                  <Button
                    size="sm"
                    variant="outline"
                    disabled={revokeMutation.isPending}
                    onClick={() => revokeMutation.mutate(link.id)}
                  >
                    Revoke
                  </Button>
                </li>
              ))}
            </ul>
          </div>
        ) : (
          <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
            <Link2 className="h-3.5 w-3.5" />
            No active share links yet.
          </p>
        )}
      </div>
    </EApprovalSectionCard>
  );
}
