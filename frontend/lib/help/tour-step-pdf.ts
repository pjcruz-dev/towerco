import type { LiveTourChapterId, LiveTourStep } from "@/lib/help/e-approval-live-tour";
import { LIVE_TOUR_CHAPTER_LABELS } from "@/lib/help/e-approval-live-tour";

export type TourStepPdfInput = {
  title: string;
  fileName: string;
  steps: LiveTourStep[];
  /** Overrides shared chapter names (Ticketing uses "Track tickets", not "Track requests"). */
  chapterLabels?: Partial<Record<LiveTourChapterId, string>>;
  /** PNG bytes keyed by the public screenshot path. */
  screenshots?: Record<string, Uint8Array>;
};

const PAGE_W = 595.28;
const PAGE_H = 841.89;
const MARGIN_X = 40;
const MARGIN_TOP = 40;
const MARGIN_BOTTOM = 36;
const IMAGE_GAP = 14;

const TICKETING_OVERVIEW = "/help/ticketing/01-overview.png";
const TICKETING_TICKETS = "/help/ticketing/02-tickets.png";
const TICKETING_NEW = "/help/ticketing/03-new-ticket.png";
const TICKETING_DETAIL = "/help/ticketing/04-detail.png";

const E_APPROVAL_OVERVIEW = "/help/e-approval/01-overview.png";
const E_APPROVAL_SUBMISSIONS = "/help/e-approval/02-submissions.png";
const E_APPROVAL_PICKER = "/help/e-approval/03-new-submission.png";
const E_APPROVAL_COMPOSE = "/help/e-approval/04-compose.png";
const E_APPROVAL_DETAIL = "/help/e-approval/05-detail-request.png";
const E_APPROVAL_WORKFLOW = "/help/e-approval/06-detail-approvals.png";
const E_APPROVAL_INBOX = "/help/e-approval/07-approval-inbox.png";
const E_APPROVAL_ACTIVITY = "/help/e-approval/10-activity.png";

export type TourHighlightBox = {
  /** Percent of the screenshot, origin at the top-left. */
  x: number;
  y: number;
  w: number;
  h: number;
};

/**
 * Red box on the guide screenshot, matching the live tour highlight.
 * Percents are of the full screenshot, sidebar included.
 */
