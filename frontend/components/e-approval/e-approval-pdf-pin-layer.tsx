"use client";

import { useEffect, useRef, useState, type PointerEvent, type RefObject } from "react";

import { commentMarkColor } from "@/components/e-approval/e-approval-comment-color";
import {
  EApprovalMentionField,
  mentionIdsInMessage,
  type EApprovalMentionPerson,
} from "@/components/e-approval/e-approval-mention-field";
import { Button } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { downloadEApprovalAttachment, downloadEApprovalAttachmentFile } from "@/lib/api/modules/e-approval-api";
import { useEApprovalPdfPreview } from "@/hooks/use-e-approval-pdf-preview";
import type { EApprovalCommentRow } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Highlight = {
  id: string;
  message: string;
  user_name: string;
  created_at: string | null;
  pin_page: number;
  pin_x: number;
  pin_y: number;
  pin_w: number;
  pin_h: number;
  replies: EApprovalCommentRow["replies"];
};

type Draft = {
  page: number;
  x: number;
  y: number;
  w: number;
  h: number;
  message: string;
};

type PdfPage = {
  getViewport: (options: { scale: number }) => { width: number; height: number };
  render: (options: {
    canvas: HTMLCanvasElement;
    canvasContext: CanvasRenderingContext2D;
    viewport: { width: number; height: number };
  }) => { promise: Promise<void>; cancel: () => void };
};

type PdfDoc = {
  numPages: number;
  getPage: (pageNumber: number) => Promise<PdfPage>;
};

type PdfLoadingTask = {
  promise: Promise<PdfDoc>;
  destroy: () => Promise<void> | void;
};

