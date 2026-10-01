import {
  BIR_2307_FIELDS,
  BIR_2307_PAGE,
  type Bir2307Values,
} from "@/lib/dynamic-entities/bir-2307-field-map";

type PdfLibModule = typeof import("pdf-lib");

let pdfLibPromise: Promise<PdfLibModule> | null = null;
function loadPdfLib(): Promise<PdfLibModule> {
  pdfLibPromise ??= import("pdf-lib");
  return pdfLibPromise;
}

/**
 * Build a 1-page filled BIR 2307 from the official vector PDF (sharp print),
 * falling back to the page-1 PNG only if the PDF cannot be loaded.
 * Page 2 (ATC schedule) is intentionally omitted.
 */
export async function fillBir2307OfficialPdf(values: Bir2307Values): Promise<Uint8Array> {
  const { PDFDocument, StandardFonts, rgb } = await loadPdfLib();

  const pdf = await PDFDocument.create();
  let pageWidth = BIR_2307_PAGE.width;
  let pageHeight = BIR_2307_PAGE.height;

  const templateLoaded = await embedOfficialTemplatePage(pdf, PDFDocument);
  if (templateLoaded) {
    const page = pdf.getPage(0);
    const size = page.getSize();
    pageWidth = size.width;
    pageHeight = size.height;
  } else {
    const artRes = await fetch(BIR_2307_PAGE.backgroundSrc);
    if (!artRes.ok) {
      throw new Error("Official BIR 2307 form assets are missing.");
    }
    const artBytes = new Uint8Array(await artRes.arrayBuffer());
    const page = pdf.addPage([pageWidth, pageHeight]);
    const png = await pdf.embedPng(artBytes);
    page.drawImage(png, {
      x: 0,
      y: 0,
      width: pageWidth,
      height: pageHeight,
    });
  }

  const page = pdf.getPage(0);
  const font = await pdf.embedFont(StandardFonts.Helvetica);
  const fontBold = await pdf.embedFont(StandardFonts.HelveticaBold);

  // Field map is calibrated to BIR_2307_PAGE (612×934); scale if the PDF page differs.
  const sx = pageWidth / BIR_2307_PAGE.width;
  const sy = pageHeight / BIR_2307_PAGE.height;

  for (const field of BIR_2307_FIELDS) {
    const value = values[field.id] ?? "";
    if (!value) continue;

    if (field.kind === "chars") {
      const size = (field.size ?? 10) * sy;
      const chars = [...value];
      if (field.xs && field.xs.length > 0) {
        for (let i = 0; i < chars.length; i++) {
          const left = field.xs[i];
          if (left == null) break;
          const ch = chars[i]!;
          const next = field.xs[i + 1];
          const cellW = ((next != null ? next - left : field.pitch) || field.pitch) * sx;
          const textW = fontBold.widthOfTextAtSize(ch, size);
          page.drawText(ch, {
            x: left * sx + Math.max(0, (cellW - textW) / 2),
            y: pageHeight - field.y * sy - size * 0.78,
            size,
            font: fontBold,
            color: rgb(0, 0, 0),
          });
        }
        continue;
      }
      let x = field.x * sx;
      const pitch = field.pitch * sx;
      const gapExtra = (field.gapExtra ?? 0) * sx;
      for (let i = 0; i < chars.length; i++) {
        if (i > 0 && field.gapsAfter?.includes(i)) x += gapExtra;
        const ch = chars[i]!;
        const textW = fontBold.widthOfTextAtSize(ch, size);
        page.drawText(ch, {
          x: x + Math.max(0, (pitch - textW) / 2),
          y: pageHeight - field.y * sy - size * 0.78,
          size,
          font: fontBold,
          color: rgb(0, 0, 0),
        });
        x += pitch;
      }
      continue;
    }

    const size = (field.size ?? 9) * sy;
    const text = field.uppercase ? value.toUpperCase() : value;
    const useFont = field.bold ? fontBold : font;
    let draw = text;
    const maxW = (field.w ?? 200) * sx;
    while (draw.length > 1 && useFont.widthOfTextAtSize(draw, size) > maxW) {
      draw = draw.slice(0, -1);
    }
    let x = field.x * sx;
    const textW = useFont.widthOfTextAtSize(draw, size);
    if (field.align === "center") x = field.x * sx + (maxW - textW) / 2;
    if (field.align === "right") x = field.x * sx + maxW - textW;
    page.drawText(draw, {
      x,
      y: pageHeight - field.y * sy - size * 0.78,
      size,
      font: useFont,
      color: rgb(0, 0, 0),
    });
  }

  return pdf.save();
}

async function embedOfficialTemplatePage(
  target: import("pdf-lib").PDFDocument,
  PDFDocument: PdfLibModule["PDFDocument"],
): Promise<boolean> {
  try {
    const res = await fetch(BIR_2307_PAGE.templatePdfSrc);
    if (!res.ok) return false;
    const bytes = new Uint8Array(await res.arrayBuffer());
    const src = await PDFDocument.load(bytes, { ignoreEncryption: true });
    if (src.getPageCount() < 1) return false;
    const [copied] = await target.copyPages(src, [0]);
    target.addPage(copied);
    return true;
  } catch {
    return false;
  }
}
