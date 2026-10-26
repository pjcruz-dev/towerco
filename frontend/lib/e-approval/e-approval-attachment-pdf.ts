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

/** Helvetica in pdf-lib is WinAnsi. An ellipsis or smart quote aborts the whole stamp. */
function pdfSafeText(text: string): string {
  return text
    .replaceAll("\u2026", "...")
    .replaceAll("\u2014", "-")
    .replaceAll("\u2013", "-")
    .replaceAll("\u2018", "'")
    .replaceAll("\u2019", "'")
    .replaceAll("\u201C", '"')
    .replaceAll("\u201D", '"')
    .replace(/[^\u0020-\u007E\u00A0-\u00FF]/g, "");
}

function truncateFooterText(text: string, maxChars: number): string {
  const clean = pdfSafeText(text.trim());
  if (clean.length <= maxChars) return clean;
  if (maxChars <= 3) return clean.slice(0, Math.max(0, maxChars));
  return `${clean.slice(0, Math.max(0, maxChars - 3))}...`;
}

function isEncryptedPdfError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error);
  return /encrypt/i.test(message);
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
/** Signatures drawn on each page before a "+ more" count. */
const APPROVAL_HISTORY_VISIBLE_CAP = 12;
const APPROVAL_HISTORY_ROW_H = 48;

function approvalHistoryColumns(pageWidth: number): number {
  return pageWidth >= 700 ? 6 : 4;
}

/** Stamp box height for up to 12 signatures, in rows of 4 (portrait) or 6 (landscape). */
function approvalHistoryBandHeight(pageWidth: number, signatureCount: number): number {
  if (signatureCount <= 0) return 0;
  const visible = Math.min(APPROVAL_HISTORY_VISIBLE_CAP, signatureCount);
  const rows = Math.ceil(visible / approvalHistoryColumns(pageWidth));
  return 18 + rows * APPROVAL_HISTORY_ROW_H;
}

function approvalHistoryFooterReserve(pageWidth: number, signatureCount: number): number {
  return (
    APPROVAL_HISTORY_FOOTER_Y +
    approvalHistoryBandHeight(pageWidth, signatureCount) +
    APPROVAL_HISTORY_FOOTER_CONTENT_GAP
  );
}

async function applyApprovalHistoryFooter(
  pdfDoc: PDFDocument,
  rows: FooterRow[],
): Promise<void> {
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
    const { width } = page.getSize();
    const marginX = Math.max(14, width * 0.03);
    const y = APPROVAL_HISTORY_FOOTER_Y;
    const columns = approvalHistoryColumns(width);
    const visible = Math.min(APPROVAL_HISTORY_VISIBLE_CAP, embeddedSignatures.length);
    const footerH = approvalHistoryBandHeight(width, embeddedSignatures.length);

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
    const headerY = y + footerH - titleSize - 4;
    page.drawText(pdfSafeText("APPROVAL HISTORY"), {
      x: marginX + 8,
      y: headerY,
      size: titleSize,
      font: strong,
      color: rgb(0.35, 0.39, 0.45),
    });

    const hiddenApprovalsCount = Math.max(0, embeddedSignatures.length - APPROVAL_HISTORY_VISIBLE_CAP);
    if (hiddenApprovalsCount > 0) {
      page.drawText(`+${hiddenApprovalsCount} more`, {
        x: Math.max(marginX + 8, width - marginX - 118),
        y: headerY,
        size: Math.max(5, Math.min(6.5, width / 155)),
        font: regular,
        color: rgb(0.42, 0.46, 0.52),
      });
    }

    const slotGap = 6;
    const usableW = Math.max(20, width - marginX * 2 - 16);
    const slotW = (usableW - slotGap * (columns - 1)) / columns;
    const labelFontSize = Math.max(5.6, Math.min(7.2, slotW / 16));
    const actionFontSize = Math.max(5, Math.min(6.2, slotW / 18));
    const labelMaxChars = Math.max(8, Math.floor(slotW / 4.2));
    const actionMaxChars = Math.max(10, Math.floor(slotW / 3.8));

    for (let i = 0; i < visible; i++) {
      const entry = embeddedSignatures[i];
      if (!entry) continue;
      const col = i % columns;
      const row = Math.floor(i / columns);
      const x = marginX + 8 + col * (slotW + slotGap);
      const rowTop = headerY - 3 - row * APPROVAL_HISTORY_ROW_H;
      const metaLabelY = rowTop - labelFontSize;
      const metaActionY = metaLabelY - actionFontSize - 1.5;
      const sigBoxY = rowTop - APPROVAL_HISTORY_ROW_H + 4;
      const sigBoxH = Math.max(16, metaActionY - sigBoxY - 3);
      const sigMaxW = Math.max(18, slotW - 8);
      const sigMaxH = Math.max(12, sigBoxH - 2);

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
        const typedSize = Math.max(6, Math.min(9, slotW / 12));
        page.drawText(truncateFooterText(entry.signature, Math.max(8, Math.floor(slotW / 4.5))), {
          x: x + 3,
          y: sigBoxY + sigBoxH / 2 - typedSize / 3,
          size: typedSize,
          font: oblique,
          color: rgb(0.12, 0.14, 0.18),
          maxWidth: slotW - 6,
        });
      } else if (isImageSignature(entry.signature)) {
        page.drawText("Signature unavailable", {
          x: x + 3,
          y: sigBoxY + sigBoxH / 2 - 2,
          size: Math.max(4.8, Math.min(6, slotW / 16)),
          font: regular,
          color: rgb(0.62, 0.66, 0.72),
        });
      } else {
        page.drawText("No signature", {
          x: x + 3,
          y: sigBoxY + sigBoxH / 2 - 2,
          size: Math.max(4.8, Math.min(6, slotW / 16)),
          font: regular,
          color: rgb(0.62, 0.66, 0.72),
        });
      }
    }
  }
}

