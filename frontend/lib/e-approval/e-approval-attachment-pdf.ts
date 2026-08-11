import type { PDFDocument } from "pdf-lib";

import { buildPdfFooterRows } from "@/modules/e-approval/approval-history-print";
import { downloadEApprovalAttachment } from "@/lib/api/modules/e-approval-api";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import { isImageSignature, isTypedSignature } from "@/modules/e-approval/signature";

type FooterRow = {
  label: string;
  signature: string;
  status: string;
  actedAt: string;
};

// pdf-lib is a heavy dependency only needed when the user actually exports/previews a PDF.
// Load it on demand (and cache the promise) so it stays out of the initial page bundle.
type PdfLibModule = typeof import("pdf-lib");
let pdfLibPromise: Promise<PdfLibModule> | null = null;
function loadPdfLib(): Promise<PdfLibModule> {
  pdfLibPromise ??= import("pdf-lib");
  return pdfLibPromise;
}

function isPdfFileName(fileName: string): boolean {
  return /\.pdf$/i.test(fileName);
}

function isImageFileName(fileName: string): boolean {
  return /\.(png|jpe?g)$/i.test(fileName);
}

function decodeDataUrl(dataUrl: string): { bytes: Uint8Array; mime: string } | null {
  const m = /^data:([^;]+);base64,(.+)$/i.exec(dataUrl);
  if (!m) return null;
  const mime = m[1].toLowerCase();
  const raw = atob(m[2]);
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
  return { bytes: out, mime };
}

function formatFooterActionLine(status: string, actedAt: string): string {
  const statusText = status.trim().toUpperCase() || "ACTION";
  if (!actedAt) return `${statusText} · —`;
  const dt = new Date(actedAt);
  const timeText = Number.isNaN(dt.getTime())
    ? actedAt
    : dt.toLocaleString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
      });
  return `${statusText} · ${timeText}`;
}

function truncateFooterText(text: string, maxChars: number): string {
  const clean = text.trim();
  if (clean.length <= maxChars) return clean;
  if (maxChars <= 1) return clean.slice(0, Math.max(0, maxChars));
  return `${clean.slice(0, Math.max(1, maxChars - 1))}…`;
}

function buildFooterRows(payload: EApprovalPrintPayload): FooterRow[] {
  return buildPdfFooterRows(payload).map((row) => ({
    label: row.label,
    signature: row.signature,
    status: row.status,
    actedAt: row.actedAt,
  }));
}

/** Bottom inset of the approval-history stamp box (pdf points). */
const APPROVAL_HISTORY_FOOTER_Y = 8;
/** Gap between attachment content and the stamp box. */
const APPROVAL_HISTORY_FOOTER_CONTENT_GAP = 6;
const APPROVAL_HISTORY_FOOTER_MIN_H = 52;
const APPROVAL_HISTORY_FOOTER_MAX_H = 74;

/** Stamp box height used by applyApprovalHistoryFooter (based on final page height). */
function approvalHistoryFooterHeight(pageHeight: number): number {
  return Math.max(
    APPROVAL_HISTORY_FOOTER_MIN_H,
    Math.min(APPROVAL_HISTORY_FOOTER_MAX_H, pageHeight * 0.125),
  );
}

/**
 * Extra page height to append under attachment content so the stamp never covers pixels.
 * Sized against the eventual extended page so footerH and reserve stay aligned.
 */
function approvalHistoryFooterReserveForContent(contentHeight: number): number {
  const provisionalPageHeight =
    contentHeight +
    APPROVAL_HISTORY_FOOTER_Y +
    APPROVAL_HISTORY_FOOTER_MAX_H +
    APPROVAL_HISTORY_FOOTER_CONTENT_GAP;
  const footerH = approvalHistoryFooterHeight(provisionalPageHeight);
  return APPROVAL_HISTORY_FOOTER_Y + footerH + APPROVAL_HISTORY_FOOTER_CONTENT_GAP;
}

