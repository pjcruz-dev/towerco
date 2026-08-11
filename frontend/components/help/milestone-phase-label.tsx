"use client";

import { AcronymLabel } from "@/components/help/acronym-label";
import { AcronymText } from "@/components/help/acronym-text";
import { resolveRolloutPhaseAcronym } from "@/lib/operational-acronyms/rollout-phase-acronyms";

type Props = {
  phaseKey: string;
  label: string;
  className?: string;
};

/**
 * Rollout milestone / timeline label: glossary tooltip via phase_key map, then inline acronym scan on label text.
 */
export function MilestonePhaseLabel({ phaseKey, label, className }: Props) {
  const term = resolveRolloutPhaseAcronym(phaseKey);

  if (term) {
    return (
      <AcronymLabel term={term} className={className}>
        {label}
      </AcronymLabel>
    );
  }

  return <AcronymText text={label} className={className} />;
}

/** Project QMS milestone name — scan label text for known acronyms (MNO, SAQ, RFI, etc.). */
export function ProjectMilestoneLabel({ name, className }: { name: string; className?: string }) {
  return <AcronymText text={name} className={className} />;
}

/** Gate approval / control gate description with acronym tooltips. */
export function GateLabelText({ text, className }: { text: string; className?: string }) {
  return <AcronymText text={text} className={className} />;
}
