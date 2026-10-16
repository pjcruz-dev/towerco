"use client";

import { useState } from "react";
import { Bug, Copy, RotateCcw, ThumbsDown, ThumbsUp, TriangleAlert } from "lucide-react";

import { AssistantActionConfirmCard } from "@/components/assistant/assistant-action-confirm-card";
import { AssistantActivity } from "@/components/assistant/assistant-activity";
import { HelpMarkdown } from "@/components/help/help-markdown";
import { Button } from "@/components/ui/button";
import type {
  AssistantActivityStep,
  AssistantCitation,
  AssistantProposedAction,
  AssistantProviderNotice,
  AssistantRelatedLink,
} from "@/lib/api/modules/assistant-api";
import { visibleWorkSteps } from "@/lib/assistant/work-log";
import { cn } from "@/lib/utils";

export type AssistantChatMessage = {
  id: string;
  role: "user" | "assistant";
  content: string;
  status?: string;
  errorCode?: string | null;
  providerNotice?: AssistantProviderNotice | null;
  citations?: AssistantCitation[];
  relatedLinks?: AssistantRelatedLink[];
  usedLiveData?: boolean;
  proposedAction?: AssistantProposedAction | null;
  activity?: AssistantActivityStep[];
  workedMs?: number | null;
  modelName?: string | null;
  actionResolved?: boolean;
  actionResultHref?: string | null;
  actionResultLabel?: string | null;
  feedback?: "up" | "down" | null;
};

type Props = {
  message: AssistantChatMessage;
  onFeedback?: (messageId: string, rating: "up" | "down") => void;
  feedbackPending?: boolean;
  onConfirmAction?: (messageId: string, proposalId: string, payload: Record<string, unknown>) => void;
  onCancelAction?: (messageId: string, proposalId: string) => void;
  onRetry?: (content: string) => void;
  actionPending?: boolean;
  intent?: string | null;
};

