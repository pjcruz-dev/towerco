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
 * Build a 1-page filled BIR 2307 using the official page-1 artwork as background.
 * Page 2 (ATC schedule) is intentionally omitted.
 */
export async function fillBir2307OfficialPdf(values: Bir2307Values): Promise<Uint8Array> {
  const { PDFDocument, StandardFonts, rgb } = await loadPdfLib();

  const artRes = await fetch(BIR_2307_PAGE.backgroundSrc);
  if (!artRes.ok) {
    throw new Error("Official BIR 2307 page-1 artwork is missing.");
  }
  const artBytes = new Uint8Array(await artRes.arrayBuffer());

  const pdf = await PDFDocument.create();
  const page = pdf.addPage([BIR_2307_PAGE.width, BIR_2307_PAGE.height]);
  const png = await pdf.embedPng(artBytes);
  page.drawImage(png, {
    x: 0,
    y: 0,
    width: BIR_2307_PAGE.width,
    height: BIR_2307_PAGE.height,
  });

  const font = await pdf.embedFont(StandardFonts.Helvetica);
  const fontBold = await pdf.embedFont(StandardFonts.HelveticaBold);
  const height = BIR_2307_PAGE.height;

  for (const field of BIR_2307_FIELDS) {
    const value = values[field.id] ?? "";
    if (!value) continue;

    if (field.kind === "chars") {
      const size = field.size ?? 10;
      let x = field.x;
      const chars = [...value];
      for (let i = 0; i < chars.length; i++) {
        if (i > 0 && field.gapsAfter?.includes(i)) x += field.gapExtra ?? 0;
        const ch = chars[i]!;
        const textW = fontBold.widthOfTextAtSize(ch, size);
        // field.y = top of target row; baseline ~75% down the font size for optical center in boxes
        page.drawText(ch, {
          x: x + Math.max(0, (field.pitch - textW) / 2),
          y: height - field.y - size * 0.78,
          size,
          font: fontBold,
          color: rgb(0, 0, 0),
        });
        x += field.pitch;
      }
      continue;
    }

    const size = field.size ?? 9;
    const text = field.uppercase ? value.toUpperCase() : value;
    const useFont = field.bold ? fontBold : font;
    let draw = text;
    const maxW = field.w ?? 200;
    while (draw.length > 1 && useFont.widthOfTextAtSize(draw, size) > maxW) {
      draw = draw.slice(0, -1);
    }
    let x = field.x;
    const textW = useFont.widthOfTextAtSize(draw, size);
    if (field.align === "center") x = field.x + (maxW - textW) / 2;
    if (field.align === "right") x = field.x + maxW - textW;
    page.drawText(draw, {
      x,
      y: height - field.y - size * 0.78,
      size,
      font: useFont,
      color: rgb(0, 0, 0),
    });
  }

  return pdf.save();
}
