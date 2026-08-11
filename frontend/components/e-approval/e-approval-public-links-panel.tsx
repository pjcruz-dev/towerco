"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Copy, Link2, RotateCcw, ShieldOff } from "lucide-react";
import { useState } from "react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { getErrorMessage } from "@/lib/api/error";
import {
  createEApprovalPublicFormLink,
  fetchEApprovalPublicFormLinks,
  revealEApprovalPublicFormLink,
  revokeEApprovalPublicFormLink,
  rotateEApprovalPublicFormLink,
} from "@/lib/api/modules/e-approval-api";
import {
  mapEApprovalAssignableUsersToOptions,
  useEApprovalAssignableUsers,
} from "@/hooks/use-e-approval-assignable-users";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  formId: string;
  formPublished: boolean;
};

export function EApprovalPublicLinksPanel({ formId, formPublished }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const usersQuery = useEApprovalAssignableUsers();
  const sponsorOptions = mapEApprovalAssignableUsersToOptions(usersQuery.data ?? []);

  const [label, setLabel] = useState("");
  const [sponsorUserId, setSponsorUserId] = useState("");
  const [maxSubmissions, setMaxSubmissions] = useState("");
  const [expiresAt, setExpiresAt] = useState("");
  const [linkPassword, setLinkPassword] = useState("");
  const [lastCreatedUrl, setLastCreatedUrl] = useState<string | null>(null);

  const linksQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "public-links"],
    queryFn: () => fetchEApprovalPublicFormLinks(formId),
    enabled: formPublished,
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createEApprovalPublicFormLink(formId, {
        label: label.trim() || undefined,
        sponsor_user_id: sponsorUserId,
        max_submissions: maxSubmissions ? Number.parseInt(maxSubmissions, 10) : undefined,
        expires_at: expiresAt || undefined,
        password: linkPassword.trim() || undefined,
      }),
    onSuccess: (data) => {
      setLastCreatedUrl(data.public_url);
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "public-links"] });
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "forms", "published-picker"] });
      push({
        level: "success",
        title: "Public link created",
        message: "You can copy this URL anytime from the link list or New submission.",
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not create link", message: getErrorMessage(error) }),
  });

  const revokeMutation = useMutation({
    mutationFn: (linkId: string) => revokeEApprovalPublicFormLink(linkId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "public-links"] });
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "forms", "published-picker"] });
      push({ level: "success", title: "Link revoked" });
    },
  });

  const rotateMutation = useMutation({
    mutationFn: (linkId: string) => rotateEApprovalPublicFormLink(linkId),
    onSuccess: (data) => {
      setLastCreatedUrl(data.public_url);
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "public-links"] });
      void queryClient.invalidateQueries({ queryKey: ["e-approval", "forms", "published-picker"] });
      push({
        level: "success",
        title: "Link rotated",
        message: "Previous URL is invalid. Copy the new URL.",
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not rotate link", message: getErrorMessage(error) }),
  });

  const copyUrl = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      push({ level: "success", title: "Copied", message: "Public form URL copied to clipboard." });
    } catch {
      push({ level: "warning", title: "Copy failed", message: url });
    }
  };

  const revealMutation = useMutation({
    mutationFn: (linkId: string) => revealEApprovalPublicFormLink(linkId),
    onSuccess: async (data) => {
      setLastCreatedUrl(data.public_url);
      await copyUrl(data.public_url);
    },
    onError: (error) =>
      push({ level: "error", title: "Could not copy link", message: getErrorMessage(error) }),
  });

  if (!formPublished) {
    return (
      <EApprovalSectionCard
        title="External sharing"
        description="Publish this form before generating a public link for vendors or partners."
      >
        <p className="text-sm text-muted-foreground">External links are only available for published forms.</p>
      </EApprovalSectionCard>
    );
  }

  return (
    <EApprovalSectionCard
      title="External sharing"
      description="Secure links for vendors and partners to submit without a TowerOS account. Ops users can also copy the active link from New submission. Internal sponsor receives notifications; approvals stay in your workflow."
    >
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="ea-public-label">Link label</Label>
          <Input
            id="ea-public-label"
            value={label}
            onChange={(e) => setLabel(e.target.value)}
            placeholder="e.g. Vendor intake Q2"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="ea-public-sponsor">Internal sponsor</Label>
          <Select
            id="ea-public-sponsor"
            value={sponsorUserId}
            onChange={(e) => setSponsorUserId(e.target.value)}
          >
            <option value="">Select sponsor…</option>
            {sponsorOptions.map((opt) => (
              <option key={opt.id} value={opt.id}>
                {opt.label}
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-2">
          <Label htmlFor="ea-public-max">Max submissions (optional)</Label>
          <Input
            id="ea-public-max"
            type="number"
            min={1}
            value={maxSubmissions}
            onChange={(e) => setMaxSubmissions(e.target.value)}
            placeholder="Unlimited"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="ea-public-expires">Expires (optional)</Label>
          <DatePicker
            id="ea-public-expires"
            value={expiresAt}
            onChange={setExpiresAt}
          />
        </div>
        <div className="space-y-2 md:col-span-2">
          <Label htmlFor="ea-public-password">Link password (optional)</Label>
          <Input
            id="ea-public-password"
            type="password"
            value={linkPassword}
            onChange={(e) => setLinkPassword(e.target.value)}
            placeholder="Require password before opening the form"
          />
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          className="gap-1.5"
          disabled={!sponsorUserId || createMutation.isPending}
          onClick={() => createMutation.mutate()}
        >
          <Link2 className="h-4 w-4" />
          {createMutation.isPending ? "Creating…" : "Create public link"}
        </Button>
        {lastCreatedUrl ? (
          <Button type="button" variant="outline" className="gap-1.5" onClick={() => copyUrl(lastCreatedUrl)}>
            <Copy className="h-4 w-4" />
            Copy latest URL
          </Button>
        ) : null}
      </div>

      {linksQuery.isLoading ? (
        <p className="text-sm text-muted-foreground">Loading links…</p>
      ) : (linksQuery.data?.length ?? 0) === 0 ? (
        <p className="text-sm text-muted-foreground">No public links yet.</p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border">
          {(linksQuery.data ?? []).map((link) => (
            <li key={link.id} className="flex flex-wrap items-center justify-between gap-3 px-3 py-3 text-sm">
              <div>
                <p className="font-medium text-foreground">{link.label ?? "Public link"}</p>
                <p className="text-xs text-muted-foreground">
                  {link.submissions_count} submission{link.submissions_count === 1 ? "" : "s"}
                  {link.revoked_at ? " · Revoked" : link.is_enabled ? "" : " · Disabled"}
                  {link.sponsor ? ` · Sponsor: ${link.sponsor.name}` : ""}
                </p>
              </div>
              <div className="flex gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-1"
                  disabled={
                    revealMutation.isPending ||
                    !!link.revoked_at ||
                    !link.is_enabled ||
                    !link.can_reveal_url
                  }
                  title={
                    link.can_reveal_url
                      ? "Copy public form URL"
                      : "Rotate once to enable re-copy for links created before this feature"
                  }
                  onClick={() => revealMutation.mutate(link.id)}
                >
                  <Copy className="h-3.5 w-3.5" />
                  Copy URL
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-1"
                  disabled={rotateMutation.isPending || !!link.revoked_at}
                  onClick={() => rotateMutation.mutate(link.id)}
                >
                  <RotateCcw className="h-3.5 w-3.5" />
                  Rotate
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="gap-1 text-destructive"
                  disabled={revokeMutation.isPending || !!link.revoked_at}
                  onClick={() => revokeMutation.mutate(link.id)}
                >
                  <ShieldOff className="h-3.5 w-3.5" />
                  Revoke
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </EApprovalSectionCard>
  );
}
