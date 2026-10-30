import type {
  EApprovalWorkflowCondition,
  EApprovalWorkflowConditionOperator,
  EApprovalWorkflowStepInput,
} from "@/modules/e-approval/types";
import { isStepOwnedCondition, parseStepWhen, patchStepWhen } from "@/modules/e-approval/workflow-conditions";
import {
  compactWorkflowStepOrdersPreservingTies,
  detectParallelApprovalGroups,
  type ParallelApprovalGroup,
} from "@/modules/e-approval/workflow-parallel-groups";

export type BranchThresholdOperatorLow = "lte" | "lt";
export type BranchThresholdOperatorHigh = "gt" | "gte";

export type ExclusiveBranchGroup = {
  /** First index of the adjacent pair (min of the two step indexes). */
  startIndex: number;
  /** Step with ≤ / < condition. */
  lowIndex: number;
  /** Step with > / ≥ condition. */
  highIndex: number;
  field: string;
  threshold: string;
  lowOperator: BranchThresholdOperatorLow;
  highOperator: BranchThresholdOperatorHigh;
};

/** One exclusive numeric band on a field (open-ended or closed). */
export type ThresholdBand = {
  field: string;
  lower: { operator: BranchThresholdOperatorHigh; value: string } | null;
  upper: { operator: BranchThresholdOperatorLow; value: string } | null;
};

/** Adjacent exclusive bands on one field (2+ steps), including classic If/Else. */
export type ExclusiveThresholdLadder = {
  startIndex: number;
  endIndex: number;
  field: string;
  bandIndexes: number[];
  thresholds: string[];
  /** Amount bands use ≤ / >. Value bands match a choice or text exactly. */
  match?: "amount" | "value";
};

export type ValueMatchOperator = "equals" | "not_equals";

export type ValueMatchCase = {
  operator: ValueMatchOperator;
  value: string;
};

export type WorkflowEditorSegment =
  | { type: "single"; index: number }
  | { type: "branch"; group: ExclusiveBranchGroup }
  | { type: "ladder"; ladder: ExclusiveThresholdLadder }
  | { type: "parallel"; group: ParallelApprovalGroup };

/** Adjacent complementary ops on the same field, but different thresholds (won’t group). */
export type NearMissBranchPair = {
  startIndex: number;
  lowIndex: number;
  highIndex: number;
  field: string;
  lowThreshold: string;
  highThreshold: string;
  lowOperator: BranchThresholdOperatorLow;
  highOperator: BranchThresholdOperatorHigh;
};

const COMPLEMENTARY_PAIRS: Array<{
  low: BranchThresholdOperatorLow;
  high: BranchThresholdOperatorHigh;
}> = [
  { low: "lte", high: "gt" },
  { low: "lt", high: "gte" },
];

function normalizeThreshold(value: string | undefined): string {
  return String(value ?? "").trim();
}

function isLowOperator(operator: EApprovalWorkflowConditionOperator): operator is BranchThresholdOperatorLow {
  return operator === "lte" || operator === "lt";
}

function isHighOperator(operator: EApprovalWorkflowConditionOperator): operator is BranchThresholdOperatorHigh {
  return operator === "gt" || operator === "gte";
}

/** True when a boundary is a number, so the band is an amount comparison. */
export function isAmountBoundary(value: string): boolean {
  const trimmed = value.trim().replace(/,/g, "");
  return trimmed !== "" && /^-?\d+(\.\d+)?$/.test(trimmed);
}

function numericSortThresholds(values: string[]): string[] {
  return [...new Set(values.map(normalizeThreshold).filter(Boolean))].sort(
    (a, b) => Number(a) - Number(b),
  );
}

/** Single numeric-threshold condition usable for exclusive if/else pairing. */
export function getExclusiveThresholdCondition(
  step: EApprovalWorkflowStepInput,
): EApprovalWorkflowCondition | null {
  const when = parseStepWhen(step);
  if (when.length !== 1) {
    return null;
  }

  const condition = when[0];
  const field = condition.field.trim();
  const threshold = normalizeThreshold(condition.value);
  if (!field || !threshold) {
    return null;
  }

  if (!isLowOperator(condition.operator) && !isHighOperator(condition.operator)) {
    return null;
  }

  return {
    field,
    operator: condition.operator,
    value: threshold,
  };
}

/** Parse a step into an open/closed threshold band (1–2 conditions, same field). */
export function parseThresholdBand(step: EApprovalWorkflowStepInput): ThresholdBand | null {
  const when = parseStepWhen(step);
  if (when.length === 0 || when.length > 2) {
    return null;
  }

  const field = when[0].field.trim();
  if (!field || when.some((condition) => condition.field.trim() !== field)) {
    return null;
  }

  let lower: ThresholdBand["lower"] = null;
  let upper: ThresholdBand["upper"] = null;

  for (const condition of when) {
    const value = normalizeThreshold(condition.value);
    if (!value) {
      return null;
    }
    if (isHighOperator(condition.operator)) {
      if (lower) {
        return null;
      }
      lower = { operator: condition.operator, value };
      continue;
    }
    if (isLowOperator(condition.operator)) {
      if (upper) {
        return null;
      }
      upper = { operator: condition.operator, value };
      continue;
    }
    return null;
  }

  if (!lower && !upper) {
    return null;
  }

  return { field, lower, upper };
}

