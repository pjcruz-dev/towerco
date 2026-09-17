type ExportOptions = {
  filename: string;
  title?: string;
};

type Html2CanvasOptions = {
  backgroundColor?: string;
  scale?: number;
  useCORS?: boolean;
  logging?: boolean;
  width?: number;
  height?: number;
  windowWidth?: number;
  windowHeight?: number;
  onclone?: (document: Document, element: HTMLElement) => void;
};

type Html2CanvasFn = (element: HTMLElement, options?: Html2CanvasOptions) => Promise<HTMLCanvasElement>;

async function loadHtml2Canvas(): Promise<Html2CanvasFn> {
  const module = await import("html2canvas-pro");
  return module.default as Html2CanvasFn;
}

function normalizeExportColors(root: HTMLElement): void {
  const view = root.ownerDocument.defaultView;
  if (!view) {
    return;
  }

  const nodes = [root, ...Array.from(root.querySelectorAll<HTMLElement>("*"))];

  for (const node of nodes) {
    const computed = view.getComputedStyle(node);

    if (computed.backgroundColor && computed.backgroundColor !== "rgba(0, 0, 0, 0)") {
      node.style.backgroundColor = computed.backgroundColor;
    }

    if (computed.color) {
      node.style.color = computed.color;
    }

    if (computed.borderTopColor) {
      node.style.borderTopColor = computed.borderTopColor;
    }
    if (computed.borderRightColor) {
      node.style.borderRightColor = computed.borderRightColor;
    }
    if (computed.borderBottomColor) {
      node.style.borderBottomColor = computed.borderBottomColor;
    }
    if (computed.borderLeftColor) {
      node.style.borderLeftColor = computed.borderLeftColor;
    }
  }
}

async function captureOrgChartCanvas(element: HTMLElement): Promise<HTMLCanvasElement> {
  const html2canvas = await loadHtml2Canvas();
  const width = Math.max(element.scrollWidth, element.offsetWidth, 800);
  const height = Math.max(element.scrollHeight, element.offsetHeight, 600);

  return html2canvas(element, {
    backgroundColor: "#ffffff",
    scale: 2,
    useCORS: true,
    logging: false,
    width,
    height,
    windowWidth: width,
    windowHeight: height,
    onclone: (_document, clonedElement) => {
      normalizeExportColors(clonedElement);
      clonedElement.style.transform = "none";
      clonedElement.style.maxHeight = "none";
      clonedElement.style.overflow = "visible";
    },
  });
}

export async function downloadOrgChartAsPng(element: HTMLElement, options: ExportOptions): Promise<void> {
  const canvas = await captureOrgChartCanvas(element);
  const link = document.createElement("a");
  link.download = `${options.filename}.png`;
  link.href = canvas.toDataURL("image/png");
  link.click();
}

export async function downloadOrgChartAsPdf(element: HTMLElement, options: ExportOptions): Promise<void> {
  const { jsPDF } = await import("jspdf");
  const canvas = await captureOrgChartCanvas(element);
  const imgData = canvas.toDataURL("image/png");

  const landscape = canvas.width >= canvas.height;
  const pdf = new jsPDF({ orientation: landscape ? "landscape" : "portrait", unit: "pt", format: "a4" });
  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  const margin = 28;
  const headerHeight = options.title ? 28 : 0;
  const contentWidth = pageWidth - margin * 2;
  const contentHeight = pageHeight - margin * 2 - headerHeight;
  const imgHeight = (canvas.height * contentWidth) / canvas.width;

  if (options.title) {
    pdf.setFontSize(11);
    pdf.setTextColor(15, 23, 42);
    pdf.text(options.title, margin, margin + 12);
  }

  let yOffset = 0;
  let pageIndex = 0;

  while (yOffset < imgHeight) {
    if (pageIndex > 0) {
      pdf.addPage();
    }

    const imageY = margin + headerHeight - yOffset;
    pdf.addImage(imgData, "PNG", margin, imageY, contentWidth, imgHeight);

    yOffset += contentHeight;
    pageIndex += 1;
  }

  pdf.save(`${options.filename}.pdf`);
}

/** Opens the browser print dialog with a full-page clone of the chart. */
export async function printOrgChartElement(
  element: HTMLElement,
  options: { title?: string } = {},
): Promise<void> {
  const canvas = await captureOrgChartCanvas(element);
  const dataUrl = canvas.toDataURL("image/png");
  const popup = window.open("", "_blank", "noopener,noreferrer,width=1200,height=900");
  if (!popup) {
    throw new Error("Pop-up blocked. Allow pop-ups to print the org chart.");
  }

  const title = options.title ?? "Organization chart";
  popup.document.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>${escapeHtml(title)}</title>
    <style>
      @page { margin: 12mm; }
      body { margin: 0; font-family: Inter, system-ui, sans-serif; color: #0f172a; }
      h1 { font-size: 14px; font-weight: 600; margin: 0 0 12px; }
      img { max-width: 100%; height: auto; display: block; }
    </style>
  </head>
  <body>
    <h1>${escapeHtml(title)}</h1>
    <img src="${dataUrl}" alt="${escapeHtml(title)}" />
    <script>
      const img = document.querySelector("img");
      const done = () => { window.focus(); window.print(); };
      if (img.complete) setTimeout(done, 50);
      else img.onload = () => setTimeout(done, 50);
    <\/script>
  </body>
</html>`);
  popup.document.close();
}

function escapeHtml(value: string): string {
  return value
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}
