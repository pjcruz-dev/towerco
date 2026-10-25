"use client";

import { FileDown } from "lucide-react";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { downloadTourStepPdf, type TourStepPdfInput } from "@/lib/help/tour-step-pdf";

type Props = TourStepPdfInput;

/** Downloads one PDF page for every step in the role-filtered tour. */
export function TourExportPdfButton({ title, fileName, steps, chapterLabels }: Props) {
  const [busy, setBusy] = useState(false);

  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      className="print:hidden"
      disabled={busy || steps.length === 0}
      aria-label={`Export ${title} to PDF, one page per step`}
      onClick={() => {
        setBusy(true);
        void downloadTourStepPdf({ title, fileName, steps, chapterLabels }).finally(() => setBusy(false));
      }}
    >
      <FileDown className="mr-1.5 h-4 w-4" aria-hidden />
      {busy ? "Exporting…" : "Export PDF"}
    </Button>
  );
}
