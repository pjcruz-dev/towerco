import { describe, expect, it } from "vitest";

import {
  eventSubjectPreview,
  formMailSettingsFromMetadata,
  isMailHtml,
  mailInlineHtml,
  mailTokenQuery,
  defaultMailSample,
  previewEventMail,
  sanitizeMailUrl,
  mergeFormMailSettingsIntoMetadata,
  stepMailSubjectPreview,
  stepStatusPreview,
} from "@/modules/e-approval/form-mail-config";

describe("mail writing helpers", () => {
  it("detects a field token as it is typed and keeps bold text", () => {
    expect(mailTokenQuery("Hello {{doc")).toBe("doc");
    expect(mailTokenQuery("Hello {{document_no}}")).toBeNull();
    expect(mailInlineHtml("**Please** review *today*")).toContain("<strong>Please</strong>");
    expect(mailInlineHtml("**Please** review *today*")).toContain("<em>today</em>");
    expect(mailInlineHtml("### Heading")).toContain("font-semibold");
    expect(mailInlineHtml("- Bring **ID**")).toContain("• Bring <strong>ID</strong>");
    expect(mailInlineHtml("> A note")).toContain("A note");
    expect(mailInlineHtml("[Open](https://example.com/file)")).toContain('href="https://example.com/file"');
    expect(mailInlineHtml("[Open](javascript:alert(1))")).not.toContain("<a ");
    expect(sanitizeMailUrl("https://example.com/a")).toBe("https://example.com/a");
    expect(sanitizeMailUrl("javascript:alert(1)")).toBeNull();
    expect(isMailHtml("<p>Hello <strong>Ada</strong></p>")).toBe(true);
    const settings = formMailSettingsFromMetadata({
      notifications: { events: { approved: { body: "<p>Hello <strong>{{requestor_name}}</strong></p>" } } },
    });
    expect(settings.events.approved.body).toContain("<strong>{{requestor_name}}</strong>");
    expect(previewEventMail(settings, "approved", {
      documentNo: "DOC-0001",
      formName: "Leave",
      stepLabel: "Pending",
      requestorName: "Ada",
      actorName: "Sam",
    }).html).toContain("<strong>Ada</strong>");
  });
});

describe("form mail settings", () => {
  it("keeps the standard emails when nothing is set", () => {
    const settings = formMailSettingsFromMetadata({});
    expect(settings.events.approved.enabled).toBe(true);
    expect(settings.events.approved.note).toBe("");
    expect(settings.statuses.pending).toBe("");
    expect(mergeFormMailSettingsIntoMetadata({ form_family: "cash_advance" }, settings)).toEqual({
      form_family: "cash_advance",
    });
    expect(eventSubjectPreview(settings, "approved")).toBe("Request approved — DOC-0001");
    expect(stepMailSubjectPreview(settings, "")).toBe("Approval required — DOC-0001");
    expect(stepStatusPreview(settings, "Release")).toBe("Pending — Release");
  });

  it("stores only the overrides and uses them in the subject preview", () => {
    const settings = formMailSettingsFromMetadata({
      notifications: {
        statuses: { approved: "Cleared", pending: "For sign-off" },
        events: { rejected: { enabled: false, note: "Contact finance." } },
      },
    });

    expect(settings.statuses.approved).toBe("Cleared");
    expect(settings.events.rejected.enabled).toBe(false);
    expect(settings.events.submitted.enabled).toBe(true);

    const merged = mergeFormMailSettingsIntoMetadata({}, settings);
    expect(merged.notifications).toEqual({
      statuses: { pending: "For sign-off", approved: "Cleared" },
      events: {
        rejected: { enabled: false, note: "Contact finance.", subject: "", body: "", buttons: null },
      },
    });
    expect(eventSubjectPreview(settings, "approved")).toBe("Cleared — DOC-0001");
    settings.events.approved.subject = "Cleared {{document_no}}";
    settings.events.approved.buttons = [{ label: "Open the file", target: "open_submission" }];
    const withCopy = mergeFormMailSettingsIntoMetadata({}, settings);
    const approved = (withCopy.notifications as { events: { approved: { subject: string; buttons: unknown } } }).events
      .approved;
    expect(approved.subject).toBe("Cleared {{document_no}}");
    expect(approved.buttons).toEqual([{ label: "Open the file", target: "open_submission" }]);
    expect(stepMailSubjectPreview(settings, "Department")).toBe("For sign-off — Department — DOC-0001");
    expect(stepStatusPreview(settings, "Department")).toBe("For sign-off — Department");
    const files = previewEventMail(formMailSettingsFromMetadata({}), "approved_files", defaultMailSample("ISO", ""));
    expect(files.subject).toBe("Approved files — DOC-0001");
    expect(files.lines).toContain("This request is fully approved. The current files are attached.");
    expect(files.buttons[0]?.label).toBe("Open review");
    settings.events.approved_files.subject = "Approved ";
    const withSpace = mergeFormMailSettingsIntoMetadata({}, settings);
    expect(formMailSettingsFromMetadata(withSpace).events.approved_files.subject).toBe("Approved ");
  });
});
