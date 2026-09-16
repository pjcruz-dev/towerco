"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";

import { AttachmentDropzone } from "@/components/attachments/attachment-dropzone";
import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { TicketingUserPicker } from "@/components/ticketing/ticketing-user-picker";
import { ticketingCategoryLabel } from "@/components/ticketing/ticketing-utils";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  createTicketingTicket,
  fetchTicketingDirectoryUsers,
  fetchTicketingMetadata,
  uploadTicketingAttachment,
} from "@/lib/api/modules/ticketing-api";
import { parseRaiseTicketSearchParams } from "@/lib/ticketing/raise-ticket";
import { permissions } from "@/lib/rbac/permissions";
import { usePermission } from "@/hooks/use-permission";
import { useAuthStore } from "@/stores/auth-store";

function localFileKey(file: File): string {
  return `${file.name}:${file.size}:${file.lastModified}`;
}

export function TicketingNewTicketPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const prefill = parseRaiseTicketSearchParams(searchParams);
  const currentUser = useAuthStore((state) => state.user);
  const canManageTickets = usePermission([permissions.ticketingTicketsManage]);

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [category, setCategory] = useState("general");
  const [requesterId, setRequesterId] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [uploadStateByKey, setUploadStateByKey] = useState<
    Record<string, { progress: number; status: "uploading" | "complete" | "error"; error?: string | null }>
  >({});

  useEffect(() => {
    if (prefill.title) setTitle(prefill.title);
    if (prefill.description) setDescription(prefill.description);
  }, [prefill.title, prefill.description]);

  useEffect(() => {
    if (canManageTickets && currentUser?.id && !requesterId) {
      setRequesterId(currentUser.id);
    }
  }, [canManageTickets, currentUser?.id, requesterId]);

  const { data: metadata } = useQuery({
    queryKey: ["ticketing", "metadata"],
    queryFn: fetchTicketingMetadata,
    staleTime: 300_000,
  });

  const { data: directoryUsers, isLoading: usersLoading } = useQuery({
    queryKey: ["ticketing", "directory-users"],
    queryFn: fetchTicketingDirectoryUsers,
    enabled: canManageTickets,
    staleTime: 60_000,
  });

  useEffect(() => {
    const ids = metadata?.category_options?.map((o) => o.id) ?? metadata?.categories ?? [];
    if (prefill.category && ids.includes(prefill.category)) {
      setCategory(prefill.category);
    }
  }, [prefill.category, metadata?.categories, metadata?.category_options]);

  const createMutation = useMutation({
    mutationFn: async () => {
      const ticket = await createTicketingTicket({
        title: title.trim(),
        description: description.trim() || undefined,
        category,
        source_module: prefill.source_module ?? "manual",
        source_reference_type: prefill.source_reference_type,
        source_reference_id: prefill.source_reference_id,
        source_label: prefill.source_label,
        links: prefill.links,
        ...(canManageTickets && requesterId ? { requester_id: requesterId } : {}),
      });

      const failedUploads: string[] = [];
      for (const file of files) {
        const key = localFileKey(file);
        setUploadStateByKey((prev) => ({
          ...prev,
          [key]: { progress: 0, status: "uploading" },
        }));
        try {
          await uploadTicketingAttachment(ticket.id, file, {
            onProgress: (percent) => {
              setUploadStateByKey((prev) => ({
                ...prev,
                [key]: { progress: percent, status: "uploading" },
              }));
            },
          });
          setUploadStateByKey((prev) => ({
            ...prev,
            [key]: { progress: 100, status: "complete" },
          }));
        } catch (uploadError) {
          const message = getErrorMessage(uploadError) || "Upload failed";
          setUploadStateByKey((prev) => ({
            ...prev,
            [key]: { progress: 0, status: "error", error: message },
          }));
          failedUploads.push(`${file.name}: ${message}`);
        }
      }

      if (failedUploads.length > 0) {
        // Ticket exists; send user to detail so remaining files can be retried there.
        router.push(`/ticketing/tickets/${ticket.id}`);
        throw new Error(
          failedUploads.length === 1
            ? `Ticket created, but ${failedUploads[0]}`
            : `Ticket created, but some files failed: ${failedUploads.join("; ")}`,
        );
      }

      return ticket;
    },
    onSuccess: (ticket) => {
      router.push(`/ticketing/tickets/${ticket.id}`);
    },
    onError: (err) => {
      setError(getErrorMessage(err) || "Could not create ticket. Please try again.");
    },
  });

  const isSubmitting = createMutation.isPending;

  return (
    <PermissionGate requiredPermissions={[permissions.ticketingTicketsCreate]}>
      <div className="space-y-6">
        <LiveProductTourHost />
        <TicketingPageHeader
          eyebrow={
            <Link href="/ticketing/tickets" className="hover:text-primary">
              Tickets
            </Link>
          }
          title="New ticket"
          description="Describe the issue and attach screenshots or documents to help the team resolve it faster."
        />

        {prefill.source_label ? (
          <p className="rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs text-muted-foreground">
            Linked to <span className="font-medium text-foreground">{prefill.source_label}</span>
          </p>
        ) : null}

        <form
          className="space-y-6 rounded-xl border border-border bg-card p-5 shadow-sm md:p-6"
          onSubmit={(e) => {
            e.preventDefault();
            setError(null);
            setUploadStateByKey({});
            if (!title.trim()) {
              setError("Title is required.");
              return;
            }
            if (canManageTickets && !requesterId) {
              setError("Select who this ticket is for.");
              return;
            }
            createMutation.mutate();
          }}
        >
          {canManageTickets ? (
            <section data-help="tk-compose-requester" className="space-y-2">
              <Label htmlFor="requester">Created for (requester)</Label>
              <TicketingUserPicker
                id="requester"
                users={directoryUsers ?? []}
                value={requesterId}
                onChange={setRequesterId}
                disabled={usersLoading || isSubmitting}
                placeholder={usersLoading ? "Loading users…" : "Select user…"}
              />
              <p className="text-xs text-muted-foreground">
                Ticket managers can open a ticket on behalf of another user. The selected user becomes the
                requester.
              </p>
            </section>
          ) : null}

          <section data-help="tk-compose-title" className="space-y-4">
            <h2 className="text-sm font-medium text-foreground">Issue details</h2>
            <div className="space-y-2">
              <Label htmlFor="title">Title</Label>
              <Input
                id="title"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Brief summary of the issue"
                required
                disabled={isSubmitting}
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="description">Description</Label>
              <Textarea
                id="description"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Steps to reproduce, expected vs actual behavior, module context…"
                rows={6}
                disabled={isSubmitting}
              />
            </div>
          </section>

          <section data-help="tk-compose-category" className="space-y-2 border-t border-border pt-5">
            <Label htmlFor="category">Category</Label>
            <Select
              id="category"
              className="h-10"
              value={category}
              onChange={(e) => setCategory(e.target.value)}
              disabled={isSubmitting}
            >
              {(metadata?.category_options?.length
                ? metadata.category_options
                : (metadata?.categories ?? ["general"]).map((id) => ({
                    id,
                    label: ticketingCategoryLabel(id),
                  }))
              ).map((item) => (
                <option key={item.id} value={item.id}>
                  {item.label}
                </option>
              ))}
            </Select>
            <p className="text-xs text-muted-foreground">IT will set priority during triage.</p>
          </section>

          <section data-help="tk-compose-attachments" className="space-y-3 border-t border-border pt-5">
            <div>
              <h2 className="text-sm font-medium text-foreground">Attachments</h2>
              <p className="mt-0.5 text-xs text-muted-foreground">
                PNG, JPG, PDF, and office documents up to 10 MB each. Drag & drop, browse, or paste a
                screenshot. Progress shows while submitting.
              </p>
            </div>
            <AttachmentDropzone
              files={files}
              onChange={setFiles}
              multiple
              maxFiles={20}
              accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
              enablePaste
              disabled={isSubmitting}
              uploadStateByKey={uploadStateByKey}
              hint="PNG, JPG, PDF, Office · up to 10 MB · paste screenshot with Ctrl+V / ⌘V"
            />
          </section>

          {error ? <p className="text-sm text-destructive">{error}</p> : null}

          <div className="flex flex-wrap gap-2 border-t border-border pt-5">
            <span data-help="tk-compose-submit" className="inline-flex">
              <Button type="submit" disabled={isSubmitting}>
                {isSubmitting
                  ? Object.values(uploadStateByKey).some((entry) => entry.status === "uploading")
                    ? "Uploading attachments…"
                    : "Submitting…"
                  : "Submit ticket"}
              </Button>
            </span>
            <Button variant="outline" disabled={isSubmitting} render={<Link href="/ticketing/tickets" />}>
              Cancel
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}