function complementaryHigh(
  low: BranchThresholdOperatorLow,
): BranchThresholdOperatorHigh | null {
  const pair = COMPLEMENTARY_PAIRS.find((item) => item.low === low);
  return pair?.high ?? null;
}

function complementaryLow(
  high: BranchThresholdOperatorHigh,
): BranchThresholdOperatorLow | null {
  const pair = COMPLEMENTARY_PAIRS.find((item) => item.high === high);
  return pair?.low ?? null;
}

/**
 * Detect adjacent exclusive if/else pairs: same field, complementary operators
 * (`lte`/`gt` or `lt`/`gte`), same threshold. Non-overlapping; scans left to right.
 */
export function detectExclusiveBranchGroups(
  steps: EApprovalWorkflowStepInput[],
): ExclusiveBranchGroup[] {
  const groups: ExclusiveBranchGroup[] = [];
  let index = 0;

  while (index < steps.length - 1) {
    if (isStepOwnedCondition(steps[index]) || isStepOwnedCondition(steps[index + 1])) {
      index += 1;
      continue;
    }

    const left = getExclusiveThresholdCondition(steps[index]);
    const right = getExclusiveThresholdCondition(steps[index + 1]);

    if (!left || !right || left.field !== right.field || left.value !== right.value) {
      index += 1;
      continue;
    }

    const pair = resolveComplementaryPair(left, right, index, index + 1);
    if (!pair) {
      index += 1;
      continue;
    }

    groups.push({
      ...pair,
      threshold: left.value ?? "",
    });
    index += 2;
  }

  return groups;
}

function resolveComplementaryPair(
  left: EApprovalWorkflowCondition,
  right: EApprovalWorkflowCondition,
  leftIndex: number,
  rightIndex: number,
): Omit<ExclusiveBranchGroup, "threshold"> | null {
  let lowIndex: number | null = null;
  let highIndex: number | null = null;
  let lowOperator: BranchThresholdOperatorLow | null = null;
  let highOperator: BranchThresholdOperatorHigh | null = null;

  if (isLowOperator(left.operator) && complementaryHigh(left.operator) === right.operator) {
    lowIndex = leftIndex;
    highIndex = rightIndex;
    lowOperator = left.operator;
    highOperator = right.operator;
  } else if (isHighOperator(left.operator) && complementaryLow(left.operator) === right.operator) {
    highIndex = leftIndex;
    lowIndex = rightIndex;
    highOperator = left.operator;
    lowOperator = right.operator;
  }

  if (lowIndex == null || highIndex == null || !lowOperator || !highOperator) {
    return null;
  }

  return {
    startIndex: Math.min(leftIndex, rightIndex),
    lowIndex,
    highIndex,
    field: left.field,
    lowOperator,
    highOperator,
  };
}

function ladderThresholdsFromBands(bands: ThresholdBand[]): string[] {
  const values: string[] = [];
  for (const band of bands) {
    if (band.upper) {
      values.push(band.upper.value);
    }
    if (band.lower) {
      values.push(band.lower.value);
    }
  }
  return numericSortThresholds(values);
}

/**
 * True when bands match the generated ladder shape for their sorted thresholds
 * (≤T1), (>T1 ∧ ≤T2), …, (>Tn).
 */
export function isCanonicalThresholdLadder(bands: ThresholdBand[]): boolean {
  if (bands.length < 2) {
    return false;
  }

  const field = bands[0].field;
  if (bands.some((band) => band.field !== field)) {
    return false;
  }

  const thresholds = ladderThresholdsFromBands(bands);
  if (thresholds.length !== bands.length - 1) {
    return false;
  }

  const expected = createThresholdLadderWhens(field, thresholds);
  if (expected.length !== bands.length) {
    return false;
  }

  return bands.every((band, index) => bandsMatchWhen(band, expected[index]));
}

function bandsMatchWhen(band: ThresholdBand, when: EApprovalWorkflowCondition[]): boolean {
  if (when.length === 1) {
    const only = when[0];
    if (isLowOperator(only.operator)) {
      return (
        band.lower == null &&
        band.upper?.operator === only.operator &&
        band.upper.value === only.value
      );
    }
    if (isHighOperator(only.operator)) {
      return (
        band.upper == null &&
        band.lower?.operator === only.operator &&
        band.lower.value === only.value
      );
    }
    return false;
  }

  if (when.length !== 2 || !band.lower || !band.upper) {
    return false;
  }

  const lowWhen = when.find((condition) => isHighOperator(condition.operator));
  const highWhen = when.find((condition) => isLowOperator(condition.operator));
  if (!lowWhen || !highWhen) {
    return false;
  }

  return (
    band.lower.operator === lowWhen.operator &&
    band.lower.value === lowWhen.value &&
    band.upper.operator === highWhen.operator &&
    band.upper.value === highWhen.value
  );
}

/**
 * Detect adjacent exclusive threshold ladders (2+ bands on the same field).
 * Prefers longest canonical runs; non-overlapping left-to-right.
 */
