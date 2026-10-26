"use client";

import { useState } from "react";
import { ChevronDown, ChevronUp } from "lucide-react";

import { EApprovalMailPreviewDialog } from "@/components/e-approval/e-approval-mail-preview-dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  defaultMailSample,
  previewStepOpenMail,
  stepStatusPreview,
  type FormMailSettings,
  type MailButton,
} from "@/modules/e-approval/form-mail-config";
import type { EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

type StepMail = {
  notify_assignee: boolean;
  note: string;
  subject: string;
  body: string;
  buttons: MailButton[] | null;
  cc_user_ids: string[];
  notify_requestor_on_approve: boolean;
  requestor_subject: string;
  requestor_body: string;
  requestor_buttons: MailButton[] | null;
};

function mailFromStep(step: EApprovalWorkflowStepInput): StepMail {
  const raw = step.mail;
  return {
    notify_assignee: raw?.notify_assignee !== false,
    note: raw?.note ?? "",
    subject: raw?.subject ?? "",
    body: raw?.body ?? "",
    buttons: raw?.buttons ?? null,
    cc_user_ids: raw?.cc_user_ids ?? [],
    notify_requestor_on_approve: raw?.notify_requestor_on_approve === true,
    requestor_subject: raw?.requestor_subject ?? "",
    requestor_body: raw?.requestor_body ?? "",
    requestor_buttons: raw?.requestor_buttons ?? null,
  };
}

function mailOrUndefined(mail: StepMail): EApprovalWorkflowStepInput["mail"] | undefined {
  if (
    mail.notify_assignee &&
    mail.note.trim() === "" &&
    mail.subject.trim() === "" &&
    mail.body.trim() === "" &&
    mail.buttons === null &&
    mail.cc_user_ids.length === 0 &&
    !mail.notify_requestor_on_approve &&
    mail.requestor_subject.trim() === "" &&
    mail.requestor_body.trim() === "" &&
    mail.requestor_buttons === null
  ) {
    return undefined;
  }

  return mail;
}

type Props = {
  step: EApprovalWorkflowStepInput;
  formName: string;
  approverOptions: { id: string; label: string }[];
  mailSettings: FormMailSettings;
  onUpdate: (patch: Partial<EApprovalWorkflowStepInput>) => void;
};

export function EApprovalWorkflowStepMail({ step, formName, approverOptions, mailSettings, onUpdate }: Props) {
  const [open, setOpen] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const mail = mailFromStep(step);
  const statusLabel = step.label ?? "";
  const sample = defaultMailSample(formName, statusLabel);
  const ccLabels = mail.cc_user_ids
    .map((id) => approverOptions.find((option) => option.id === id)?.label ?? id)
    .join(", ");

  const setMail = (patch: Partial<StepMail>) => {
    onUpdate({ mail: mailOrUndefined({ ...mail, ...patch }) ?? null });
  };

  const preview = previewStepOpenMail(mailSettings, sample);

  return (
    <div className="mt-4 space-y-3">
      <label className="block space-y-1">
        <span className="text-xs font-medium text-muted-foreground">Status label</span>
        <Input
          value={statusLabel}
          maxLength={60}
          placeholder="Pending"
          onChange={(event) => onUpdate({ label: event.target.value })}
        />
        <p className="text-[11px] text-muted-foreground">
          Badge and approval email use “{stepStatusPreview(mailSettings, statusLabel)}”. Leave blank to keep{" "}
          {stepStatusPreview(mailSettings, "")}.
        </p>
      </label>

      <div>
        <button
          type="button"
          className="flex items-center gap-1 text-xs font-medium text-primary"
          onClick={() => setOpen((current) => !current)}
        >
          {open ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
          {open ? "Hide email" : "Email"}
        </button>
        {open ? (
          <div className="mt-2 space-y-3 rounded-lg border border-border bg-muted/20 px-3 py-3">
            <div className="flex items-start justify-between gap-3">
              <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">Notify the approver</p>
                <p className="text-xs text-muted-foreground">
                  On by default. Subject, body, and buttons are set on the Email tab. Subject: {preview.subject}
                </p>
              </div>
              <Switch
                aria-label="Notify the approver"
                checked={mail.notify_assignee}
                onCheckedChange={(checked) => setMail({ notify_assignee: Boolean(checked) })}
              />
            </div>
            <label className="block space-y-1">
              <span className="text-xs font-medium text-muted-foreground">Also copy</span>
              <Select
                value=""
                onChange={(event) => {
                  const id = event.target.value;
                  if (!id || mail.cc_user_ids.includes(id) || mail.cc_user_ids.length >= 10) {
                    return;
                  }
                  setMail({ cc_user_ids: [...mail.cc_user_ids, id] });
                }}
              >
                <option value="">Add a person</option>
                {approverOptions.map((user) => (
                  <option key={user.id} value={user.id}>
                    {user.label}
                  </option>
                ))}
              </Select>
              {ccLabels !== "" ? (
                <ul className="space-y-1 pt-1">
                  {mail.cc_user_ids.map((id) => (
                    <li key={id} className="flex items-center justify-between gap-2 text-xs">
                      <span className="truncate">{approverOptions.find((option) => option.id === id)?.label ?? id}</span>
                      <button
                        type="button"
                        className="text-muted-foreground hover:text-foreground"
                        onClick={() => setMail({ cc_user_ids: mail.cc_user_ids.filter((item) => item !== id) })}
                      >
                        Remove
                      </button>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-[11px] text-muted-foreground">No extra copies. Up to 10 people.</p>
              )}
            </label>
            <Button type="button" size="sm" variant="outline" onClick={() => setPreviewOpen(true)}>
              Preview email
            </Button>
            <div className="space-y-3 border-t border-border pt-3">
              <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                  <p className="text-sm font-medium text-foreground">Email the requestor when this step is approved</p>
                  <p className="text-xs text-muted-foreground">Off by default. The final approval email is unchanged.</p>
                </div>
                <Switch
                  aria-label="Email the requestor when this step is approved"
                  checked={mail.notify_requestor_on_approve}
                  onCheckedChange={(checked) => setMail({ notify_requestor_on_approve: Boolean(checked) })}
                />
              </div>
              <p className="text-xs text-muted-foreground">
                The wording for that mail is Step approved on the Email tab.
              </p>
            </div>
          </div>
        ) : null}
      </div>
      <EApprovalMailPreviewDialog
        open={previewOpen}
        title="Step email"
        preview={preview}
        onOpenChange={setPreviewOpen}
      />
    </div>
  );
}
