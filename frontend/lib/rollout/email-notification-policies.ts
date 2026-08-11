export type EmailNotificationRecipient =
  | "current_approver"
  | "requester"
  | "pmo_owner"
  | "saq_owner"
  | "cme_lead";

export type GateEmailEventKey =
  | "submitted"
  | "step_approved"
  | "approved"
  | "rejected"
  | "escalated";

export type GateEmailEventPolicy = {
  enabled: boolean;
  recipients: EmailNotificationRecipient[];
};

export type GateApprovalEmailPolicy = {
  enabled: boolean;
  events: Record<GateEmailEventKey, GateEmailEventPolicy>;
};

export type EmailNotificationPolicies = {
  gate_approval: GateApprovalEmailPolicy;
};

export const GATE_EMAIL_EVENT_KEYS: GateEmailEventKey[] = [
  "submitted",
  "step_approved",
  "approved",
  "rejected",
  "escalated",
];

export const EMAIL_RECIPIENT_OPTIONS: { value: EmailNotificationRecipient; label: string }[] = [
  { value: "current_approver", label: "Current approver(s)" },
  { value: "requester", label: "Requester" },
  { value: "pmo_owner", label: "PMO owner" },
  { value: "saq_owner", label: "SAQ owner" },
  { value: "cme_lead", label: "CME lead" },
];

export const GATE_EMAIL_EVENT_LABELS: Record<GateEmailEventKey, string> = {
  submitted: "Submitted for approval",
  step_approved: "Step approved (next approver)",
  approved: "Fully approved",
  rejected: "Rejected",
  escalated: "Escalation reminder",
};

export const defaultEmailNotificationPolicies = (): EmailNotificationPolicies => ({
  gate_approval: {
    enabled: true,
    events: {
      submitted: { enabled: true, recipients: ["current_approver"] },
      step_approved: { enabled: true, recipients: ["current_approver", "requester"] },
      approved: { enabled: true, recipients: ["requester"] },
      rejected: { enabled: true, recipients: ["requester"] },
      escalated: { enabled: true, recipients: ["current_approver"] },
    },
  },
});

const ALLOWED_RECIPIENTS = new Set<EmailNotificationRecipient>(
  EMAIL_RECIPIENT_OPTIONS.map((option) => option.value),
);

export function sanitizeEmailRecipients(recipients: string[] | undefined): EmailNotificationRecipient[] {
  if (!recipients?.length) {
    return [];
  }

  const out: EmailNotificationRecipient[] = [];
  for (const raw of recipients) {
    const key = raw.trim().toLowerCase() as EmailNotificationRecipient;
    if (ALLOWED_RECIPIENTS.has(key) && !out.includes(key)) {
      out.push(key);
    }
  }
  return out;
}

/** Merge API payload with defaults so every event is editable. */
export function normalizeEmailNotificationPolicies(
  raw: EmailNotificationPolicies | undefined | null,
): EmailNotificationPolicies {
  const base = defaultEmailNotificationPolicies();
  if (!raw?.gate_approval) {
    return base;
  }

  const gate = raw.gate_approval;
  const events = { ...base.gate_approval.events };

  for (const key of GATE_EMAIL_EVENT_KEYS) {
    const event = gate.events?.[key];
    if (!event) {
      continue;
    }
    events[key] = {
      enabled: Boolean(event.enabled),
      recipients: sanitizeEmailRecipients(event.recipients),
    };
  }

  return {
    gate_approval: {
      enabled: gate.enabled ?? base.gate_approval.enabled,
      events,
    },
  };
}