async function applyApprovalHistoryFooter(pdfDoc: PDFDocument, rows: FooterRow[]): Promise<void> {
  if (rows.length === 0) return;

  const { StandardFonts, rgb } = await loadPdfLib();

  const regular = await pdfDoc.embedFont(StandardFonts.Helvetica);
  const strong = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
  const oblique = await pdfDoc.embedFont(StandardFonts.HelveticaOblique);

  const embeddedSignatures = await Promise.all(
    rows.map(async (row) => {
      if (isTypedSignature(row.signature)) {
        return { ...row, image: null };
      }

      const src = row.signature;
      try {
        const decoded = decodeDataUrl(src);
        let bytes: Uint8Array;
        let mime = "";
        if (decoded) {
          bytes = decoded.bytes;
          mime = decoded.mime;
        } else {
          const resp = await fetch(src);
          if (!resp.ok) return { ...row, image: null };
          bytes = new Uint8Array(await resp.arrayBuffer());
          mime = String(resp.headers.get("content-type") || "").toLowerCase();
        }
        const image =
          mime.includes("png") || src.startsWith("data:image/png")
            ? await pdfDoc.embedPng(bytes)
            : await pdfDoc.embedJpg(bytes);
        return { ...row, image };
      } catch {
        return { ...row, image: null };
      }
    }),
  );

  const pages = pdfDoc.getPages();
  for (const page of pages) {
    const { width, height } = page.getSize();
    const marginX = Math.max(14, width * 0.03);
    const footerH = approvalHistoryFooterHeight(height);
    const y = APPROVAL_HISTORY_FOOTER_Y;

    page.drawRectangle({
      x: marginX,
      y,
      width: Math.max(20, width - marginX * 2),
      height: footerH,
      color: rgb(0.965, 0.972, 0.98),
      borderColor: rgb(0.8, 0.84, 0.9),
      borderWidth: 0.6,
    });

    const titleSize = Math.max(7, Math.min(9, width / 95));
    const headerY = y + footerH - titleSize - 5;
    page.drawText("APPROVAL HISTORY", {
      x: marginX + 8,
      y: headerY,
      size: titleSize,
      font: strong,
      color: rgb(0.35, 0.39, 0.45),
    });

    const hiddenApprovalsCount = Math.max(0, embeddedSignatures.length - 4);
    if (hiddenApprovalsCount > 0) {
      page.drawText(`+${hiddenApprovalsCount} more approvals`, {
        x: Math.max(marginX + 8, width - marginX - 118),
        y: headerY,
        size: Math.max(5, Math.min(6.5, width / 155)),
        font: regular,
        color: rgb(0.42, 0.46, 0.52),
      });
    }

    const slots = Math.max(2, Math.min(4, embeddedSignatures.length));
    const slotGap = 8;
    const usableW = Math.max(20, width - marginX * 2 - 16);
    const slotW = (usableW - slotGap * (slots - 1)) / slots;
    const labelFontSize = Math.max(6.1, Math.min(7.6, width / 118));
    const actionFontSize = Math.max(5.4, Math.min(6.8, width / 142));
    const lineGap = Math.max(6.8, actionFontSize + 1.2);
    const metaLabelY = headerY - 9.5;
    const metaActionY = metaLabelY - lineGap;
    const sigBoxY = y + 5;
    const sigBoxH = Math.max(14, metaActionY - sigBoxY - 5);
    const sigMaxW = Math.min(120, Math.max(24, slotW - 14));
    const sigMaxH = Math.max(12, sigBoxH - 4);
    const labelMaxChars = Math.max(10, Math.floor(slotW / 4.5));
    const actionMaxChars = Math.max(14, Math.floor(slotW / 4.4));

    for (let i = 0; i < slots; i++) {
      const entry = embeddedSignatures[i];
      if (!entry) continue;
      const x = marginX + 8 + i * (slotW + slotGap);
      page.drawRectangle({
        x,
        y: sigBoxY,
        width: slotW,
        height: sigBoxH,
        color: rgb(1, 1, 1),
        borderColor: rgb(0.86, 0.89, 0.93),
        borderWidth: 0.4,
      });
      page.drawText(truncateFooterText(entry.label, labelMaxChars), {
        x,
        y: metaLabelY,
        size: labelFontSize,
        font: strong,
        color: rgb(0.25, 0.28, 0.33),
        maxWidth: slotW,
      });
      page.drawText(truncateFooterText(formatFooterActionLine(entry.status, entry.actedAt), actionMaxChars), {
        x,
        y: metaActionY,
        size: actionFontSize,
        font: regular,
        color: rgb(0.48, 0.52, 0.58),
        maxWidth: slotW,
      });
      if (entry.image) {
        const scale = Math.min(sigMaxW / entry.image.width, sigMaxH / entry.image.height);
        const drawW = entry.image.width * scale;
        const drawH = entry.image.height * scale;
        page.drawImage(entry.image, {
          x: x + (slotW - drawW) / 2,
          y: sigBoxY + (sigBoxH - drawH) / 2,
          width: drawW,
          height: drawH,
        });
      } else if (isTypedSignature(entry.signature)) {
        const typedSize = Math.max(7, Math.min(10, width / 95));
        page.drawText(truncateFooterText(entry.signature, Math.max(12, Math.floor(slotW / 5))), {
          x: x + 4,
          y: sigBoxY + sigBoxH / 2 - typedSize / 3,
          size: typedSize,
          font: oblique,
          color: rgb(0.12, 0.14, 0.18),
          maxWidth: slotW - 8,
        });
      } else if (isImageSignature(entry.signature)) {
        page.drawText("Signature unavailable", {
          x: x + 4,
          y: sigBoxY + sigBoxH / 2 - 2,
          size: Math.max(5.2, Math.min(6.8, width / 135)),
          font: regular,
          color: rgb(0.62, 0.66, 0.72),
        });
      } else {
        page.drawText("No signature", {
          x: x + 4,
          y: sigBoxY + sigBoxH / 2 - 2,
          size: Math.max(5.2, Math.min(6.8, width / 135)),
          font: regular,
          color: rgb(0.62, 0.66, 0.72),
        });
      }
    }
  }
}

