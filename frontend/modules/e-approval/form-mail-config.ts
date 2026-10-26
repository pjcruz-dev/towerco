export const FORM_MAIL_STATUS_KEYS = [
  "pending",
  "approved",
  "rejected",
  "returned",
  "cancelled",
  "awaiting_dcf",
] as const;

export const FORM_MAIL_EVENT_KEYS = [
  "approval_assigned",
  "step_approved",
  "submitted",
  "approved",
  "approved_files",
  "rejected",
  "returned",
  "cancelled",
] as const;

export type FormMailStatusKey = (typeof FORM_MAIL_STATUS_KEYS)[number];
export type FormMailEventKey = (typeof FORM_MAIL_EVENT_KEYS)[number];

export type MailButtonTarget = "open_submission" | "open_approval";

export type MailButton = {
  label: string;
  target: MailButtonTarget;
};

export type FormMailEventSetting = {
  enabled: boolean;
  note: string;
  subject: string;
  body: string;
  /** null keeps the standard button. An empty list removes it. */
  buttons: MailButton[] | null;
};

export type FormMailSettings = {
  statuses: Record<FormMailStatusKey, string>;
  events: Record<FormMailEventKey, FormMailEventSetting>;
};

export const FORM_MAIL_STATUS_DEFAULTS: Record<FormMailStatusKey, string> = {
  pending: "Pending",
  approved: "Approved",
  rejected: "Rejected",
  returned: "Needs revision",
  cancelled: "Cancelled",
  awaiting_dcf: "Awaiting document control",
};

export const FORM_MAIL_EVENT_LABELS: Record<FormMailEventKey, { title: string; subject: string }> = {
  approval_assigned: { title: "Approval required", subject: "Approval required — DOC-0001" },
  step_approved: { title: "Step approved", subject: "Step approved — DOC-0001" },
  submitted: { title: "Request submitted", subject: "Request submitted — DOC-0001" },
  approved: { title: "Fully approved", subject: "Request approved — DOC-0001" },
  approved_files: { title: "Approved files", subject: "Approved files — DOC-0001" },
  rejected: { title: "Rejected", subject: "Request rejected — DOC-0001" },
  returned: { title: "Returned for revision", subject: "Revision requested — DOC-0001" },
  cancelled: { title: "Cancelled", subject: "Request cancelled — DOC-0001" },
};

const NOTE_MAX = 500;
const BODY_MAX = 8000;
const SUBJECT_MAX = 180;
const LABEL_MAX = 60;

export const MAIL_SYSTEM_TOKENS: { token: string; label: string }[] = [
  { token: "document_no", label: "Document number" },
  { token: "form_name", label: "Form name" },
  { token: "step_label", label: "Step label" },
  { token: "requestor_name", label: "Requestor" },
  { token: "actor_name", label: "Actor" },
];

