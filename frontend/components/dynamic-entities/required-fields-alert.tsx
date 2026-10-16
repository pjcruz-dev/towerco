"use client";

import { AlertCircleIcon } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";

import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
  missingRequiredFields,
  type RequiredFieldRef,
} from "@/lib/dynamic-entities/field-required";

const DISMISS_MS = 3000;

export function RequiredFieldsAlert({
  fields,
  pulse,
}: {
  fields: RequiredFieldRef[];
  /** Bumps on each blocked save so the toast shows again. */
  pulse: number;
}) {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (pulse === 0) return;
    setOpen(true);
    const timer = window.setTimeout(() => setOpen(false), DISMISS_MS);
    const frame = window.requestAnimationFrame(() => {
      document.querySelector<HTMLElement>("[data-dyn-invalid]")?.scrollIntoView({
        behavior: "smooth",
        block: "center",
      });
    });
    return () => {
      window.clearTimeout(timer);
      window.cancelAnimationFrame(frame);
    };
  }, [pulse]);

  if (!open || fields.length === 0 || typeof document === "undefined") return null;

  const labels = fields.map((field) => field.label);
  const list =
    labels.length === 1
      ? labels[0]
      : `${labels.slice(0, -1).join(", ")} and ${labels[labels.length - 1]}`;

  return createPortal(
    <div className="pointer-events-none fixed top-4 right-4 z-[100] w-[min(24rem,calc(100vw-2rem))]">
      <Alert variant="destructive" className="pointer-events-auto bg-background shadow-lg">
        <AlertCircleIcon />
        <AlertTitle>Required fields</AlertTitle>
        <AlertDescription className="text-destructive/90">
          {list} {labels.length === 1 ? "needs a value" : "need a value"} before this can be saved.
        </AlertDescription>
      </Alert>
    </div>,
    document.body,
  );
}

/** Turns on after a blocked save. The toast clears itself; the field rings stay until filled. */
export function useRequiredFields(
  fields: RequiredFieldRef[],
  values: Record<string, string | undefined>,
) {
  const [active, setActive] = useState(false);
  const [pulse, setPulse] = useState(0);
  const missing = useMemo(
    () => (active ? missingRequiredFields(fields, values) : []),
    [active, fields, values],
  );
  const invalidNames = useMemo(() => new Set(missing.map((field) => field.name)), [missing]);

  function blockIfMissing(): boolean {
    const next = missingRequiredFields(fields, values);
    setActive(next.length > 0);
    if (next.length > 0) setPulse((current) => current + 1);
    return next.length > 0;
  }

  return { missing, invalidNames, pulse, blockIfMissing };
}
