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
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import type { SettlementDirection } from "@/modules/e-approval/settlement-verdict";

export type SettlementCaptureStatus = "paid" | "received" | "settled" | "hold";

export type SettlementCaptureDraft = {
  status: SettlementCaptureStatus;
  comment: string;
  reference: string;
  date: string;
  files: File[];
};

type Props = {
  open: boolean;
  submitting: boolean;
  direction: SettlementDirection;
  title: string;
  detail: string;
  onOpenChange: (open: boolean) => void;
  onConfirm: (capture: SettlementCaptureDraft) => void;
};

function defaultStatus(direction: SettlementDirection): SettlementCaptureStatus {
  if (direction === "surrender") {
    return "received";
  }
  if (direction === "settled") {
    return "settled";
  }
  return "paid";
}

export function EApprovalSettlementCaptureDialog({
  open,
  submitting,
  direction,
  title,
  detail,
  onOpenChange,
  onConfirm,
}: Props) {
  const [status, setStatus] = useState<SettlementCaptureStatus>(defaultStatus(direction));
  const [comment, setComment] = useState("");
  const [reference, setReference] = useState("");
  const [date, setDate] = useState("");
  const [files, setFiles] = useState<File[]>([]);

  useEffect(() => {
    if (open) {
      setStatus(defaultStatus(direction));
      setComment("");
      setReference("");
      setDate("");
      setFiles([]);
    }
  }, [open, direction]);

  const movesCash = status === "paid" || status === "received";
  const ready =
    !submitting
    && (status !== "hold" || comment.trim() !== "")
    && (!movesCash || (reference.trim() !== "" && date !== "" && files.length > 0));

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!submitting) {
          onOpenChange(next);
        }
      }}
    >
      <DialogContent className="w-[min(calc(100vw-1rem),480px)]">
        <DialogHeader>
          <DialogTitle>Settle the cash</DialogTitle>
          <DialogDescription>
            {direction === "surrender"
              ? "Received needs a reference, the date, and a file for the cash the employee returned. Hold needs a comment and stays on this step."
              : direction === "settled"
                ? "The totals match. Continue records that no cash moves."
                : "Paid needs a reference, the date, and a file, then the request continues. Hold needs a comment and stays on this step."}
          </DialogDescription>
        </DialogHeader>
        <DialogBody className="space-y-3">
          <div className="rounded-lg border border-border bg-muted/40 px-3 py-2.5">
            <p className="text-sm font-medium">{title}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">{detail}</p>
          </div>
          {direction === "settled" ? null : (
            <label className="block space-y-1 text-sm">
              <span className="font-medium">Status</span>
              <Select
                value={status}
                disabled={submitting}
                onChange={(event) => {
                  const next = event.target.value;
                  if (next === "hold") {
                    setStatus("hold");
                    return;
                  }
                  setStatus(direction === "surrender" ? "received" : "paid");
                }}
              >
                {direction === "surrender" ? (
                  <option value="received">Received</option>
                ) : (
                  <option value="paid">Paid</option>
                )}
                <option value="hold">Hold</option>
              </Select>
            </label>
          )}
          {movesCash ? (
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="block space-y-1 text-sm">
                <span className="font-medium">{status === "received" ? "Cash received reference" : "Payment reference"}</span>
                <Input
                  value={reference}
                  disabled={submitting}
                  maxLength={120}
                  onChange={(event) => setReference(event.target.value)}
                />
              </label>
              <label className="block space-y-1 text-sm">
                <span className="font-medium">{status === "received" ? "Date received" : "Date paid"}</span>
                <Input
                  type="date"
                  value={date}
                  disabled={submitting}
                  onChange={(event) => setDate(event.target.value)}
                />
              </label>
            </div>
          ) : null}
          <label className="block space-y-1 text-sm">
            <span className="font-medium">Comments</span>
            <Textarea
              value={comment}
              disabled={submitting}
              rows={3}
              maxLength={5000}
              onChange={(event) => setComment(event.target.value)}
            />
            <span className="block text-xs text-muted-foreground">
              {status === "hold" ? "Required to hold this step." : "Optional."}
            </span>
          </label>
          {direction === "settled" ? null : (
            <label className="block space-y-1 text-sm">
              <span className="font-medium">Attachments</span>
              <input
                type="file"
                multiple
                disabled={submitting}
                className="block w-full text-xs"
                onChange={(event) => {
                  setFiles(Array.from(event.target.files ?? []).slice(0, 4));
                  event.target.value = "";
                }}
              />
              {files.length > 0 ? (
                <span className="block text-xs text-muted-foreground">{files.map((file) => file.name).join(", ")}</span>
              ) : (
                <span className="block text-xs text-muted-foreground">
                  {movesCash ? "Required. Up to 4 files." : "Optional. Up to 4 files."}
                </span>
              )}
            </label>
          )}
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="outline" disabled={submitting} onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={!ready}
            onClick={() =>
              onConfirm({
                status,
                comment: comment.trim(),
                reference: reference.trim(),
                date,
                files,
              })
            }
          >
            {status === "hold" ? "Hold" : status === "received" ? "Receive" : status === "settled" ? "Settle" : "Pay"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
