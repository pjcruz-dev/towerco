"use client";

import Link from "next/link";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useMemo, useState } from "react";
import { Link2, Trash2 } from "lucide-react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  createEApprovalDocumentLink,
  deleteEApprovalDocumentLink,
  fetchEApprovalSubmissionsIndex,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import type {
  EApprovalDocumentLinkRow,
  EApprovalRelatedFormNavigation,
  EApprovalSubmissionDetail,
} from "@/modules/e-approval/types";

type Props = {
  submissionId: string;
  documentLinks?: EApprovalDocumentLinkRow[];
  incomingDocumentLinks?: EApprovalDocumentLinkRow[];
  relatedFormNavigation?: EApprovalRelatedFormNavigation[];
  canManageLinks?: boolean;
};

function linkHref(submissionId: string): string {
  return `/e-approval/submissions/${submissionId}`;
}

function LinkList({
  title,
  links,
  emptyLabel,
  canManageLinks,
  onRemove,
  removingId,
}: {
  title: string;
  links: EApprovalDocumentLinkRow[];
  emptyLabel: string;
  canManageLinks?: boolean;
  onRemove?: (linkId: string) => void;
  removingId?: string | null;
}) {
  if (links.length === 0) {
    return <p className="text-sm text-muted-foreground">{emptyLabel}</p>;
  }

  return (
    <div className="space-y-2">
      <p className="text-xs font-medium text-muted-foreground">{title}</p>
      <ul className="divide-y divide-border rounded-lg border border-border">
        {links.map((link) => (
          <li key={link.id} className="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
            <div className="min-w-0">
              <Link href={linkHref(link.submission_id)} className="font-medium text-primary hover:underline">
                {link.document_no ?? link.submission_id}
              </Link>
              <p className="truncate text-xs text-muted-foreground">
                {[link.form_name, link.link_type, link.status].filter(Boolean).join(" · ")}
              </p>
            </div>
            {canManageLinks && link.direction === "outgoing" && onRemove ? (
              <Button
                type="button"
                size="sm"
                variant="ghost"
                className="shrink-0 text-destructive hover:text-destructive"
                disabled={removingId === link.id}
                onClick={() => onRemove(link.id)}
              >
                <Trash2 className="h-4 w-4" aria-hidden />
                <span className="sr-only">Remove link</span>
              </Button>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}

export function EApprovalDocumentLinksPanel({
  submissionId,
  documentLinks = [],
  incomingDocumentLinks = [],
  relatedFormNavigation = [],
  canManageLinks = true,
}: Props) {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [searchError, setSearchError] = useState<string | null>(null);
  const [results, setResults] = useState<{ id: string; document_no: string; form_name?: string | null }[]>([]);

  const invalidateDetail = () => {
    queryClient.invalidateQueries({ queryKey: ["e-approval", "submission", submissionId] });
  };

  const removeMutation = useMutation({
    mutationFn: deleteEApprovalDocumentLink,
    onSuccess: invalidateDetail,
  });

  const addMutation = useMutation({
    mutationFn: (targetSubmissionId: string) =>
      createEApprovalDocumentLink(submissionId, { target_submission_id: targetSubmissionId }),
    onSuccess: () => {
      setSearch("");
      setResults([]);
      setSearchError(null);
      invalidateDetail();
    },
    onError: (error) => setSearchError(getErrorMessage(error)),
  });

  const hasLinks = documentLinks.length > 0 || incomingDocumentLinks.length > 0;

  const navigation = useMemo(
    () => relatedFormNavigation.filter((item) => item.form_id),
    [relatedFormNavigation],
  );

  async function handleSearch(): Promise<void> {
    setSearchError(null);
    const query = search.trim();
    if (query.length < 2) {
      setSearchError("Enter at least 2 characters to search.");
      setResults([]);
      return;
    }

    try {
      const response = await fetchEApprovalSubmissionsIndex({ search: query, per_page: 8 });
      setResults(
        response.data
          .filter((row) => row.id !== submissionId)
          .map((row) => ({
            id: row.id,
            document_no: row.document_no,
            form_name: row.form_name,
          })),
      );
    } catch (error) {
      setSearchError(getErrorMessage(error));
      setResults([]);
    }
  }

  return (
    <EApprovalSectionCard
      title="Linked documents"
      description="Cross-reference related submissions without a parent link."
    >
      <div className="space-y-4">
        {navigation.length > 0 ? (
          <div className="rounded-lg border border-border bg-muted/20 p-3">
            <p className="text-xs font-medium text-muted-foreground">Related forms</p>
            <ul className="mt-2 flex flex-wrap gap-2">
              {navigation.map((item) => (
                <li key={item.form_id}>
                  <Link
                    href={item.href}
                    className="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground hover:border-primary/30"
                  >
                    <Link2 className="h-3.5 w-3.5 text-primary" aria-hidden />
                    {item.form_name}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <LinkList
          title="Links from this document"
          links={documentLinks}
          emptyLabel={hasLinks ? "No outgoing links." : "No linked documents yet."}
          canManageLinks={canManageLinks}
          removingId={removeMutation.isPending ? removeMutation.variables : null}
          onRemove={(linkId) => removeMutation.mutate(linkId)}
        />

        <LinkList
          title="Referenced by"
          links={incomingDocumentLinks}
          emptyLabel="No incoming references."
        />

        {canManageLinks ? (
          <div className="space-y-2 border-t border-border pt-4">
            <p className="text-xs font-medium text-muted-foreground">Add link by document search</p>
            <div className="flex gap-2">
              <Input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search document no. or title"
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    event.preventDefault();
                    void handleSearch();
                  }
                }}
              />
              <Button type="button" variant="outline" onClick={() => void handleSearch()}>
                Search
              </Button>
            </div>
            {searchError ? <p className="text-sm text-destructive">{searchError}</p> : null}
            {results.length > 0 ? (
              <ul className="divide-y divide-border rounded-lg border border-border">
                {results.map((result) => (
                  <li key={result.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                    <div>
                      <p className="font-medium">{result.document_no}</p>
                      <p className="text-xs text-muted-foreground">{result.form_name ?? "Submission"}</p>
                    </div>
                    <Button
                      type="button"
                      size="sm"
                      disabled={addMutation.isPending}
                      onClick={() => addMutation.mutate(result.id)}
                    >
                      Link
                    </Button>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        ) : null}
      </div>
    </EApprovalSectionCard>
  );
}

export function pickDocumentLinkState(detail: EApprovalSubmissionDetail) {
  return {
    documentLinks: detail.document_links ?? [],
    incomingDocumentLinks: detail.incoming_document_links ?? [],
    relatedFormNavigation: detail.related_form_navigation ?? [],
  };
}
