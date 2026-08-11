/**
 * Maps rollout timeline / milestone phase_key values to platform glossary acronyms.
 * Aligned with TowerOS OperationalAcronymDefaults + RolloutPlaybook definitions.
 */
export const ROLLOUT_PHASE_ACRONYMS: Record<string, string> = {
  // Lifecycle / program
  saq: "SAQ",
  cme: "CME",
  permitting: "BP",
  permit_prep: "BP",
  building_permit: "BP",
  locational_clearance: "OBO",
  rfi: "RFI / RFTI",
  rfti_submission: "RFI / RFTI",
  rtb: "RTB",
  bts: "BTS",

  // Site acquisition & engineering
  site_hunting: "SAQ",
  endorsement: "MNO",
  endorsement_to_hunting: "MNO",
  pre_assessment: "SAQ",
  tssr_creation: "TSSR",
  tssr_mno_approval: "TSSR",
  tssr: "TSSR",
  sbt: "SBT / SI",
  structural_investigation: "SBT / SI",

  // Legal / lease
  moc_securing: "MOC",
  moc_col: "MOC",
  col_social: "COL",
  col: "COL",
  site_license: "COL",
  elas: "eLAS",
  mla: "MLA",
  row: "ROW",

  // Design & construction
  pre_construction: "CME",
  ddd: "DDD",
  boq: "BOQ",
  construction: "CME",
  energization: "MERALCO",
  skom: "SKOM",
  implementation: "CME",
  vo: "VO",
  dlp: "DLP",

  // Regulatory / utilities
  ntc: "NTC",
  denr: "DENR ECC / CNC",
  caap: "CAAP",
  ncr: "NCR",
  psh: "PSH",
  hta: "HTA",
  rgs: "RGS",

  // Operations
  billing: "PMO",
  bd_pmo: "PMO",
};

/** Resolve glossary term from phase_key (normalized snake_case). */
export function resolveRolloutPhaseAcronym(phaseKey: string): string | null {
  const key = phaseKey.trim().toLowerCase();
  if (!key) {
    return null;
  }

  if (ROLLOUT_PHASE_ACRONYMS[key]) {
    return ROLLOUT_PHASE_ACRONYMS[key];
  }

  // Partial key match (e.g. custom phase containing "tssr")
  for (const [pattern, term] of Object.entries(ROLLOUT_PHASE_ACRONYMS)) {
    if (key.includes(pattern) && pattern.length >= 3) {
      return term;
    }
  }

  return null;
}
