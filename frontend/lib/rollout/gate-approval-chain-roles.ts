/**
 * Gate approval chain step keys — must match RolloutGateApproverResolver role matching.
 */

export type GateApprovalChainRoleOption = {
  value: string;
  label: string;
  /** Shown in UI hints — maps to rollout metadata owner fields. */
  ownerHint?: string;
};

/** Primary steps — align with SAQ owner, PMO owner, and CME PM on each rollout. */
export const GATE_APPROVAL_CHAIN_CORE_ROLES: GateApprovalChainRoleOption[] = [
  { value: "saq", label: "SAQ", ownerHint: "SAQ owner" },
  { value: "pmo", label: "PMO", ownerHint: "PMO owner" },
  { value: "cme", label: "CME", ownerHint: "CME PM" },
];

/** Specialized playbook steps (TSSR MNO, engineering sign-off, escalation, etc.). */
export const GATE_APPROVAL_CHAIN_ADVANCED_ROLES: GateApprovalChainRoleOption[] = [
  { value: "saq_engineering", label: "SAQ engineering" },
  { value: "engineering", label: "Engineering" },
  { value: "cme_power", label: "CME / power" },
  { value: "mno", label: "MNO" },
  { value: "bd", label: "BD" },
  { value: "bd_pmo", label: "BD / PMO" },
  { value: "tenant_admin", label: "Tenant admin (escalation)" },
];

export const GATE_APPROVAL_CHAIN_ROLES: GateApprovalChainRoleOption[] = [
  ...GATE_APPROVAL_CHAIN_CORE_ROLES,
  ...GATE_APPROVAL_CHAIN_ADVANCED_ROLES,
];

const coreRoleValues = new Set(GATE_APPROVAL_CHAIN_CORE_ROLES.map((role) => role.value));
const roleValues = new Set(GATE_APPROVAL_CHAIN_ROLES.map((role) => role.value));

export type GateApprovalChainRole =
  | (typeof GATE_APPROVAL_CHAIN_CORE_ROLES)[number]["value"]
  | (typeof GATE_APPROVAL_CHAIN_ADVANCED_ROLES)[number]["value"];

export function isCoreGateApprovalChainRole(role: string): boolean {
  return coreRoleValues.has(role);
}

export function isAdvancedGateApprovalChainRole(role: string): boolean {
  return isKnownGateApprovalChainRole(role) && !isCoreGateApprovalChainRole(role);
}

export function chainUsesAdvancedGateRoles(chain: string[]): boolean {
  return chain.some((role) => isAdvancedGateApprovalChainRole(role));
}

export function isKnownGateApprovalChainRole(role: string): role is GateApprovalChainRole {
  return roleValues.has(role);
}

export function gateApprovalChainRoleLabel(role: string): string {
  const match = GATE_APPROVAL_CHAIN_ROLES.find((entry) => entry.value === role);
  return match?.label ?? role.replaceAll("_", " ");
}

export function roleOptionsForChainStep(
  currentRole: string,
  showAdvanced: boolean,
): GateApprovalChainRoleOption[] {
  if (showAdvanced || isAdvancedGateApprovalChainRole(currentRole)) {
    return GATE_APPROVAL_CHAIN_ROLES;
  }

  return GATE_APPROVAL_CHAIN_CORE_ROLES;
}

export function defaultRoleForNewChainStep(chain: string[]): GateApprovalChainRole {
  const used = new Set(chain);

  const coreNext = GATE_APPROVAL_CHAIN_CORE_ROLES.find((role) => !used.has(role.value));
  if (coreNext) {
    return coreNext.value as GateApprovalChainRole;
  }

  const anyNext = GATE_APPROVAL_CHAIN_ROLES.find((role) => !used.has(role.value));
  return (anyNext?.value ?? "pmo") as GateApprovalChainRole;
}

export function sanitizeGateApprovalChain(chain: string[]): GateApprovalChainRole[] {
  const seen = new Set<string>();
  const result: GateApprovalChainRole[] = [];

  for (const raw of chain) {
    const role = raw.trim().toLowerCase();
    if (!role || !isKnownGateApprovalChainRole(role) || seen.has(role)) {
      continue;
    }
    seen.add(role);
    result.push(role);
  }

  return result;
}
