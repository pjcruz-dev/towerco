"use client";

import { useEffect, useRef, useState } from "react";
import {
  Loader2,
  Mic,
  Paperclip,
  Phone,
  SendHorizontal,
} from "lucide-react";

import { AssistantMessage, type AssistantChatMessage } from "@/components/assistant/assistant-message";
import { TowerOsAssistantMark } from "@/components/assistant/toweros-assistant-mark";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  askAssistant,
  cancelAssistantAction,
  confirmAssistantAction,
  fetchAssistantConversation,
  fetchAssistantMeta,
  submitAssistantFeedback,
  type AssistantAskResponse,
  type AssistantCitation,
  type AssistantConversationMessage,
  type AssistantCostEstimate,
  type AssistantMeta,
} from "@/lib/api/modules/assistant-api";
import { getErrorMessage } from "@/lib/api/error";
import type { AssistantRouteContext } from "@/lib/assistant/route-context";
import { cn } from "@/lib/utils";

type Props = {
  routeContext: AssistantRouteContext;
  open: boolean;
  autoPilot?: boolean;
  planMode?: boolean;
  preferredModel?: string | null;
  onPreferredModelChange?: (model: string) => void;
};

const CONVERSATION_STORAGE_KEY = "toweros.assistant.conversation_id";

function modelLabel(model: string): string {
  return model
    .replace(/^models\//, "")
    .replace(/-/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

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
  onPreferredModelChange,
}: Props) {
  const [question, setQuestion] = useState("");
  const [messages, setMessages] = useState<AssistantChatMessage[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [isAsking, setIsAsking] = useState(false);
  const [feedbackPendingId, setFeedbackPendingId] = useState<string | null>(null);
  const [actionPendingId, setActionPendingId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [meta, setMeta] = useState<AssistantMeta | null>(null);
  const [activeModel, setActiveModel] = useState<string | null>(preferredModel);
  const [costEstimate, setCostEstimate] = useState<AssistantCostEstimate | null>(null);
  const [isRestoring, setIsRestoring] = useState(
    () =>
      typeof window !== "undefined" &&
      window.localStorage.getItem(CONVERSATION_STORAGE_KEY) !== null,
  );
  const bottomRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLTextAreaElement | null>(null);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const data = await fetchAssistantMeta();
        if (cancelled) return;
        setMeta(data);
        if (!preferredModel && data.model_name) {
          setActiveModel(data.model_name);
        }
      } catch {
        // Meta is optional chrome; chat still works.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [preferredModel]);

  useEffect(() => {
    if (preferredModel) {
      setActiveModel(preferredModel);
    }
  }, [preferredModel]);

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
    if (!open) {
      return;
    }
    bottomRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [messages, isAsking, open]);

  useEffect(() => {
    if (open) {
      const timer = window.setTimeout(() => inputRef.current?.focus(), 50);
      return () => window.clearTimeout(timer);
    }
  }, [open]);

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
                    ? `\n\nCreated: ${result.result.entity_label}`
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
        preferred_model: activeModel,
      });

      setConversationId(response.conversation_id);
      if (typeof window !== "undefined") {
        window.localStorage.setItem(CONVERSATION_STORAGE_KEY, response.conversation_id);
      }
      if (response.model_name) {
        setActiveModel(response.model_name);
      }
      if (response.cost_estimate) {
        setCostEstimate(response.cost_estimate);
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
    setCostEstimate(null);
    if (typeof window !== "undefined") {
      window.localStorage.removeItem(CONVERSATION_STORAGE_KEY);
    }
  };

  const models = meta?.models?.length ? meta.models : activeModel ? [activeModel] : [];
  const selectedModel = activeModel ?? meta?.model_name ?? models[0] ?? "gemini-2.0-flash";

  return (
    <div className="flex min-h-0 flex-1 flex-col bg-background">
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
            disabled={isAsking}
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
                disabled={isAsking || question.trim() === ""}
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

        <div className="mt-2.5 flex items-center justify-between gap-2">
          <div className="flex min-w-0 items-center gap-2">
            {meta?.supports_model_select && models.length > 1 ? (
              <Select
                className="h-8 max-w-[180px] text-[11px]"
                value={selectedModel}
                onChange={(e) => {
                  setActiveModel(e.target.value);
                  onPreferredModelChange?.(e.target.value);
                }}
                aria-label="Model"
              >
                {models.map((m) => (
                  <option key={m} value={m}>
                    {modelLabel(m)}
                  </option>
                ))}
              </Select>
            ) : (
              <span className="inline-flex max-w-[180px] items-center gap-1 truncate rounded-md border border-border bg-muted/40 px-2 py-1 text-[11px] font-medium text-foreground">
                <TowerOsAssistantMark className="size-3 shrink-0 text-muted-foreground" />
                {modelLabel(selectedModel)}
              </span>
            )}
            {messages.length > 0 ? (
              <Button
                type="button"
                variant="ghost"
                size="xs"
                className="h-7 px-2 text-[11px] text-muted-foreground"
                onClick={startNewConversation}
              >
                New chat
              </Button>
            ) : null}
          </div>
          {costEstimate ? (
            <span
              className={cn(
                "shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold",
                costEstimate.intensity === "high"
                  ? "bg-amber-200 text-amber-950 dark:bg-amber-400/30 dark:text-amber-100"
                  : costEstimate.intensity === "medium"
                    ? "bg-amber-100 text-amber-900 dark:bg-amber-500/20 dark:text-amber-100"
                    : "bg-emerald-100 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100",
              )}
              title="Rough estimate from token usage × configured Gemini rates (₱)"
            >
              {costEstimate.label}
            </span>
          ) : (
            <span className="text-[10px] text-muted-foreground">
              {planMode ? "Plan mode" : "Enter to send"}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
