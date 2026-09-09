import type { LiveTourDefinition, LiveTourStep } from "@/lib/help/e-approval-live-tour";

/** Standalone product tour — DocExtract upload → customize → results. */
export const DOC_EXTRACT_LIVE_TOUR_ID = "doc-extract";

/** Keep query keys as literals — avoid value-importing e-approval-live-tour (circular). */
const TOUR_QUERY = "tour";
const TOUR_STEP_QUERY = "tourStep";

export const docExtractLiveTour: LiveTourDefinition = {
  id: DOC_EXTRACT_LIVE_TOUR_ID,
  title: "DocExtract tour",
  steps: [
    {
      id: "dx-nav",
      path: "/doc-extract",
      entryPath: "/doc-extract",
      target: "dx-nav-doc-extract",
      title: "Open DocExtract",
      body: "In the sidebar, open DocExtract. Batches lists past runs; New batch starts Upload → Customize → Results.",
      missingHint: "Look for DocExtract in the left sidebar (Operate).",
    },
    {
      id: "dx-nav-batches",
      path: "/doc-extract",
      entryPath: "/doc-extract",
      target: "dx-nav-doc-extract-batches",
      title: "Batches",
      body: "Batches shows every extraction run — status, template, and how many documents are ready.",
    },
    {
      id: "dx-batches-page",
      path: "/doc-extract",
      entryPath: "/doc-extract",
      target: "dx-batches-page",
      title: "Extraction history",
      body: "Open a finished batch to review files, customize columns, and export. Or start a new extraction.",
    },
    {
      id: "dx-new-batch",
      path: "/doc-extract",
      entryPath: "/doc-extract",
      target: "dx-new-batch",
      title: "New extraction",
      body: "Click New extraction (or New batch in the sidebar) to upload documents.",
      missingHint: "You need doc-extract:run permission to start a new batch.",
    },
    {
      id: "dx-upload",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-upload-section",
      title: "1. Upload files",
      body: "Drop PDFs or images here, or use Upload files. Same document layouts work best for reusable templates.",
    },
    {
      id: "dx-file-list",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-uploaded-list",
      title: "Uploaded files",
      body: "Review the file list before extracting. Remove anything you do not want scanned.",
    },
    {
      id: "dx-template",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-template-select",
      title: "Template or auto-detect",
      body: "Auto-detect recommends fields from OCR (utility bills, payment advice, and more). Pick a saved template for fixed columns on repeat runs.",
    },
    {
      id: "dx-extract",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-extract-button",
      title: "Extract data",
      body: "Start the scan. You land on the extraction workspace: uploaded files, customize fields, then view results.",
    },
    {
      id: "dx-workspace-files",
      path: "/doc-extract/batches/",
      pathMatch: "prefix",
      entryPath: "/doc-extract",
      target: "dx-files-section",
      title: "1. Uploaded files (workspace)",
      body: "After extract, section 1 lists every document in the batch with status and page count.",
      missingHint: "Open any batch from Batches, or finish Extract data first so a workspace exists.",
    },
    {
      id: "dx-workspace-customize",
      path: "/doc-extract/batches/",
      pathMatch: "prefix",
      entryPath: "/doc-extract",
      target: "dx-customize-section",
      title: "2. Customize fields",
      body: "On auto-detect, remove noisy columns and optionally Save as template. Template runs show fixed field cards.",
      missingHint: "Open a batch detail page to see Customize data to extract.",
    },
    {
      id: "dx-workspace-results",
      path: "/doc-extract/batches/",
      pathMatch: "prefix",
      entryPath: "/doc-extract",
      target: "dx-results-section",
      title: "3. View results",
      body: "Spreadsheet-style review: click cells to edit, open table fields, Save rows, then Download Excel or CSV.",
      missingHint: "Open a batch detail page to see View results and Download.",
    },
    {
      id: "dx-complete",
      path: "/doc-extract",
      entryPath: "/doc-extract",
      target: "dx-batches-page",
      title: "Tour complete",
      body: "You’re finished. Flow is Upload → Customize → Results — same idea as docs2excel, inside INFRA SUITE. Click Finish tour to close.",
    },
  ],
};

export function docExtractTourStartHref(stepIndex = 0): string {
  const clamped = Math.max(0, Math.min(stepIndex, docExtractLiveTour.steps.length - 1));
  return `/doc-extract?${TOUR_QUERY}=${DOC_EXTRACT_LIVE_TOUR_ID}&${TOUR_STEP_QUERY}=${clamped}`;
}

export function isDocExtractTourId(tourId: string | null | undefined): boolean {
  return tourId === DOC_EXTRACT_LIVE_TOUR_ID;
}

export function isDocExtractTourStep(step: LiveTourStep | null | undefined): boolean {
  return Boolean(step?.id.startsWith("dx-"));
}

export function isDocExtractTourActive(searchParams: URLSearchParams | { get: (key: string) => string | null }): boolean {
  return searchParams.get(TOUR_QUERY) === DOC_EXTRACT_LIVE_TOUR_ID;
}