const TOUR_HIGHLIGHTS: Record<string, TourHighlightBox> = {
  "ea-nav-e-approval": { x: 0.4, y: 20, w: 16, h: 6 },
  "ea-nav-e-approval-overview": { x: 1.2, y: 26, w: 14.5, h: 4.5 },
  "ea-nav-e-approval-submissions": { x: 1.2, y: 31, w: 14.5, h: 4.5 },
  "ea-nav-e-approval-approvals": { x: 1.2, y: 39, w: 14.5, h: 4.5 },
  "ea-nav-settings": { x: 0.4, y: 52, w: 16, h: 5.5 },
  "ea-nav-e-approval-profile": { x: 1.2, y: 58, w: 14.5, h: 4.5 },
  "ea-overview-kpis": { x: 17, y: 15, w: 80, h: 14 },
  "ea-overview-awaiting": { x: 17, y: 31, w: 39, h: 20 },
  "ea-overview-attention": { x: 57, y: 31, w: 40, h: 20 },
  "ea-overview-quick-actions": { x: 68, y: 7.5, w: 29, h: 7 },
  "ea-submissions-filters": { x: 17, y: 17, w: 42, h: 7 },
  "ea-submissions-search": { x: 17, y: 24, w: 46, h: 7 },
  "ea-submissions-advanced-filters": { x: 17, y: 24, w: 55, h: 8 },
  "ea-submissions-gallery": { x: 17, y: 32, w: 78, h: 42 },
  "ea-submissions-table": { x: 17, y: 32, w: 78, h: 42 },
  "ea-submissions-step-show": { x: 48, y: 34, w: 22, h: 10 },
  "ea-submissions-status": { x: 58, y: 33, w: 16, h: 8 },
  "ea-submissions-new": { x: 80, y: 7.5, w: 17, h: 7 },
  "ea-submissions-card-actions": { x: 70, y: 36, w: 22, h: 10 },
  "ea-picker-search": { x: 17, y: 18, w: 50, h: 8 },
  "ea-picker-form-card": { x: 17, y: 28, w: 36, h: 16 },
  "ea-picker-start": { x: 17, y: 44, w: 18, h: 7 },
  "ea-compose-fields": { x: 17, y: 16, w: 66, h: 22 },
  "ea-compose-parent": { x: 17, y: 16, w: 66, h: 14 },
  "ea-compose-lines": { x: 17, y: 34, w: 66, h: 18 },
  "ea-compose-settlement": { x: 17, y: 40, w: 40, h: 12 },
  "ea-compose-totals": { x: 17, y: 48, w: 30, h: 10 },
  "ea-compose-upload": { x: 17, y: 38, w: 50, h: 14 },
  "ea-compose-save-draft": { x: 62, y: 52, w: 14, h: 7 },
  "ea-compose-submit": { x: 76, y: 52, w: 14, h: 7 },
  "ea-detail-header": { x: 17, y: 8, w: 70, h: 8 },
  "ea-detail-summary": { x: 17, y: 18, w: 78, h: 14 },
  "ea-detail-print": { x: 17, y: 31, w: 16, h: 6 },
  "ea-print-sample": { x: 17, y: 14, w: 78, h: 40 },
  "ea-print-doc-fields": { x: 17, y: 18, w: 70, h: 22 },
  "ea-print-approval-trail": { x: 17, y: 48, w: 70, h: 16 },
  "ea-print-attachments": { x: 17, y: 66, w: 70, h: 18 },
  "ea-detail-tabs": { x: 17, y: 38, w: 48, h: 6 },
  "ea-detail-attachments": { x: 17, y: 68, w: 70, h: 18 },
  "ea-detail-fields": { x: 17, y: 18, w: 70, h: 16 },
  "ea-detail-lines": { x: 17, y: 36, w: 70, h: 16 },
  "ea-detail-parent": { x: 17, y: 28, w: 40, h: 12 },
  "ea-detail-settlement": { x: 17, y: 42, w: 40, h: 12 },
  "ea-detail-totals": { x: 17, y: 50, w: 30, h: 10 },
  "ea-detail-related": { x: 17, y: 28, w: 46, h: 14 },
  "ea-detail-workflow-path": { x: 22, y: 36, w: 62, h: 28 },
  "ea-detail-comment": { x: 17, y: 58, w: 66, h: 16 },
  "ea-detail-decide-tab": { x: 48, y: 38, w: 12, h: 6 },
  "ea-detail-requestor-actions": { x: 17, y: 46, w: 50, h: 14 },
  "ea-detail-cancel": { x: 17, y: 50, w: 16, h: 6 },
  "ea-detail-follow-up": { x: 34, y: 50, w: 22, h: 6 },
  "ea-approvals-tabs": { x: 17, y: 17, w: 22, h: 6 },
  "ea-approvals-queue": { x: 17, y: 26, w: 78, h: 36 },
  "ea-profile-signature": { x: 17, y: 16, w: 66, h: 28 },
  "ea-profile-signature-modes": { x: 17, y: 22, w: 36, h: 7 },
  "ea-profile-signature-pad": { x: 17, y: 30, w: 50, h: 18 },
  "ea-profile-signature-consent": { x: 17, y: 50, w: 50, h: 10 },
  "ea-profile-signature-save": { x: 17, y: 62, w: 16, h: 6 },
  "ea-profile-signature-current": { x: 17, y: 70, w: 40, h: 12 },
  "ea-decide-panel": { x: 17, y: 44, w: 66, h: 28 },
  "ea-decide-signature": { x: 17, y: 46, w: 50, h: 14 },
  "ea-decide-signature-modes": { x: 17, y: 46, w: 32, h: 6 },
  "ea-decide-signature-pad": { x: 17, y: 53, w: 46, h: 12 },
  "ea-decide-approve": { x: 17, y: 70, w: 14, h: 6 },
  "ea-decide-remarks": { x: 17, y: 62, w: 50, h: 8 },
  "ea-decide-actions": { x: 17, y: 70, w: 42, h: 7 },
  "tk-nav-ticketing": { x: 0.4, y: 17, w: 16, h: 6 },
  "tk-nav-ticketing-overview": { x: 1.2, y: 23, w: 14.5, h: 4.5 },
  "tk-nav-ticketing-tickets": { x: 1.2, y: 28, w: 14.5, h: 4.5 },
  "tk-nav-ticketing-new": { x: 1.2, y: 33, w: 14.5, h: 4.5 },
  "tk-overview-kpis": { x: 17, y: 15, w: 80, h: 14 },
  "tk-overview-quick-actions": { x: 17, y: 32, w: 78, h: 12 },
  "tk-overview-recent": { x: 17, y: 46, w: 78, h: 28 },
  "tk-tickets-filters": { x: 17, y: 18, w: 48, h: 10 },
  "tk-tickets-scope": { x: 62, y: 20, w: 22, h: 8 },
  "tk-tickets-table": { x: 17, y: 32, w: 78, h: 40 },
  "tk-tickets-open": { x: 17, y: 34, w: 22, h: 8 },
  "tk-compose-title": { x: 17, y: 18, w: 66, h: 22 },
  "tk-compose-category": { x: 17, y: 48, w: 40, h: 12 },
  "tk-compose-attachments": { x: 17, y: 60, w: 66, h: 14 },
  "tk-compose-submit": { x: 17, y: 74, w: 16, h: 6 },
  "tk-detail-header": { x: 17, y: 14, w: 55, h: 12 },
  "tk-detail-description": { x: 17, y: 28, w: 55, h: 14 },
  "tk-detail-attachments": { x: 17, y: 42, w: 55, h: 14 },
  "tk-detail-activity": { x: 17, y: 56, w: 55, h: 22 },
  "tk-detail-manage": { x: 74, y: 28, w: 22, h: 36 },
};

