"use client";

import { useEffect, useState } from "react";
import { useParams, useSearchParams } from "next/navigation";

import { Button } from "@/components/ui/button";
import { apiClient } from "@/lib/api/client";
import { getErrorMessage } from "@/lib/api/error";

function axiosStatus(error: unknown): number | null {
  if (!error || typeof error !== "object" || !("response" in error)) {
    return null;
  }
  const status = (error as { response?: { status?: number } }).response?.status;
  return typeof status === "number" ? status : null;
}

export function ControlledDocumentPublishedFilePageClient() {
  const params = useParams<{ id: string }>();
  const searchParams = useSearchParams();
  const documentId = params.id;
  const token = searchParams.get("token") ?? "";
  const [src, setSrc] = useState<string | null>(null);
  const [fileName, setFileName] = useState("Published file");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (token.trim() === "") {
      setLoading(false);
      setError("This link does not include a published-file token.");
      return;
    }

    let objectUrl: string | null = null;
    let cancelled = false;
    setLoading(true);
    setError(null);
    setSrc(null);

    void apiClient
      .get(`/documents/controlled/${documentId}/published-file`, {
        params: { token },
        responseType: "blob",
      })
      .then((response) => {
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
        objectUrl = URL.createObjectURL(blob);
        setSrc(objectUrl);
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
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
      }
    };
  }, [documentId, token]);

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
      <div>
        <h1 className="text-lg font-semibold text-foreground">Published file</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          This link opens only the revision that is published now. When a new revision is published, the same link opens that file. An obsolete revision cannot be opened here.
        </p>
      </div>
      {loading ? <p className="text-sm text-muted-foreground">Loading the published file…</p> : null}
      {error ? (
        <p className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive" role="alert">
          {error}
        </p>
      ) : null}
      {src ? (
        <div className="overflow-hidden rounded-xl border border-border bg-card">
          <div className="flex items-center justify-between gap-3 border-b border-border px-3 py-2">
            <p className="min-w-0 truncate text-sm font-medium text-foreground">{fileName}</p>
            <Button type="button" size="sm" variant="outline" onClick={() => window.open(src, "_blank", "noopener,noreferrer")}>
              Open in a new tab
            </Button>
          </div>
          <iframe title={fileName} src={src} className="h-[75vh] w-full bg-background" />
        </div>
      ) : null}
    </div>
  );
}
