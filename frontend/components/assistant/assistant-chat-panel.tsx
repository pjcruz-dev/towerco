"use client";

import { useEffect, useRef, useState } from "react";
import { Loader2, SendHorizontal, Sparkles } from "lucide-react";

import { AssistantMessage, type AssistantChatMessage } from "@/components/assistant/assistant-message";
import { AssistantSuggestedQuestions } from "@/components/assistant/assistant-suggested-questions";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  askAssistant,
  cancelAssistantAction,
  confirmAssistantAction,
  fetchAssistantConversation,
  submitAssistantFeedback,
  type AssistantAskResponse,
  type AssistantCitation,
  type AssistantConversationMessage,
} from "@/lib/api/modules/assistant-api";
import { getErrorMessage } from "@/lib/api/error";
import type { AssistantRouteContext } from "@/lib/assistant/route-context";

type Props = {
  routeContext: AssistantRouteContext;
  open: boolean;
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

export function AssistantChatPanel({ routeContext, open }: Props) {
  const [question, setQuestion] = useState("");
  const [messages, setMessages] = useState<AssistantChatMessage[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [followups, setFollowups] = useState<string[]>(routeContext.suggestedQuestions);
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
  const inputRef = useRef<HTMLInputElement | null>(null);

  // Rehydrate the last conversation across page refreshes. Only the conversation id is
  // persisted locally; message content is fetched from the server (access-checked), so
  // no chat content leaks between accounts on a shared browser.
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
        // Conversation missing or not accessible for this session — start fresh.
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
    if (messages.length === 0) {
      setFollowups(routeContext.suggestedQuestions);
    }
  }, [routeContext.suggestedQuestions, messages.length]);

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
      });

      setConversationId(response.conversation_id);
      if (typeof window !== "undefined") {
        window.localStorage.setItem(CONVERSATION_STORAGE_KEY, response.conversation_id);
      }
      setMessages((current) => [...current, toAssistantMessage(response)]);
      if (response.suggested_followups.length > 0) {
        setFollowups(response.suggested_followups);
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
    setFollowups(routeContext.suggestedQuestions);
    if (typeof window !== "undefined") {
      window.localStorage.removeItem(CONVERSATION_STORAGE_KEY);
    }
  };

  return (
    <div className="flex min-h-0 flex-1 flex-col bg-background">
      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
        {isRestoring ? (
          <div className="flex items-center gap-2 px-1 py-6 text-sm text-muted-foreground">
            <Loader2 className="h-4 w-4 animate-spin" />
            Restoring your conversation…
          </div>
        ) : messages.length === 0 ? (
          <div className="space-y-4">
            <div className="flex items-start gap-3 rounded-xl bg-muted/40 px-3.5 py-3">
              <div className="mt-0.5 rounded-full bg-card p-2 text-muted-foreground shadow-sm ring-1 ring-border">
                <Sparkles className="h-4 w-4" />
              </div>
              <div className="space-y-1">
                <p className="text-sm font-medium text-foreground">How can I help?</p>
                <p className="text-sm leading-relaxed text-muted-foreground">
                  Ask about workflows, permissions, or processes. Answers use approved help for your
                  workspace.
                </p>
              </div>
            </div>
            <AssistantSuggestedQuestions
              questions={followups}
              disabled={isAsking}
              onSelect={(value) => void sendQuestion(value)}
            />
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
                Looking up approved guidance…
              </div>
            ) : null}
            {!isAsking && followups.length > 0 ? (
              <AssistantSuggestedQuestions
                questions={followups}
                disabled={isAsking}
                onSelect={(value) => void sendQuestion(value)}
              />
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
        <div className="flex items-center gap-2 rounded-full border border-border bg-background px-2 py-1.5 shadow-sm">
          <Input
            ref={inputRef}
            value={question}
            onChange={(event) => setQuestion(event.target.value)}
            placeholder="Type your message…"
            className="h-9 flex-1 border-0 bg-transparent px-3 text-sm shadow-none focus-visible:ring-0"
            disabled={isAsking}
            onKeyDown={(event) => {
              if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                void sendQuestion(question);
              }
            }}
          />
          <Button
            type="button"
            size="icon"
            className="h-9 w-9 shrink-0 rounded-full"
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
        <div className="mt-2 flex items-center justify-between gap-2 px-1">
          <p className="text-[11px] text-muted-foreground">
            {routeContext.moduleKey ?? "workspace"} · Enter to send
          </p>
          {messages.length > 0 ? (
            <button
              type="button"
              className="text-[11px] font-medium text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
              onClick={startNewConversation}
            >
              New chat
            </button>
          ) : null}
        </div>
      </div>
    </div>
  );
}
