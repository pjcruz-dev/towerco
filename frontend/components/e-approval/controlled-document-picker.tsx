"use client";

import { useQuery } from "@tanstack/react-query";
import { FileSearch } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { Select } from "@/components/ui/select";
import {
  fetchControlledDocuments,
  lookupControlledDocument,
  type ControlledDocumentLookupResult,
  type ControlledDocumentRow,
} from "@/lib/api/modules/controlled-documents-api";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { cn } from "@/lib/utils";

type Props = {
  documentCode: string;
  onDocumentCodeChange: (code: string) => void;
  onLookupResolved: (lookup: ControlledDocumentLookupResult) => void;
  disabled?: boolean;
  className?: string;
};

function formatDocumentOption(row: ControlledDocumentRow): string {
  const type = row.document_type ? ` · ${row.document_type}` : "";
  const department = row.department ? ` · ${row.department}` : "";
  return `${row.document_code} — ${row.title}${type}${department}`;
}

export function ControlledDocumentPicker({
  documentCode,
  onDocumentCodeChange,
  onLookupResolved,
  disabled,
  className,
}: Props) {
  const [search, setSearch] = useState(documentCode);
  const debouncedSearch = useDebouncedValue(search, 300);

  const listQuery = useQuery({
    queryKey: ["documents", "controlled", "picker", debouncedSearch],
    queryFn: () =>
      fetchControlledDocuments({
        search: debouncedSearch.trim() || undefined,
        per_page: 25,
        status: "published",
      }),
    staleTime: 30_000,
  });

  const lookupQuery = useQuery({
    queryKey: ["documents", "controlled", "lookup", documentCode],
    queryFn: () => lookupControlledDocument(documentCode),
    enabled: documentCode.trim().length >= 3,
    staleTime: 30_000,
  });

  const options = listQuery.data?.documents.data ?? [];
  const selected = useMemo(
    () => options.find((row) => row.document_code === documentCode) ?? null,
    [documentCode, options],
  );

  const lookup = lookupQuery.data;
  const lookupReady = lookup?.exists === true && lookup.document_code === documentCode;

  useEffect(() => {
    if (lookupReady && lookup) {
      onLookupResolved(lookup);
    }
  }, [lookup, lookupReady, onLookupResolved]);

  const handleSelect = (code: string) => {
    onDocumentCodeChange(code);
    setSearch(code);
  };

  return (
    <EApprovalSectionCard
      className={className}
      title="Select controlled document"
      description="Search the document registry, then confirm the details prefilled below."
    >
      <div className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="controlled-doc-search">
            <span className="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
              <FileSearch className="h-3.5 w-3.5" />
              Search by code or title
            </span>
          </Label>
          <Input
            id="controlled-doc-search"
            value={search}
            disabled={disabled}
            placeholder="e.g. ATC-EDD-F-001 or policy title"
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>

        {listQuery.isLoading ? (
          <div className="h-10 animate-pulse rounded-lg bg-muted/50" />
        ) : listQuery.isError ? (
          <OperationalAlert
            level="error"
            title="Could not load documents"
            description="Refresh the page or try again."
          />
        ) : options.length === 0 ? (
          <OperationalAlert
            level="warning"
            title="No matching documents"
            description="Try another search term, or publish a new document first."
          />
        ) : (
          <div className="space-y-2">
            <Label htmlFor="controlled-doc-select">
              <span className="text-xs font-medium text-muted-foreground">Controlled document</span>
            </Label>
            <Select
              id="controlled-doc-select"
              value={documentCode}
              disabled={disabled}
              onChange={(event) => handleSelect(event.target.value)}
            >
              <option value="">Select a document…</option>
              {options.map((row) => (
                <option key={row.id} value={row.document_code}>
                  {formatDocumentOption(row)}
                </option>
              ))}
            </Select>
          </div>
        )}

        {documentCode && lookupQuery.isFetching ? (
          <RefreshingHint label="Loading document details" />
        ) : null}

        {documentCode && lookupQuery.isError ? (
          <OperationalAlert
            level="warning"
            title="Document not found"
            description={`No controlled document matches "${documentCode}".`}
          />
        ) : null}

        {lookupReady && lookup ? (
          <div className={cn("rounded-lg border border-primary/20 bg-primary/5 px-3 py-3 text-sm")}>
            <p className="font-medium text-foreground">{lookup.title ?? selected?.title ?? "—"}</p>
            <dl className="mt-2 grid gap-2 text-xs sm:grid-cols-2">
              <div>
                <dt className="text-muted-foreground">Document code</dt>
                <dd className="font-mono font-medium text-foreground">{lookup.document_code}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">Next revision</dt>
                <dd className="font-medium text-foreground">
                  {lookup.current_revision ?? 0} → {lookup.next_revision}
                </dd>
              </div>
              <div>
                <dt className="text-muted-foreground">Type</dt>
                <dd>{lookup.document_type ?? "—"}</dd>
              </div>
              <div>
                <dt className="text-muted-foreground">Department</dt>
                <dd>{lookup.department ?? "—"}</dd>
              </div>
            </dl>
            <p className="mt-2 text-xs text-muted-foreground">
              Title, type, department, dates, and revision below are prefilled from the registry. Update only what
              changes in this revision.
            </p>
          </div>
        ) : null}
      </div>
    </EApprovalSectionCard>
  );
}
