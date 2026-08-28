"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useCallback, useMemo, useState } from "react";
import { FileText, Search } from "lucide-react";

import { EApprovalBackLink, EApprovalPageHeader } from "@/components/e-approval/e-approval-page-header";
import { EApprovalListShell } from "@/components/e-approval/e-approval-list-shell";
import { EApprovalListViewToggle } from "@/components/e-approval/e-approval-list-view-toggle";
import { EApprovalSubmissionFormPickerCard } from "@/components/e-approval/e-approval-submission-form-picker-card";
import { EApprovalTourPickerFixtures } from "@/components/help/e-approval-tour-fixtures";
import { LiveProductTourHost } from "@/components/help/live-product-tour-host";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { createEApprovalSubmissionNewTableColumns } from "@/components/e-approval/e-approval-submission-new-table-columns";
import { RegistryDataTableView } from "@/components/registry/registry-data-table-view";
import { useEApprovalListView } from "@/hooks/use-e-approval-list-view";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchEApprovalFormPublicShareUrl,
  fetchEApprovalFormsIndex,
} from "@/lib/api/modules/e-approval-api";
import {
  E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH,
  isEApprovalDocumentApprovalFormName,
  isEApprovalTourActive,
} from "@/lib/help/e-approval-tour-fixtures";
import { eApprovalFocusUrl, eApprovalRequestUrlFromNewSubmissionQuery } from "@/modules/documents/controlled-document-submission-url";
import type { EApprovalFormListRow } from "@/modules/e-approval/types";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

const VIEW_STORAGE_KEY = "e-approval-submission-new-view";

export function EApprovalSubmissionNewPageClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const tourActive = isEApprovalTourActive(searchParams);
  const push = useNotificationStore((s) => s.push);
  const requestRedirect = useMemo(
    () => eApprovalRequestUrlFromNewSubmissionQuery(searchParams),
    [searchParams],
  );
  const [search, setSearch] = useState("");
  const [viewMode, setViewMode] = useEApprovalListView(VIEW_STORAGE_KEY, "gallery");
  const [copyingFormId, setCopyingFormId] = useState<string | null>(null);

  useEffect(() => {
    if (requestRedirect) {
      router.replace(requestRedirect);
    }
  }, [requestRedirect, router]);

  const formsQuery = useQuery({
    queryKey: ["e-approval", "forms", "published-picker"],
    queryFn: () => fetchEApprovalFormsIndex({ page: 1, per_page: 100, status: "published" }),
    staleTime: 0,
    refetchOnMount: "always",
    enabled: !requestRedirect,
  });

  const publishedForms = formsQuery.data?.data ?? [];
  const filteredForms = useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return publishedForms;

    return publishedForms.filter(
      (form) =>
        form.name.toLowerCase().includes(query) ||
        (form.description ?? "").toLowerCase().includes(query) ||
        (form.category ?? "").toLowerCase().includes(query),
    );
  }, [publishedForms, search]);

  /** Tour uses Document Approval only — never Cash Advance / Liquidation as the example. */
  const formsForDisplay = useMemo(() => {
    if (!tourActive) {
      return filteredForms;
    }
    return filteredForms.filter((form) => isEApprovalDocumentApprovalFormName(form.name));
  }, [filteredForms, tourActive]);

  const openRequestForm = useCallback(
    (formId: string) => {
      const params = new URLSearchParams();
      const tour = searchParams.get("tour");
      const tourStep = searchParams.get("tourStep");
      if (tour) {
        params.set("tour", tour);
      }
      if (tourStep) {
        params.set("tourStep", tourStep);
      }
      const qs = params.toString();
      if (tourActive) {
        router.push(`${E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH}${qs ? `?${qs}` : ""}`);
        return;
      }
      router.push(`/e-approval/request/${formId}${qs ? `?${qs}` : ""}`);
    },
    [router, searchParams, tourActive],
  );

  const copyExternalMutation = useMutation({
    mutationFn: (formId: string) => fetchEApprovalFormPublicShareUrl(formId),
    onMutate: (formId) => {
      setCopyingFormId(formId);
    },
    onSuccess: async (data) => {
      try {
        await navigator.clipboard.writeText(data.public_url);
        push({
          level: "success",
          title: "External link copied",
          message: data.requires_password
            ? "Share the URL with the vendor. They will need the link password."
            : "Share this URL with the vendor or partner to fill the form.",
        });
      } catch {
        push({ level: "warning", title: "Copy failed", message: data.public_url });
      }
    },
    onError: (error) =>
      push({ level: "error", title: "Could not copy external link", message: getErrorMessage(error) }),
    onSettled: () => {
      setCopyingFormId(null);
    },
  });

  const copyExternalLink = useCallback(
    (formId: string) => {
      copyExternalMutation.mutate(formId);
    },
    [copyExternalMutation],
  );

  const submissionNewColumns = useMemo(
    () =>
      createEApprovalSubmissionNewTableColumns({
        onStart: openRequestForm,
        onCopyExternalLink: copyExternalLink,
        copyingFormId,
      }),
    [openRequestForm, copyExternalLink, copyingFormId],
  );

  const openFocusedRequestForm = (formId: string) => {
    window.open(eApprovalFocusUrl(formId, searchParams), "_blank", "noopener,noreferrer");
  };

  const renderEmpty = () =>
    tourActive ? (
      <EApprovalTourPickerFixtures />
    ) : (
      <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-muted/20 px-6 py-14 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
          <FileText className="h-6 w-6" />
        </div>
        <h2 className="mt-4 text-base font-medium">No published forms yet</h2>
        <p className="mt-1 max-w-md text-sm text-muted-foreground">
          Publish a form in{" "}
          <Link href="/e-approval/forms" className="text-primary hover:underline">
            Forms
          </Link>{" "}
          before requestors can submit requests.
        </p>
      </div>
    );

  const renderGallery = (forms: EApprovalFormListRow[]) => (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      {forms.map((form, index) => (
        <EApprovalSubmissionFormPickerCard
          key={form.id}
          form={form}
          onStart={() => openRequestForm(form.id)}
          onStartFocused={tourActive ? undefined : () => openFocusedRequestForm(form.id)}
          onCopyExternalLink={tourActive ? undefined : () => copyExternalLink(form.id)}
          copyingExternalLink={copyingFormId === form.id}
          helpTour={index === 0}
          tourNavPath={tourActive ? E_APPROVAL_TOUR_SAMPLE_COMPOSE_PATH : undefined}
        />
      ))}
    </div>
  );

  const renderTable = (forms: EApprovalFormListRow[]) => (
    <RegistryDataTableView
      columns={submissionNewColumns}
      data={forms}
      getRowId={(row) => row.id}
      isEmpty={forms.length === 0}
      emptyMessage="No published forms match this search."
      enableColumnVisibility
      columnVisibilityStorageKey="toweros.table.columns.e-approval.submission-new"
      manualSorting={false}
    />
  );

  const renderContent = () => {
    if (formsQuery.isError) {
      return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-destructive/30 bg-destructive/5 px-6 py-10 text-center">
          <p className="text-sm text-destructive">{getErrorMessage(formsQuery.error)}</p>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="mt-4"
            onClick={() => formsQuery.refetch()}
          >
            Retry
          </Button>
        </div>
      );
    }

    if (formsQuery.isFetching && publishedForms.length === 0) {
      return viewMode === "gallery" ? (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <div key={index} className="h-56 animate-pulse rounded-xl border border-border bg-muted/40" />
          ))}
        </div>
      ) : (
        <div className="h-48 animate-pulse rounded-lg bg-muted/40" />
      );
    }

    if (publishedForms.length === 0) return renderEmpty();
    if (tourActive && formsForDisplay.length === 0) {
      return <EApprovalTourPickerFixtures />;
    }
    if (formsForDisplay.length === 0) {
      return <p className="text-sm text-muted-foreground">No forms match your search.</p>;
    }

    return viewMode === "gallery" ? renderGallery(formsForDisplay) : renderTable(formsForDisplay);
  };

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalSubmissionsCreate]}>
      {requestRedirect ? (
        <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
          Opening request form…
        </div>
      ) : (
      <div className="space-y-5">
        <LiveProductTourHost />
        <EApprovalPageHeader
          title="New submission"
          description={
            <>
              <EApprovalBackLink href="/e-approval/submissions">Back to submissions</EApprovalBackLink>
              {" · "}Pick a published form to start a request, or copy an external link for vendors when available.
            </>
          }
        />

        <EApprovalListShell
          toolbar={
            <div className="flex flex-wrap items-end justify-between gap-3">
              <div data-help="ea-picker-search" className="min-w-0 flex-1">
                <label className="mb-1 block text-xs font-medium text-muted-foreground" htmlFor="ea-submission-form-search">
                  Search published forms
                </label>
                <div className="relative max-w-md">
                  <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="ea-submission-form-search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Form name, category, or description"
                    className="h-11 pl-9 text-base sm:h-9 sm:text-sm"
                  />
                </div>
              </div>
              <EApprovalListViewToggle value={viewMode} onChange={setViewMode} ariaLabel="Form picker view" />
            </div>
          }
        >
          <div className="p-4">{renderContent()}</div>
        </EApprovalListShell>

      </div>
      )}
    </PermissionGate>
  );
}
