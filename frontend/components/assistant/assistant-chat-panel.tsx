"use client";

import { useEffect, useRef, useState } from "react";
import {
  Loader2,
  Mic,
  Paperclip,
  Phone,
  SendHorizontal,
} from "lucide-react";

import { AssistantHistoryPanel } from "@/components/assistant/assistant-history-panel";
import { AssistantMessage, type AssistantChatMessage } from "@/components/assistant/assistant-message";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import {
  askAssistant,
  cancelAssistantAction,
  confirmAssistantAction,
  fetchAssistantConversation,
  submitAssistantFeedback,
  type AssistantAskResponse,
  type AssistantCitation,
  type AssistantConversationMessage,
  type AssistantCostEstimate,
  type AssistantMeta,
  type AssistantRateLimit,
} from "@/lib/api/modules/assistant-api";
import { getErrorMessage } from "@/lib/api/error";
import type { AssistantRouteContext } from "@/lib/assistant/route-context";

type Props = {
  routeContext: AssistantRouteContext;
  open: boolean;
  autoPilot?: boolean;
  planMode?: boolean;
  preferredModel?: string | null;
  meta?: AssistantMeta | null;
  historyOpen?: boolean;
  onHistoryOpenChange?: (open: boolean) => void;
  newChatNonce?: number;
  onRateLimitChange?: (rateLimit: AssistantRateLimit) => void;
  onCostEstimateChange?: (estimate: AssistantCostEstimate | null) => void;
};

const CONVERSATION_STORAGE_KEY = "toweros.assistant.conversation_id";

function toAssistantMessage(response: AssistantAskResponse): AssistantChatMessage {
  return {
    id: response.message_id,
    role: "assistant",
    content: response.answer,
    status: response.status,
    errorCode: response.error_code ?? null,
    providerNotice: response.provider_notice ?? null,
    citations: response.citations,
    relatedLinks: response.related_links,
    usedLiveData: response.used_live_data === true,
    proposedAction: response.proposed_action ?? null,
    actionResolved: false,
    feedback: null,
  };
}

function toRestoredMessage(message: AssistantConversationMessage): AssistantChatMessage | null {
  if (message.role !== "user" && message.role !== "assistant") {
    return null;
  }

  if (message.role === "user") {
    return { id: message.id, role: "user", content: message.content };
  }

  const proposedAction = message.proposed_action ?? null;

  return {
    id: message.id,
    role: "assistant",
    content: message.content,
    status: (message.status as AssistantChatMessage["status"]) ?? "completed",
    citations: Array.isArray(message.citations)
      ? (message.citations as AssistantCitation[])
      : [],
    relatedLinks: [],
    usedLiveData: false,
    proposedAction,
    actionResolved: proposedAction ? proposedAction.status !== "pending" : false,
    feedback: null,
  };
}

