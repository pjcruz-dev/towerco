"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { FilterSelect } from "@/components/forms/filter-select";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import { fetchEApprovalAssignableUsers, sendEApprovalOptionalPreview } from "@/lib/api/modules/e-approval-api";
import { useNotificationStore } from "@/stores/notification-store";

export function EApprovalOptionalPreviewPanel({ submissionId }: { submissionId: string }) {
  const push = useNotificationStore((state) => state.push);
  const queryClient = useQueryClient();
  const [approverId, setApproverId] = useState("");
  const [duty, setDuty] = useState<"review" | "approve">("review");
  const [reason, setReason] = useState("");
  const usersQuery = useQuery({
    queryKey: ["e-approval", "assignable-users", "approvers"],
    queryFn: () => fetchEApprovalAssignableUsers("approvers"),
  });
  const mutation = useMutation({
    mutationFn: () =>
      sendEApprovalOptionalPreview(submissionId, { approver_id: approverId, duty, reason: reason.trim() }),
    onSuccess: async () => {
      setApproverId("");
      setReason("");
      await queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
      push({
        level: "success",
        title: "Preview sent",
        message: "They review it, then it comes back to you. Only your approval publishes the request.",
      });
    },
    onError: (error) => {
      push({ level: "error", title: "Could not send the preview", message: getErrorMessage(error) });
    },
  });

  return (
    <div className="rounded-lg border border-dashed border-border bg-muted/20 p-3">
      <p className="text-sm font-medium text-foreground">Optional preview</p>
      <p className="mt-0.5 text-xs text-muted-foreground">
        Send this to one person before you approve. They review or approve, then it returns here. Only your approval publishes it.
      </p>
      <div className="mt-3 grid gap-3 sm:grid-cols-2">
        <FilterSelect id="preview-person" label="Person" value={approverId} onChange={setApproverId}>
          <option value="">Choose a person</option>
          {(usersQuery.data ?? []).map((user) => (
            <option key={user.id} value={user.id}>
              {user.name}
            </option>
          ))}
        </FilterSelect>
        <FilterSelect
          id="preview-duty"
          label="What they do"
          value={duty}
          onChange={(value) => setDuty(value === "approve" ? "approve" : "review")}
        >
          <option value="review">Review</option>
          <option value="approve">Approval</option>
        </FilterSelect>
      </div>
      <div className="mt-3 space-y-1.5">
        <Label htmlFor="preview-reason">Why</Label>
        <Textarea
          id="preview-reason"
          rows={2}
          value={reason}
          placeholder="What you want them to check"
          onChange={(event) => setReason(event.target.value)}
        />
      </div>
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="mt-3"
        disabled={mutation.isPending || approverId === "" || reason.trim().length < 5}
        onClick={() => mutation.mutate()}
      >
        {mutation.isPending ? "Sending…" : "Send for preview"}
      </Button>
    </div>
  );
}
