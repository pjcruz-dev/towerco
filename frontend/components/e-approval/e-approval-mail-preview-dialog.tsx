"use client";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { prepareMailHtml } from "@/components/e-approval/e-approval-mail-rich-body";
import { mailInlineHtml, type MailPreviewModel } from "@/modules/e-approval/form-mail-config";

type Props = {
  open: boolean;
  title: string;
  preview: MailPreviewModel;
  onOpenChange: (open: boolean) => void;
};

export function EApprovalMailPreviewDialog({ open, title, preview, onOpenChange }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>
            This is the message before it is sent. The document number, requestor, and actor are samples.
          </DialogDescription>
        </DialogHeader>
        <DialogBody>
          <p className="text-sm font-medium text-foreground">{preview.subject}</p>
          <div className="mt-3 space-y-2 rounded-lg border border-border bg-muted/20 p-3 text-sm text-foreground">
            {preview.lines.map((line, index) => (
              <p key={`${index}-${line}`} dangerouslySetInnerHTML={{ __html: mailInlineHtml(line) }} />
            ))}
            {preview.html ? (
              <div
                className="[&_p]:mb-2 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-border [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-border [&_th]:px-2 [&_th]:py-1"
                dangerouslySetInnerHTML={{ __html: prepareMailHtml(preview.html) }}
              />
            ) : null}
            {preview.buttons.length > 0 ? (
              <div className="flex flex-wrap gap-2 pt-2">
                {preview.buttons.map((button, index) => (
                  <Button key={`${index}-${button.target}-${button.label}`} type="button" size="sm" disabled>
                    {button.label}
                  </Button>
                ))}
              </div>
            ) : (
              <p className="text-xs text-muted-foreground">No button on this email.</p>
            )}
          </div>
        </DialogBody>
      </DialogContent>
    </Dialog>
  );
}
