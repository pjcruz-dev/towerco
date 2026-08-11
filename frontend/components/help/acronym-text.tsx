"use client";

import { Fragment, useMemo, type ReactNode } from "react";

import { AcronymTip } from "@/components/help/acronym-tip";
import { useOperationalAcronyms } from "@/components/help/acronym-provider";
import { escapeRegExp, sortedAcronymKeys } from "@/lib/operational-acronyms/build-acronym-map";
import { cn } from "@/lib/utils";

type Props = {
  text: string;
  className?: string;
};

type Segment = { type: "text" | "acronym"; value: string };

function parseSegments(text: string, keys: string[]): Segment[] {
  if (!text || keys.length === 0) {
    return [{ type: "text", value: text }];
  }

  const pattern = new RegExp(`\\b(${keys.map(escapeRegExp).join("|")})\\b`, "gi");
  const parts = text.split(pattern).filter((part) => part !== "");

  return parts.map((part) => {
    const matched = keys.find((key) => key.toLowerCase() === part.toLowerCase());
    if (matched) {
      return { type: "acronym", value: matched };
    }
    return { type: "text", value: part };
  });
}

export function AcronymText({ text, className }: Props) {
  const { map } = useOperationalAcronyms();
  const keys = useMemo(() => sortedAcronymKeys(map), [map]);
  const segments = useMemo(() => parseSegments(text, keys), [keys, text]);

  const nodes: ReactNode[] = segments.map((segment, index) => {
    if (segment.type === "acronym") {
      return <AcronymTip key={`${segment.value}-${index}`} acronym={segment.value} />;
    }
    return <Fragment key={`t-${index}`}>{segment.value}</Fragment>;
  });

  return <span className={cn(className)}>{nodes}</span>;
}
