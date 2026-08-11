"use client";

import { Checkbox } from "@/components/ui/checkbox";
import {
  EMAIL_RECIPIENT_OPTIONS,
  GATE_EMAIL_EVENT_KEYS,
  GATE_EMAIL_EVENT_LABELS,
  type EmailNotificationPolicies,
  type EmailNotificationRecipient,
  type GateEmailEventKey,
} from "@/lib/rollout/email-notification-policies";
import { cn } from "@/lib/utils";

type Props = {
  value: EmailNotificationPolicies;
  onChange: (value: EmailNotificationPolicies) => void;
  disabled?: boolean;
  className?: string;
};

export function EmailNotificationPolicyEditor({ value, onChange, disabled = false, className }: Props) {
  const gate = value.gate_approval;

  function setGateEnabled(enabled: boolean) {
    onChange({
      gate_approval: {
        ...gate,
        enabled,
      },
    });
  }

  function setEventEnabled(eventKey: GateEmailEventKey, enabled: boolean) {
    onChange({
      gate_approval: {
        ...gate,
        events: {
          ...gate.events,
          [eventKey]: {
            ...gate.events[eventKey],
            enabled,
          },
        },
      },
    });
  }

  function toggleRecipient(eventKey: GateEmailEventKey, recipient: EmailNotificationRecipient) {
    const current = gate.events[eventKey].recipients;
    const next = current.includes(recipient)
      ? current.filter((item) => item !== recipient)
      : [...current, recipient];

    onChange({
      gate_approval: {
        ...gate,
        events: {
          ...gate.events,
          [eventKey]: {
            ...gate.events[eventKey],
            recipients: next,
          },
        },
      },
    });
  }

  return (
    <div className={cn("space-y-4", className)}>
      <label className="flex items-center gap-2 text-sm">
        <Checkbox
          className="size-4"
          checked={gate.enabled}
          disabled={disabled}
          onCheckedChange={(v) => setGateEnabled(v === true)}
        />
        <span className="font-medium text-foreground">Gate approval emails</span>
        <span className="text-muted-foreground">(master switch)</span>
      </label>

      <div className="overflow-x-auto rounded-lg border border-border">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="border-b border-border bg-muted/40 text-muted-foreground">
            <tr>
              <th className="px-3 py-2 font-medium">Event</th>
              <th className="w-24 px-3 py-2 font-medium">Enabled</th>
              <th className="px-3 py-2 font-medium">Recipients</th>
            </tr>
          </thead>
          <tbody>
            {GATE_EMAIL_EVENT_KEYS.map((eventKey) => {
              const event = gate.events[eventKey];
              const rowDisabled = disabled || !gate.enabled;

              return (
                <tr key={eventKey} className="border-b border-border/60">
                  <td className="px-3 py-3 align-top">
                    <p className="font-medium text-foreground">{GATE_EMAIL_EVENT_LABELS[eventKey]}</p>
                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">{eventKey}</p>
                  </td>
                  <td className="px-3 py-3 align-top">
                    <Checkbox
                      className="size-4"
                      checked={event.enabled}
                      disabled={rowDisabled}
                      onCheckedChange={(v) => setEventEnabled(eventKey, v === true)}
                    />
                  </td>
                  <td className="px-3 py-3 align-top">
                    <div className="flex flex-wrap gap-2">
                      {EMAIL_RECIPIENT_OPTIONS.map((option) => {
                        const checked = event.recipients.includes(option.value);
                        return (
                          <label
                            key={option.value}
                            className={cn(
                              "inline-flex cursor-pointer items-center gap-1.5 rounded-md border px-2 py-1 text-xs",
                              checked
                                ? "border-primary/40 bg-primary/10 text-foreground"
                                : "border-border text-muted-foreground",
                              rowDisabled || !event.enabled ? "cursor-not-allowed opacity-50" : "",
                            )}
                          >
                            <Checkbox
                              className="size-3"
                              checked={checked}
                              disabled={rowDisabled || !event.enabled}
                              onCheckedChange={() => toggleRecipient(eventKey, option.value)}
                            />
                            {option.label}
                          </label>
                        );
                      })}
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
