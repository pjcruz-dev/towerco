"use client";

import { ThumbsDown, ThumbsUp, TriangleAlert } from "lucide-react";

import { AssistantActionConfirmCard } from "@/components/assistant/assistant-action-confirm-card";
import { AssistantCitations } from "@/components/assistant/assistant-citations";
import { Button } from "@/components/ui/button";
import type {
  AssistantCitation,
  AssistantProposedAction,
  AssistantProviderNotice,
  AssistantRelatedLink,
} from "@/lib/api/modules/assistant-api";
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
  actionPending?: boolean;
};

export function AssistantMessage({
  message,
  onFeedback,
  feedbackPending,
  onConfirmAction,
  onCancelAction,
  actionPending,
}: Props) {
  const isUser = message.role === "user";
  const isQuotaExceeded =
    message.status === "provider_quota_exceeded" ||
    message.errorCode === "openai_quota_exceeded" ||
    message.errorCode === "cursor_rate_limit_exceeded";
  const usedLiveData =
    message.usedLiveData === true ||
    (message.citations ?? []).some((c) => c.type === "live_data" || c.scope === "live");

  return (
    <div className={cn("flex", isUser ? "justify-end" : "justify-start")}>
      <div
        className={cn(
          "max-w-[92%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed",
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
                    "Ask TowerOS cannot answer right now because the configured OpenAI API key has exceeded its quota or billing limit."}
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
        <p className="whitespace-pre-wrap">{message.content}</p>

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

        {!isUser ? (
          <>
            <AssistantCitations
              citations={message.citations ?? []}
              relatedLinks={message.relatedLinks}
            />
            {onFeedback && !isQuotaExceeded ? (
              <div className="mt-3 flex items-center gap-1 border-t border-border/50 pt-2">
                <span className="mr-1 text-xs text-muted-foreground">Was this helpful?</span>
                <Button
                  type="button"
                  size="icon-sm"
                  variant="ghost"
                  disabled={feedbackPending || message.feedback === "up"}
                  aria-label="Helpful"
                  onClick={() => onFeedback(message.id, "up")}
                  className={cn(message.feedback === "up" && "text-foreground")}
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
                  className={cn(message.feedback === "down" && "text-foreground")}
                >
                  <ThumbsDown className="h-3.5 w-3.5" />
                </Button>
              </div>
            ) : null}
          </>
        ) : null}
      </div>
    </div>
  );
}