export function mailTokenQuery(textBeforeCaret: string): string | null {
  const match = textBeforeCaret.match(/\{\{([A-Za-z0-9_]*)$/);
  return match ? match[1] : null;
}

export function sanitizeMailUrl(value: string): string | null {
  try {
    const url = new URL(value.trim());
    if (url.protocol !== "http:" && url.protocol !== "https:") {
      return null;
    }

    return url.toString();
  } catch {
    return null;
  }
}

export function mailInlineHtml(source: string): string {
  const escaped = source
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");

  return escaped
    .replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" class="underline">$1</a>')
    .replace(/^### (.+)$/gm, '<span class="text-base font-semibold">$1</span>')
    .replace(/^&gt; (.+)$/gm, '<span class="block border-l-2 border-border pl-2">$1</span>')
    .replace(/^[-*] (.+)$/gm, "• $1")
    .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
    .replace(/(^|[^*])\*(.+?)\*/g, "$1<em>$2</em>");
}

export const DEFAULT_APPROVER_BUTTON: MailButton = {
  label: "Open approval inbox",
  target: "open_approval",
};

export const DEFAULT_REQUESTOR_BUTTON: MailButton = {
  label: "Open submission",
  target: "open_submission",
};

export const DEFAULT_APPROVED_FILES_BUTTON: MailButton = {
  label: "Open review",
  target: "open_submission",
};

export type MailSample = {
  documentNo: string;
  formName: string;
  stepLabel: string;
  requestorName: string;
  actorName: string;
};

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

function cleanLabel(value: unknown): string {
  return String(value ?? "")
    .replace(/<[^>]*>/g, "")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, LABEL_MAX);
}

function cleanNote(value: unknown): string {
  return String(value ?? "")
    .replace(/<[^>]*>/g, "")
    .trim()
    .slice(0, NOTE_MAX);
}

function emptyEvent(): FormMailEventSetting {
  return { enabled: true, note: "", subject: "", body: "", buttons: null };
}

function emptySettings(): FormMailSettings {
  return {
    statuses: {
      pending: "",
      approved: "",
      rejected: "",
      returned: "",
      cancelled: "",
      awaiting_dcf: "",
    },
    events: {
      approval_assigned: emptyEvent(),
      step_approved: emptyEvent(),
      submitted: emptyEvent(),
      approved: emptyEvent(),
      approved_files: emptyEvent(),
      rejected: emptyEvent(),
      returned: emptyEvent(),
      cancelled: emptyEvent(),
    },
  };
}

function editorSubject(value: unknown): string {
  return String(value ?? "")
    .replace(/<[^>]*>/g, "")
    .replace(/\s+/g, " ")
    .slice(0, SUBJECT_MAX);
}

function cleanSubject(value: unknown): string {
  return editorSubject(value).trim();
}

export function isMailHtml(value: string): boolean {
  return /<(?:p|div|span|table|thead|tbody|tr|td|th|ul|ol|li|h[1-4]|blockquote|strong|b|em|i|u|a|br|font)\b/i.test(value);
}

function cleanBody(value: unknown): string {
  const text = String(value ?? "").trim();
  if (text === "") {
    return "";
  }
  if (isMailHtml(text)) {
    return text.slice(0, BODY_MAX);
  }

  return text.replace(/<[^>]*>/g, "").trim().slice(0, BODY_MAX);
}

export function parseMailButtons(value: unknown): MailButton[] | null {
  if (value == null) {
    return null;
  }
  if (!Array.isArray(value)) {
    return null;
  }

  const buttons: MailButton[] = [];
  for (const item of value) {
    const row = asRecord(item);
    if (!row) {
      continue;
    }
    const label = cleanLabel(row.label).replace(/[[\]()]/g, "");
    if (!label) {
      continue;
    }
    buttons.push({
      label,
      target: row.target === "open_approval" ? "open_approval" : "open_submission",
    });
    if (buttons.length >= 2) {
      break;
    }
  }

  return buttons;
}

export function formMailSettingsFromMetadata(metadata: unknown): FormMailSettings {
  const settings = emptySettings();
  const root = asRecord(asRecord(metadata)?.notifications);
  const statuses = asRecord(root?.statuses);
  const events = asRecord(root?.events);

  if (statuses) {
    for (const key of FORM_MAIL_STATUS_KEYS) {
      if (key in statuses) {
        settings.statuses[key] = cleanLabel(statuses[key]);
      }
    }
  }

  if (events) {
    for (const key of FORM_MAIL_EVENT_KEYS) {
      const row = asRecord(events[key]);
      if (!row) {
        continue;
      }
      if ("enabled" in row) {
        settings.events[key].enabled = row.enabled !== false && row.enabled !== "false" && row.enabled !== 0;
      }
      if ("note" in row) {
        settings.events[key].note = cleanNote(row.note);
      }
      if ("subject" in row) {
        settings.events[key].subject = editorSubject(row.subject);
      }
      if ("body" in row) {
        settings.events[key].body = cleanBody(row.body);
      }
      if ("buttons" in row) {
        settings.events[key].buttons = parseMailButtons(row.buttons);
      }
    }
  }

  return settings;
}

export function mergeFormMailSettingsIntoMetadata(
  metadata: Record<string, unknown>,
  settings: FormMailSettings,
): Record<string, unknown> {
  const next = { ...metadata };
  const statuses: Record<string, string> = {};
  const events: Record<string, FormMailEventSetting> = {};

  for (const key of FORM_MAIL_STATUS_KEYS) {
    const label = cleanLabel(settings.statuses[key]);
    if (label !== "") {
      statuses[key] = label;
    }
  }

  for (const key of FORM_MAIL_EVENT_KEYS) {
    const event = settings.events[key];
    const note = cleanNote(event.note);
    const subject = editorSubject(event.subject);
    const body = cleanBody(event.body);
    const buttons = event.buttons;
    if (!event.enabled || note !== "" || subject.trim() !== "" || body !== "" || buttons !== null) {
      events[key] = { enabled: event.enabled, note, subject: subject.trim() === "" ? "" : subject, body, buttons };
    }
  }

  if (Object.keys(statuses).length === 0 && Object.keys(events).length === 0) {
    delete next.notifications;
    return next;
  }

  next.notifications = {
    ...(Object.keys(statuses).length > 0 ? { statuses } : {}),
    ...(Object.keys(events).length > 0 ? { events } : {}),
  };

  return next;
}

export function statusWord(settings: FormMailSettings, key: FormMailStatusKey): string {
  const custom = cleanLabel(settings.statuses[key]);
  return custom !== "" ? custom : FORM_MAIL_STATUS_DEFAULTS[key];
}

export function eventSubjectPreview(settings: FormMailSettings, key: FormMailEventKey, sample?: MailSample): string {
  const subject = cleanSubject(settings.events[key].subject);
  const filled = sample ?? defaultMailSample("Form", "");
  if (subject !== "") {
    return fillMailTokens(subject, filled);
  }
  const custom = cleanLabel(settings.statuses[key === "submitted" ? "pending" : key]);
  if (
    key === "submitted"
    || key === "approval_assigned"
    || key === "step_approved"
    || key === "approved_files"
    || custom === ""
  ) {
    return FORM_MAIL_EVENT_LABELS[key].subject.replace("DOC-0001", filled.documentNo);
  }

  return `${custom} — ${filled.documentNo}`;
}

export function defaultMailSample(formName: string, stepLabel: string): MailSample {
  return {
    documentNo: "DOC-0001",
    formName: formName.trim() || "Form",
    stepLabel: stepLabel.trim() || "Pending",
    requestorName: "Sample requestor",
    actorName: "Sample approver",
  };
}

export function fillMailTokens(text: string, sample: MailSample): string {
  return text
    .replaceAll("{{document_no}}", sample.documentNo)
    .replaceAll("{{form_name}}", sample.formName)
    .replaceAll("{{step_label}}", sample.stepLabel)
    .replaceAll("{{requestor_name}}", sample.requestorName)
    .replaceAll("{{actor_name}}", sample.actorName);
}

export function effectiveButtons(buttons: MailButton[] | null, fallback: MailButton): MailButton[] {
  return buttons === null ? [fallback] : buttons;
}

export function stepMailSubjectPreview(settings: FormMailSettings, stepLabel: string): string {
  const pending = cleanLabel(settings.statuses.pending);
  const label = cleanLabel(stepLabel);
  if (pending !== "" && label !== "") {
    return `${pending} — ${label} — DOC-0001`;
  }
  if (pending !== "") {
    return `${pending} — DOC-0001`;
  }

  return "Approval required — DOC-0001";
}

export function stepStatusPreview(settings: FormMailSettings, stepLabel: string): string {
  const word = statusWord(settings, "pending");
  const label = cleanLabel(stepLabel);
  return label !== "" ? `${word} — ${label}` : word;
}

export type MailPreviewModel = {
  subject: string;
  lines: string[];
  html: string | null;
  buttons: MailButton[];
};

export function previewEventMail(settings: FormMailSettings, key: FormMailEventKey, sample: MailSample): MailPreviewModel {
  const event = settings.events[key];
  const body = cleanBody(event.body);
  const lines = [
    `Document: ${sample.documentNo}`,
    `Form: ${sample.formName}`,
    `Requestor: ${sample.requestorName}`,
  ];
  let html: string | null = null;
  if (isMailHtml(body)) {
    html = fillMailTokens(body, sample);
  } else if (body !== "") {
    lines.push(...body.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => fillMailTokens(line, sample)));
  } else {
    lines.push(defaultEventLine(key));
    const note = cleanNote(event.note);
    if (note !== "") {
      lines.push(fillMailTokens(note, sample));
    }
  }

  return {
    subject: eventSubjectPreview(settings, key, sample),
    lines,
    html,
    buttons: effectiveButtons(
      event.buttons,
      key === "approval_assigned"
        ? DEFAULT_APPROVER_BUTTON
        : key === "approved_files"
          ? DEFAULT_APPROVED_FILES_BUTTON
          : DEFAULT_REQUESTOR_BUTTON,
    ),
  };
}

export function previewStepOpenMail(settings: FormMailSettings, sample: MailSample): MailPreviewModel {
  const preview = previewEventMail(settings, "approval_assigned", sample);
  if (sample.stepLabel && sample.stepLabel !== "Pending") {
    preview.lines.push(`Status: ${stepStatusPreview(settings, sample.stepLabel)}`);
  }

  return preview;
}

function defaultEventLine(key: FormMailEventKey): string {
  switch (key) {
    case "approval_assigned":
      return "You have a pending approval step. Please review and decide when ready.";
    case "step_approved":
      return "This step was approved. The request continues to the next step.";
    case "submitted":
      return "Your request was submitted successfully and is pending approval.";
    case "approved":
      return "Your request was approved.";
    case "approved_files":
      return "This request is fully approved. The current files are attached.";
    case "rejected":
      return "Your request was rejected.";
    case "returned":
      return "Your request was returned for revision.";
    case "cancelled":
      return "This request was cancelled. No further approval action is required.";
  }
}
