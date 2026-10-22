"use client";

import { useEffect, useRef, useState, type PointerEvent } from "react";

import { commentMarkColor } from "@/components/e-approval/e-approval-comment-color";
import { EApprovalPdfPinLayer } from "@/components/e-approval/e-approval-pdf-pin-layer";
import {
  EApprovalMentionField,
  mentionIdsInMessage,
  type EApprovalMentionPerson,
} from "@/components/e-approval/e-approval-mention-field";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  downloadEApprovalAttachment,
  downloadEApprovalAttachmentFile,
} from "@/lib/api/modules/e-approval-api";
import type { EApprovalCommentRow } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Attachment = {
  id: string;
  file_name: string;
  metadata?: {
    lat?: number;
    lng?: number;
    captured_at?: string;
    caption?: string;
    slot?: string;
  } | null;
};

function isPdfFile(fileName: string): boolean {
  return /\.pdf$/i.test(fileName);
}

function isImageFile(fileName: string): boolean {
  return /\.(png|jpe?g|gif|webp|bmp|heic|heif)$/i.test(fileName);
}

function formatGeotag(metadata: NonNullable<Attachment["metadata"]>): string | null {
  const parts: string[] = [];
  if (metadata.lat != null && metadata.lng != null) {
    parts.push(`${Number(metadata.lat).toFixed(5)}, ${Number(metadata.lng).toFixed(5)}`);
  }
  if (metadata.captured_at) {
    const date = new Date(metadata.captured_at);
    parts.push(Number.isNaN(date.getTime()) ? metadata.captured_at : date.toLocaleString());
  }
  return parts.length > 0 ? parts.join(" · ") : null;
}

type ImageSpot = { x: number; y: number; w: number; h: number };

function pointOnImage(event: PointerEvent<HTMLDivElement>): { x: number; y: number } | null {
  const box = event.currentTarget.getBoundingClientRect();
  if (box.width <= 0 || box.height <= 0) {
    return null;
  }
  return {
    x: Math.min(1, Math.max(0, (event.clientX - box.left) / box.width)),
    y: Math.min(1, Math.max(0, (event.clientY - box.top) / box.height)),
  };
}

