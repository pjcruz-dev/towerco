"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { ArrowLeft, Pencil } from "lucide-react";

import { getErrorMessage } from "@/lib/api/error";
import { renderDynHtmlReport } from "@/lib/api/modules/dynamic-entities-api";
import { hasPermission, permissions } from "@/lib/rbac/permissions";
import { useAuthStore } from "@/stores/auth-store";

/** HTML report slugs that have a first-class React workbench. */
const WORKBENCH_BY_SLUG: Record<string, string> = {
  "aging-invoice-adjustments": "/dynamic-entities/reports/aging-invoice-adjustments",
  "inventory-stock-on-hand": "/dynamic-entities/reports/stock-on-hand",
  "ar-ap-aging": "/dynamic-entities/reports/ar-ap-aging",
  "materials-per-tower": "/dynamic-entities/reports/materials-issued",
  "tower-build-tracker": "/dynamic-entities/reports/live-tower-build-forecasts",
  "trial-balance": "/dynamic-entities/reports/trial-balance",
  "income-statement": "/dynamic-entities/reports/income-statement",
  "balance-sheet": "/dynamic-entities/reports/balance-sheet",
  "cash-flow-statement": "/dynamic-entities/reports/cash-flow",
  "monthly-management-accounts": "/dynamic-entities/reports/monthly-management-accounts",
  "bank-reconciliation-report": "/dynamic-entities/reports/bank-reconciliation",
  "permit-status-compliance": "/dynamic-entities/reports/permit-status-compliance",
  "saq-milestone-aging": "/dynamic-entities/reports/saq-milestone-aging",
  "rfti-pipeline-sla": "/dynamic-entities/reports/rfti-pipeline",
  "tax-compliance-bir": "/dynamic-entities/reports/bir-compliance",
};

export function DynHtmlReportViewPageClient() {
  const params = useParams<{ slug: string }>();
  const router = useRouter();
  const slug = params.slug;
  const user = useAuthStore((s) => s.user);
  const permissionsReady = useAuthStore((s) => s.permissionsReady);
  const canManage = hasPermission(user, [permissions.htmlReportsManage]);
  const canView = canManage || hasPermission(user, [permissions.dynamicEntitiesView]);

  const [title, setTitle] = useState("HTML Report");
  const [doc, setDoc] = useState<string | null>(null);
  const [reportId, setReportId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!permissionsReady) return;
    if (!canView) {
      router.replace("/dashboard");
    }
  }, [permissionsReady, canView, router]);

  useEffect(() => {
    if (!canView || !slug) return;

    const workbench = WORKBENCH_BY_SLUG[slug];
    if (workbench) {
      router.replace(workbench);
      return;
    }

    let cancelled = false;
    async function run() {
      setLoading(true);
      setError(null);
      try {
        const rendered = await renderDynHtmlReport(slug);
        if (cancelled) return;
        setTitle(rendered.name);
        setDoc(rendered.document_html);
        setReportId(rendered.id);
      } catch (err) {
        if (!cancelled) setError(getErrorMessage(err));
      } finally {
        if (!cancelled) setLoading(false);
      }
    }
    void run();
    return () => {
      cancelled = true;
    };
  }, [slug, canView, router]);

  if (!permissionsReady || !canView) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Loading…
      </div>
    );
  }

  if (WORKBENCH_BY_SLUG[slug]) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-sm text-muted-foreground">
        Opening workbench…
      </div>
    );
  }

  return (
    <div className="flex h-[calc(100vh-4rem)] flex-col">
      <div className="flex items-center justify-between gap-2 border-b border-border px-4 py-2">
        <div className="flex items-center gap-2 text-sm">
          {canManage ? (
            <Link
              href="/dynamic-entities/html-reports"
              className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft className="size-4" />
              Reports
            </Link>
          ) : (
            <span className="text-muted-foreground">Report</span>
          )}
          <span className="text-muted-foreground">/</span>
          <span className="font-medium text-foreground">{title}</span>
        </div>
        {canManage && reportId ? (
          <Link
            href={`/dynamic-entities/html-reports/edit?id=${reportId}`}
            className="inline-flex h-8 items-center gap-1 rounded-md border border-input px-3 text-xs font-medium hover:bg-muted"
          >
            <Pencil className="size-4" />
            Edit
          </Link>
        ) : null}
      </div>
      {error ? (
        <div className="m-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
          {error}
        </div>
      ) : null}
      {loading ? (
        <p className="py-16 text-center text-sm text-muted-foreground">Loading report…</p>
      ) : doc ? (
        <iframe title={title} srcDoc={doc} className="min-h-0 w-full flex-1 border-0 bg-white" />
      ) : null}
    </div>
  );
}
