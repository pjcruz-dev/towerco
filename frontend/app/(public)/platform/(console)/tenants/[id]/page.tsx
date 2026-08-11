import { PlatformTenantDetailPageClient } from "@/app/(public)/platform/(console)/tenants/[id]/platform-tenant-detail-page-client";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function PlatformTenantDetailPage({ params }: Props) {
  const { id } = await params;
  return <PlatformTenantDetailPageClient tenantId={id} />;
}