async function appendAttachmentToPdf(
  mainPdfDoc: PDFDocument,
  fileName: string,
  bytes: ArrayBuffer,
  options?: { reserveFooterBand?: boolean },
): Promise<void> {
  const reserveFooterBand = Boolean(options?.reserveFooterBand);

  if (isPdfFileName(fileName)) {
    const uint8 = new Uint8Array(bytes);
    if (uint8.length >= 5 && uint8[0] === 0x25 && uint8[1] === 0x50) {
      const { PDFDocument } = await loadPdfLib();
      const atPdfDoc = await PDFDocument.load(bytes);
      const sourcePages = atPdfDoc.getPages();

      if (!reserveFooterBand) {
        const copiedPages = await mainPdfDoc.copyPages(atPdfDoc, atPdfDoc.getPageIndices());
        copiedPages.forEach((page) => mainPdfDoc.addPage(page));
        return;
      }

      for (let index = 0; index < sourcePages.length; index++) {
        const sourcePage = sourcePages[index]!;
        const { width: contentWidth, height: contentHeight } = sourcePage.getSize();
        const reservedBottom = approvalHistoryFooterReserveForContent(contentHeight);
        const embeddedPage = await mainPdfDoc.embedPage(sourcePage);
        const page = mainPdfDoc.addPage([contentWidth, contentHeight + reservedBottom]);
        page.drawPage(embeddedPage, {
          x: 0,
          y: reservedBottom,
          width: contentWidth,
          height: contentHeight,
        });
      }
    }
    return;
  }

  if (isImageFileName(fileName)) {
    const image = /\.png$/i.test(fileName)
      ? await mainPdfDoc.embedPng(bytes)
      : await mainPdfDoc.embedJpg(bytes);
    const { width, height } = image.scale(1);
    if (!reserveFooterBand) {
      const page = mainPdfDoc.addPage([width, height]);
      page.drawImage(image, { x: 0, y: 0, width, height });
      return;
    }

    const reservedBottom = approvalHistoryFooterReserveForContent(height);
    const page = mainPdfDoc.addPage([width, height + reservedBottom]);
    page.drawImage(image, { x: 0, y: reservedBottom, width, height });
  }
}

/**
 * Builds a merged PDF from submission attachments and stamps approval history on every page
 * (same approach as legacy atcformbuiilder processPdfExport + pdf-lib).
 */
export type EApprovalAttachmentPdfOptions = {
  /** When set, only this attachment is merged (still stamps approval history on every page). */
  attachmentId?: string;
};

export async function buildEApprovalAttachmentPdfBlob(
  payload: EApprovalPrintPayload,
  options?: EApprovalAttachmentPdfOptions,
): Promise<Blob> {
  const attachments = payload.attachments ?? [];
  let printable = attachments.filter((a) => isPdfFileName(a.file_name) || isImageFileName(a.file_name));
  if (options?.attachmentId) {
    printable = printable.filter((a) => a.id === options.attachmentId);
  }

  if (printable.length === 0) {
    throw new Error("NO_PRINTABLE_ATTACHMENTS");
  }

  const { PDFDocument } = await loadPdfLib();
  const mainPdfDoc = await PDFDocument.create();
  const footerRows = buildFooterRows(payload);
  const reserveFooterBand = footerRows.length > 0;

  for (const attachment of printable) {
    const blob = await downloadEApprovalAttachment(attachment.id);
    const bytes = await blob.arrayBuffer();
    await appendAttachmentToPdf(mainPdfDoc, attachment.file_name, bytes, { reserveFooterBand });
  }

  if (mainPdfDoc.getPageCount() === 0) {
    throw new Error("EMPTY_PDF");
  }

  await applyApprovalHistoryFooter(mainPdfDoc, footerRows);

  const mergedBytes = await mainPdfDoc.save();
  return new Blob([Uint8Array.from(mergedBytes)], { type: "application/pdf" });
}

export async function openEApprovalAttachmentPdfPreview(
  payload: EApprovalPrintPayload,
  options?: EApprovalAttachmentPdfOptions,
): Promise<void> {
  const blob = await buildEApprovalAttachmentPdfBlob(payload, options);
  const url = URL.createObjectURL(blob);
  window.open(url, "_blank", "noopener,noreferrer");
}
