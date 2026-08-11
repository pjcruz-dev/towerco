"use client";

import type { ReactNode } from "react";

import { AcronymTip } from "@/components/help/acronym-tip";

/** Standard table/header label with hover definition from the platform glossary. */
export function AcronymLabel({
  term,
  children,
  className,
}: {
  term: string;
  children?: ReactNode;
  className?: string;
}) {
  return (
    <AcronymTip acronym={term} className={className}>
      {children ?? term}
    </AcronymTip>
  );
}
