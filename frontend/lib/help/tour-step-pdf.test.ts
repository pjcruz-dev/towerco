import { PDFDocument } from "pdf-lib";
import { describe, expect, it } from "vitest";

import type { LiveTourStep } from "@/lib/help/e-approval-live-tour";
import { buildTourStepPdf, toPdfText, tourStepHighlight, tourStepScreenshot } from "@/lib/help/tour-step-pdf";

function step(partial: Partial<LiveTourStep> & Pick<LiveTourStep, "id" | "title" | "body">): LiveTourStep {
  return {
    path: "/ticketing",
    target: "tk-nav",
    ...partial,
  };
}

describe("tour step PDF", () => {
  it("keeps one page per step and strips punctuation Helvetica cannot draw", async () => {
    expect(toPdfText("You're finished - Step Show")).toBe("You're finished - Step Show");
    expect(toPdfText("You're finished · follow-up")).toBe("You're finished - follow-up");

    const bytes = await buildTourStepPdf({
      title: "Ticketing tour",
      fileName: "ticketing-tour.pdf",
      chapterLabels: { track: "Track tickets" },
      steps: [
        step({ id: "a", chapter: "overview", title: "Open Ticketing", body: "Raise issues and track the queue." }),
        step({
          id: "b",
          chapter: "track",
          title: "Ticket queue",
          body: "Status, priority, and SLA.",
          missingHint: "Sample row appears while the tour is active.",
        }),
      ],
    });

    const doc = await PDFDocument.load(bytes);
    expect(doc.getPageCount()).toBe(2);
  });

  it("attaches the tour screenshot for every step", () => {
    expect(
      tourStepScreenshot(step({ id: "overview-kpis", chapter: "overview", title: "Status cards", body: "Counts." })),
    ).toBe("/help/ticketing/01-overview.png");
    expect(
      tourStepScreenshot(
        step({
          id: "compose-title",
          chapter: "create",
          path: "/ticketing/tickets/new",
          title: "Title",
          body: "Summarize.",
        }),
      ),
    ).toBe("/help/ticketing/03-new-ticket.png");
    expect(
      tourStepScreenshot(
        step({
          id: "detail-activity",
          chapter: "view",
          path: "/e-approval/submissions/",
          title: "Activity",
          body: "Comments.",
        }),
      ),
    ).toBe("/help/e-approval/10-activity.png");
    expect(
      tourStepScreenshot(
        step({
          id: "fin-ca-compose-fields",
          chapter: "cash_advance",
          path: "/e-approval/request/tour-finance/cash-advance",
          title: "Fields",
          body: "Fill the form.",
        }),
      ),
    ).toBe("/help/e-approval/04-compose.png");
    expect(
      tourStepHighlight(
        step({
          id: "nav-e-approval",
          target: "ea-nav-e-approval",
          chapter: "overview",
          path: "/e-approval",
          title: "Open E-Forms",
          body: "Open it.",
        }),
      ),
    ).toEqual({ x: 0.4, y: 20, w: 16, h: 6 });
    expect(
      tourStepHighlight(
        step({
          id: "detail-manage",
          target: "tk-detail-manage",
          chapter: "manage",
          path: "/ticketing/tickets/",
          title: "Manage",
          body: "Triage.",
        }),
      )?.w,
    ).toBeGreaterThan(0);
  });
});