export function detectExclusiveThresholdLadders(
  steps: EApprovalWorkflowStepInput[],
): ExclusiveThresholdLadder[] {
  const ladders: ExclusiveThresholdLadder[] = [];
  let index = 0;

  while (index < steps.length) {
    const first = parseThresholdBand(steps[index]);
    if (!first || isStepOwnedCondition(steps[index])) {
      index += 1;
      continue;
    }

    const bandIndexes = [index];
    let cursor = index + 1;
    while (cursor < steps.length) {
      const next = parseThresholdBand(steps[cursor]);
      if (!next || next.field !== first.field || isStepOwnedCondition(steps[cursor])) {
        break;
      }
      const primary = bandIndexes[bandIndexes.length - 1];
      if (ladderCaseMemberIndexes(steps, primary).includes(cursor) && cursor !== primary) {
        cursor += 1;
        continue;
      }
      bandIndexes.push(cursor);
      cursor += 1;
    }

    if (bandIndexes.length >= 2) {
      const bands = bandIndexes.map((bandIndex) => parseThresholdBand(steps[bandIndex])!);
      if (isCanonicalThresholdLadder(bands)) {
        ladders.push({
          startIndex: bandIndexes[0],
          endIndex: bandIndexes[bandIndexes.length - 1],
          field: first.field,
          bandIndexes,
          thresholds: ladderThresholdsFromBands(bands),
        });
        index = cursor;
        continue;
      }
    }

    index += 1;
  }

  return ladders;
}

/**
 * Adjacent steps that look like If/Else (same field, complementary ≤/> ops)
 * but thresholds differ — so they stay as separate cards.
 */
export function detectNearMissBranchPairs(
  steps: EApprovalWorkflowStepInput[],
): NearMissBranchPair[] {
  const covered = new Set<number>();
  for (const ladder of detectExclusiveThresholdLadders(steps)) {
    for (const bandIndex of ladder.bandIndexes) {
      covered.add(bandIndex);
    }
  }
  for (const group of detectExclusiveBranchGroups(steps)) {
    covered.add(group.lowIndex);
    covered.add(group.highIndex);
  }

  const nearMisses: NearMissBranchPair[] = [];

  for (let index = 0; index < steps.length - 1; index += 1) {
    if (covered.has(index) || covered.has(index + 1)) {
      continue;
    }

    const left = getExclusiveThresholdCondition(steps[index]);
    const right = getExclusiveThresholdCondition(steps[index + 1]);
    if (!left || !right || left.field !== right.field) {
      continue;
    }
    if (left.value === right.value) {
      continue;
    }

    const pair = resolveComplementaryPair(left, right, index, index + 1);
    if (!pair) {
      continue;
    }

    const lowCondition = getExclusiveThresholdCondition(steps[pair.lowIndex]);
    const highCondition = getExclusiveThresholdCondition(steps[pair.highIndex]);
    if (!lowCondition || !highCondition) {
      continue;
    }

    nearMisses.push({
      ...pair,
      lowThreshold: lowCondition.value ?? "",
      highThreshold: highCondition.value ?? "",
    });
  }

  return nearMisses;
}

/** Sync both near-miss steps to one threshold so they group as If/Else. */
export function alignNearMissBranchThresholds(
  steps: EApprovalWorkflowStepInput[],
  nearMiss: NearMissBranchPair,
  threshold?: string,
): EApprovalWorkflowStepInput[] {
  const value = normalizeThreshold(threshold ?? nearMiss.lowThreshold);
  if (!value) {
    return steps;
  }

  return steps.map((step, index) => {
    if (index !== nearMiss.lowIndex && index !== nearMiss.highIndex) {
      return step;
    }

    const condition = getExclusiveThresholdCondition(step);
    if (!condition) {
      return step;
    }

    return patchStepWhen(step, [{ ...condition, value }]);
  });
}

/** Update both sides of an If/Else pair so the exclusive band stays grouped. */
export function updateExclusiveBranchThreshold(
  steps: EApprovalWorkflowStepInput[],
  group: ExclusiveBranchGroup,
  field: string,
  threshold: string,
): EApprovalWorkflowStepInput[] {
  const trimmedField = field.trim();
  const value = normalizeThreshold(threshold);
  if (!trimmedField || !value) {
    return steps;
  }

  const { ifWhen, elseWhen } = createComplementaryIfElseConditions(trimmedField, value);
  return steps.map((step, index) => {
    if (index === group.lowIndex) {
      return patchStepWhen(step, ifWhen);
    }
    if (index === group.highIndex) {
      return patchStepWhen(step, elseWhen);
    }
    return step;
  });
}

/**
 * Rewrite all band `when` clauses for a ladder / If-Else while keeping approvers
 * and other step fields. Threshold count must stay N = bandCount - 1.
 */
export function updateExclusiveLadderThresholds(
  steps: EApprovalWorkflowStepInput[],
  ladder: ExclusiveThresholdLadder,
  field: string,
  thresholds: string[],
): EApprovalWorkflowStepInput[] {
  const trimmedField = field.trim();
  const sorted = numericSortThresholds(thresholds);
  if (!trimmedField || sorted.length !== ladder.bandIndexes.length - 1) {
    return steps;
  }

  const whens = createThresholdLadderWhens(trimmedField, sorted);
  if (whens.length !== ladder.bandIndexes.length) {
    return steps;
  }

  return steps.map((step, index) => {
    const bandOffset = ladder.bandIndexes.findIndex((primary) =>
      ladderCaseMemberIndexes(steps, primary).includes(index),
    );
    if (bandOffset < 0) {
      return step;
    }
    return patchStepWhen(step, whens[bandOffset]);
  });
}