/** Where the live tour's red box sits on this step's screenshot. */
export function tourStepHighlight(step: LiveTourStep): TourHighlightBox | null {
  return TOUR_HIGHLIGHTS[step.target] ?? null;
}

/** Screenshot from the tour guide for this coach-mark step. */
export function tourStepScreenshot(step: LiveTourStep): string | null {
  if (step.path.startsWith("/ticketing")) {
    return ticketingScreenshot(step);
  }
  if (step.path.startsWith("/e-approval")) {
    return eApprovalScreenshot(step);
  }
  return null;
}

function ticketingScreenshot(step: LiveTourStep): string {
  if (step.chapter === "create" || step.path.startsWith("/ticketing/tickets/new")) {
    return TICKETING_NEW;
  }
  if (step.path.startsWith("/ticketing/tickets/") && step.path !== "/ticketing/tickets") {
    return TICKETING_DETAIL;
  }
  if (
    step.chapter === "view" ||
    step.chapter === "manage" ||
    step.chapter === "track" ||
    step.path === "/ticketing/tickets"
  ) {
    return TICKETING_TICKETS;
  }
  return TICKETING_OVERVIEW;
}

function eApprovalScreenshot(step: LiveTourStep): string | null {
  const id = step.id;
  const path = step.path;

  if (path.startsWith("/e-approval/profile") || id.startsWith("profile-signature")) {
    return null;
  }
  if (id.includes("activity")) {
    return E_APPROVAL_ACTIVITY;
  }
  if (id.includes("workflow") || id.includes("approval-trail")) {
    return E_APPROVAL_WORKFLOW;
  }
  if (id.startsWith("decide-") || id === "nav-decide-approvals" || id.includes("follow-up") || id.includes("cancel") || id.includes("requestor")) {
    return E_APPROVAL_DETAIL;
  }
  if (path.startsWith("/e-approval/approvals") || id.includes("approvals")) {
    return E_APPROVAL_INBOX;
  }
  if (path.startsWith("/e-approval/request") || id.includes("compose-")) {
    return E_APPROVAL_COMPOSE;
  }
  if (path.startsWith("/e-approval/submissions/new") || id.startsWith("picker-")) {
    return E_APPROVAL_PICKER;
  }
  if (path.includes("/print") || id.includes("print") || path.startsWith("/e-approval/submissions/") || id.startsWith("detail-")) {
    return E_APPROVAL_DETAIL;
  }
  if (path.startsWith("/e-approval/submissions") || id.startsWith("submissions-") || id.startsWith("after-submit")) {
    return E_APPROVAL_SUBMISSIONS;
  }
  if (step.chapter === "approvals") {
    return E_APPROVAL_INBOX;
  }
  if (step.chapter === "signature") {
    return null;
  }
  return E_APPROVAL_OVERVIEW;
}

/** Helvetica is WinAnsi. Tour copy uses punctuation outside that set. */
export function toPdfText(value: string): string {
  return value
    .replace(/\u2018|\u2019/g, "'")
    .replace(/\u201c|\u201d/g, '"')
    .replace(/\u2013|\u2014/g, "-")
    .replace(/\u00b7/g, "-")
    .replace(/\u2026/g, "...")
    .replace(/[^\n\x20-\x7E]/g, "");
}

function wrapText(
  text: string,
  widthOf: (line: string) => number,
  maxWidth: number,
): string[] {
  const lines: string[] = [];
  for (const paragraph of toPdfText(text).split("\n")) {
    const words = paragraph.split(/\s+/).filter(Boolean);
    if (words.length === 0) {
      lines.push("");
      continue;
    }
    let current = "";
    for (const word of words) {
      const next = current ? `${current} ${word}` : word;
      if (widthOf(next) <= maxWidth) {
        current = next;
      } else {
        if (current) lines.push(current);
        current = word;
      }
    }
    if (current) lines.push(current);
  }
  return lines;
}

