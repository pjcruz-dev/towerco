"use client";

import Link from "next/link";
import { ExternalLink } from "lucide-react";

import type { AssistantCitation, AssistantRelatedLink } from "@/lib/api/modules/assistant-api";

type Props = {
  citations: AssistantCitation[];
  relatedLinks?: AssistantRelatedLink[];
};

export function AssistantCitations({ citations, relatedLinks = [] }: Props) {
  if (citations.length === 0 && relatedLinks.length === 0) {
    return null;
  }

  const uniqueCitations = Array.from(
    new Map(
      citations.map((citation) => [
        citation.slug || citation.source_id || citation.chunk_id,
        citation,
      ]),
    ).values(),
  ).slice(0, 5);

  const routes = relatedLinks.length > 0
    ? relatedLinks
    : uniqueCitations.flatMap((citation) =>
        (citation.related_routes ?? []).map((href) => ({
          label: href,
          href,
        })),
      );

  const uniqueRoutes = Array.from(
    new Map(routes.map((route) => [route.href, route])).values(),
  ).slice(0, 6);

  return (
    <div className="mt-3 space-y-2 border-t border-border/70 pt-3">
      {uniqueCitations.length > 0 ? (
        <div>
          <p className="text-xs font-medium text-muted-foreground">Sources</p>
          <ul className="mt-1.5 space-y-1">
            {uniqueCitations.map((citation) => (
              <li key={citation.chunk_id} className="text-xs text-foreground/90">
                <span className="font-medium">{citation.title}</span>
                {citation.type === "live_data" || citation.scope === "live" ? (
                  <span className="text-sky-700 dark:text-sky-300"> · live</span>
                ) : citation.slug ? (
                  <span className="text-muted-foreground"> · {citation.slug}</span>
                ) : null}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {uniqueRoutes.length > 0 ? (
        <div>
          <p className="text-xs font-medium text-muted-foreground">Related pages</p>
          <ul className="mt-1.5 space-y-1">
            {uniqueRoutes.map((route) => (
              <li key={route.href}>
                <Link
                  href={route.href}
                  className="inline-flex items-center gap-1 text-xs font-medium text-foreground underline-offset-2 hover:underline"
                >
                  {route.label}
                  <ExternalLink className="h-3 w-3 text-muted-foreground" />
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