/** Flatten steps into singles, parallel bands, 2-way branches, or multi-band ladders. */
export function toWorkflowEditorSegments(
  steps: EApprovalWorkflowStepInput[],
): WorkflowEditorSegment[] {
  const valueLadders = detectValueMatchLadders(steps);
  const coveredByValue = new Set(valueLadders.flatMap((ladder) => ladder.bandIndexes));
  const ladders = [
    ...valueLadders,
    ...detectExclusiveThresholdLadders(steps).filter((ladder) =>
      ladder.bandIndexes.every((index) => !coveredByValue.has(index)),
    ),
  ];
  const ladderByStart = new Map(ladders.map((ladder) => [ladder.startIndex, ladder]));
  const coveredByLadder = new Set(
    ladders.flatMap((ladder) =>
      ladder.bandIndexes.flatMap((bandIndex) => ladderCaseMemberIndexes(steps, bandIndex)),
    ),
  );

  const parallels = detectParallelApprovalGroups(steps).filter(
    (group) => group.memberIndexes.every((index) => !coveredByLadder.has(index)),
  );
  const parallelByFirst = new Map(
    parallels.map((group) => [group.memberIndexes[0], group] as const),
  );
  const coveredByParallel = new Set(parallels.flatMap((group) => group.memberIndexes));

  const pairs = detectExclusiveBranchGroups(steps).filter(
    (group) =>
      !coveredByLadder.has(group.lowIndex) &&
      !coveredByLadder.has(group.highIndex) &&
      !coveredByParallel.has(group.lowIndex) &&
      !coveredByParallel.has(group.highIndex),
  );
  const pairByStart = new Map(pairs.map((group) => [group.startIndex, group]));

  const segments: WorkflowEditorSegment[] = [];
  let index = 0;

  while (index < steps.length) {
    if (coveredByParallel.has(index) && !parallelByFirst.has(index)) {
      index += 1;
      continue;
    }

    const parallel = parallelByFirst.get(index);
    if (parallel) {
      segments.push({ type: "parallel", group: parallel });
      index = Math.max(...parallel.memberIndexes) + 1;
      continue;
    }

    const ladder = ladderByStart.get(index);
    if (ladder) {
      segments.push({ type: "ladder", ladder });
      const tail = ladderCaseMemberIndexes(
        steps,
        ladder.bandIndexes[ladder.bandIndexes.length - 1] ?? ladder.endIndex,
      );
      index = Math.max(ladder.endIndex, tail[tail.length - 1] ?? ladder.endIndex) + 1;
      continue;
    }

    const group = pairByStart.get(index);
    if (group) {
      segments.push({ type: "branch", group });
      index += 2;
      continue;
    }

    segments.push({ type: "single", index });
    index += 1;
  }

  return segments;
}

/** Default if/else pair: field ≤ threshold / field > threshold. */
export function createComplementaryIfElseConditions(
  field: string,
  threshold: string,
): { ifWhen: EApprovalWorkflowCondition[]; elseWhen: EApprovalWorkflowCondition[] } {
  const bands = createThresholdLadderWhens(field, [threshold]);
  return {
    ifWhen: bands[0] ?? [],
    elseWhen: bands[1] ?? [],
  };
}

/**
 * Build exclusive band conditions for sorted thresholds.
 * N thresholds → N+1 bands: ≤T1 | (>T1 ∧ ≤T2) | … | >Tn
 */
export function createThresholdLadderWhens(
  field: string,
  thresholds: string[],
): EApprovalWorkflowCondition[][] {
  const trimmedField = field.trim();
  const sorted = numericSortThresholds(thresholds);
  if (!trimmedField || sorted.length === 0) {
    return [];
  }

  const bands: EApprovalWorkflowCondition[][] = [
    [{ field: trimmedField, operator: "lte", value: sorted[0] }],
  ];

  for (let index = 0; index < sorted.length - 1; index += 1) {
    bands.push([
      { field: trimmedField, operator: "gt", value: sorted[index] },
      { field: trimmedField, operator: "lte", value: sorted[index + 1] },
    ]);
  }

  bands.push([{ field: trimmedField, operator: "gt", value: sorted[sorted.length - 1] }]);
  return bands;
}

export type IfElseBranchStepInput = {
  field: string;
  threshold: string;
  ifApproverId: string;
  elseApproverId: string;
  duty?: "review";
};

export type ThresholdLadderStepInput = {
  field: string;
  /** Boundary values; length N creates N+1 bands. Must match approverIds length = N+1. */
  thresholds: string[];
  approverIds: string[];
  duty?: "review";
};

/** Insert (or append) two fixed-user steps with complementary amount/threshold conditions. */
export function insertIfElseBranchSteps(
  steps: EApprovalWorkflowStepInput[],
  input: IfElseBranchStepInput,
  atIndex?: number,
): EApprovalWorkflowStepInput[] {
  return insertThresholdLadderSteps(
    steps,
    {
      field: input.field,
      thresholds: [input.threshold],
      approverIds: [input.ifApproverId, input.elseApproverId],
      duty: input.duty,
    },
    atIndex,
  );
}