export function AssistantMessage({
  message,
  onFeedback,
  feedbackPending,
  onConfirmAction,
  onCancelAction,
  onRetry,
  actionPending,
  intent = null,
}: Props) {
  const [workOpen, setWorkOpen] = useState(true);
  const [copied, setCopied] = useState<"user" | "answer" | null>(null);
  const isUser = message.role === "user";
  const isQuotaExceeded =
    message.status === "provider_quota_exceeded" ||
    message.errorCode === "openai_quota_exceeded" ||
    message.errorCode === "cursor_rate_limit_exceeded";
  const usedLiveData =
    message.usedLiveData === true ||
    (message.citations ?? []).some((c) => c.type === "live_data" || c.scope === "live");

  const activity = message.activity ?? [];
  const hasWork = !isUser && visibleWorkSteps(activity, "", false).length > 0;

  async function copyText(text: string, which: "user" | "answer") {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(which);
      window.setTimeout(() => setCopied((current) => (current === which ? null : current)), 1500);
    } catch {
      setCopied(null);
    }
  }

  return (
    <div className={cn("flex", isUser ? "justify-end" : "justify-start")}>
      <div className={cn("flex max-w-[92%] flex-col gap-2", isUser ? "items-end" : "w-full")}>
      {!isUser ? (
        <AssistantActivity
          steps={activity}
          workedMs={message.workedMs}
          intent={intent}
          open={workOpen}
          onOpenChange={setWorkOpen}
        />
      ) : null}
      <div
        className={cn(
          "rounded-2xl px-3.5 py-2.5 leading-relaxed",
          isUser ? "text-sm" : "text-xs",
          isUser
            ? "rounded-br-md bg-foreground text-background"
            : "rounded-bl-md bg-muted text-foreground",
        )}
      >
        {!isUser && isQuotaExceeded ? (
          <div
            className="mb-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100"
            role="alert"
          >
            <div className="flex items-start gap-2">
              <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-300" />
              <div className="space-y-1">
                <p className="text-sm font-medium">
                  {message.providerNotice?.title ??
                    (message.errorCode === "cursor_rate_limit_exceeded"
                      ? "Cursor API limit reached"
                      : "OpenAI quota exceeded")}
                </p>
                <p className="text-xs leading-relaxed text-amber-900/90 dark:text-amber-100/90">
                  {message.providerNotice?.message ??
                    "Ask INFRA SUITE cannot answer right now because the configured OpenAI API key has exceeded its quota or billing limit."}
                </p>
                {message.providerNotice?.admin_action ? (
                  <p className="text-xs leading-relaxed text-amber-900/80 dark:text-amber-100/80">
                    {message.providerNotice.admin_action}
                  </p>
                ) : null}
              </div>
            </div>
          </div>
        ) : null}
        {!isUser && usedLiveData ? (
          <span className="mb-2 inline-flex rounded-md bg-sky-100 px-2 py-0.5 text-[11px] font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-200">
            Used live data
          </span>
        ) : null}
        {isUser ? (
          <p className="whitespace-pre-wrap">{message.content}</p>
        ) : (
          <HelpMarkdown variant="chat" content={message.content.replace(/^\s*\[V\]\s*/, "")} />
        )}

        {!isUser && message.proposedAction ? (
          <AssistantActionConfirmCard
            proposal={message.proposedAction}
            pending={actionPending}
            resolved={message.actionResolved === true}
            resultHref={message.actionResultHref}
            resultLabel={message.actionResultLabel}
            onConfirm={(proposalId, payload) =>
              onConfirmAction?.(message.id, proposalId, payload)
            }
            onCancel={(proposalId) => onCancelAction?.(message.id, proposalId)}
          />
        ) : null}

      </div>
      {isUser ? (
        <div className="flex items-center gap-1 px-1">
          {onRetry ? (
            <Button
              type="button"
              size="xs"
              variant="ghost"
              className="text-muted-foreground"
              onClick={() => onRetry(message.content)}
            >
              <RotateCcw className="size-3.5" />
              Retry
            </Button>
          ) : null}
          <Button
            type="button"
            size="xs"
            variant="ghost"
            className="text-muted-foreground"
            onClick={() => void copyText(message.content, "user")}
          >
            <Copy className="size-3.5" />
            {copied === "user" ? "Copied" : "Copy"}
          </Button>
        </div>
      ) : (
        <div className="flex w-full flex-wrap items-center gap-1 px-1">
          <Button
            type="button"
            size="xs"
            variant="ghost"
            className="text-muted-foreground"
            onClick={() => void copyText(message.content, "answer")}
          >
            <Copy className="size-3.5" />
            {copied === "answer" ? "Copied" : "Copy"}
          </Button>
          {hasWork ? (
            <Button
              type="button"
              size="xs"
              variant="ghost"
              className="text-muted-foreground"
              aria-expanded={workOpen}
              onClick={() => setWorkOpen(true)}
            >
              <Bug className="size-3.5" />
              Debug
            </Button>
          ) : null}
          {onFeedback && !isQuotaExceeded ? (
            <>
              <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                disabled={feedbackPending || message.feedback === "up"}
                aria-label="Helpful"
                onClick={() => onFeedback(message.id, "up")}
                className={cn("text-muted-foreground", message.feedback === "up" && "text-foreground")}
              >
                <ThumbsUp className="h-3.5 w-3.5" />
              </Button>
              <Button
                type="button"
                size="icon-sm"
                variant="ghost"
                disabled={feedbackPending || message.feedback === "down"}
                aria-label="Not helpful"
                onClick={() => onFeedback(message.id, "down")}
                className={cn("text-muted-foreground", message.feedback === "down" && "text-foreground")}
              >
                <ThumbsDown className="h-3.5 w-3.5" />
              </Button>
            </>
          ) : null}
          {message.modelName ? (
            <span className="ms-auto truncate text-[11px] text-muted-foreground">{message.modelName}</span>
          ) : null}
        </div>
      )}
      </div>
    </div>
  );
}