type RasterPdfPage = {
  getViewport: (options: { scale: number }) => { width: number; height: number };
  render: (params: {
    canvas: HTMLCanvasElement;
    canvasContext: CanvasRenderingContext2D;
    viewport: { width: number; height: number };
  }) => { promise?: Promise<void> } | Promise<void>;
  cleanup?: () => void;
};

/**
 * Owner-locked PDFs open in the browser but pdf-lib cannot copy their streams.
 * Draw each page as an image and leave the same footer band the stamp expects.
 */
async function rasterizeEncryptedPdf(
  mainPdfDoc: PDFDocument,
  bytes: ArrayBuffer,
  footerRowCount: number,
): Promise<void> {
  if (typeof document === "undefined") {
    throw new Error("Could not draw the protected PDF in this browser.");
  }

  const pdfjs = await import("pdfjs-dist");
  pdfjs.GlobalWorkerOptions.workerSrc = "/pdfjs/pdf.worker.min.mjs";
  const loadingTask = pdfjs.getDocument({ data: Uint8Array.from(new Uint8Array(bytes)) });
  const source = (await loadingTask.promise) as {
    numPages: number;
    getPage: (pageNumber: number) => Promise<RasterPdfPage>;
  };

  try {
    for (let pageNumber = 1; pageNumber <= source.numPages; pageNumber += 1) {
      const sourcePage = await source.getPage(pageNumber);
      const points = sourcePage.getViewport({ scale: 1 });
      const scale = Math.min(2, 1800 / Math.max(points.width, points.height, 1));
      const viewport = sourcePage.getViewport({ scale });
      const canvas = document.createElement("canvas");
      canvas.width = Math.max(1, Math.ceil(viewport.width));
      canvas.height = Math.max(1, Math.ceil(viewport.height));
      const context = canvas.getContext("2d");
      if (!context) {
        throw new Error("Could not draw the protected PDF in this browser.");
      }
      context.fillStyle = "#ffffff";
      context.fillRect(0, 0, canvas.width, canvas.height);
      const rendered = sourcePage.render({ canvas, canvasContext: context, viewport });
      if (rendered && typeof (rendered as { promise?: Promise<void> }).promise?.then === "function") {
        await (rendered as { promise: Promise<void> }).promise;
      } else {
        await rendered;
      }
      const png = await new Promise<Uint8Array>((resolve, reject) => {
        canvas.toBlob((blob) => {
          if (!blob) {
            reject(new Error("Could not draw the protected PDF in this browser."));
            return;
          }
          void blob.arrayBuffer().then((buffer) => resolve(new Uint8Array(buffer)), reject);
        }, "image/png");
      });
      const image = await mainPdfDoc.embedPng(png);
      const reservedBottom = footerRowCount > 0 ? approvalHistoryFooterReserve(points.width, footerRowCount) : 0;
      const page = mainPdfDoc.addPage([points.width, points.height + reservedBottom]);
      page.drawImage(image, {
        x: 0,
        y: reservedBottom,
        width: points.width,
        height: points.height,
      });
      sourcePage.cleanup?.();
    }
  } finally {
    await loadingTask.destroy();
  }
}

