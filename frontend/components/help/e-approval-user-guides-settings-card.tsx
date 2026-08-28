"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchAdminHelpGuide,
  fetchAdminHelpGuides,
  publishAdminHelpGuide,
  unpublishAdminHelpGuide,
  updateAdminHelpGuide,
  type HelpGuideDetail,
  type HelpGuideListRow,
} from "@/lib/api/modules/help-guides-api";

export function EApprovalUserGuidesSettingsCard() {
  const queryClient = useQueryClient();
  const [editingSlug, setEditingSlug] = useState<string | null>(null);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const listQuery = useQuery({
    queryKey: ["help", "admin", "guides", "e_approval"],
    queryFn: () => fetchAdminHelpGuides("e_approval"),
  });

  const openEditor = async (row: HelpGuideListRow) => {
    setError(null);
    setMessage(null);
    setEditingSlug(row.slug);
    try {
      const detail = await fetchAdminHelpGuide(row.slug);
      setTitle(detail.title);
      setBody(detail.body);
    } catch (err) {
      setError(getErrorMessage(err) || "Could not load guide.");
    }
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (!editingSlug) throw new Error("No guide selected");
      return updateAdminHelpGuide(editingSlug, { title: title.trim(), body });
    },
    onSuccess: (detail: HelpGuideDetail) => {
      setMessage("Guide saved.");
      setTitle(detail.title);
      setBody(detail.body);
      void queryClient.invalidateQueries({ queryKey: ["help", "admin", "guides"] });
      void queryClient.invalidateQueries({ queryKey: ["help", "guides"] });
    },
    onError: (err) => setError(getErrorMessage(err) || "Could not save guide."),
  });

  const publishMutation = useMutation({
    mutationFn: async () => {
      if (!editingSlug) throw new Error("No guide selected");
      await updateAdminHelpGuide(editingSlug, { title: title.trim(), body });
      return publishAdminHelpGuide(editingSlug);
    },
    onSuccess: () => {
      setMessage("Guide published. Users can see it under Help.");
      void queryClient.invalidateQueries({ queryKey: ["help"] });
    },
    onError: (err) => setError(getErrorMessage(err) || "Could not publish guide."),
  });

  const unpublishMutation = useMutation({
    mutationFn: async () => {
      if (!editingSlug) throw new Error("No guide selected");
      return unpublishAdminHelpGuide(editingSlug);
    },
    onSuccess: () => {
      setMessage("Guide unpublished.");
      void queryClient.invalidateQueries({ queryKey: ["help"] });
    },
    onError: (err) => setError(getErrorMessage(err) || "Could not unpublish guide."),
  });

  return (
    <EApprovalSectionCard
      title="User guides"
      description="Edit requestor and approver guides shown under Help. Changes apply after you save (and publish if unpublished)."
    >
      <div className="space-y-4">
        {listQuery.isLoading ? <p className="text-sm text-muted-foreground">Loading guides…</p> : null}
        {listQuery.isError ? (
          <p className="text-sm text-destructive">
            {getErrorMessage(listQuery.error) || "Could not load guides. Run help:seed-e-approval-guides if empty."}
          </p>
        ) : null}

        {(listQuery.data ?? []).length === 0 && !listQuery.isLoading ? (
          <p className="text-sm text-muted-foreground">
            No guides yet. On the API host run:{" "}
            <code className="rounded bg-muted px-1 py-0.5 text-xs">php artisan help:seed-e-approval-guides</code>
          </p>
        ) : null}

        <ul className="divide-y divide-border rounded-lg border border-border">
          {(listQuery.data ?? []).map((row) => (
            <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-sm">
              <div className="min-w-0">
                <p className="font-medium text-foreground">{row.title}</p>
                <p className="text-xs text-muted-foreground">
                  {row.role} · {row.status} · {row.slug}
                </p>
              </div>
              <Button type="button" size="sm" variant="outline" onClick={() => void openEditor(row)}>
                Edit
              </Button>
            </li>
          ))}
        </ul>

        {editingSlug ? (
          <div className="space-y-3 rounded-lg border border-border bg-muted/20 p-4">
            <div className="space-y-2">
              <Label htmlFor="help-guide-title">Title</Label>
              <Input id="help-guide-title" value={title} onChange={(e) => setTitle(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="help-guide-body">Body (markdown)</Label>
              <Textarea
                id="help-guide-body"
                value={body}
                onChange={(e) => setBody(e.target.value)}
                rows={18}
                className="font-mono text-xs"
              />
            </div>
            {error ? <p className="text-sm text-destructive">{error}</p> : null}
            {message ? <p className="text-sm text-emerald-700 dark:text-emerald-400">{message}</p> : null}
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                disabled={saveMutation.isPending}
                onClick={() => {
                  setError(null);
                  setMessage(null);
                  saveMutation.mutate();
                }}
              >
                {saveMutation.isPending ? "Saving…" : "Save"}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={publishMutation.isPending}
                onClick={() => {
                  setError(null);
                  setMessage(null);
                  publishMutation.mutate();
                }}
              >
                {publishMutation.isPending ? "Publishing…" : "Publish"}
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                disabled={unpublishMutation.isPending}
                onClick={() => {
                  setError(null);
                  setMessage(null);
                  unpublishMutation.mutate();
                }}
              >
                Unpublish
              </Button>
              <Button type="button" size="sm" variant="ghost" onClick={() => setEditingSlug(null)}>
                Close editor
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </EApprovalSectionCard>
  );
}
