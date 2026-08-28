"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Paperclip, X } from "lucide-react";

import { TicketingPageHeader } from "@/components/ticketing/ticketing-page-header";
import { formatFileSize, ticketingCategoryLabel } from "@/components/ticketing/ticketing-utils";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  createTicketingTicket,
  fetchTicketingMetadata,
  uploadTicketingAttachment,
} from "@/lib/api/modules/ticketing-api";
import { parseRaiseTicketSearchParams } from "@/lib/ticketing/raise-ticket";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

export function TicketingNewTicketPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const prefill = parseRaiseTicketSearchParams(searchParams);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [category, setCategory] = useState("general");
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (prefill.title) setTitle(prefill.title);
    if (prefill.description) setDescription(prefill.description);
  }, [prefill.title, prefill.description]);

  const { data: metadata } = useQuery({
    queryKey: ["ticketing", "metadata"],
    queryFn: fetchTicketingMetadata,
    staleTime: 300_000,
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
      });

      for (const file of files) {
        await uploadTicketingAttachment(ticket.id, file);
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

  function onFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const selected = Array.from(event.target.files ?? []);
    if (selected.length > 0) {
      setFiles((prev) => [...prev, ...selected]);
    }
    event.target.value = "";
  }

  function removeFile(index: number) {
    setFiles((prev) => prev.filter((_, i) => i !== index));
  }

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
            if (!title.trim()) {
              setError("Title is required.");
              return;
            }
            createMutation.mutate();
          }}
        >
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
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <h2 className="text-sm font-medium text-foreground">Attachments</h2>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  PNG, JPG, PDF, and office documents up to 10 MB each.
                </p>
              </div>
              <button
                type="button"
                className={cn(buttonVariants({ variant: "outline", size: "sm" }), "inline-flex cursor-pointer")}
                onClick={() => fileInputRef.current?.click()}
              >
                <Paperclip className="mr-1.5 h-4 w-4" aria-hidden />
                Add files
              </button>
              <input
                ref={fileInputRef}
                type="file"
                multiple
                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                className="sr-only"
                onChange={onFileChange}
              />
            </div>
            {files.length > 0 ? (
              <ul className="divide-y divide-border rounded-lg border border-border">
                {files.map((file, index) => (
                  <li
                    key={`${file.name}-${index}`}
                    className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm"
                  >
                    <div className="min-w-0">
                      <p className="truncate font-medium text-foreground">{file.name}</p>
                      <p className="text-xs text-muted-foreground">{formatFileSize(file.size)}</p>
                    </div>
                    <button
                      type="button"
                      className="text-muted-foreground hover:text-foreground"
                      onClick={() => removeFile(index)}
                      aria-label={`Remove ${file.name}`}
                    >
                      <X className="h-4 w-4" />
                    </button>
                  </li>
                ))}
              </ul>
            ) : null}
          </section>

          {error ? <p className="text-sm text-destructive">{error}</p> : null}

          <div className="flex flex-wrap gap-2 border-t border-border pt-5">
            <span data-help="tk-compose-submit" className="inline-flex">
              <Button type="submit" disabled={createMutation.isPending}>
                {createMutation.isPending ? "Submitting…" : "Submit ticket"}
              </Button>
            </span>
            <Button variant="outline" render={<Link href="/ticketing/tickets" />}>
              Cancel
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}
