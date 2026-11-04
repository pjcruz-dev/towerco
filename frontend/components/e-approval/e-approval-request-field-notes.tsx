"use client";

import { useEffect, useRef, useState, type PointerEvent, type ReactNode } from "react";
import { createPortal } from "react-dom";

import { commentMarkColor } from "@/components/e-approval/e-approval-comment-color";
import {
  EApprovalMentionField,
  EApprovalMentionText,
  mentionIdsInMessage,
  type EApprovalMentionPerson,
} from "@/components/e-approval/e-approval-mention-field";
import { Button } from "@/components/ui/button";
import type { EApprovalCommentRow } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Spot = { x: number; y: number; w: number; h: number };

const ACTIVE_MARK_EVENT = "ea-request-field-mark";

function claimRequestFieldMark(fieldName: string) {
  window.dispatchEvent(new CustomEvent(ACTIVE_MARK_EVENT, { detail: fieldName }));
}

function pointOnBox(event: PointerEvent<HTMLDivElement>): { x: number; y: number } | null {
  const box = event.currentTarget.getBoundingClientRect();
  if (box.width <= 0 || box.height <= 0) {
    return null;
  }
  return {
    x: Math.min(1, Math.max(0, (event.clientX - box.left) / box.width)),
    y: Math.min(1, Math.max(0, (event.clientY - box.top) / box.height)),
  };
}

type Props = {
  fieldName: string;
  comments: EApprovalCommentRow[];
  people: EApprovalMentionPerson[];
  posting: boolean;
  decision?: {
    mode: "review" | "approval";
    busy: boolean;
    onAcknowledge: (message: string) => void;
    onApprove: (message: string) => void;
    onReject: (message: string) => void;
    onRevision: (message: string) => void;
  } | null;
  onPost: (payload: {
    message: string;
    parent_id?: string;
    field_name?: string;
    pin_page?: number;
    pin_x?: number;
    pin_y?: number;
    pin_w?: number;
    pin_h?: number;
    mention_user_ids?: string[];
  }) => void;
  children: ReactNode;
};