async function appendAttachmentToPdf(
  mainPdfDoc: PDFDocument,
  fileName: string,
  bytes: ArrayBuffer,
  options?: { footerRowCount?: number },
): Promise<void> {
  const footerRowCount = options?.footerRowCount ?? 0;
  const reserveFooterBand = footerRowCount > 0;

      if (isPdfFileName(fileName)) {
    const uint8 = new Uint8Array(bytes);
    if (uint8.length >= 5 && uint8[0] === 0x25 && uint8[1] === 0x50) {
      const { PDFDocument } = await loadPdfLib();
      let atPdfDoc: PDFDocument;
      try {
        atPdfDoc = await PDFDocument.load(bytes);
      } catch (error) {
        if (!isEncryptedPdfError(error)) throw error;
        await rasterizeEncryptedPdf(mainPdfDoc, bytes, footerRowCount);
        return;
      }

      const sourcePages = atPdfDoc.getPages();

      if (!reserveFooterBand) {
        const copiedPages = await mainPdfDoc.copyPages(atPdfDoc, atPdfDoc.getPageIndices());
        copiedPages.forEach((page) => mainPdfDoc.addPage(page));
        return;
      }

      for (let index = 0; index < sourcePages.length; index++) {
        const sourcePage = sourcePages[index]!;
        const { width: contentWidth, height: contentHeight } = sourcePage.getSize();
        const reservedBottom = approvalHistoryFooterReserve(contentWidth, footerRowCount);
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
    const landscape = width >= height;
    const pageW = landscape ? 841.89 : 595.28;
    const pageH = landscape ? 595.28 : 841.89;
    const footerBand = reserveFooterBand ? approvalHistoryFooterReserve(pageW, footerRowCount) : 0;
    const margin = 16;
    const maxW = pageW - margin * 2;
    const maxH = pageH - footerBand - margin;
    const scale = Math.min(maxW / width, maxH / height);
    const drawW = width * scale;
    const drawH = height * scale;
    const page = mainPdfDoc.addPage([pageW, pageH]);
    page.drawImage(image, {
      x: (pageW - drawW) / 2,
      y: footerBand + Math.max(0, (maxH - drawH) / 2),
      width: drawW,
      height: drawH,
    });
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
    await appendAttachmentToPdf(mainPdfDoc, attachment.file_name, bytes, {
      footerRowCount: reserveFooterBand ? footerRows.length : 0,
    });
  }

  if (mainPdfDoc.getPageCount() === 0) {
    throw new Error("EMPTY_PDF");
  }

  await applyApprovalHistoryFooter(mainPdfDoc, footerRows);

  const mergedBytes = await mainPdfDoc.save();
  return new Blob([Uint8Array.from(mergedBytes)], { type: "application/pdf" });
}

function showPdfPreviewStatus(preview: Window, message: string): void {
  preview.document.title = "Approval PDF";
  preview.document.body.style.margin = "0";
  preview.document.body.innerHTML = `<p style="font-family:system-ui,sans-serif;padding:24px;color:#334155">${message}</p>`;
}

/**
 * Open the tab during the click. A blob URL opened after the downloads finish,
 * or with noopener, leaves Chrome's PDF viewer on Loading.
 */
export function openEApprovalPdfPreviewTab(): Window | null {
  const preview = window.open("", "_blank");
  if (preview) {
    showPdfPreviewStatus(preview, "Preparing PDF…");
  }
  return preview;
}

export async function openEApprovalAttachmentPdfPreview(
  payload: EApprovalPrintPayload,
  options?: EApprovalAttachmentPdfOptions,
  preview?: Window | null,
): Promise<void> {
  const tab = preview === undefined ? openEApprovalPdfPreviewTab() : preview;
  try {
    const blob = await buildEApprovalAttachmentPdfBlob(payload, options);
    const url = URL.createObjectURL(blob);
    if (tab && !tab.closed) {
      tab.location.replace(url);
      return;
    }
    window.location.assign(url);
  } catch (error) {
    if (tab && !tab.closed) {
      tab.close();
    }
    throw error;
  }
}