/** Insert exclusive multi-band ladder steps (2+ bands). */
export function insertThresholdLadderSteps(
  steps: EApprovalWorkflowStepInput[],
  input: ThresholdLadderStepInput,
  atIndex?: number,
): EApprovalWorkflowStepInput[] {
  const whens = createThresholdLadderWhens(input.field, input.thresholds);
  if (whens.length < 2 || whens.length !== input.approverIds.length) {
    return steps;
  }

  const insertAt = Math.max(0, Math.min(atIndex ?? steps.length, steps.length));
  const before = compactWorkflowStepOrdersPreservingTies(steps.slice(0, insertAt));
  const afterSource = steps.slice(insertAt);
  const baseOrder =
    before.length > 0 ? Math.max(...before.map((step, index) => step.step_order ?? index + 1)) : 0;

  const ladderSteps = whens.map((when, offset) =>
    patchStepWhen(
      {
        type: "user",
        approverId: input.approverIds[offset]?.trim() ?? "",
        step_order: baseOrder + offset + 1,
        ...(input.duty === "review" ? { duty: "review" as const } : {}),
      },
      when,
    ),
  );

  const after = compactWorkflowStepOrdersPreservingTies(afterSource).map((step, index) => ({
    ...step,
    step_order: (step.step_order ?? index + 1) + baseOrder + whens.length,
  }));

  return [...before, ...ladderSteps, ...after];
}

export function appendIfElseBranchSteps(
  steps: EApprovalWorkflowStepInput[],
  input: IfElseBranchStepInput,
): EApprovalWorkflowStepInput[] {
  return insertIfElseBranchSteps(steps, input);
}

export function branchGroupLabel(
  group: ExclusiveBranchGroup,
  fieldLabel?: string,
): { ifLabel: string; elseLabel: string; header: string } {
  const field = fieldLabel?.trim() || group.field;
  if (!isAmountBoundary(group.threshold)) {
    return {
      ifLabel: `${field} is ${group.threshold}`,
      elseLabel: `${field} is not ${group.threshold}`,
      header: `If / Else — ${field}`,
    };
  }

  const lowSymbol = group.lowOperator === "lte" ? "≤" : "<";
  const highSymbol = group.highOperator === "gt" ? ">" : "≥";

  return {
    ifLabel: `${field} ${lowSymbol} ${group.threshold}`,
    elseLabel: `${field} ${highSymbol} ${group.threshold}`,
    header: `If / Else — ${field}`,
  };
}

export function thresholdBandLabel(band: ThresholdBand, fieldLabel?: string): string {
  const field = fieldLabel?.trim() || band.field;
  if (band.upper && !band.lower && !isAmountBoundary(band.upper.value)) {
    return `${field} is ${band.upper.value}`;
  }
  if (band.lower && !band.upper && !isAmountBoundary(band.lower.value)) {
    return `${field} is not ${band.lower.value}`;
  }

  const lowSymbol = (operator: BranchThresholdOperatorHigh) => (operator === "gt" ? ">" : "≥");
  const highSymbol = (operator: BranchThresholdOperatorLow) => (operator === "lte" ? "≤" : "<");

  if (band.lower && band.upper) {
    return `${field} ${lowSymbol(band.lower.operator)} ${band.lower.value} and ${highSymbol(band.upper.operator)} ${band.upper.value}`;
  }
  if (band.upper) {
    return `${field} ${highSymbol(band.upper.operator)} ${band.upper.value}`;
  }
  if (band.lower) {
    return `${field} ${lowSymbol(band.lower.operator)} ${band.lower.value}`;
  }
  return field;
}

export function ladderGroupLabel(
  ladder: ExclusiveThresholdLadder,
  fieldLabel?: string,
): { header: string; bandLabels: string[]; thresholdsLabel: string } {
  const field = fieldLabel?.trim() || ladder.field;
  const valueMatch = ladder.match === "value";
  return {
    header:
      ladder.bandIndexes.length === 2
        ? `If / Else — ${field}`
        : valueMatch
          ? `Match — ${field}`
          : `Threshold ladder — ${field}`,
    bandLabels: [],
    thresholdsLabel: ladder.thresholds.join(" / "),
  };
}

/** One equals / is-not condition, used when the field is a choice or plain text. */
export function parseValueMatchBand(
  step: EApprovalWorkflowStepInput,
): (ValueMatchCase & { field: string }) | null {
  const when = parseStepWhen(step);
  if (when.length !== 1) {
    return null;
  }

  const operator = when[0].operator;
  if (operator !== "equals" && operator !== "not_equals") {
    return null;
  }

  const field = when[0].field.trim();
  if (!field) {
    return null;
  }

  return { field, operator, value: normalizeThreshold(when[0].value) };
}

export function ladderCaseLabel(step: EApprovalWorkflowStepInput, fieldLabel?: string): string {
  const amountBand = parseThresholdBand(step);
  if (amountBand) {
    return thresholdBandLabel(amountBand, fieldLabel);
  }

  const match = parseValueMatchBand(step);
  if (!match) {
    return fieldLabel?.trim() || "";
  }

  const field = fieldLabel?.trim() || match.field;
  const value = match.value.trim() === "" ? "…" : match.value;
  return match.operator === "not_equals" ? `${field} is not ${value}` : `${field} is ${value}`;
}

/** Cases currently stored on a ladder, including an amount ladder used as a value match. */
export function ladderMatchCases(
  steps: EApprovalWorkflowStepInput[],
  bandIndexes: number[],
): ValueMatchCase[] {
  return bandIndexes.map((index) => {
    const step = steps[index];
    const match = step ? parseValueMatchBand(step) : null;
    if (match) {
      return { operator: match.operator, value: match.value };
    }

    const band = step ? parseThresholdBand(step) : null;
    if (band?.upper && !band.lower) {
      return { operator: "equals", value: band.upper.value };
    }
    if (band?.lower && !band.upper) {
      return { operator: "not_equals", value: band.lower.value };
    }

    return { operator: "equals", value: "" };
  });
}