type Props = {
  submissionId: string;
  attachmentId: string;
  fileName: string;
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
  markingEnabled?: boolean;
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

function formatWhen(value: string | null): string {
  if (!value) {
    return "";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  const minutes = Math.max(0, Math.round((Date.now() - date.getTime()) / 60000));
  if (minutes < 1) {
    return "just now";
  }
  if (minutes < 60) {
    return `${minutes} min ago`;
  }
  return date.toLocaleString();
}

function pointOnPage(event: PointerEvent<HTMLDivElement>): { x: number; y: number } | null {
  const box = event.currentTarget.getBoundingClientRect();
  if (box.width <= 0 || box.height <= 0) {
    return null;
  }
  return {
    x: Math.min(1, Math.max(0, (event.clientX - box.left) / box.width)),
    y: Math.min(1, Math.max(0, (event.clientY - box.top) / box.height)),
  };
}

const MIN_ZOOM = 0.75;
const MAX_ZOOM = 2.5;

function PdfPageView({
  doc,
  pageNumber,
  highlights,
  activeId,
  hoverId,
  draft,
  zoom,
  fitWidth,
  message,
  posting,
  replyTo,
  replyDraft,
  onPlace,
  onHover,
  onMessage,
  onPostNote,
  onClearNote,
  onStartReply,
  onReplyDraft,
  onPostReply,
  noteRef,
  people,
  decision,
}: {
  doc: PdfDoc;
  pageNumber: number;
  highlights: Highlight[];
  activeId: string | null;
  hoverId: string | null;
  draft: Draft | null;
  zoom: number;
  fitWidth: number;
  message: string;
  posting: boolean;
  replyTo: string | null;
  replyDraft: string;
  onPlace: (spot: Omit<Draft, "message">) => void;
  onHover: (id: string | null) => void;
  onMessage: (value: string) => void;
  onPostNote: () => void;
  onClearNote: () => void;
  onStartReply: (id: string) => void;
  onReplyDraft: (value: string) => void;
  onPostReply: (id: string) => void;
  noteRef: RefObject<HTMLTextAreaElement | null>;
  people: EApprovalMentionPerson[];
  decision: {
    mode: "review" | "approval";
    busy: boolean;
    onAcknowledge: (message: string) => void;
    onApprove: (message: string) => void;
    onReject: (message: string) => void;
    onRevision: (message: string) => void;
  } | null;
}) {
  const [canvas, setCanvas] = useState<HTMLCanvasElement | null>(null);
  const dragStart = useRef<{ x: number; y: number } | null>(null);
  const hoverTimer = useRef<number | null>(null);
  useEffect(() => {
    return () => {
      if (hoverTimer.current !== null) {
        window.clearTimeout(hoverTimer.current);
      }
    };
  }, []);
  const cssWidth = Math.max(280, Math.round(fitWidth * zoom));
  const keepHover = (id: string) => {
    if (hoverTimer.current !== null) {
      window.clearTimeout(hoverTimer.current);
      hoverTimer.current = null;
    }
    onHover(id);
  };
  const releaseHover = () => {
    if (hoverTimer.current !== null) {
      window.clearTimeout(hoverTimer.current);
    }
    hoverTimer.current = window.setTimeout(() => onHover(null), 180);
  };

  useEffect(() => {
    if (!canvas) {
      return;
    }
    let cancelled = false;
    let renderTask: { cancel: () => void } | null = null;
    void (async () => {
      const page = await doc.getPage(pageNumber);
      if (cancelled) {
        return;
      }
      const unscaled = page.getViewport({ scale: 1 });
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const viewport = page.getViewport({ scale: (cssWidth / unscaled.width) * dpr });
      canvas.width = Math.ceil(viewport.width);
      canvas.height = Math.ceil(viewport.height);
      canvas.style.width = `${cssWidth}px`;
      canvas.style.height = `${Math.ceil(viewport.height / dpr)}px`;
      const context = canvas.getContext("2d");
      if (!context) {
        return;
      }
      renderTask = page.render({ canvas, canvasContext: context, viewport });
      try {
        await renderTask.promise;
      } catch {
        // Cancelled when the page unmounts.
      }
    })();
    return () => {
      cancelled = true;
      renderTask?.cancel();
    };
  }, [canvas, cssWidth, doc, pageNumber]);

  return (
    <div
      className="relative cursor-crosshair bg-white touch-none"
      style={{ width: cssWidth }}
      onPointerDown={(event) => {
        const point = pointOnPage(event);
        if (!point) {
          return;
        }
        dragStart.current = point;
        event.currentTarget.setPointerCapture(event.pointerId);
        onPlace({ page: pageNumber, x: point.x, y: Math.max(0, point.y - 0.012), w: 0.22, h: 0.02 });
      }}
      onPointerMove={(event) => {
        const start = dragStart.current;
        const point = pointOnPage(event);
        if (!start || !point) {
          return;
        }
        const x = Math.min(start.x, point.x);
        const y = Math.min(start.y, point.y);
        const w = Math.abs(point.x - start.x);
        const h = Math.abs(point.y - start.y);
        if (w < 0.015 && h < 0.01) {
          return;
        }
        onPlace({
          page: pageNumber,
          x,
          y,
          w: Math.min(1 - x, Math.max(w, 0.02)),
          h: Math.min(1 - y, Math.max(h, 0.015)),
        });
      }}
      onPointerUp={() => {
        dragStart.current = null;
      }}
    >
      <canvas ref={setCanvas} className="pointer-events-none block" />
      {highlights.map((mark) => {
        const open = hoverId === mark.id || activeId === mark.id;
        const color = commentMarkColor(mark.user_name);
        const top = mark.pin_y * 100;
        const height = Math.max(mark.pin_h, 0.02) * 100;
        return (
          <span key={mark.id}>
            <span
              className="absolute z-10"
              style={{
                left: `${mark.pin_x * 100}%`,
                top: `${top}%`,
                width: `${mark.pin_w * 100}%`,
                height: `${height}%`,
              }}
              onMouseEnter={() => keepHover(mark.id)}
              onMouseLeave={releaseHover}
            >
              <button
                type="button"
                aria-label={`Comment by ${mark.user_name}`}
                className={cn("absolute inset-0", color.fill, open && "ring-2", open && color.ring)}
                onPointerDown={(event) => event.stopPropagation()}
                onFocus={() => onHover(mark.id)}
                onBlur={() => onHover(null)}
              />
            </span>
            {open ? (
              <span
                className="absolute z-30 w-64 rounded-md border border-border bg-card p-2 text-left text-card-foreground shadow-md"
                style={{
                  left: `${Math.min(mark.pin_x, 0.55) * 100}%`,
                  top: `calc(${top + height}% + 8px)`,
                  backgroundColor: "var(--card)",
                }}
                onMouseEnter={() => keepHover(mark.id)}
                onMouseLeave={releaseHover}
                onPointerDown={(event) => event.stopPropagation()}
              >
                <span className="flex items-center gap-1.5 text-xs font-medium">
                  <span className={cn("size-2 shrink-0 rounded-full", color.dot)} aria-hidden />
                  {mark.user_name}
                </span>
                {mark.created_at ? (
                  <span className="block text-[11px] text-muted-foreground">{formatWhen(mark.created_at)}</span>
                ) : null}
                <span className="mt-1 block whitespace-pre-wrap bg-card text-sm">{mark.message}</span>
                {(mark.replies ?? []).length > 0 ? (
                  <span className="mt-2 block space-y-1 border-l-2 border-border pl-2">
                    {mark.replies.map((reply) => (
                      <span key={reply.id} className="block text-xs">
                        <span className="font-medium">{reply.user_name}</span>
                        <span className="text-muted-foreground"> · {reply.message}</span>
                      </span>
                    ))}
                  </span>
                ) : null}
                <button
                  type="button"
                  className="mt-1 text-xs text-primary"
                  onClick={() => onStartReply(mark.id)}
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
                      onChange={onReplyDraft}
                    />
                    <Button
                      type="button"
                      size="sm"
                      disabled={posting || replyDraft.trim() === ""}
                      onClick={() => onPostReply(mark.id)}
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
      {draft ? (
        <>
          <span
            className="pointer-events-none absolute z-10 bg-warning/50 ring-2 ring-warning"
            style={{
              left: `${draft.x * 100}%`,
              top: `${draft.y * 100}%`,
              width: `${draft.w * 100}%`,
              height: `${draft.h * 100}%`,
            }}
          />
          <span
            className={cn(
              "absolute z-20 rounded-md border border-border bg-card p-2 shadow-md",
              decision ? "w-80" : "w-64",
            )}
            style={{
              left: `${Math.min(draft.x, 0.55) * 100}%`,
              top: `calc(${(draft.y + draft.h) * 100}% + 8px)`,
              backgroundColor: "var(--card)",
            }}
            onPointerDown={(event) => event.stopPropagation()}
          >
            <span className="block text-xs text-muted-foreground">Page {draft.page}</span>
            <EApprovalMentionField
              textareaRef={noteRef}
              value={message}
              rows={3}
              people={people}
              placeholder="Write a comment. Use @ to notify someone."
              className="mt-1"
              onChange={onMessage}
            />
            <span className="mt-2 flex flex-wrap justify-end gap-2">
              <Button type="button" size="sm" variant="ghost" onClick={onClearNote}>
                Cancel
              </Button>
              <Button type="button" size="sm" disabled={posting || message.trim() === ""} onClick={onPostNote}>
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
                      onPostNote();
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
                        onPostNote();
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
                      onPostNote();
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
                      onPostNote();
                      decision.onRevision(text);
                    }}
                  >
                    Revision
                  </Button>
                </>
              ) : null}
            </span>
            {decision?.mode === "approval" ? (
              <span className="mt-1 block text-[11px] text-muted-foreground">
                Reject and revision need at least 5 characters in the note.
              </span>
            ) : null}
          </span>
        </>
      ) : null}
    </div>
  );
}

export function EApprovalPdfPinLayer({
  submissionId,
  attachmentId,
  fileName,
  comments,
  posting,
  people = [],
  decision = null,
  focusCommentId = null,
  onFocusHandled,
  markingEnabled = true,
  onPost,
}: Props) {
  const [doc, setDoc] = useState<PdfDoc | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState("Downloading the file…");
  const [spot, setSpot] = useState<Omit<Draft, "message"> | null>(null);
  const [message, setMessage] = useState("");

  useEffect(() => {
    setSpot(null);
    setMessage("");
  }, [attachmentId]);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [hoverId, setHoverId] = useState<string | null>(null);
  const [zoom, setZoom] = useState(1);
  const [visiblePage, setVisiblePage] = useState(1);
  const [replyTo, setReplyTo] = useState<string | null>(null);
  const [replyDraft, setReplyDraft] = useState("");
  const noteRef = useRef<HTMLTextAreaElement>(null);
  const frameRef = useRef<HTMLDivElement>(null);
  const scrollerRef = useRef<HTMLDivElement>(null);
  const [fitWidth, setFitWidth] = useState(960);
  const push = useNotificationStore((state) => state.push);
  const { openPdfPreview, isGenerating } = useEApprovalPdfPreview();

  const placeMark = (next: Omit<Draft, "message">) => {
    if (!markingEnabled) {
      return;
    }
    setSpot(next);
    setActiveId(null);
    noteRef.current?.focus();
  };

  useEffect(() => {
    let cancelled = false;
    let task: PdfLoadingTask | null = null;
    setDoc(null);
    setError(null);
    setSpot(null);
    setMessage("");
    setStatus("Downloading the file…");
    void (async () => {
      try {
        const blob = await downloadEApprovalAttachment(attachmentId);
        if (cancelled) {
          return;
        }
        const bytes = new Uint8Array(await blob.arrayBuffer());
        if (cancelled) {
          return;
        }
        if (bytes.byteLength < 5 || blob.type.includes("json") || blob.type.includes("text/html")) {
          setError(`This PDF could not be opened for comments. (${blob.size} bytes, ${blob.type || "unknown type"})`);
          return;
        }
        setStatus("Opening pages…");
        const pdfjs = await import("pdfjs-dist");
        if (cancelled) {
          return;
        }
        pdfjs.GlobalWorkerOptions.workerSrc = "/pdfjs/pdf.worker.min.mjs";
        // pdf.js transfers this buffer to the worker. Hand it a copy.
        const loadingTask = pdfjs.getDocument({ data: Uint8Array.from(bytes) }) as PdfLoadingTask;
        task = loadingTask;
        const next = await loadingTask.promise;
        if (cancelled) {
          void loadingTask.destroy();
          return;
        }
        setDoc(next);
      } catch (caught) {
        if (!cancelled) {
          const detail = caught instanceof Error ? caught.message : "Unknown error";
          setError(
            detail.startsWith("This PDF") ? detail : `This PDF could not be opened for comments. ${detail}`,
          );
        }
      }
    })();
    return () => {
      cancelled = true;
      const current = task;
      task = null;
      if (!current) {
        return;
      }
      try {
        void current.destroy();
      } catch {
        // The preview was already closed.
      }
    };
  }, [attachmentId]);

  useEffect(() => {
    const node = frameRef.current;
    if (!node) {
      return;
    }
    const measure = () => setFitWidth(Math.max(320, Math.floor(node.clientWidth)));
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(node);
    return () => observer.disconnect();
  }, [doc]);

  useEffect(() => {
    const root = scrollerRef.current;
    if (!root || !doc) {
      return;
    }
    const pages = root.querySelectorAll<HTMLElement>("[data-pdf-page]");
    if (pages.length === 0) {
      return;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((left, right) => right.intersectionRatio - left.intersectionRatio)[0];
        const page = Number(visible?.target instanceof HTMLElement ? visible.target.dataset.pdfPage : 0);
        if (page > 0) {
          setVisiblePage(page);
        }
      },
      { root, threshold: [0.35, 0.6] },
    );
    pages.forEach((page) => observer.observe(page));
    return () => observer.disconnect();
  }, [doc]);

  const goToPage = (page: number) => {
    const root = scrollerRef.current;
    const target = root?.querySelector<HTMLElement>(`[data-pdf-page="${page}"]`);
    if (!root || !target) {
      return;
    }
    const top = target.getBoundingClientRect().top - root.getBoundingClientRect().top + root.scrollTop;
    root.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
    setVisiblePage(page);
  };

  useEffect(() => {
    if (!focusCommentId || !doc) {
      return;
    }
    const comment = comments.find(
      (row) => row.id === focusCommentId && row.attachment_id === attachmentId && row.pin_page != null,
    );
    if (!comment?.pin_page) {
      return;
    }
    setActiveId(comment.id);
    setHoverId(comment.id);
    frameRef.current?.scrollIntoView({ block: "center", behavior: "smooth" });
    const page = comment.pin_page;
    window.setTimeout(() => {
      const root = scrollerRef.current;
      const target = root?.querySelector<HTMLElement>(`[data-pdf-page="${page}"]`);
      if (root && target) {
        const top = target.getBoundingClientRect().top - root.getBoundingClientRect().top + root.scrollTop;
        root.scrollTo({ top: Math.max(0, top), behavior: "smooth" });
      }
      onFocusHandled?.();
    }, 50);
  }, [attachmentId, comments, doc, focusCommentId, onFocusHandled]);

  const marks: Highlight[] = comments.flatMap((comment) => {
    if (
      comment.attachment_id !== attachmentId ||
      comment.pin_page == null ||
      comment.pin_x == null ||
      comment.pin_y == null
    ) {
      return [];
    }
    return [
      {
        id: comment.id,
        message: comment.message,
        user_name: comment.user_name,
        created_at: comment.created_at,
        pin_page: comment.pin_page,
        pin_x: comment.pin_x,
        pin_y: comment.pin_y,
        pin_w: comment.pin_w && comment.pin_w > 0 ? comment.pin_w : 0.08,
        pin_h: comment.pin_h && comment.pin_h > 0 ? comment.pin_h : 0.02,
        replies: comment.replies ?? [],
      },
    ];
  });

  if (error) {
    return <p className="text-sm text-destructive">{error}</p>;
  }
  if (!doc) {
    return <p className="text-sm text-muted-foreground">{status}</p>;
  }

  const pageCount = Math.min(doc.numPages, 40);

  const postNote = () => {
    if (!spot || message.trim() === "") {
      return;
    }
    onPost({
      message: message.trim(),
      attachment_id: attachmentId,
      pin_page: spot.page,
      pin_x: Number(spot.x.toFixed(6)),
      pin_y: Number(spot.y.toFixed(6)),
      pin_w: Number(spot.w.toFixed(6)),
      pin_h: Number(spot.h.toFixed(6)),
      mention_user_ids: mentionIdsInMessage(message, people),
    });
    setSpot(null);
    setMessage("");
  };

  return (
    <div ref={frameRef} className="w-full">
      <div className="mb-2 flex flex-wrap items-center gap-2">
        <Button
          type="button"
          size="sm"
          variant="outline"
          aria-label="Zoom out"
          disabled={zoom <= MIN_ZOOM}
          onClick={() => setZoom((current) => Math.max(MIN_ZOOM, Math.round((current - 0.25) * 100) / 100))}
        >
          −
        </Button>
        <span className="min-w-12 text-center text-xs tabular-nums text-foreground">{Math.round(zoom * 100)}%</span>
        <Button
          type="button"
          size="sm"
          variant="outline"
          aria-label="Zoom in"
          disabled={zoom >= MAX_ZOOM}
          onClick={() => setZoom((current) => Math.min(MAX_ZOOM, Math.round((current + 0.25) * 100) / 100))}
        >
          +
        </Button>
        <span className="inline-flex items-center gap-1 text-xs text-foreground">
          <Button
            type="button"
            size="sm"
            variant="outline"
            aria-label="Previous page"
            disabled={visiblePage <= 1}
            onClick={() => goToPage(visiblePage - 1)}
          >
            Prev
          </Button>
          <span className="min-w-16 text-center tabular-nums">
            Page {visiblePage} of {pageCount}
          </span>
          <Button
            type="button"
            size="sm"
            variant="outline"
            aria-label="Next page"
            disabled={visiblePage >= pageCount}
            onClick={() => goToPage(visiblePage + 1)}
          >
            Next
          </Button>
        </span>
        <p className="min-w-0 flex-1 text-xs text-muted-foreground">
          {markingEnabled
            ? "Click or drag on a page to mark it. Each person has their own color."
            : "Previous file. Its marks stay on this copy."}
        </p>
        <button
          type="button"
          className="inline-flex min-h-8 items-center rounded-md px-1 text-xs font-medium text-sky-700 hover:underline disabled:opacity-50 dark:text-sky-400"
          disabled={isGenerating}
          onClick={() => {
            void openPdfPreview(submissionId, attachmentId).catch((error: unknown) => {
              push({
                level: "error",
                title: "PDF preview failed",
                message: getErrorMessage(error) || "Could not generate PDF with approval footer.",
              });
            });
          }}
        >
          Open preview
        </button>
        <button
          type="button"
          className="inline-flex min-h-8 items-center rounded-md px-1 text-xs font-medium text-foreground hover:underline disabled:opacity-50"
          onClick={() => {
            void downloadEApprovalAttachmentFile(attachmentId, fileName).catch((error: unknown) => {
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
      </div>
      <div ref={scrollerRef} className="relative max-h-[75vh] w-full space-y-3 overflow-auto">
        {Array.from({ length: pageCount }, (_, index) => {
          const pageNumber = index + 1;
          return (
            <div
              key={pageNumber}
              id={`ea-pdf-page-${attachmentId}-${pageNumber}`}
              data-pdf-page={pageNumber}
              className="w-full"
            >
              <p className="mb-1 text-[11px] text-muted-foreground">Page {pageNumber}</p>
              <PdfPageView
                doc={doc}
                pageNumber={pageNumber}
                highlights={marks.filter((mark) => mark.pin_page === pageNumber)}
                activeId={activeId}
                hoverId={hoverId}
                zoom={zoom}
                fitWidth={fitWidth}
                message={message}
                posting={posting}
                replyTo={replyTo}
                replyDraft={replyDraft}
                draft={spot?.page === pageNumber ? { ...spot, message } : null}
                noteRef={noteRef}
                onPlace={placeMark}
                onHover={setHoverId}
                onMessage={setMessage}
                onPostNote={postNote}
                onClearNote={() => {
                  setSpot(null);
                  setMessage("");
                }}
                onStartReply={(id) => {
                  setReplyTo(id);
                  setReplyDraft("");
                }}
                onReplyDraft={setReplyDraft}
                onPostReply={(id) => {
                  if (replyDraft.trim() === "") {
                    return;
                  }
                  onPost({
                    message: replyDraft.trim(),
                    parent_id: id,
                    attachment_id: attachmentId,
                    mention_user_ids: mentionIdsInMessage(replyDraft, people),
                  });
                  setReplyDraft("");
                  setReplyTo(null);
                }}
                people={people}
                decision={decision}
              />
            </div>
          );
        })}
      </div>
    </div>
  );
}
