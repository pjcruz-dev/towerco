"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import type { EApprovalFormFieldInput, EApprovalWorkflowStepInput } from "@/modules/e-approval/types";

import type { EApprovalFormDocumentNumberSettings } from "@/modules/e-approval/form-document-number";

export type EApprovalFormEditorSnapshot = {
  name: string;
  description: string;
  status: string;
  fields: EApprovalFormFieldInput[];
  steps: EApprovalWorkflowStepInput[];
  metadataJson: string;
  brandLogoUrl: string | null;
  documentNumber: EApprovalFormDocumentNumberSettings;
};

export function serializeEApprovalFormEditorSnapshot(snapshot: EApprovalFormEditorSnapshot): string {
  return JSON.stringify({
    name: snapshot.name.trim(),
    description: snapshot.description.trim(),
    status: snapshot.status,
    fields: snapshot.fields,
    steps: snapshot.steps,
    metadataJson: snapshot.metadataJson.trim(),
    brandLogoUrl: snapshot.brandLogoUrl,
    documentNumber: snapshot.documentNumber,
  });
}

export function useEApprovalFormEditorDirty(snapshot: EApprovalFormEditorSnapshot, enabled = true) {
  const baselineRef = useRef<string | null>(null);
  const [dirty, setDirty] = useState(false);

  const serialized = useMemo(() => serializeEApprovalFormEditorSnapshot(snapshot), [snapshot]);

  const markSaved = useCallback(() => {
    baselineRef.current = serialized;
    setDirty(false);
  }, [serialized]);

  /**
   * After an async save: keep baseline at what was persisted. If the editor moved on,
   * stay dirty so the next autosave can catch up (avoids wiping in-progress edits).
   */
  const reconcileAfterSave = useCallback((savedSerialized: string) => {
    baselineRef.current = savedSerialized;
    setDirty(serialized !== savedSerialized);
  }, [serialized]);

  const resetBaseline = useCallback((next?: string) => {
    baselineRef.current = next ?? serialized;
    setDirty(false);
  }, [serialized]);

  useEffect(() => {
    if (!enabled) {
      baselineRef.current = serialized;
      setDirty(false);
      return;
    }

    if (baselineRef.current === null) {
      baselineRef.current = serialized;
      setDirty(false);
      return;
    }

    setDirty(baselineRef.current !== serialized);
  }, [enabled, serialized]);

  useEffect(() => {
    if (!enabled || !dirty) {
      return;
    }

    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = "";
    };

    window.addEventListener("beforeunload", onBeforeUnload);

    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [dirty, enabled]);

  const confirmDiscard = useCallback(
    (message = "You have unsaved changes. Leave without saving?") => {
      if (!dirty) {
        return true;
      }

      return window.confirm(message);
    },
    [dirty],
  );

  return { dirty, markSaved, reconcileAfterSave, resetBaseline, confirmDiscard };
}