function whenKey(step: EApprovalWorkflowStepInput): string {
  return JSON.stringify(
    parseStepWhen(step).map((condition) => ({
      field: condition.field.trim(),
      operator: condition.operator,
      value: String(condition.value ?? "").trim(),
    })),
  );
}

/** First step of a case, walking back over same-order siblings with the same when. */
export function ladderCasePrimaryIndex(
  steps: EApprovalWorkflowStepInput[],
  index: number,
): number {
  const step = steps[index];
  if (!step) {
    return index;
  }
  const key = whenKey(step);
  const order = step.step_order ?? index + 1;
  let primary = index;
  for (let cursor = index - 1; cursor >= 0; cursor -= 1) {
    const previous = steps[cursor];
    if ((previous.step_order ?? cursor + 1) !== order || whenKey(previous) !== key) {
      break;
    }
    primary = cursor;
  }
  return primary;
}

/**
 * The case step plus later fixed approvers that share its step order and when.
 * Two people on one case: the first approval finishes that case.
 */
export function ladderCaseMemberIndexes(
  steps: EApprovalWorkflowStepInput[],
  primaryIndex: number,
): number[] {
  const primary = steps[primaryIndex];
  if (!primary) {
    return [];
  }
  const order = primary.step_order ?? primaryIndex + 1;
  const key = whenKey(primary);
  const members = [primaryIndex];
  for (let index = primaryIndex + 1; index < steps.length; index += 1) {
    const step = steps[index];
    if ((step.step_order ?? index + 1) !== order || whenKey(step) !== key) {
      break;
    }
    members.push(index);
  }
  return members;
}

function clearParallelMode(step: EApprovalWorkflowStepInput): EApprovalWorkflowStepInput {
  const next = { ...step };
  delete next.parallel_mode;
  delete next.parallel_quorum;
  return next;
}

/** Add one more fixed approver on a case. The first extra person defaults to first-to-approve. */
export function addFixedApproverToLadderCase(
  steps: EApprovalWorkflowStepInput[],
  primaryIndex: number,
  approverId: string,
): EApprovalWorkflowStepInput[] {
  const trimmed = approverId.trim();
  const primary = ladderCasePrimaryIndex(steps, primaryIndex);
  const members = ladderCaseMemberIndexes(steps, primary);
  if (members.length === 0) {
    return steps;
  }
  if (trimmed && members.some((index) => steps[index]?.approverId === trimmed)) {
    return steps;
  }

  const source = steps[primary];
  const order = source.step_order ?? primary + 1;
  const existingMode = members.length >= 2 ? (source.parallel_mode ?? "all") : "any";
  const quorum = source.parallel_quorum;
  const { id: _id, ...rest } = source;
  const created = {
    ...clearParallelMode({
      ...rest,
      type: "user",
      approverId: trimmed,
      step_order: order,
    }),
    ...(existingMode === "all"
      ? {}
      : {
          parallel_mode: existingMode,
          ...(existingMode === "n_of_m" ? { parallel_quorum: quorum ?? 1 } : {}),
        }),
  };
  const insertAt = members[members.length - 1] + 1;
  const next = [...steps.slice(0, insertAt), created, ...steps.slice(insertAt)];
  const memberSet = new Set([...members, insertAt]);

  return next.map((step, index) => {
    if (!memberSet.has(index)) {
      return step;
    }
    if (existingMode === "all") {
      return clearParallelMode({ ...step, step_order: order });
    }
    const marked = {
      ...step,
      step_order: order,
      parallel_mode: existingMode,
      ...(existingMode === "n_of_m" ? { parallel_quorum: quorum ?? 1 } : {}),
    };
    if (existingMode !== "n_of_m") {
      delete marked.parallel_quorum;
    }
    return marked;
  });
}

/** Set or clear the second fixed approver. Empty removes them. Two people default to first-to-approve. */
export function setLadderCaseSecondApprover(
  steps: EApprovalWorkflowStepInput[],
  primaryIndex: number,
  approverId: string,
): EApprovalWorkflowStepInput[] {
  const trimmed = approverId.trim();
  const primary = ladderCasePrimaryIndex(steps, primaryIndex);
  const members = ladderCaseMemberIndexes(steps, primary);
  if (members.length === 0) {
    return steps;
  }
  if (!trimmed) {
    return members.length < 2 ? steps : removeFixedApproverFromLadderCase(steps, members[1]);
  }
  if (members.length < 2) {
    return addFixedApproverToLadderCase(steps, primary, trimmed);
  }
  if (steps[members[0]]?.approverId === trimmed) {
    return steps;
  }

  return steps.map((step, index) =>
    index === members[1] ? { ...step, type: "user", approverId: trimmed, parallel_mode: step.parallel_mode ?? "any" } : step,
  );
}

