import type { OperationalAcronym } from "@/lib/operational-acronyms/types";

/** Offline fallback when the public glossary API is unavailable (dev/local). */
export const DEFAULT_OPERATIONAL_ACRONYMS: Array<Pick<OperationalAcronym, "acronym" | "definition" | "category">> = [
  { acronym: "MNO", definition: "Mobile Network Operator", category: "Rollout" },
  { acronym: "BTS", definition: "Build-to-Suit", category: "Rollout" },
  { acronym: "RTB", definition: "Ready-to-Build", category: "Rollout" },
  { acronym: "SAQ", definition: "Site Acquisition", category: "Rollout" },
  { acronym: "CME", definition: "Civil, Mechanical, Electrical (construction discipline)", category: "Rollout" },
  { acronym: "RFI / RFTI", definition: "Ready for Installation / Ready for Telecom Installation", category: "Rollout" },
  { acronym: "SLA", definition: "Service Level Agreement", category: "Operations" },
  { acronym: "PMO", definition: "Project Management Office", category: "Operations" },
  { acronym: "SR", definition: "Search Ring", category: "Rollout" },
  { acronym: "TSSR", definition: "Technical Site Survey Report", category: "Engineering" },
  { acronym: "TCO ID", definition: "Tower Co Identifier (internal site ID)", category: "Rollout" },
];
