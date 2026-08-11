"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { AcronymText } from "@/components/help/acronym-text";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import { cancelRollout } from "@/lib/api/modules/rollout-api";
import type { RolloutDetail } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  detail: RolloutDetail | undefined;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function RolloutCancelSheet({ rolloutId, detail, open, onOpenChange }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);
  const [reason, setReason] = useState("");

  const mutation = useMutation({
    mutationFn: () => cancelRollout(rolloutId, reason.trim()),
    onSuccess: async (updated) => {
      queryClient.setQueryData(["project-one", "rollouts", "detail", rolloutId], updated);
      await queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      push({ level: "success", title: "Rollout cancelled" });
      setReason("");
      onOpenChange(false);
    },
    onError: (error) => {
      push({ level: "error", title: "Cancel failed", message: getErrorMessage(error) });
    },
  });

  const locked =
    detail?.status === "completed" ||
    detail?.status === "cancelled" ||
    detail?.is_batch;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Cancel rollout</SheetTitle>
          <SheetDescription>
            <AcronymText text="This removes the rollout from active SLA tracking. Completed rollouts cannot be cancelled." />
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-3 px-4 py-2">
          <label className="block text-xs font-medium text-muted-foreground" htmlFor="cancel-reason">
            Cancellation reason
          </label>
          <textarea
            id="cancel-reason"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            rows={4}
            className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm text-foreground"
            placeholder="Why is this rollout being cancelled?"
          />
        </div>

        <SheetFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Keep rollout
          </Button>
          <Button
            type="button"
            variant="destructive"
            disabled={locked || reason.trim().length < 3 || mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {mutation.isPending ? "Cancelling…" : "Confirm cancel"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