/** Completion rule for the two fixed approvers on one case. */
export function setLadderCaseCompletion(
  steps: EApprovalWorkflowStepInput[],
  primaryIndex: number,
  mode: "all" | "any" | "n_of_m",
  quorum?: number,
): EApprovalWorkflowStepInput[] {
  const primary = ladderCasePrimaryIndex(steps, primaryIndex);
  const members = ladderCaseMemberIndexes(steps, primary);
  if (members.length < 2) {
    return steps;
  }
  const resolvedQuorum = Math.max(1, Math.min(members.length, Math.floor(quorum ?? 1)));

  return steps.map((step, index) => {
    if (!members.includes(index)) {
      return step;
    }
    if (mode === "all") {
      return clearParallelMode(step);
    }
    const next = {
      ...step,
      parallel_mode: mode,
      ...(mode === "n_of_m" ? { parallel_quorum: resolvedQuorum } : {}),
    };
    if (mode !== "n_of_m") {
      delete next.parallel_quorum;
    }
    return next;
  });
}

/** Drop one person from a two-approver case. The remaining person approves alone. */
export function removeFixedApproverFromLadderCase(
  steps: EApprovalWorkflowStepInput[],
  memberIndex: number,
): EApprovalWorkflowStepInput[] {
  const primary = ladderCasePrimaryIndex(steps, memberIndex);
  const members = ladderCaseMemberIndexes(steps, primary);
  if (members.length < 2 || !members.includes(memberIndex)) {
    return steps;
  }

  const next = steps.filter((_, index) => index !== memberIndex);
  if (members.length === 2) {
    const survivor = members.find((index) => index !== memberIndex);
    if (survivor != null) {
      const adjusted = survivor > memberIndex ? survivor - 1 : survivor;
      next[adjusted] = clearParallelMode(next[adjusted]);
    }
  }

  return next;
}

/**
 * A blank condition is an unfinished row on its own step.
 * Two blank cases still belong together while an If/Else is being retargeted.
 */
function continuesValueMatchLadder(
  first: { field: string; value: string },
  next: { field: string; value: string },
): boolean {
  if (next.field !== first.field) {
    return false;
  }
  if (first.value === "" && next.value === "") {
    return true;
  }
  if (first.value === "" || next.value === "") {
    return false;
  }
  return true;
}

/** Adjacent steps that each match one value of the same field. */
export function detectValueMatchLadders(steps: EApprovalWorkflowStepInput[]): ExclusiveThresholdLadder[] {
  const ladders: ExclusiveThresholdLadder[] = [];
  let index = 0;

  while (index < steps.length) {
    const first = parseValueMatchBand(steps[index]);
    if (!first || isStepOwnedCondition(steps[index])) {
      index += 1;
      continue;
    }

    const bandIndexes = [index];
    let cursor = index + 1;
    while (cursor < steps.length) {
      const next = parseValueMatchBand(steps[cursor]);
      if (!next || !continuesValueMatchLadder(first, next) || isStepOwnedCondition(steps[cursor])) {
        break;
      }
      const primary = bandIndexes[bandIndexes.length - 1];
      if (ladderCaseMemberIndexes(steps, primary).includes(cursor) && cursor !== primary) {
        cursor += 1;
        continue;
      }
      bandIndexes.push(cursor);
      cursor += 1;
    }

    if (bandIndexes.length >= 2) {
      const cases = bandIndexes.map((bandIndex) => parseValueMatchBand(steps[bandIndex])!);
      ladders.push({
        startIndex: bandIndexes[0],
        endIndex: bandIndexes[bandIndexes.length - 1],
        field: first.field,
        bandIndexes,
        thresholds: cases.map((item) => item.value),
        match: "value",
      });
      index = cursor;
      continue;
    }

    index += 1;
  }

  return ladders;
}

export function applyValueMatchCases(
  steps: EApprovalWorkflowStepInput[],
  bandIndexes: number[],
  field: string,
  cases: ValueMatchCase[],
): EApprovalWorkflowStepInput[] {
  const trimmedField = field.trim();
  if (!trimmedField || cases.length !== bandIndexes.length) {
    return steps;
  }

  return steps.map((step, index) => {
    const offset = bandIndexes.findIndex((primary) =>
      ladderCaseMemberIndexes(steps, primary).includes(index),
    );
    if (offset < 0) {
      return step;
    }

    const item = cases[offset];
    return patchStepWhen(step, [
      { field: trimmedField, operator: item.operator, value: item.value.trim() },
    ]);
  });
}

/**
 * Replace the steps in a ladder, adding or dropping bands so the count matches `whens`.
 * New bands copy the last step and clear the approver.
 */