export async function buildTourStepPdf(input: TourStepPdfInput): Promise<Uint8Array> {
  const { PDFDocument, StandardFonts, rgb } = await import("pdf-lib");
  const doc = await PDFDocument.create();
  const regular = await doc.embedFont(StandardFonts.Helvetica);
  const strong = await doc.embedFont(StandardFonts.HelveticaBold);
  const ink = rgb(0.1, 0.12, 0.16);
  const muted = rgb(0.39, 0.43, 0.48);
  const maxWidth = PAGE_W - MARGIN_X * 2;
  const total = input.steps.length;

  for (let index = 0; index < input.steps.length; index += 1) {
    const step = input.steps[index];
    if (!step) continue;
    const page = doc.addPage([PAGE_W, PAGE_H]);
    let y = PAGE_H - MARGIN_TOP;
    const chapterId = step.chapter;
    const chapter =
      (chapterId && input.chapterLabels?.[chapterId]) ||
      (chapterId ? LIVE_TOUR_CHAPTER_LABELS[chapterId] : "");

    page.drawText(toPdfText(input.title), {
      x: MARGIN_X,
      y,
      size: 10,
      font: regular,
      color: muted,
    });
    y -= 16;
    page.drawText(`Step ${index + 1} of ${total}`, {
      x: MARGIN_X,
      y,
      size: 10,
      font: strong,
      color: ink,
    });
    y -= 22;
    if (chapter) {
      page.drawText(toPdfText(chapter), {
        x: MARGIN_X,
        y,
        size: 11,
        font: regular,
        color: muted,
      });
      y -= 20;
    }
    for (const line of wrapText(step.title, (value) => strong.widthOfTextAtSize(value, 18), maxWidth)) {
      page.drawText(line, { x: MARGIN_X, y, size: 18, font: strong, color: ink });
      y -= 24;
    }
    y -= 6;
    const shotPath = tourStepScreenshot(step);
    const shotBytes = shotPath ? input.screenshots?.[shotPath] : undefined;
    const imageReserve = shotBytes ? 200 : 0;
    for (const line of wrapText(step.body, (value) => regular.widthOfTextAtSize(value, 11), maxWidth)) {
      if (y < MARGIN_BOTTOM + imageReserve) break;
      page.drawText(line, { x: MARGIN_X, y, size: 11, font: regular, color: ink });
      y -= 14;
    }
    if (shotBytes) {
      y -= IMAGE_GAP;
      try {
        const image = await doc.embedPng(shotBytes);
        const maxH = Math.max(120, y - MARGIN_BOTTOM);
        const scale = Math.min(maxWidth / image.width, maxH / image.height);
        const drawW = image.width * scale;
        const drawH = image.height * scale;
        const imageY = y - drawH;
        page.drawImage(image, {
          x: MARGIN_X,
          y: imageY,
          width: drawW,
          height: drawH,
        });
        const highlight = tourStepHighlight(step);
        if (highlight) {
          const boxH = (highlight.h / 100) * drawH;
          page.drawRectangle({
            x: MARGIN_X + (highlight.x / 100) * drawW,
            y: imageY + drawH - ((highlight.y + highlight.h) / 100) * drawH,
            width: (highlight.w / 100) * drawW,
            height: boxH,
            borderColor: rgb(0.94, 0.2, 0.2),
            borderWidth: 2.25,
          });
        }
      } catch {
        page.drawText("Screenshot could not be attached.", {
          x: MARGIN_X,
          y: Math.max(MARGIN_BOTTOM, y - 14),
          size: 10,
          font: regular,
          color: muted,
        });
      }
    }
  }

  if (doc.getPageCount() === 0) {
    const page = doc.addPage([PAGE_W, PAGE_H]);
    page.drawText(toPdfText(`${input.title} has no steps for this role.`), {
      x: MARGIN_X,
      y: PAGE_H - MARGIN_TOP,
      size: 12,
      font: regular,
      color: ink,
    });
  }

  return doc.save();
}

async function loadTourScreenshots(steps: LiveTourStep[]): Promise<Record<string, Uint8Array>> {
  const paths = [...new Set(steps.map((step) => tourStepScreenshot(step)).filter((path): path is string => Boolean(path)))];
  const screenshots: Record<string, Uint8Array> = {};
  await Promise.all(
    paths.map(async (path) => {
      const response = await fetch(path);
      if (!response.ok) return;
      screenshots[path] = new Uint8Array(await response.arrayBuffer());
    }),
  );
  return screenshots;
}

export async function downloadTourStepPdf(input: TourStepPdfInput): Promise<void> {
  const screenshots = input.screenshots ?? (await loadTourScreenshots(input.steps));
  const bytes = await buildTourStepPdf({ ...input, screenshots });
  const blob = new Blob([Uint8Array.from(bytes)], { type: "application/pdf" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = input.fileName;
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  URL.revokeObjectURL(url);
}
