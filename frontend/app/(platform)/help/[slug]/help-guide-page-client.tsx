"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";

import { HelpMarkdown } from "@/components/help/help-markdown";
import { PermissionGate } from "@/components/layout/permission-gate";
import { buttonVariants } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { fetchPublishedHelpGuide } from "@/lib/api/modules/help-guides-api";
import { stripLeadingMarkdownH1 } from "@/lib/help/strip-leading-markdown-h1";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

export function HelpGuidePageClient({ slug }: { slug: string }) {
  const query = useQuery({
    queryKey: ["help", "guides", slug],
    queryFn: () => fetchPublishedHelpGuide(slug),
  });

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalView]}>
      <div className="mx-auto max-w-3xl space-y-6">
        <div className="flex flex-wrap items-center gap-2">
          <Link href="/help" className={cn(buttonVariants({ variant: "outline", size: "sm" }))}>
            All guides
          </Link>
        </div>

        {query.isLoading ? <p className="text-sm text-muted-foreground">Loading guide…</p> : null}
        {query.isError ? (
          <p className="text-sm text-destructive">
            {getErrorMessage(query.error) || "This guide is not available."}
          </p>
        ) : null}

        {query.data ? (
          <article className="rounded-xl border border-border bg-card p-5 shadow-sm md:p-8">
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">{query.data.title}</h1>
            <div className="mt-6">
              <HelpMarkdown content={stripLeadingMarkdownH1(query.data.body)} />
            </div>
          </article>
        ) : null}
      </div>
    </PermissionGate>
  );
}
