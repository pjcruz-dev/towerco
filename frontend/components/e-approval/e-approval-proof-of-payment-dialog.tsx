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
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

export type PaymentProofDraft = {
  status: "released" | "hold";
  comment: string;
  files: File[];
};

type Props = {
  open: boolean;
  submitting: boolean;
  onOpenChange: (open: boolean) => void;
  onConfirm: (proof: PaymentProofDraft) => void;
};

export function EApprovalProofOfPaymentDialog({ open, submitting, onOpenChange, onConfirm }: Props) {
  const [status, setStatus] = useState<"released" | "hold">("released");
  const [comment, setComment] = useState("");
  const [files, setFiles] = useState<File[]>([]);

  const reset = () => {
    setStatus("released");
    setComment("");
    setFiles([]);
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next && !submitting) {
          reset();
        }
        onOpenChange(next);
      }}
    >
      <DialogContent className="w-[min(calc(100vw-1rem),480px)]">
        <DialogHeader>
          <DialogTitle>Proof of payment</DialogTitle>
          <DialogDescription>
            Hold needs a comment and stays on this step. Released needs a file and continues the request.
          </DialogDescription>
        </DialogHeader>
        <DialogBody className="space-y-3">
          <label className="block space-y-1 text-sm">
            <span className="font-medium">Status</span>
            <Select
              value={status}
              disabled={submitting}
              onChange={(event) => setStatus(event.target.value === "hold" ? "hold" : "released")}
            >
              <option value="released">Released</option>
              <option value="hold">Hold</option>
            </Select>
          </label>
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
                {status === "released" ? "Required. Up to 4 files." : "Optional. Up to 4 files."}
              </span>
            )}
          </label>
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="outline" disabled={submitting} onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={
              submitting
              || (status === "hold" && comment.trim() === "")
              || (status === "released" && files.length === 0)
            }
            onClick={() => onConfirm({ status, comment: comment.trim(), files })}
          >
            {status === "hold" ? "Hold" : "Release"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
