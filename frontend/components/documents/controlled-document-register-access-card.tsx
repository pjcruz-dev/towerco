"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ChevronDown, ChevronUp, Shield } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import {
  DEFAULT_REGISTER_ACCESS_EDITOR,
  registerAccessEditorFromPolicy,
  registerAccessEditorToApiPayload,
  validateRegisterAccessEditor,
  type ControlledDocumentRegisterAccessEditor,
} from "@/modules/documents/controlled-document-register-access";
import {
  fetchControlledDocumentRegisterAccess,
  updateControlledDocumentRegisterAccess,
} from "@/lib/api/modules/controlled-documents-api";
import { getErrorMessage } from "@/lib/api/error";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

export function ControlledDocumentRegisterAccessCard() {
  const push = useNotificationStore((s) => s.push);
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editor, setEditor] = useState<ControlledDocumentRegisterAccessEditor>(DEFAULT_REGISTER_ACCESS_EDITOR);
  const [dirty, setDirty] = useState(false);

  const query = useQuery({
    queryKey: ["documents", "controlled", "register-access"],
    queryFn: fetchControlledDocumentRegisterAccess,
  });

  useEffect(() => {
    if (!query.data || dirty) {
      return;
    }
    setEditor(registerAccessEditorFromPolicy(query.data.access_policy));
  }, [query.data, dirty]);

  const saveMutation = useMutation({
    mutationFn: updateControlledDocumentRegisterAccess,
    onSuccess: (payload) => {
      queryClient.setQueryData(["documents", "controlled", "register-access"], payload);
      setEditor(registerAccessEditorFromPolicy(payload.access_policy));
      setDirty(false);
      push({ level: "success", title: "Register access saved", message: "Access rules apply on the next registry load." });
    },
    onError: (error) => {
      push({ level: "error", title: "Could not save access rules", message: getErrorMessage(error) });
    },
  });

  const patch = (partial: Partial<ControlledDocumentRegisterAccessEditor>) => {
    setDirty(true);
    setEditor((current) => ({ ...current, ...partial }));
  };

  const handleSave = () => {
    const validationError = validateRegisterAccessEditor(editor);
    if (validationError) {
      push({ level: "error", title: "Invalid access rules", message: validationError });
      return;
    }

    try {
      saveMutation.mutate(registerAccessEditorToApiPayload(editor));
    } catch (error) {
      push({
        level: "error",
        title: "Invalid access rules",
        message: error instanceof Error ? error.message : "Check the role map JSON.",
      });
    }
  };

  const configured = query.data?.configured ?? false;

  return (
    <section className="rounded-xl border border-border bg-card shadow-sm">
      <button
        type="button"
        className="flex w-full items-start justify-between gap-3 px-4 py-3 text-left"
        onClick={() => setOpen((value) => !value)}
      >
        <div className="flex min-w-0 items-start gap-3">
          <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
            <Shield className="h-4 w-4 text-muted-foreground" />
          </span>
          <span>
            <span className="block text-sm font-medium text-foreground">Register access</span>
            <span className="mt-0.5 block text-xs text-muted-foreground">
              Role filters for who sees which rows. <code className="rounded bg-muted px-1">dcf_author</code>{" "}
              defaults to own published documents only. Users still need{" "}
              <code className="rounded bg-muted px-1">documents:controlled:view</code>.
            </span>
          </span>
        </div>
        {open ? (
          <ChevronUp className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
        ) : (
          <ChevronDown className="mt-1 h-4 w-4 shrink-0 text-muted-foreground" />
        )}
      </button>

      <div className={cn("border-t border-border px-4 pb-4 pt-3", !open && "hidden")}>
        {query.isLoading ? (
          <RefreshingHint label="Loading access rules" />
        ) : !configured ? (
          <p className="text-xs text-muted-foreground">
            Publish an E-Approval form with controlled document sync enabled before configuring register access.
          </p>
        ) : (
          <div className="space-y-4">
            <div className="grid gap-3 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="cd-register-viewer-roles">Viewer roles (comma-separated)</Label>
                <Input
                  id="cd-register-viewer-roles"
                  value={editor.viewerRoles}
                  placeholder="dcf_viewer"
                  onChange={(event) => patch({ viewerRoles: event.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="cd-register-full-roles">Full register access roles</Label>
                <Input
                  id="cd-register-full-roles"
                  value={editor.fullAccessRoles}
                  placeholder="dcf_controller, dcf_admin"
                  onChange={(event) => patch({ fullAccessRoles: event.target.value })}
                />
              </div>
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="cd-register-own-roles">Own documents only roles</Label>
                <Input
                  id="cd-register-own-roles"
                  value={editor.ownOnlyRoles}
                  placeholder="dcf_author"
                  onChange={(event) => patch({ ownOnlyRoles: event.target.value })}
                />
                <p className="text-[11px] text-muted-foreground">
                  These roles only see documents they created or revised (default:{" "}
                  <code className="rounded bg-muted px-1">dcf_author</code>). Broader roles like viewer / approver /
                  controller still see more.
                </p>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="cd-register-role-dept-map">Role → department map (JSON)</Label>
              <textarea
                id="cd-register-role-dept-map"
                value={editor.roleDepartmentMapJson}
                rows={4}
                placeholder='{"member":["PMO","QMS"],"document_controller":["*"]}'
                className="w-full rounded-lg border border-border bg-background px-3 py-2 font-mono text-xs"
                onChange={(event) => patch({ roleDepartmentMapJson: event.target.value })}
              />
              <p className="text-[11px] text-muted-foreground">
                Use department codes matching the form field. <code className="rounded bg-muted px-1">*</code> means all
                departments for that role.
              </p>
            </div>

            <div className="flex justify-end">
              <Button type="button" size="sm" disabled={saveMutation.isPending} onClick={handleSave}>
                {saveMutation.isPending ? "Saving…" : "Save access rules"}
              </Button>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
