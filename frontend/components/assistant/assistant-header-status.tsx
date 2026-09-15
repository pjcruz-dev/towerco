"use client";

import { TowerOsAssistantMark } from "@/components/assistant/toweros-assistant-mark";
import { Select } from "@/components/ui/select";
import {
  modelLabel,
  providerDisplayName,
  formatResetsIn,
} from "@/lib/assistant/assistant-model-display";
import type { AssistantCostEstimate, AssistantMeta, AssistantRateLimit } from "@/lib/api/modules/assistant-api";
import { cn } from "@/lib/utils";

type Props = {
  meta: AssistantMeta | null;
  rateLimit: AssistantRateLimit | null;
  costEstimate?: AssistantCostEstimate | null;
  selectedModel: string | null;
  planMode?: boolean;
  onModelChange?: (model: string) => void;
};

export function AssistantHeaderStatus({
  meta,
  rateLimit,
  costEstimate,
  selectedModel,
  planMode,
  onModelChange,
}: Props) {
  const models = meta?.models?.length ? meta.models : selectedModel ? [selectedModel] : [];
  const model = selectedModel ?? meta?.model_name ?? models[0] ?? null;
  const provider = providerDisplayName(meta?.llm_provider);
  const chipLabel = provider && model ? `${provider} · ${modelLabel(model)}` : model ? modelLabel(model) : "Loading…";

  const rateLimitLabel = rateLimit ? `${rateLimit.remaining} / ${rateLimit.limit} left` : null;
  const resetsLabel =
    rateLimit && rateLimit.remaining < rateLimit.limit && rateLimit.resets_in_seconds > 0
      ? formatResetsIn(rateLimit.resets_in_seconds)
      : null;
  const daily = rateLimit?.daily ?? null;
  const dailyLabel =
    daily && daily.limit > 0 ? `${daily.remaining} / ${daily.limit} today` : null;
  const dailyResetsLabel =
    daily && daily.remaining < daily.limit && daily.resets_in_seconds > 0
      ? formatResetsIn(daily.resets_in_seconds)
      : null;

  // Prefer length over the flag so a stale API still shows the picker when models are listed.
  const showModelSelect = models.length > 1;

  return (
    <div className="mt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-2.5">
      <div className="flex min-w-0 items-center gap-2">
        {showModelSelect ? (
          <Select
            className="h-8 max-w-[260px] text-[11px]"
            value={model && models.includes(model) ? model : models[0]}
            onChange={(e) => onModelChange?.(e.target.value)}
            aria-label="Model"
          >
            {models.map((m) => (
              <option key={m} value={m}>
                {provider ? `${provider} · ${modelLabel(m)}` : modelLabel(m)}
              </option>
            ))}
          </Select>
        ) : (
          <span
            className="inline-flex max-w-[220px] items-center gap-1 truncate rounded-md border border-border bg-muted/40 px-2 py-1 text-[11px] font-medium text-foreground"
            title={meta ? `Provider: ${meta.llm_provider} · Model: ${meta.model_name}` : undefined}
          >
            <TowerOsAssistantMark className="size-3 shrink-0 text-muted-foreground" />
            {chipLabel}
          </span>
        )}
      </div>

      <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
        {rateLimitLabel ? (
          <span
            className={cn(
              "rounded-full px-2 py-1 text-[10px] font-medium",
              rateLimit && rateLimit.remaining <= Math.max(2, Math.floor(rateLimit.limit * 0.15))
                ? "bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-100"
                : "bg-muted text-muted-foreground",
            )}
            title={
              resetsLabel
                ? `Asks remaining this minute (resets in ${resetsLabel})`
                : "Asks remaining this minute"
            }
          >
            {rateLimitLabel}
            {resetsLabel ? ` · ${resetsLabel}` : ""}
          </span>
        ) : meta ? (
          <span className="rounded-full bg-muted px-2 py-1 text-[10px] font-medium text-muted-foreground">
            Rate limit…
          </span>
        ) : null}
        {dailyLabel ? (
          <span
            className={cn(
              "rounded-full px-2 py-1 text-[10px] font-medium",
              daily && daily.remaining <= Math.max(2, Math.floor(daily.limit * 0.15))
                ? "bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-100"
                : "bg-muted text-muted-foreground",
            )}
            title={
              dailyResetsLabel
                ? `Daily asks remaining (resets in ${dailyResetsLabel})`
                : "Daily asks remaining"
            }
          >
            {dailyLabel}
          </span>
        ) : null}
        {costEstimate ? (
          <span
            className={cn(
              "rounded-full px-2 py-1 text-[10px] font-semibold",
              costEstimate.intensity === "high"
                ? "bg-amber-200 text-amber-950 dark:bg-amber-400/30 dark:text-amber-100"
                : costEstimate.intensity === "medium"
                  ? "bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-100"
                  : "bg-emerald-100 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100",
            )}
            title="Rough estimate from token usage"
          >
            {costEstimate.label}
          </span>
        ) : planMode ? (
          <span className="text-[10px] text-muted-foreground">Plan mode</span>
        ) : (
          <span
            className="rounded-full bg-muted px-2 py-1 text-[10px] font-medium text-muted-foreground"
            title="Cost estimate appears after each reply"
          >
            Est: —
          </span>
        )}
      </div>
    </div>
  );
}