export function EApprovalRequestFieldMark({
  fieldName,
  comments,
  people,
  posting,
  decision = null,
  onPost,
  children,
}: Props) {
  const frameRef = useRef<HTMLDivElement>(null);
  const dragStart = useRef<{ x: number; y: number } | null>(null);
  const spotRef = useRef<Spot | null>(null);
  const [spot, setSpot] = useState<Spot | null>(null);
  const [composer, setComposer] = useState<{ x: number; y: number } | null>(null);
  const [message, setMessage] = useState("");
  const [hoverId, setHoverId] = useState<string | null>(null);
  const hoverCloseTimer = useRef<number | null>(null);
  const keepHover = (id: string) => {
    if (hoverCloseTimer.current !== null) {
      window.clearTimeout(hoverCloseTimer.current);
      hoverCloseTimer.current = null;
    }
    setHoverId(id);
  };
  const scheduleHoverClose = () => {
    if (hoverCloseTimer.current !== null) {
      window.clearTimeout(hoverCloseTimer.current);
    }
    hoverCloseTimer.current = window.setTimeout(() => setHoverId(null), 250);
  };
  const [replyTo, setReplyTo] = useState<string | null>(null);
  const [replyDraft, setReplyDraft] = useState("");
  const marks = comments.filter(
    (comment) =>
      comment.field_name === fieldName &&
      comment.attachment_id == null &&
      comment.pin_x != null &&
      comment.pin_y != null,
  );

  const rememberSpot = (next: Spot | null) => {
    spotRef.current = next;
    setSpot(next);
    if (!next) {
      setComposer(null);
    }
  };

  useEffect(() => {
    return () => {
      if (hoverCloseTimer.current !== null) {
        window.clearTimeout(hoverCloseTimer.current);
      }
    };
  }, []);

  useEffect(() => {
    const onActive = (event: Event) => {
      const active = (event as CustomEvent<string>).detail;
      if (active !== fieldName) {
        rememberSpot(null);
        setMessage("");
      }
    };
    window.addEventListener(ACTIVE_MARK_EVENT, onActive);
    return () => window.removeEventListener(ACTIVE_MARK_EVENT, onActive);
  }, [fieldName]);

  const postSpot = (text: string) => {
    const current = spotRef.current;
    if (!current || text.trim() === "") {
      return;
    }
    onPost({
      field_name: fieldName,
      message: text.trim(),
      pin_page: 1,
      pin_x: Number(current.x.toFixed(6)),
      pin_y: Number(current.y.toFixed(6)),
      pin_w: Number(current.w.toFixed(6)),
      pin_h: Number(current.h.toFixed(6)),
      mention_user_ids: mentionIdsInMessage(text, people),
    });
    rememberSpot(null);
    setMessage("");
  };

  return (
    <div className="relative min-h-8">
      {children}
      <div
        ref={frameRef}
        className="absolute inset-0 cursor-crosshair"
        onPointerDown={(event) => {
          const point = pointOnBox(event);
          if (!point) {
            return;
          }
          claimRequestFieldMark(fieldName);
          dragStart.current = point;
          event.currentTarget.setPointerCapture(event.pointerId);
          rememberSpot({ x: point.x, y: Math.max(0, point.y - 0.02), w: 0.12, h: 0.04 });
          setComposer(null);
          setMessage("");
        }}
        onPointerMove={(event) => {
          const start = dragStart.current;
          const point = pointOnBox(event);
          if (!start || !point) {
            return;
          }
          const x = Math.min(start.x, point.x);
          const y = Math.min(start.y, point.y);
          const w = Math.abs(point.x - start.x);
          const h = Math.abs(point.y - start.y);
          if (w < 0.02 && h < 0.02) {
            return;
          }
          rememberSpot({
            x,
            y,
            w: Math.min(1 - x, Math.max(w, 0.04)),
            h: Math.min(1 - y, Math.max(h, 0.03)),
          });
        }}
        onPointerUp={() => {
          dragStart.current = null;
          const current = spotRef.current;
          const frame = frameRef.current;
          if (!current || !frame) {
            return;
          }
          const box = frame.getBoundingClientRect();
          const width = 288;
          const highlightLeft = box.left + current.x * box.width;
          const highlightTop = box.top + current.y * box.height;
          const highlightBottom = highlightTop + current.h * box.height;
          const left = Math.max(8, Math.min(highlightLeft, window.innerWidth - width - 8));
          const below = highlightBottom + 6;
          const above = highlightTop - 168;
          const top = below + 160 < window.innerHeight ? below : Math.max(8, above);
          setComposer({ x: left, y: top });
        }}
      >
        {marks.map((mark) => {
          const open = hoverId === mark.id;
          const color = commentMarkColor(mark.user_name);
          const pinX = mark.pin_x ?? 0;
          const pinY = mark.pin_y ?? 0;
          const pinW = mark.pin_w && mark.pin_w > 0 ? mark.pin_w : 0.12;
          const pinH = mark.pin_h && mark.pin_h > 0 ? mark.pin_h : 0.04;
          return (
            <span key={mark.id}>
              <button
                type="button"
                aria-label={`Comment by ${mark.user_name}`}
                className={cn("absolute", color.fill, open && "ring-2", open && color.ring)}
                style={{
                  left: `${pinX * 100}%`,
                  top: `${pinY * 100}%`,
                  width: `${pinW * 100}%`,
                  height: `${pinH * 100}%`,
                }}
                onPointerDown={(event) => event.stopPropagation()}
                onMouseEnter={() => keepHover(mark.id)}
                onMouseLeave={scheduleHoverClose}
                onFocus={() => keepHover(mark.id)}
              />
              {open
                ? createPortal(
                <div
                  className="fixed z-[80] w-64 rounded-md border border-border bg-card p-2 text-left text-card-foreground shadow-md"
                  style={{
                    left: Math.max(
                      8,
                      Math.min(
                        (frameRef.current?.getBoundingClientRect().left ?? 0) + pinX * (frameRef.current?.getBoundingClientRect().width ?? 0),
                        window.innerWidth - 272,
                      ),
                    ),
                    top:
                      (frameRef.current?.getBoundingClientRect().top ?? 0) +
                      (pinY + pinH) * (frameRef.current?.getBoundingClientRect().height ?? 0) +
                      6,
                  }}
                  onPointerDown={(event) => event.stopPropagation()}
                  onMouseEnter={() => keepHover(mark.id)}
                  onMouseLeave={scheduleHoverClose}
                >
                  <span className="flex items-center gap-1.5 text-xs font-medium">
                    <span className={cn("size-2 shrink-0 rounded-full", color.dot)} aria-hidden />
                    {mark.user_name}
                  </span>
                  <span className="mt-1 block whitespace-pre-wrap text-sm">
                    <EApprovalMentionText message={mark.message} people={people} />
                  </span>
                  {mark.replies.length > 0 ? (
                    <span className="mt-1 block space-y-1">
                      {mark.replies.map((reply) => (
                        <span key={reply.id} className="block text-xs">
                          <span className="font-medium">{reply.user_name}</span>
                          <span className="text-muted-foreground">
                            {" · "}
                            <EApprovalMentionText message={reply.message} people={people} />
                          </span>
                        </span>
                      ))}
                    </span>
                  ) : null}
                  <button
                    type="button"
                    className="mt-1 text-xs text-primary hover:underline"
                    onClick={() => {
                      setReplyTo(mark.id);
                      setReplyDraft("");
                    }}
                  >
                    Reply
                  </button>
                  {replyTo === mark.id ? (
                    <span className="mt-2 block space-y-2">
                      <EApprovalMentionField
                        value={replyDraft}
                        rows={2}
                        people={people}
                        placeholder="Reply. Use @ to notify someone."
                        onChange={setReplyDraft}
                      />
                      <Button
                        type="button"
                        size="sm"
                        disabled={posting || replyDraft.trim() === ""}
                        onClick={() => {
                          onPost({
                            message: replyDraft.trim(),
                            parent_id: mark.id,
                            field_name: fieldName,
                            mention_user_ids: mentionIdsInMessage(replyDraft, people),
                          });
                          setReplyDraft("");
                          setReplyTo(null);
                        }}
                      >
                        Reply
                      </Button>
                    </span>
                  ) : null}
                </div>,
                  document.body,
                )
              : null}
            </span>
          );
        })}
        {spot ? (
          <>
            <span
              className="pointer-events-none absolute bg-warning/50 ring-2 ring-warning"
              style={{
                left: `${spot.x * 100}%`,
                top: `${spot.y * 100}%`,
                width: `${spot.w * 100}%`,
                height: `${spot.h * 100}%`,
              }}
            />
            {composer
              ? createPortal(
                  <div
                    className={cn(
                      "fixed z-[80] rounded-md border border-border bg-card p-2 text-card-foreground shadow-md",
                      decision ? "w-80" : "w-72",
                    )}
                    style={{ left: composer.x, top: composer.y }}
                    onPointerDown={(event) => event.stopPropagation()}
                  >
                    <EApprovalMentionField
                      value={message}
                      rows={3}
                      people={people}
                      placeholder="Write a comment. Use @ to notify someone."
                      onChange={setMessage}
                    />
                    <span className="mt-2 flex flex-wrap justify-end gap-2">
                      <Button type="button" size="sm" variant="ghost" onClick={() => rememberSpot(null)}>
                        Cancel
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        disabled={posting || message.trim() === ""}
                        onClick={() => postSpot(message)}
                      >
                        Post
                      </Button>
                      {decision?.mode === "review" ? (
                        <Button
                          type="button"
                          size="sm"
                          disabled={decision.busy}
                          onClick={() => {
                            const text = message.trim();
                            if (text) {
                              postSpot(text);
                            }
                            decision.onAcknowledge(text);
                          }}
                        >
                          Acknowledge
                        </Button>
                      ) : null}
                      {decision?.mode === "approval" ? (
                        <>
                          <Button
                            type="button"
                            size="sm"
                            disabled={decision.busy}
                            onClick={() => {
                              const text = message.trim();
                              if (text) {
                                postSpot(text);
                              }
                              decision.onApprove(text);
                            }}
                          >
                            Approve
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            disabled={decision.busy || message.trim().length < 5}
                            onClick={() => {
                              const text = message.trim();
                              postSpot(text);
                              decision.onReject(text);
                            }}
                          >
                            Reject
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={decision.busy || message.trim().length < 5}
                            onClick={() => {
                              const text = message.trim();
                              postSpot(text);
                              decision.onRevision(text);
                            }}
                          >
                            Revision
                          </Button>
                        </>
                      ) : null}
                    </span>
                  </div>,
                  document.body,
                )
              : null}
          </>
        ) : null}
      </div>
    </div>
  );
}
