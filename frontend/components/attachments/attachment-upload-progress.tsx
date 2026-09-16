"use client";

import { createContext, useContext } from "react";

export type AttachmentUploadProgressEntry = {
  progress: number;
  status: "uploading" | "complete" | "error";
  error?: string | null;
};

export type AttachmentUploadProgressMap = Record<string, AttachmentUploadProgressEntry>;

const AttachmentUploadProgressContext = createContext<AttachmentUploadProgressMap>({});

export function AttachmentUploadProgressProvider({
  value,
  children,
}: {
  value: AttachmentUploadProgressMap;
  children: React.ReactNode;
}) {
  return (
    <AttachmentUploadProgressContext.Provider value={value}>
      {children}
    </AttachmentUploadProgressContext.Provider>
  );
}

export function useAttachmentUploadProgress(): AttachmentUploadProgressMap {
  return useContext(AttachmentUploadProgressContext);
}

export function attachmentLocalFileKey(file: File): string {
  return `${file.name}:${file.size}:${file.lastModified}`;
}
