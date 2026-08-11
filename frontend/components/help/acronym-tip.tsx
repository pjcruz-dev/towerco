"use client";

import { lookupAcronym } from "@/lib/operational-acronyms/build-acronym-map";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { useOperationalAcronyms } from "@/components/help/acronym-provider";
import { cn } from "@/lib/utils";

type Props = {
  acronym: string;
  className?: string;
  children?: React.ReactNode;
};

/**
 * Inline glossary trigger — uses <span>, never <button>, so it is safe inside tabs/links/buttons.
 */
export function AcronymTip({ acronym, className, children }: Props) {
  const { map } = useOperationalAcronyms();
  const row = lookupAcronym(map, acronym);

  if (!row) {
    return <>{children ?? acronym}</>;
  }

  const label = children ?? row.acronym;

  return (
    <Tooltip>
      <TooltipTrigger
        closeOnClick
        delay={400}
        render={
          <span
            className={cn(
              "inline cursor-help touch-manipulation border-b border-dotted border-primary/50 font-medium text-inherit",
              className,
            )}
            tabIndex={0}
            role="term"
            aria-label={`${row.acronym}: ${row.definition}`}
          />
        }
      >
        {label}
      </TooltipTrigger>
      <TooltipContent side="top" className="max-w-[min(20rem,calc(100vw-2rem))] text-left">
        <p className="font-medium">{row.acronym}</p>
        <p className="mt-0.5 font-normal text-background/90">{row.definition}</p>
        {row.category ? <p className="mt-1 text-[10px] text-background/70">{row.category}</p> : null}
      </TooltipContent>
    </Tooltip>
  );
}
