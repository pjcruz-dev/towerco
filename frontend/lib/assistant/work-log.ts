import type { AssistantActivityStep } from "@/lib/api/modules/assistant-api";

export type PresentedWorkStep = {
  id: string;
  kind: "thought" | "query" | "tool";
  title: string;
  result: string;
  status: string;
};

const CHROME_TITLE = /^(reading your question|choosing an operation|chose an operation|asking |asked |answered from)/i;

export function formatWorked(ms: number): string {
  const seconds = Math.max(0, ms) / 1000;
  if (seconds < 10) {
    const shown = seconds.toFixed(1).replace(/\.0$/, "");
    return `${shown}s`;
  }
  if (seconds < 60) {
    return `${Math.round(seconds)}s`;
  }
  const minutes = Math.floor(seconds / 60);
  const rest = Math.round(seconds % 60);
  return rest === 0 ? `${minutes}m` : `${minutes}m ${rest}s`;
}

export function stepCountLabel(count: number): string {
  return count === 1 ? "1 step" : `${count} steps`;
}

export function visibleWorkSteps(
  steps: AssistantActivityStep[],
  thought: string | undefined,
  live: boolean,
): AssistantActivityStep[] {
  const visible = steps.filter((step) => {
    if (step.kind === "thought") {
      return (step.detail ?? "").trim() !== "";
    }
    if (step.id === "question") {
      return false;
    }
    if (step.kind === "model" && step.status !== "failed") {
      return false;
    }
    if (CHROME_TITLE.test(step.title.trim())) {
      return false;
    }
    return true;
  });

  const thoughtText = (thought ?? "").replace(/^\s*\[V\]\s*/, "").trim();
  const hasFinishedTool = visible.some((step) => step.kind === "tool" && step.status !== "running");
  if (thoughtText !== "" && (live || hasFinishedTool) && !visible.some((step) => step.kind === "thought")) {
    const thoughtStep: AssistantActivityStep = {
      id: "thought-live",
      kind: "thought",
      title: "Thinking",
      detail: thoughtText,
      status: live ? "running" : "done",
    };
    if (visible.some((step) => step.kind === "tool")) {
      visible.push(thoughtStep);
    } else {
      visible.unshift(thoughtStep);
    }
  }

  const llm = visible.some((step) => step.kind === "thought" || step.kind === "tool" || step.kind === "action");
  if (llm) {
    return visible;
  }

  if (live) {
    return [
      {
        id: "waiting",
        kind: "model",
        title: "Asking the model",
        detail: null,
        status: "running",
      },
    ];
  }

  return [];
}

export function presentWorkStep(step: AssistantActivityStep, intent?: string | null): PresentedWorkStep {
  const detail = (step.detail ?? "").replace(/^\s*\[V\]\s*/, "").trim();

  if (step.kind === "thought") {
    return {
      id: step.id,
      kind: "thought",
      title: "",
      result: thoughtProse(detail),
      status: step.status,
    };
  }

  if (isDatabaseQuery(step)) {
    return {
      id: step.id,
      kind: "query",
      title: queryTitle(step, intent),
      result: foundLine(detail),
      status: step.status,
    };
  }

  return {
    id: step.id,
    kind: "tool",
    title: step.title.trim() !== "" ? step.title.trim() : "Working",
    result: humanResult(detail, step.status),
    status: step.status,
  };
}

function queryTitle(step: AssistantActivityStep, intent?: string | null): string {
  const title = step.title.trim();
  if (/running database query:/i.test(title)) {
    return title;
  }
  const quoted = clip(intent ?? "", 72);
  return quoted !== "" ? `Running database query: "${quoted}"` : "Running database query";
}

function thoughtProse(detail: string): string {
  const lines = detail
    .split("\n")
    .map((line) => line.trim())
    .filter((line) => {
      if (line === "" || line.startsWith("```") || line.startsWith("{") || line.startsWith("[")) {
        return false;
      }
      return !/^(select|insert|update|delete|with)\b/i.test(line);
    });

  return lines.join("\n");
}

function isDatabaseQuery(step: AssistantActivityStep): boolean {
  const blob = `${step.id} ${step.title}`.toLowerCase();
  return blob.includes("sql") || blob.includes("database query");
}

function foundLine(detail: string): string {
  const match = detail.match(/(\d+)\s+rows?/i);
  if (match) {
    const count = Number(match[1]);
    return `found ${count} record${count === 1 ? "" : "s"}`;
  }
  return humanResult(detail, "done");
}

function humanResult(detail: string, status: string): string {
  if (/^(creating|running|starting|working|finishing|finished)$/i.test(detail)) {
    return status === "running" ? "Waiting for the model" : "";
  }
  if (detail === "" || isRawDump(detail)) {
    if (status === "failed") {
      return "Did not finish.";
    }
    if (status === "running") {
      return "Waiting for the model";
    }
    return "Finished.";
  }

  const first = detail.split("\n")[0]?.trim() ?? "";
  const rows = first.match(/^(\d+)\s+rows?\.?\s*(.*)$/i);
  if (rows) {
    const rest = rows[2]?.replace(/\.$/, "").trim() ?? "";
    if (rest !== "" && !isRawDump(rest) && rest.length <= 80) {
      return rest;
    }
    const count = Number(rows[1]);
    return `found ${count} record${count === 1 ? "" : "s"}`;
  }

  return clip(first.replace(/\.$/, ""), 140);
}

function isRawDump(text: string): boolean {
  const trimmed = text.trim();
  if (trimmed === "") {
    return false;
  }
  if (trimmed.split("\n").length > 6) {
    return true;
  }
  if (trimmed.length > 280) {
    return true;
  }
  if (/^\s*(select|insert|update|delete|with)\b/i.test(trimmed)) {
    return true;
  }
  if (trimmed.includes("```")) {
    return true;
  }
  if (/^\s*[\[{]/.test(trimmed)) {
    return true;
  }
  return false;
}

function clip(text: string, max: number): string {
  const flat = text.replace(/\s+/g, " ").trim();
  if (flat.length <= max) {
    return flat;
  }
  return `${flat.slice(0, max - 1)}…`;
}
