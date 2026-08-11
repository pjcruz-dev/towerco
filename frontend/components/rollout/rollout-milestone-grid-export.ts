type ExportOptions = {
  filename: string;
  title?: string;
};

type Html2CanvasOptions = {
  backgroundColor?: string;
  scale?: number;
  useCORS?: boolean;
  logging?: boolean;
  onclone?: (document: Document, element: HTMLElement) => void;
};

type Html2CanvasFn = (element: HTMLElement, options?: Html2CanvasOptions) => Promise<HTMLCanvasElement>;

export async function exportMilestoneGridAsPng(element: HTMLElement, options: ExportOptions): Promise<void> {
  const canvas = await captureGridCanvas(element);
  const link = document.createElement("a");
  link.download = `${options.filename}.png`;
  link.href = canvas.toDataURL("image/png");
  link.click();
}

export async function exportMilestoneGridAsPdf(element: HTMLElement, options: ExportOptions): Promise<void> {
  const { jsPDF } = await import("jspdf");
  const canvas = await captureGridCanvas(element);
  const imgData = canvas.toDataURL("image/png");

  const pdf = new jsPDF({ orientation: "landscape", unit: "pt", format: "a4" });
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

async function captureGridCanvas(element: HTMLElement): Promise<HTMLCanvasElement> {
  const html2canvas = await loadHtml2Canvas();

  return html2canvas(element, {
    backgroundColor: "#ffffff",
    scale: 2,
    useCORS: true,
    logging: false,
    onclone: (_document, clonedElement) => {
      normalizeExportColors(clonedElement);
    },
  });
}

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
      node.style.borderRightColor = computed.borderRightColor;
      node.style.borderBottomColor = computed.borderBottomColor;
      node.style.borderLeftColor = computed.borderLeftColor;
    }
  }
}
