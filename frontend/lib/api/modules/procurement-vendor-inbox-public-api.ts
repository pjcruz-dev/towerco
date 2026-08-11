import { publicTenantApiClient } from "@/lib/api/public-tenant-client";

export type ProcurementVendorInboxItem = {
  invitation_id: string;
  rfq_id: string;
  document_no: string | null;
  title: string | null;
  status: string | null;
  status_label: string | null;
  invitation_status: string;
  invited_at: string | null;
  responded_at: string | null;
  bidding_closes_at: string | null;
  can_quote: boolean;
  quote_url: string | null;
};

export type ProcurementVendorInboxPayload = {
  vendor: {
    id: string;
    company_name: string | null;
    vendor_code: string | null;
  };
  items: ProcurementVendorInboxItem[];
};

export async function fetchProcurementVendorInbox(accessToken: string): Promise<ProcurementVendorInboxPayload> {
  const response = await publicTenantApiClient.get<{ data: ProcurementVendorInboxPayload }>(
    `/public/procurement/vendor-inbox/${encodeURIComponent(accessToken)}`,
  );
  return response.data.data;
}
