"use client";

import { useEffect, useRef, useState } from "react";
import { useParams, useSearchParams } from "next/navigation";

import { apiClient } from "@/lib/api/client";
import { getErrorMessage } from "@/lib/api/error";

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
  destroy: () => Promise<void>;
};

function axiosStatus(error: unknown): number | null {
  if (!error || typeof error !== "object" || !("response" in error)) {
    return null;
  }
  const status = (error as { response?: { status?: number } }).response?.status;
  return typeof status === "number" ? status : null;
}

function blockCopyKeys(event: KeyboardEvent) {
  const key = event.key.toLowerCase();
  const modified = event.ctrlKey || event.metaKey;
  if (modified && (key === "p" || key === "s" || key === "c")) {
    event.preventDefault();
    event.stopPropagation();
  }
  if (key === "printscreen") {
    event.preventDefault();
  }
}

function PublishedFilePage({ doc, pageNumber, width }: { doc: PdfDoc; pageNumber: number; width: number }) {
  const [canvas, setCanvas] = useState<HTMLCanvasElement | null>(null);

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
      const viewport = page.getViewport({ scale: (width / unscaled.width) * dpr });
      canvas.width = Math.ceil(viewport.width);
      canvas.height = Math.ceil(viewport.height);
      canvas.style.width = `${width}px`;
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
  }, [canvas, doc, pageNumber, width]);

  return (
    <canvas
      ref={setCanvas}
      className="mx-auto block max-w-full bg-white shadow-sm"
      draggable={false}
      aria-label={`Page ${pageNumber}`}
    />
  );
}

function PublishedFilePages({ bytes }: { bytes: Uint8Array }) {
  const frameRef = useRef<HTMLDivElement>(null);
  const [doc, setDoc] = useState<PdfDoc | null>(null);
  const [failed, setFailed] = useState(false);
  const [width, setWidth] = useState(960);

  useEffect(() => {
    let cancelled = false;
    let current: PdfDoc | null = null;
    void (async () => {
      try {
        const pdfjs = await import("pdfjs-dist");
        pdfjs.GlobalWorkerOptions.workerSrc = "/pdfjs/pdf.worker.min.mjs";
        const next = (await pdfjs.getDocument({ data: Uint8Array.from(bytes), verbosity: 0 }).promise) as PdfDoc;
        if (cancelled) {
          void next.destroy();
          return;
        }
        current = next;
        setDoc(next);
      } catch {
        if (!cancelled) {
          setFailed(true);
        }
      }
    })();
    return () => {
      cancelled = true;
      if (current) {
        void current.destroy();
      }
    };
  }, [bytes]);

  useEffect(() => {
    const node = frameRef.current;
    if (!node) {
      return;
    }
    const measure = () => setWidth(Math.max(320, Math.floor(node.clientWidth - 32)));
    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(node);
    return () => observer.disconnect();
  }, [doc]);

  useEffect(() => {
    window.addEventListener("keydown", blockCopyKeys, true);
    return () => window.removeEventListener("keydown", blockCopyKeys, true);
  }, []);

  if (failed) {
    return <p className="text-sm text-destructive">This published file could not be opened.</p>;
  }
  if (!doc) {
    return <p className="text-sm text-muted-foreground">Opening the published file…</p>;
  }

  return (
    <div
      ref={frameRef}
      className="published-file-pages h-[calc(100vh-14rem)] space-y-4 overflow-auto bg-muted/40 p-4 select-none"
      onContextMenu={(event) => event.preventDefault()}
      onDragStart={(event) => event.preventDefault()}
    >
      {Array.from({ length: doc.numPages }, (_, index) => (
        <PublishedFilePage key={index + 1} doc={doc} pageNumber={index + 1} width={width} />
      ))}
    </div>
  );
}

export function ControlledDocumentPublishedFilePageClient() {
  const params = useParams<{ id: string }>();
  const searchParams = useSearchParams();
  const documentId = params.id;
  const token = searchParams.get("token") ?? "";
  const [bytes, setBytes] = useState<Uint8Array | null>(null);
  const [fileName, setFileName] = useState("Published file");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (token.trim() === "") {
      setLoading(false);
      setError("This link does not include a published-file token.");
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);
    setBytes(null);

    void apiClient
      .get(`/documents/controlled/${documentId}/published-file`, {
        params: { token },
        responseType: "blob",
      })
      .then(async (response) => {
        if (cancelled) {
          return;
        }
        const blob = response.data as Blob;
        if (blob.type.includes("json") || blob.type.includes("text/html")) {
          setError("This document is not published.");
          return;
        }
        const header = String(response.headers["content-disposition"] ?? "");
        const named = /filename="([^"]+)"/.exec(header);
        if (named?.[1]) {
          setFileName(named[1]);
        }
        setBytes(new Uint8Array(await blob.arrayBuffer()));
      })
      .catch((caught: unknown) => {
        if (cancelled) {
          return;
        }
        const status = axiosStatus(caught);
        if (status === 404) {
          setError("This document is not published. An obsolete revision cannot be opened from this link.");
          return;
        }
        if (status === 403) {
          setError("This link is not valid for the published file.");
          return;
        }
        setError(getErrorMessage(caught) || "The published file could not be opened.");
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [documentId, token]);

  return (
    <div className="flex w-full flex-col gap-4">
      <style>{`
        @media print {
          .published-file-pages { display: none !important; }
          .published-file-print-note { display: block !important; }
        }
      `}</style>
      <div>
        <h1 className="text-lg font-semibold text-foreground">Published file</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          This link opens only the revision that is published now. When a new revision is published, the same link opens that file. An obsolete revision cannot be opened here. This view has no download or print.
        </p>
      </div>
      <p className="published-file-print-note hidden text-sm text-foreground">
        This published file cannot be printed from this page.
      </p>
      {loading ? <p className="text-sm text-muted-foreground">Loading the published file…</p> : null}
      {error ? (
        <p className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
          {error}
        </p>
      ) : null}
      {bytes ? (
        <div className="overflow-hidden rounded-xl border border-border bg-card">
          <div className="border-b border-border px-3 py-2">
            <p className="min-w-0 truncate text-sm font-medium text-foreground">{fileName}</p>
          </div>
          <PublishedFilePages bytes={bytes} />
        </div>
      ) : null}
    </div>
  );
}