function ImageMarkLayer({
  attachmentId,
  fileName,
  comments,
  posting,
  people,
  decision,
  focusCommentId,
  onFocusHandled,
  onPost,
}: {
  attachmentId: string;
  fileName: string;
  comments: EApprovalCommentRow[];
  posting: boolean;
  people: EApprovalMentionPerson[];
  decision: Props["decision"];
  focusCommentId: string | null;
  onFocusHandled?: () => void;
  onPost: Props["onPost"];
}) {
  const frameRef = useRef<HTMLDivElement>(null);
  const dragStart = useRef<{ x: number; y: number } | null>(null);
  const [url, setUrl] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);
  const [spot, setSpot] = useState<ImageSpot | null>(null);
  const [message, setMessage] = useState("");
  const [hoverId, setHoverId] = useState<string | null>(null);
  const [replyTo, setReplyTo] = useState<string | null>(null);
  const [replyDraft, setReplyDraft] = useState("");

  useEffect(() => {
    let active = true;
    let objectUrl: string | null = null;
    setUrl(null);
    setFailed(false);
    void downloadEApprovalAttachment(attachmentId)
      .then((blob) => {
        const next = URL.createObjectURL(blob);
        if (!active) {
          URL.revokeObjectURL(next);
          return;
        }
        objectUrl = next;
        setUrl(next);
      })
      .catch(() => {
        if (active) {
          setFailed(true);
        }
      });
    return () => {
      active = false;
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [attachmentId]);

  const marks = comments.filter(
    (comment) =>
      comment.attachment_id === attachmentId &&
      comment.pin_x != null &&
      comment.pin_y != null,
  );

  useEffect(() => {
    if (!focusCommentId || !url) {
      return;
    }
    const mark = comments.find(
      (comment) => comment.id === focusCommentId && comment.attachment_id === attachmentId && comment.pin_x != null,
    );
    if (!mark) {
      return;
    }
    setHoverId(mark.id);
    frameRef.current?.scrollIntoView({ block: "center", behavior: "smooth" });
    onFocusHandled?.();
  }, [attachmentId, comments, focusCommentId, onFocusHandled, url]);

  const postSpot = (text: string) => {
    if (!spot || text.trim() === "") {
      return;
    }
    onPost({
      message: text.trim(),
      attachment_id: attachmentId,
      pin_page: 1,
      pin_x: Number(spot.x.toFixed(6)),
      pin_y: Number(spot.y.toFixed(6)),
      pin_w: Number(spot.w.toFixed(6)),
      pin_h: Number(spot.h.toFixed(6)),
      mention_user_ids: mentionIdsInMessage(text, people),
    });
    setSpot(null);
    setMessage("");
  };

  if (failed) {
    return <p className="mt-3 text-sm text-muted-foreground">This photo could not be opened.</p>;
  }
  if (!url) {
    return <p className="mt-3 text-sm text-muted-foreground">Loading photo…</p>;
  }

  return (
    <div className="mt-3">
      <p className="mb-2 text-xs text-muted-foreground">
        Click or drag on the picture to mark a spot. Hover a mark to read it.
      </p>
      <div ref={frameRef} className="relative inline-block max-w-full align-top">
        <img src={url} alt={fileName} className="block max-h-[32rem] max-w-full rounded-md border border-border" />
        <div
          className="absolute inset-0 cursor-crosshair"
          onPointerDown={(event) => {
            const point = pointOnImage(event);
            if (!point) {
              return;
            }
            dragStart.current = point;
            event.currentTarget.setPointerCapture(event.pointerId);
            setSpot({ x: point.x, y: Math.max(0, point.y - 0.02), w: 0.12, h: 0.04 });
            setMessage("");
          }}
          onPointerMove={(event) => {
            const start = dragStart.current;
            const point = pointOnImage(event);
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
            setSpot({
              x,
              y,
              w: Math.min(1 - x, Math.max(w, 0.04)),
              h: Math.min(1 - y, Math.max(h, 0.03)),
            });
          }}
          onPointerUp={() => {
            dragStart.current = null;
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
                  onMouseEnter={() => setHoverId(mark.id)}
                  onMouseLeave={() => setHoverId(null)}
                  onFocus={() => setHoverId(mark.id)}
                />
                {open ? (
                  <span
                    className="absolute z-30 w-64 rounded-md border border-border bg-card p-2 text-left text-card-foreground shadow-md"
                    style={{
                      left: `${Math.min(pinX, 0.45) * 100}%`,
                      top: `calc(${(pinY + pinH) * 100}% + 8px)`,
                      backgroundColor: "var(--card)",
                    }}
                    onPointerDown={(event) => event.stopPropagation()}
                    onMouseEnter={() => setHoverId(mark.id)}
                  >
                    <span className="flex items-center gap-1.5 text-xs font-medium">
                      <span className={cn("size-2 shrink-0 rounded-full", color.dot)} aria-hidden />
                      {mark.user_name}
                    </span>
                    <span className="mt-1 block whitespace-pre-wrap bg-card text-sm">{mark.message}</span>
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
                              attachment_id: attachmentId,
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
                  </span>
                ) : null}
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
              <span
                className="absolute z-30 w-72 rounded-md border border-border bg-card p-2 shadow-md"
                style={{
                  left: `${Math.min(spot.x, 0.4) * 100}%`,
                  top: `calc(${(spot.y + spot.h) * 100}% + 8px)`,
                  backgroundColor: "var(--card)",
                }}
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
                  <Button type="button" size="sm" variant="ghost" onClick={() => setSpot(null)}>
                    Cancel
                  </Button>
                  <Button type="button" size="sm" disabled={posting || message.trim() === ""} onClick={() => postSpot(message)}>
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
              </span>
            </>
          ) : null}
        </div>
      </div>
    </div>
  );
}

type Props = {
  submissionId: string;
  attachments: Attachment[];
  comments: EApprovalCommentRow[];
  posting: boolean;
  people?: EApprovalMentionPerson[];
  decision?: {
    mode: "review" | "approval";
    busy: boolean;
    onAcknowledge: (message: string) => void;
    onApprove: (message: string) => void;
    onReject: (message: string) => void;
    onRevision: (message: string) => void;
  } | null;
  focusCommentId?: string | null;
  onFocusHandled?: () => void;
  onPost: (payload: {
    message: string;
    parent_id?: string;
    attachment_id?: string;
    pin_page?: number;
    pin_x?: number;
    pin_y?: number;
    pin_w?: number;
    pin_h?: number;
    mention_user_ids?: string[];
  }) => void;
};

export function EApprovalAttachmentComments({
  submissionId,
  attachments,
  comments,
  posting,
  onPost,
  people = [],
  decision = null,
  focusCommentId = null,
  onFocusHandled,
}: Props) {
  const push = useNotificationStore((state) => state.push);
  const [drafts, setDrafts] = useState<Record<string, string>>({});
  const [replyTo, setReplyTo] = useState<string | null>(null);
  const [replyDraft, setReplyDraft] = useState("");

  if (attachments.length === 0) {
    return null;
  }

  return (
    <div className="mt-4 space-y-4">
      {attachments.map((attachment) => {
        const thread = comments.filter((comment) => comment.attachment_id === attachment.id);
        const isPdf = isPdfFile(attachment.file_name);
        const isImage = isImageFile(attachment.file_name);
        const loose = isImage ? thread.filter((comment) => comment.pin_x == null) : thread;
        const geotag = attachment.metadata ? formatGeotag(attachment.metadata) : null;
        return (
          <div key={attachment.id} className="rounded-lg border border-border bg-muted/20 p-3">
            <p className="text-sm font-medium text-foreground">{attachment.file_name}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">
              Comments stay on this file. They do not approve or reject the request.
            </p>
            {geotag ? <p className="mt-0.5 text-xs text-muted-foreground">{geotag}</p> : null}
            {isPdf ? null : (
              <button
                type="button"
                className="mt-2 inline-flex min-h-8 items-center rounded-md px-1 text-xs font-medium text-foreground hover:underline"
                onClick={() => {
                  void downloadEApprovalAttachmentFile(attachment.id, attachment.file_name).catch((error: unknown) => {
                    push({
                      level: "error",
                      title: "Download failed",
                      message: getErrorMessage(error) || "Could not download this file.",
                    });
                  });
                }}
              >
                Download
              </button>
            )}
            {isImage ? (
              <ImageMarkLayer
                attachmentId={attachment.id}
                fileName={attachment.file_name}
                comments={comments}
                posting={posting}
                people={people}
                decision={decision}
                focusCommentId={focusCommentId}
                onFocusHandled={onFocusHandled}
                onPost={onPost}
              />
            ) : null}
            {isPdf ? (
              <div className="mt-3">
                <EApprovalPdfPinLayer
                  submissionId={submissionId}
                  attachmentId={attachment.id}
                  fileName={attachment.file_name}
                  comments={comments}
                  posting={posting}
                  onPost={onPost}
                  people={people}
                  decision={decision}
                  focusCommentId={focusCommentId}
                  onFocusHandled={onFocusHandled}
                />
              </div>
            ) : isImage ? null : loose.length === 0 ? (
              <p className="mt-2 text-sm text-muted-foreground">No comments on this file yet.</p>
            ) : loose.length > 0 ? (
              <ul className="mt-3 space-y-2">
                {loose.map((comment) => (
                  <li key={comment.id} className="text-sm">
                    <p>
                      <span className="font-medium">{comment.user_name}</span>
                      {comment.pin_page ? (
                        <span className="text-muted-foreground"> · Page {comment.pin_page}</span>
                      ) : null}
                      <span className="text-muted-foreground"> · {comment.message}</span>
                    </p>
                    {(comment.replies ?? []).length > 0 ? (
                      <ul className="mt-1 space-y-1 border-l-2 border-border pl-3">
                        {comment.replies.map((reply) => (
                          <li key={reply.id}>
                            <span className="font-medium">{reply.user_name}</span>
                            <span className="text-muted-foreground"> · {reply.message}</span>
                          </li>
                        ))}
                      </ul>
                    ) : null}
                    <button
                      type="button"
                      className="mt-1 text-xs text-primary hover:underline"
                      onClick={() => {
                        setReplyTo(comment.id);
                        setReplyDraft("");
                      }}
                    >
                      Reply
                    </button>
                    {replyTo === comment.id ? (
                      <div className="mt-2 flex flex-col gap-2 sm:flex-row">
                        <Textarea
                          value={replyDraft}
                          rows={2}
                          placeholder="Reply"
                          onChange={(event) => setReplyDraft(event.target.value)}
                        />
                        <Button
                          type="button"
                          size="sm"
                          className="self-end sm:self-auto"
                          disabled={posting || replyDraft.trim() === ""}
                          onClick={() => {
                            onPost({
                              message: replyDraft.trim(),
                              parent_id: comment.id,
                              attachment_id: attachment.id,
                              mention_user_ids: mentionIdsInMessage(replyDraft, people),
                            });
                            setReplyDraft("");
                            setReplyTo(null);
                          }}
                        >
                          Reply
                        </Button>
                      </div>
                    ) : null}
                  </li>
                ))}
              </ul>
            ) : null}
            {isPdf || isImage ? null : (
            <div className="mt-3 flex flex-col gap-2 sm:flex-row">
              <EApprovalMentionField
                value={drafts[attachment.id] ?? ""}
                rows={2}
                people={people}
                placeholder="Comment on this file. Use @ to notify someone."
                className="flex-1"
                onChange={(value) =>
                  setDrafts((current) => ({ ...current, [attachment.id]: value }))
                }
              />
              <Button
                type="button"
                size="sm"
                variant="outline"
                className="self-end sm:self-auto"
                disabled={posting || (drafts[attachment.id] ?? "").trim() === ""}
                onClick={() => {
                  onPost({
                    message: (drafts[attachment.id] ?? "").trim(),
                    attachment_id: attachment.id,
                    mention_user_ids: mentionIdsInMessage(drafts[attachment.id] ?? "", people),
                  });
                  setDrafts((current) => ({ ...current, [attachment.id]: "" }));
                }}
              >
                Post
              </Button>
            </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
