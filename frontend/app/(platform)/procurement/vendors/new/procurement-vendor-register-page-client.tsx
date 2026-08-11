"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";

import { EApprovalSubmissionComposePanel } from "@/components/e-approval/e-approval-submission-compose-panel";
import { OperationalAlert } from "@/components/feedback/operational-alert";
import { PROCUREMENT_SIMPLE_FORM_SHELL_CLASS } from "@/components/procurement-one/procurement-compose-layout";
import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { fetchProcurementVendorFormSchema } from "@/lib/api/modules/procurement-one-api";
import { permissions } from "@/lib/rbac/permissions";

export function ProcurementVendorRegisterPageClient() {
  const router = useRouter();

  const schemaQuery = useQuery({
    queryKey: ["procurement-one", "vendors", "form-schema"],
    queryFn: () => fetchProcurementVendorFormSchema(),
    staleTime: 60_000,
  });

  const formId = schemaQuery.data?.form?.id ?? null;

  if (schemaQuery.isLoading) {
    return <SectionCardSkeleton />;
  }

  if (!formId) {
    return (
      <PermissionGate requiredPermissions={[permissions.procurementOneVendorsManage]}>
        <div className={PROCUREMENT_SIMPLE_FORM_SHELL_CLASS}>
          <ProcurementOnePageHeader
            eyebrow={
              <Link href="/procurement/vendors" className="hover:text-primary">
                Vendors
              </Link>
            }
            title="Register vendor"
            description="Vendor intake is driven by your published E-Approval vendor registration form."
          />
          <OperationalAlert
            level="warning"
            title="No published vendor registration form"
            description="Publish a form with form family vendor_registration in E-Approval before registering suppliers here."
            actions={
              <Button size="sm" variant="outline" render={<Link href="/e-approval/forms" />}>
                Open E-Approval forms
              </Button>
            }
          />
        </div>
      </PermissionGate>
    );
  }

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneVendorsManage]}>
      <div className={PROCUREMENT_SIMPLE_FORM_SHELL_CLASS}>
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement/vendors" className="hover:text-primary">
              Vendors
            </Link>
          }
          title="Register vendor"
          description="Submit a vendor registration for approval. Accredited vendors appear in this registry after final approval."
        />

        <EApprovalSubmissionComposePanel
          formId={formId}
          fullPage
          shellClassName="max-w-none"
          notifyOnSuccess
          onCancel={() => router.push("/procurement/vendors")}
          onSubmitted={({ submission }) => router.push(`/e-approval/submissions/${submission.id}`)}
        />
      </div>
    </PermissionGate>
  );
}
