"use client";

import { useEffect, useState } from "react";

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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export type BulkResetPasswordMode = "generate" | "shared";

type Props = {
  open: boolean;
  selectedCount: number;
  pending?: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: (options: { mode: BulkResetPasswordMode; password?: string }) => void;
};

export function AdminUserBulkResetPasswordDialog({
  open,
  selectedCount,
  pending = false,
  onOpenChange,
  onConfirm,
}: Props) {
  const [mode, setMode] = useState<BulkResetPasswordMode>("generate");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) {
      setMode("generate");
      setPassword("");
      setConfirmPassword("");
      setError(null);
    }
  }, [open]);

  function handleOpenChange(next: boolean) {
    if (!pending) {
      onOpenChange(next);
    }
  }

  function handleConfirm() {
    if (mode === "generate") {
      setError(null);
      onConfirm({ mode: "generate" });
      return;
    }

    const trimmed = password.trim();
    if (trimmed.length < 8) {
      setError("Password must be at least 8 characters.");
      return;
    }
    if (trimmed.length > 128) {
      setError("Password must be at most 128 characters.");
      return;
    }
    if (trimmed !== confirmPassword.trim()) {
      setError("Passwords do not match.");
      return;
    }

    setError(null);
    onConfirm({ mode: "shared", password: trimmed });
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="gap-0 p-0 sm:max-w-md">
        <DialogHeader className="shrink-0">
          <DialogTitle>Reset passwords</DialogTitle>
          <DialogDescription className="text-pretty">
            Reset passwords for {selectedCount} selected user{selectedCount === 1 ? "" : "s"}.
            Active sessions will be revoked. Results are shown once — download or copy before closing.
          </DialogDescription>
        </DialogHeader>
        <DialogBody className="space-y-4 border-0 py-5">
          <fieldset className="space-y-3">
            <legend className="text-sm font-medium text-foreground">Password source</legend>
            <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border px-3 py-2.5">
              <input
                type="radio"
                name="bulk-reset-password-mode"
                className="mt-1"
                checked={mode === "generate"}
                disabled={pending}
                onChange={() => {
                  setMode("generate");
                  setError(null);
                }}
              />
              <span className="space-y-0.5">
                <span className="block text-sm font-medium text-foreground">Generate unique passwords</span>
                <span className="block text-xs text-muted-foreground">
                  Each user gets a different random temporary password (recommended).
                </span>
              </span>
            </label>
            <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border px-3 py-2.5">
              <input
                type="radio"
                name="bulk-reset-password-mode"
                className="mt-1"
                checked={mode === "shared"}
                disabled={pending}
                onChange={() => {
                  setMode("shared");
                  setError(null);
                }}
              />
              <span className="space-y-0.5">
                <span className="block text-sm font-medium text-foreground">Set one password for all</span>
                <span className="block text-xs text-muted-foreground">
                  Enter a shared temporary password applied to every selected user.
                </span>
              </span>
            </label>
          </fieldset>

          {mode === "shared" ? (
            <div className="space-y-3">
              <div className="space-y-1.5">
                <Label htmlFor="bulk-reset-password">Temporary password</Label>
                <Input
                  id="bulk-reset-password"
                  type="text"
                  autoComplete="new-password"
                  value={password}
                  disabled={pending}
                  onChange={(event) => setPassword(event.target.value)}
                  placeholder="At least 8 characters"
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="bulk-reset-password-confirm">Confirm password</Label>
                <Input
                  id="bulk-reset-password-confirm"
                  type="text"
                  autoComplete="new-password"
                  value={confirmPassword}
                  disabled={pending}
                  onChange={(event) => setConfirmPassword(event.target.value)}
                  placeholder="Re-enter password"
                />
              </div>
            </div>
          ) : null}

          {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </DialogBody>
        <DialogFooter className="shrink-0">
          <Button type="button" variant="outline" disabled={pending} onClick={() => handleOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={pending} onClick={handleConfirm}>
            {pending ? "Resetting…" : "Reset passwords"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
