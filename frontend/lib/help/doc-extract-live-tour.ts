import type { LiveTourDefinition, LiveTourStep } from "@/lib/help/e-approval-live-tour";

/** Standalone product tour — DocExtract upload → consolidate → customize → results. */
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
      body: "In the sidebar, open DocExtract. Batches lists past runs; New extraction starts Upload → Consolidate → Customize → Results.",
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
      body: "Browse runs by status. File name shows the first uploaded file (+++ when multiple). Ready rows offer View results and Download.",
    },
    {
      id: "dx-list-toolkit",
      path: "/doc-extract",
      entryPath: "/doc-extract",
      target: "dx-list-toolkit",
      title: "Columns, layouts & export",
      body: "Use Columns and Layouts (personal; Share is admin-only). Export filtered/selected rows — large sets queue to Settings → My exports. Search: status:ready, status:ready|processing, status!=failed, filename~invoice, created>=2026-01-01. Print this page or all filtered (capped at 5000; larger sets use Export). In Customize, × or gear → Remove removes a widget (re-add from + Add widget).",
      missingHint: "Open the batches table toolbar (Columns / Layouts / Export).",
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
      body: "Review the file list before continuing. Remove anything you do not want scanned.",
    },
    {
      id: "dx-continue-consolidate",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-continue-consolidate",
      title: "Continue to consolidate",
      body: "After files are listed, continue to group pages into records before extraction.",
      missingHint: "Upload at least one file to enable Continue.",
      skipIfMissing: true,
    },
    {
      id: "dx-consolidate",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-consolidate-section",
      title: "2. Consolidate",
      body: "Group multi-page files into one record each, or split pages across records. This runs before OCR so columns stay aligned.",
      missingHint: "Advance past Upload to see the consolidate step, or open an existing batch workspace.",
      skipIfMissing: true,
    },
    {
      id: "dx-template",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-template-select",
      title: "Template or auto-detect",
      body: "Auto-detect recommends fields from OCR (utility bills, payment advice, and more). Pick a saved template for fixed columns on repeat runs.",
      skipIfMissing: true,
    },
    {
      id: "dx-extract",
      path: "/doc-extract/new",
      entryPath: "/doc-extract/new",
      target: "dx-extract-button",
      title: "Extract data",
      body: "Start the scan. You land on the extraction workspace: files, consolidate, customize fields, then view results.",
      skipIfMissing: true,
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
      id: "dx-workspace-consolidate",
      path: "/doc-extract/batches/",
      pathMatch: "prefix",
      entryPath: "/doc-extract",
      target: "dx-consolidate-section",
      title: "2. Consolidate (workspace)",
      body: "Adjust how pages map to records if needed, then continue to customize columns.",
      missingHint: "Open a batch detail page to see Consolidate.",
    },
    {
      id: "dx-workspace-customize",
      path: "/doc-extract/batches/",
      pathMatch: "prefix",
      entryPath: "/doc-extract",
      target: "dx-customize-section",
      title: "3. Customize fields",
      body: "On auto-detect, remove noisy columns and optionally Save as template. Template runs show fixed field cards.",
      missingHint: "Open a batch detail page to see Customize data to extract.",
    },
    {
      id: "dx-workspace-results",
      path: "/doc-extract/batches/",
      pathMatch: "prefix",
      entryPath: "/doc-extract",
      target: "dx-results-section",
      title: "4. View results",
      body: "Spreadsheet-style review: click cells to edit, open table fields, Save rows, then Download Excel or CSV.",
      missingHint: "Open a batch detail page to see View results and Download.",
    },
    {
      id: "dx-complete",
      path: "/doc-extract",
      entryPath: "/doc-extract",
      target: "dx-batches-page",
      title: "Tour complete",
      body: "You’re finished. Flow is Upload → Consolidate → Customize → Results — same idea as docs2excel, inside INFRA SUITE. Click Finish tour to close.",
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
