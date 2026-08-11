"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { startUserImpersonation } from "@/lib/auth/impersonation-session";
import { getErrorMessage } from "@/lib/api/error";
import type { AdminUserRow } from "@/lib/api/modules/admin-users-api";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  user: AdminUserRow | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function AdminUserImpersonateDialog({ user, open, onOpenChange }: Props) {
  const notify = useNotificationStore((state) => state.push);
  const [reason, setReason] = useState("");
  const [submitting, setSubmitting] = useState(false);

  function handleOpenChange(next: boolean) {
    if (!submitting) {
      onOpenChange(next);
      if (!next) {
        setReason("");
      }
    }
  }

  async function handleSubmit() {
    if (!user) {
      return;
    }
    const trimmed = reason.trim();
    if (trimmed.length < 3) {
      notify({
        level: "warning",
        title: "Reason required",
        message: "Enter at least 3 characters (ticket ID or support note).",
      });
      return;
    }

    setSubmitting(true);
    try {
      await startUserImpersonation(user.id, trimmed);
    } catch (error) {
      notify({
        level: "error",
        title: "Impersonation failed",
        message: getErrorMessage(error),
      });
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="gap-0 p-0 sm:max-w-md">
        <DialogHeader className="shrink-0">
          <DialogTitle>View as user</DialogTitle>
          <DialogDescription className="text-pretty">
            You will see the workspace as{" "}
            <span className="font-medium text-foreground">{user?.name ?? "this user"}</span> (
            {user?.email}). A banner stays visible until you end the session. This action is audited.
          </DialogDescription>
        </DialogHeader>
        <DialogBody className="space-y-3 border-0 py-5">
          <div className="space-y-2">
            <Label htmlFor="impersonate-reason">Reason</Label>
            <Textarea
              id="impersonate-reason"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="e.g. INC-1042 — reproduce approval inbox issue"
              rows={3}
              maxLength={500}
              disabled={submitting}
              className="min-h-[5.5rem] resize-y"
            />
            <p className="text-xs text-muted-foreground">3–500 characters. Stored in the audit log.</p>
          </div>
        </DialogBody>
        <DialogFooter className="shrink-0 gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" onClick={() => handleOpenChange(false)} disabled={submitting}>
            Cancel
          </Button>
          <Button type="button" onClick={() => void handleSubmit()} disabled={submitting || !user}>
            {submitting ? "Starting…" : "Start session"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