export function AssistantChatPanel({
  routeContext,
  open,
  autoPilot = false,
  planMode = false,
  preferredModel = null,
  meta = null,
  historyOpen = false,
  onHistoryOpenChange,
  newChatNonce = 0,
  onRateLimitChange,
  onCostEstimateChange,
}: Props) {
  const [question, setQuestion] = useState("");
  const [messages, setMessages] = useState<AssistantChatMessage[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [isAsking, setIsAsking] = useState(false);
  const [feedbackPendingId, setFeedbackPendingId] = useState<string | null>(null);
  const [actionPendingId, setActionPendingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isRestoring, setIsRestoring] = useState(
    () =>
      typeof window !== "undefined" &&
      window.localStorage.getItem(CONVERSATION_STORAGE_KEY) !== null,
  );
  const bottomRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLTextAreaElement | null>(null);

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }

    const storedId = window.localStorage.getItem(CONVERSATION_STORAGE_KEY);
    if (!storedId) {
      setIsRestoring(false);
      return;
    }

    let cancelled = false;
    void (async () => {
      try {
        const detail = await fetchAssistantConversation(storedId);
        if (cancelled) {
          return;
        }
        const restored = detail.messages
          .map(toRestoredMessage)
          .filter((message): message is AssistantChatMessage => message !== null);
        if (restored.length > 0) {
          setConversationId(detail.id);
          setMessages(restored);
        } else {
          window.localStorage.removeItem(CONVERSATION_STORAGE_KEY);
        }
      } catch {
        window.localStorage.removeItem(CONVERSATION_STORAGE_KEY);
      } finally {
        if (!cancelled) {
          setIsRestoring(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!open || historyOpen) {
      return;
    }
    bottomRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [messages, isAsking, open, historyOpen]);

  useEffect(() => {
    if (open && !historyOpen) {
      const timer = window.setTimeout(() => inputRef.current?.focus(), 50);
      return () => window.clearTimeout(timer);
    }
  }, [open, historyOpen]);

  const onConfirmAction = async (
    messageId: string,
    proposalId: string,
    payload: Record<string, unknown>,
  ) => {
    setActionPendingId(messageId);
    setError(null);
    try {
      const result = await confirmAssistantAction({
        proposal_id: proposalId,
        payload,
      });
      setMessages((current) =>
        current.map((message) =>
          message.id === messageId
            ? {
                ...message,
                actionResolved: true,
                actionResultHref: result.result.href,
                actionResultLabel: result.result.entity_label,
                proposedAction: message.proposedAction
                  ? { ...message.proposedAction, status: "confirmed" }
                  : null,
                content:
                  message.content +
                  (result.result.entity_label
                    ? `\n\nUpdated: ${result.result.entity_label}`
                    : "\n\nAction confirmed."),
              }
            : message,
        ),
      );
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setActionPendingId(null);
    }
  };

  const sendQuestion = async (raw: string) => {
    const trimmed = raw.trim();
    if (!trimmed || isAsking) {
      return;
    }

    setError(null);
    setQuestion("");
    const tempUserId = `local-user-${Date.now()}`;
    setMessages((current) => [
      ...current,
      { id: tempUserId, role: "user", content: trimmed },
    ]);
    setIsAsking(true);

    try {
      const response = await askAssistant({
        question: trimmed,
        conversation_id: conversationId,
        module_context: routeContext.moduleKey,
        page_path: routeContext.pagePath,
        plan_mode: planMode,
        preferred_model: preferredModel,
      });

      setConversationId(response.conversation_id);
      if (typeof window !== "undefined") {
        window.localStorage.setItem(CONVERSATION_STORAGE_KEY, response.conversation_id);
      }
      if (response.cost_estimate) {
        onCostEstimateChange?.(response.cost_estimate);
      }
      if (response.rate_limit) {
        onRateLimitChange?.(response.rate_limit);
      }

      const assistantMsg = toAssistantMessage(response);
      setMessages((current) => [...current, assistantMsg]);

      if (
        autoPilot &&
        !planMode &&
        response.proposed_action?.id &&
        response.proposed_action.requires_confirmation !== false
      ) {
        void onConfirmAction(
          response.message_id,
          response.proposed_action.id,
          response.proposed_action.payload ?? {},
        );
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsAsking(false);
    }
  };

  const onFeedback = async (messageId: string, rating: "up" | "down") => {
    setFeedbackPendingId(messageId);
    setError(null);
    try {
      await submitAssistantFeedback({ message_id: messageId, rating });
      setMessages((current) =>
        current.map((message) =>
          message.id === messageId ? { ...message, feedback: rating } : message,
        ),
      );
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setFeedbackPendingId(null);
    }
  };

  const onCancelAction = async (messageId: string, proposalId: string) => {
    setActionPendingId(messageId);
    setError(null);
    try {
      await cancelAssistantAction(proposalId);
      setMessages((current) =>
        current.map((message) =>
          message.id === messageId
            ? {
                ...message,
                proposedAction: message.proposedAction
                  ? { ...message.proposedAction, status: "cancelled" }
                  : null,
              }
            : message,
        ),
      );
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setActionPendingId(null);
    }
  };

  const startNewConversation = () => {
    setConversationId(null);
    setMessages([]);
    setError(null);
    setQuestion("");
    onCostEstimateChange?.(null);
    if (typeof window !== "undefined") {
      window.localStorage.removeItem(CONVERSATION_STORAGE_KEY);
    }
  };

  useEffect(() => {
    if (newChatNonce > 0) {
      startNewConversation();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- header New chat trigger
  }, [newChatNonce]);

  const loadConversation = async (id: string) => {
    setError(null);
    setIsRestoring(true);
    try {
      const detail = await fetchAssistantConversation(id);
      const restored = detail.messages
        .map(toRestoredMessage)
        .filter((message): message is AssistantChatMessage => message !== null);
      setConversationId(detail.id);
      setMessages(restored);
      onCostEstimateChange?.(null);
      if (typeof window !== "undefined") {
        window.localStorage.setItem(CONVERSATION_STORAGE_KEY, detail.id);
      }
      onHistoryOpenChange?.(false);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsRestoring(false);
    }
  };

  return (
    <div className="relative flex min-h-0 flex-1 flex-col bg-background">
      <AssistantHistoryPanel
        open={historyOpen}
        activeConversationId={conversationId}
        onClose={() => onHistoryOpenChange?.(false)}
        onSelect={(id) => void loadConversation(id)}
        onNewChat={startNewConversation}
        onArchived={(id) => {
          if (id === conversationId) {
            startNewConversation();
          }
        }}
      />

      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
        {isRestoring ? (
          <div className="flex items-center gap-2 px-1 py-6 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Restoring your conversation…
          </div>
        ) : messages.length === 0 ? (
          <div className="max-w-[92%] rounded-2xl border border-border bg-card px-3.5 py-3 text-sm text-foreground shadow-sm">
            {meta?.greeting ?? "Hey! What are we building or fixing today?"}
          </div>
        ) : (
          <>
            {messages.map((message) => (
              <AssistantMessage
                key={message.id}
                message={message}
                onFeedback={message.role === "assistant" ? onFeedback : undefined}
                feedbackPending={feedbackPendingId === message.id}
                onConfirmAction={message.role === "assistant" ? onConfirmAction : undefined}
                onCancelAction={message.role === "assistant" ? onCancelAction : undefined}
                actionPending={actionPendingId === message.id}
              />
            ))}
            {isAsking ? (
              <div className="flex items-center gap-2 px-1 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                Thinking…
              </div>
            ) : null}
          </>
        )}
        <div ref={bottomRef} />
      </div>

      <div className="border-t border-border bg-card px-3 py-3">
        {error ? (
          <p className="mb-2 px-1 text-xs text-destructive" role="alert">
            {error}
          </p>
        ) : null}
        <div className="rounded-xl border border-border bg-background px-3 py-2 shadow-sm">
          <Textarea
            ref={inputRef}
            value={question}
            onChange={(event) => setQuestion(event.target.value)}
            placeholder="Type your message..."
            rows={2}
            className="max-h-28 min-h-0 resize-none border-0 bg-transparent p-0 shadow-none focus-visible:ring-0"
            disabled={isAsking || historyOpen}
            onKeyDown={(event) => {
              if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                void sendQuestion(question);
              }
            }}
          />
          <div className="mt-1 flex items-center justify-between gap-2">
            <Button
              type="button"
              variant="ghost"
              size="icon-sm"
              className="text-muted-foreground"
              title="Attachments coming soon"
              disabled
            >
              <Paperclip className="size-4" />
            </Button>
            <div className="flex items-center gap-1">
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                className="text-muted-foreground"
                title="Voice coming soon"
                disabled
              >
                <Phone className="size-4" />
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                className="text-muted-foreground"
                title="Microphone coming soon"
                disabled
              >
                <Mic className="size-4" />
              </Button>
              <Button
                type="button"
                size="icon"
                className="size-9 shrink-0"
                disabled={isAsking || historyOpen || question.trim() === ""}
                onClick={() => void sendQuestion(question)}
                aria-label="Send message"
              >
                {isAsking ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <SendHorizontal className="h-4 w-4" />
                )}
              </Button>
            </div>
          </div>
        </div>
        <p className="mt-2 px-1 text-[10px] text-muted-foreground">Enter to send · Shift+Enter for new line</p>
      </div>
    </div>
  );
}
