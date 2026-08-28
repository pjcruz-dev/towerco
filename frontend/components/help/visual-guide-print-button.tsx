"use client";

import { Printer } from "lucide-react";

import { Button } from "@/components/ui/button";

export function VisualGuidePrintButton() {
  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      className="print:hidden"
      onClick={() => {
        // Let layout settle so print:block sections (all tabs) are measured.
        window.requestAnimationFrame(() => {
          window.print();
        });
      }}
    >
      <Printer className="mr-1.5 h-4 w-4" aria-hidden />
      Print
    </Button>
  );
}
