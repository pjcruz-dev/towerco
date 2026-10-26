"use client";

import { useState } from "react";

import { EApprovalMailButtonsEditor } from "@/components/e-approval/e-approval-mail-buttons-editor";
import { EApprovalMailBodyField, EApprovalMailSubjectField, mailTokensForFields } from "@/components/e-approval/e-approval-mail-compose-field";
import { EApprovalMailPreviewDialog } from "@/components/e-approval/e-approval-mail-preview-dialog";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  DEFAULT_APPROVED_FILES_BUTTON,
  DEFAULT_APPROVER_BUTTON,
  DEFAULT_REQUESTOR_BUTTON,
  FORM_MAIL_EVENT_KEYS,
  FORM_MAIL_EVENT_LABELS,
  FORM_MAIL_STATUS_DEFAULTS,
  FORM_MAIL_STATUS_KEYS,
  defaultMailSample,
  eventSubjectPreview,
  previewEventMail,
  type FormMailEventKey,
  type FormMailSettings,
  type FormMailStatusKey,
} from "@/modules/e-approval/form-mail-config";

const STATUS_LABELS: Record<FormMailStatusKey, string> = {
  pending: "Pending",
  approved: "Approved",
  rejected: "Rejected",
  returned: "Needs revision",
  cancelled: "Cancelled",
  awaiting_dcf: "Awaiting document control",
};

type Props = {
  value: FormMailSettings;
  formName: string;
  fields: { name: string; label: string }[];
  onChange: (next: FormMailSettings) => void;
  disabled?: boolean;
};

export function EApprovalFormMailCard({ value, formName, fields, onChange, disabled }: Props) {
  const [previewKey, setPreviewKey] = useState<FormMailEventKey | null>(null);
  const sample = defaultMailSample(formName, "");
  const tokens = mailTokensForFields(fields);
  const setStatus = (key: FormMailStatusKey, label: string) => {
    onChange({ ...value, statuses: { ...value.statuses, [key]: label } });
  };

  return (
    <EApprovalSectionCard
      title="Email and status"
      description="Subject, body, and buttons are edited here. A workflow step only chooses whether that mail is sent and who is copied. Blank fields keep the standard wording."
    >
      <div className="space-y-2">
        <h3 className="text-sm font-medium text-foreground">Status names</h3>
        <p className="text-xs text-muted-foreground">
          These words appear on the request badge and in the matching email.
        </p>
        <div className="grid gap-3 sm:grid-cols-2">
          {FORM_MAIL_STATUS_KEYS.map((key) => (
            <div key={key} className="space-y-1">
              <Label htmlFor={`ea-mail-status-${key}`}>{STATUS_LABELS[key]}</Label>
              <Input
                id={`ea-mail-status-${key}`}
                value={value.statuses[key]}
                maxLength={60}
                disabled={disabled}
                placeholder={FORM_MAIL_STATUS_DEFAULTS[key]}
                onChange={(event) => setStatus(key, event.target.value)}
              />
            </div>
          ))}
        </div>
      </div>

      <div className="mt-5 space-y-3">
        <h3 className="text-sm font-medium text-foreground">Form emails</h3>
        {FORM_MAIL_EVENT_KEYS.map((key) => {
          const event = value.events[key];
          return (
            <div key={key} className="rounded-lg border border-border bg-muted/20 px-3 py-3">
              <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                  <Label htmlFor={`ea-mail-event-${key}`}>{FORM_MAIL_EVENT_LABELS[key].title}</Label>
                  <p className="text-xs text-muted-foreground">Subject: {eventSubjectPreview(value, key)}</p>
                  {key === "approved_files" ? (
                    <p className="text-xs text-muted-foreground">
                      This is the email that attaches the approved files. Turning it off stops that email. The document, form, and sign-off lines stay.
                    </p>
                  ) : null}
                </div>
                <Switch
                  id={`ea-mail-event-${key}`}
                  checked={event.enabled}
                  disabled={disabled}
                  onCheckedChange={(checked) =>
                    onChange({
                      ...value,
                      events: { ...value.events, [key]: { ...event, enabled: Boolean(checked) } },
                    })
                  }
                />
              </div>
              <div className="mt-3 space-y-1">
                <span className="text-xs font-medium text-muted-foreground">Subject</span>
                <EApprovalMailSubjectField
                  value={event.subject}
                  disabled={disabled || !event.enabled}
                  placeholder={eventSubjectPreview(value, key, sample)}
                  tokens={tokens}
                  onChange={(subject) =>
                    onChange({
                      ...value,
                      events: { ...value.events, [key]: { ...event, subject } },
                    })
                  }
                />
              </div>
              <div className="mt-3 space-y-1">
                <span className="text-xs font-medium text-muted-foreground">Body</span>
                <EApprovalMailBodyField
                  value={event.body}
                  disabled={disabled || !event.enabled}
                  tokens={tokens}
                  onChange={(body) =>
                    onChange({
                      ...value,
                      events: { ...value.events, [key]: { ...event, body: body.slice(0, 8000) } },
                    })
                  }
                />
              </div>
              <div className="mt-3 space-y-1">
                <p className="text-xs font-medium text-muted-foreground">Buttons</p>
                <EApprovalMailButtonsEditor
                  value={event.buttons}
                  fallback={
                    key === "approved_files"
                      ? DEFAULT_APPROVED_FILES_BUTTON
                      : key === "approval_assigned"
                        ? DEFAULT_APPROVER_BUTTON
                        : DEFAULT_REQUESTOR_BUTTON
                  }
                  onChange={(buttons) =>
                    onChange({
                      ...value,
                      events: { ...value.events, [key]: { ...event, buttons } },
                    })
                  }
                />
              </div>
              <Button type="button" size="sm" variant="outline" className="mt-3" onClick={() => setPreviewKey(key)}>
                Preview email
              </Button>
            </div>
          );
        })}
      </div>
      <EApprovalMailPreviewDialog
        open={previewKey !== null}
        title={previewKey ? FORM_MAIL_EVENT_LABELS[previewKey].title : "Email"}
        preview={previewEventMail(value, previewKey ?? "submitted", sample)}
        onOpenChange={(open) => {
          if (!open) {
            setPreviewKey(null);
          }
        }}
      />
    </EApprovalSectionCard>
  );
}
