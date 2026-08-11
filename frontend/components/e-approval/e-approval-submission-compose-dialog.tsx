"use client";

import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { useQuery } from "@tanstack/react-query";

import { EApprovalSubmissionComposePanel } from "@/components/e-approval/e-approval-submission-compose-panel";
import { fetchEApprovalForm } from "@/lib/api/modules/e-approval-api";
import type { EApprovalSubmissionDetail } from "@/modules/e-approval/types";

type Props = {
  formId: string | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmitted: (submission: EApprovalSubmissionDetail) => void;
};

export function EApprovalSubmissionComposeDialog({ formId, open, onOpenChange, onSubmitted }: Props) {
  const formQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "compose-dialog-title"],
    queryFn: () => fetchEApprovalForm(formId!),
    enabled: open && !!formId,
  });

  if (!formId) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="w-[min(calc(100vw-1.5rem),1100px)] max-h-[min(92vh,920px)]">
        <DialogHeader>
          <DialogTitle>{formQuery.data?.name ?? "New request"}</DialogTitle>
          <DialogDescription>
            Complete the fields below and submit. For more space, use the full-page request form from the form list.
          </DialogDescription>
        </DialogHeader>

        <DialogBody className="min-h-0">
          <EApprovalSubmissionComposePanel
            formId={formId}
            enabled={open}
            notifyOnSuccess
            onCancel={() => onOpenChange(false)}
            onSubmitted={({ submission }) => {
              onSubmitted(submission);
              onOpenChange(false);
            }}
          />
        </DialogBody>
      </DialogContent>
    </Dialog>
  );
}