export function replaceLadderConditions(
  steps: EApprovalWorkflowStepInput[],
  bandIndexes: number[],
  whens: EApprovalWorkflowCondition[][],
): EApprovalWorkflowStepInput[] {
  if (bandIndexes.length === 0 || whens.length < 2) {
    return steps;
  }

  const groups = bandIndexes.map((index) => ladderCaseMemberIndexes(steps, index));
  if (groups.some((group) => group.length === 0)) {
    return steps;
  }
  const start = groups[0][0];
  const lastGroup = groups[groups.length - 1];
  const end = lastGroup[lastGroup.length - 1];
  if (end >= steps.length) {
    return steps;
  }
  const covered = new Set(groups.flat());
  for (let cursor = start; cursor <= end; cursor += 1) {
    if (!covered.has(cursor)) {
      return steps;
    }
  }

  const before = compactWorkflowStepOrdersPreservingTies(steps.slice(0, start));
  const afterSource = steps.slice(end + 1);
  const baseOrder =
    before.length > 0 ? Math.max(...before.map((step, index) => step.step_order ?? index + 1)) : 0;

  const nextBands = whens.flatMap((when, offset) => {
    const order = baseOrder + offset + 1;
    const group = groups[offset];
    if (!group) {
      const source = steps[groups[groups.length - 1][0]];
      const {
        id: _id,
        parallel_mode: _mode,
        parallel_quorum: _quorum,
        ...rest
      } = source;
      return [patchStepWhen({ ...rest, approverId: "", step_order: order }, when)];
    }

    return group.map((memberIndex) =>
      patchStepWhen({ ...steps[memberIndex], step_order: order }, when),
    );
  });

  const after = compactWorkflowStepOrdersPreservingTies(afterSource).map((step, index) => ({
    ...step,
    step_order: (step.step_order ?? index + 1) + baseOrder + whens.length,
  }));

  return [...before, ...nextBands, ...after];
}

/** Point an existing ladder at another field and rebuild cases for that field's kind. */
export function retargetLadderField(
  steps: EApprovalWorkflowStepInput[],
  ladder: ExclusiveThresholdLadder,
  field: string,
  kind: "amount" | "choice" | "value",
  choiceValues: string[] = [],
): EApprovalWorkflowStepInput[] {
  const trimmedField = field.trim();
  if (!trimmedField) {
    return steps;
  }

  if (kind === "amount") {
    const reusable =
      ladder.thresholds.length === ladder.bandIndexes.length - 1 &&
      ladder.thresholds.every((value) => isAmountBoundary(value))
        ? ladder.thresholds
        : Array.from({ length: Math.max(1, ladder.bandIndexes.length - 1) }, (_, index) =>
            String((index + 1) * 1000),
          );
    return replaceLadderConditions(
      steps,
      ladder.bandIndexes,
      createThresholdLadderWhens(trimmedField, reusable),
    );
  }

  if (kind === "choice" && choiceValues.length >= 2) {
    return replaceLadderConditions(
      steps,
      ladder.bandIndexes,
      choiceValues.map((value) => [{ field: trimmedField, operator: "equals", value }]),
    );
  }

  const count = Math.max(2, ladder.bandIndexes.length);
  const cases = Array.from({ length: count }, (_, index) => ({
    operator: (count === 2 && index === 1 ? "not_equals" : "equals") as ValueMatchOperator,
    value: "",
  }));

  return replaceLadderConditions(
    steps,
    ladder.bandIndexes,
    cases.map((item) => [{ field: trimmedField, operator: item.operator, value: item.value }]),
  );
}

export function addLadderValueCase(
  steps: EApprovalWorkflowStepInput[],
  ladder: ExclusiveThresholdLadder,
  item: ValueMatchCase,
): EApprovalWorkflowStepInput[] {
  const current = ladderMatchCases(steps, ladder.bandIndexes);
  return replaceLadderConditions(
    steps,
    ladder.bandIndexes,
    [...current, item].map((entry) => [
      { field: ladder.field, operator: entry.operator, value: entry.value },
    ]),
  );
}

export function removeLadderValueCase(
  steps: EApprovalWorkflowStepInput[],
  ladder: ExclusiveThresholdLadder,
  caseIndex: number,
): EApprovalWorkflowStepInput[] {
  const current = ladderMatchCases(steps, ladder.bandIndexes);
  if (current.length <= 2 || caseIndex < 0 || caseIndex >= current.length) {
    return steps;
  }

  return replaceLadderConditions(
    steps,
    ladder.bandIndexes,
    current
      .filter((_, index) => index !== caseIndex)
      .map((entry) => [{ field: ladder.field, operator: entry.operator, value: entry.value }]),
  );
}

export type ValueMatchStepInput = {
  field: string;
  cases: Array<ValueMatchCase & { approverId: string }>;
  duty?: "review";
};

/** Insert one fixed-user step per value case. Only the matching case runs. */
export function insertValueMatchSteps(
  steps: EApprovalWorkflowStepInput[],
  input: ValueMatchStepInput,
  atIndex?: number,
): EApprovalWorkflowStepInput[] {
  const trimmedField = input.field.trim();
  const cases = input.cases.filter((item) => item.value.trim() !== "" && item.approverId.trim() !== "");
  if (!trimmedField || cases.length < 2) {
    return steps;
  }

  const insertAt = Math.max(0, Math.min(atIndex ?? steps.length, steps.length));
  const before = compactWorkflowStepOrdersPreservingTies(steps.slice(0, insertAt));
  const afterSource = steps.slice(insertAt);
  const baseOrder =
    before.length > 0 ? Math.max(...before.map((step, index) => step.step_order ?? index + 1)) : 0;

  const matchSteps = cases.map((item, offset) =>
    patchStepWhen(
      {
        type: "user",
        approverId: item.approverId.trim(),
        step_order: baseOrder + offset + 1,
        ...(input.duty === "review" ? { duty: "review" as const } : {}),
      },
      [{ field: trimmedField, operator: item.operator, value: item.value.trim() }],
    ),
  );

  const after = compactWorkflowStepOrdersPreservingTies(afterSource).map((step, index) => ({
    ...step,
    step_order: (step.step_order ?? index + 1) + baseOrder + cases.length,
  }));

  return [...before, ...matchSteps, ...after];
}
