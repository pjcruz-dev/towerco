import { publicTenantApiClient } from "@/lib/api/public-tenant-client";

export type ProcurementRfqPublicQuoteLine = {
  id: string;
  line_order: number;
  description: string;
  uom: string | null;
  quantity: number;
  quote_basis?: string;
  quote_basis_label?: string;
};

export type ProcurementRfqPublicQuotePayload = {
  rfq: {
    id: string;
    document_no: string | null;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    currency_code: string;
    bidding_opens_at: string | null;
    bidding_closes_at: string | null;
    lines_source?: "purchase_requisition" | "rfq";
    lines: ProcurementRfqPublicQuoteLine[];
  };
  vendor: {
    id: string;
    company_name: string | null;
    vendor_code: string | null;
  };
  invitation: {
    status: string;
    invited_at: string | null;
    responded_at: string | null;
    portal_contact_name: string | null;
  };
  existing_bid: {
    id: string;
    total_amount: number;
    currency_code: string;
    submitted_at: string | null;
    lines: Array<{
      rfq_line_id: string;
      quantity: number;
      unit_price: number;
      monthly_unit_price?: number | null;
      yearly_unit_price?: number | null;
      lead_time_days: number | null;
      notes: string | null;
    }>;
  } | null;
  has_existing_bid: boolean;
  can_submit: boolean;
  can_revise: boolean;
  submission_blocked_reason?: string | null;
};

export async function fetchProcurementRfqPublicQuote(accessToken: string): Promise<ProcurementRfqPublicQuotePayload> {
  const response = await publicTenantApiClient.get<{ data: ProcurementRfqPublicQuotePayload }>(
    `/public/procurement/rfq-quotes/${encodeURIComponent(accessToken)}`,
  );
  return response.data.data;
}

export async function submitProcurementRfqPublicQuote(
  accessToken: string,
  payload: {
    contact_name: string;
    validity_until?: string;
    notes?: string;
    lines: Array<{
      rfq_line_id: string;
      quantity: number;
      unit_price?: number;
      monthly_unit_price?: number;
      yearly_unit_price?: number;
      lead_time_days?: number;
      notes?: string;
    }>;
  },
  files?: File[],
): Promise<{ bid: ProcurementRfqPublicQuotePayload["existing_bid"]; message: string }> {
  if (files && files.length > 0) {
    const formData = new FormData();
    formData.append("contact_name", payload.contact_name);
    if (payload.notes) formData.append("notes", payload.notes);
    if (payload.validity_until) formData.append("validity_until", payload.validity_until);
    payload.lines.forEach((line, index) => {
      formData.append(`lines[${index}][rfq_line_id]`, line.rfq_line_id);
      formData.append(`lines[${index}][quantity]`, String(line.quantity));
      if (line.unit_price != null) formData.append(`lines[${index}][unit_price]`, String(line.unit_price));
      if (line.monthly_unit_price != null) {
        formData.append(`lines[${index}][monthly_unit_price]`, String(line.monthly_unit_price));
      }
      if (line.yearly_unit_price != null) {
        formData.append(`lines[${index}][yearly_unit_price]`, String(line.yearly_unit_price));
      }
      if (line.lead_time_days != null) formData.append(`lines[${index}][lead_time_days]`, String(line.lead_time_days));
      if (line.notes) formData.append(`lines[${index}][notes]`, line.notes);
    });
    files.forEach((file) => formData.append("attachments[]", file));

    const response = await publicTenantApiClient.post<{
      data: { bid: ProcurementRfqPublicQuotePayload["existing_bid"]; message: string };
    }>(`/public/procurement/rfq-quotes/${encodeURIComponent(accessToken)}/bids`, formData);
    return response.data.data;
  }

  const response = await publicTenantApiClient.post<{
    data: { bid: ProcurementRfqPublicQuotePayload["existing_bid"]; message: string };
  }>(`/public/procurement/rfq-quotes/${encodeURIComponent(accessToken)}/bids`, payload);
  return response.data.data;
}
