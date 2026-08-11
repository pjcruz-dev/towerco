import type {
  ProcurementApInvoiceDetail,
  ProcurementGrnDetail,
  ProcurementGrnMismatch,
  ProcurementPoDetail,
  ProcurementPrDetail,
  ProcurementVendorDetail,
} from "@/modules/procurement-one/types";
import type { RaiseTicketLinkInput, RaiseTicketPrefill } from "@/lib/ticketing/raise-ticket";

const SOURCE_MODULE = "procurement_one";

function procurementLink(
  linkType: string,
  linkId: string,
  linkLabel?: string,
): RaiseTicketLinkInput {
  return {
    link_module: SOURCE_MODULE,
    link_type: linkType,
    link_id: linkId,
    link_label: linkLabel,
  };
}

function eApprovalLink(submissionId: string, label?: string): RaiseTicketLinkInput {
  return {
    link_module: "e_approval",
    link_type: "submission",
    link_id: submissionId,
    link_label: label,
  };
}

export function buildProcurementPrTicketPrefill(pr: ProcurementPrDetail): RaiseTicketPrefill {
  const label = pr.document_no ?? pr.title;
  const links = [procurementLink("purchase_requisition", pr.id, label)];
  if (pr.e_approval_submission_id) {
    links.push(eApprovalLink(pr.e_approval_submission_id, pr.document_no ?? undefined));
  }

  return {
    title: `PR follow-up: ${label}`,
    description: [
      `Purchase requisition ${label}`,
      pr.title ? `Title: ${pr.title}` : null,
      pr.department ? `Department: ${pr.department}` : null,
    ]
      .filter(Boolean)
      .join("\n"),
    source_module: SOURCE_MODULE,
    source_reference_type: "purchase_requisition",
    source_reference_id: pr.id,
    source_label: label,
    category: "procurement_general",
    links,
  };
}

export function isPoDeliveryDelayed(po: Pick<ProcurementPoDetail, "delivery_date" | "status">): boolean {
  if (!po.delivery_date) {
    return false;
  }

  if (!["approved", "sent", "partially_received"].includes(po.status)) {
    return false;
  }

  const delivery = new Date(`${po.delivery_date}T00:00:00`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  return delivery < today;
}

export function buildProcurementPoTicketPrefill(
  po: ProcurementPoDetail,
  options?: { deliveryDelay?: boolean },
): RaiseTicketPrefill {
  const label = po.document_no ?? po.id;
  const links = [procurementLink("purchase_order", po.id, label)];
  if (po.e_approval_submission_id) {
    links.push(eApprovalLink(po.e_approval_submission_id, po.document_no ?? undefined));
  }

  const deliveryDelay = options?.deliveryDelay ?? isPoDeliveryDelayed(po);

  return {
    title: deliveryDelay ? `Delivery delay: ${label}` : `PO follow-up: ${label}`,
    description: [
      `Purchase order ${label}`,
      po.vendor_name || po.vendor_code ? `Vendor: ${po.vendor_name ?? po.vendor_code}` : null,
      po.delivery_date ? `Expected delivery: ${po.delivery_date}` : null,
      deliveryDelay ? "Delivery date has passed and goods are not fully received." : null,
    ]
      .filter(Boolean)
      .join("\n"),
    source_module: SOURCE_MODULE,
    source_reference_type: "purchase_order",
    source_reference_id: po.id,
    source_label: label,
    category: deliveryDelay ? "procurement_delivery_delay" : "procurement_general",
    links,
  };
}

export function buildProcurementGrnTicketPrefill(grn: ProcurementGrnDetail): RaiseTicketPrefill {
  const label = grn.document_no ?? grn.id;
  const links = [procurementLink("goods_receipt", grn.id, label)];
  if (grn.po_id) {
    links.push(procurementLink("purchase_order", grn.po_id, grn.po_document_no ?? undefined));
  }

  return {
    title: `GRN follow-up: ${label}`,
    description: [
      `Goods receipt ${label}`,
      grn.po_document_no ? `PO: ${grn.po_document_no}` : null,
    ]
      .filter(Boolean)
      .join("\n"),
    source_module: SOURCE_MODULE,
    source_reference_type: "goods_receipt",
    source_reference_id: grn.id,
    source_label: label,
    category: "procurement_general",
    links,
  };
}

export function buildProcurementGrnMismatchTicketPrefill(
  grn: ProcurementGrnDetail,
  mismatches?: ProcurementGrnMismatch[],
): RaiseTicketPrefill {
  const rows = mismatches ?? grn.mismatches ?? [];
  const summary = rows.map((row) => row.message).join("\n");
  const label = grn.document_no ?? grn.id;
  const links = [procurementLink("goods_receipt", grn.id, label)];
  if (grn.po_id) {
    links.push(procurementLink("purchase_order", grn.po_id, grn.po_document_no ?? undefined));
  }

  return {
    title: `GRN receipt mismatch: ${label}`,
    description: [
      `Goods receipt ${label} for PO ${grn.po_document_no ?? grn.po_id}.`,
      summary !== "" ? `Issues:\n${summary}` : "Quantity mismatch detected during goods receipt.",
    ].join("\n\n"),
    source_module: SOURCE_MODULE,
    source_reference_type: "goods_receipt",
    source_reference_id: grn.id,
    source_label: label,
    category: "procurement_grn_mismatch",
    links,
  };
}

export function buildProcurementApInvoiceTicketPrefill(invoice: ProcurementApInvoiceDetail): RaiseTicketPrefill {
  const label = invoice.document_no ?? invoice.id;
  const links = [procurementLink("ap_invoice", invoice.id, label)];
  if (invoice.po_id) {
    links.push(procurementLink("purchase_order", invoice.po_id, invoice.po_document_no ?? undefined));
  }

  return {
    title: `AP invoice follow-up: ${label}`,
    description: [
      `AP invoice ${label}`,
      invoice.vendor_invoice_no ? `Vendor invoice: ${invoice.vendor_invoice_no}` : null,
      invoice.po_document_no ? `PO: ${invoice.po_document_no}` : null,
    ]
      .filter(Boolean)
      .join("\n"),
    source_module: SOURCE_MODULE,
    source_reference_type: "ap_invoice",
    source_reference_id: invoice.id,
    source_label: label,
    category: "procurement_invoice_dispute",
    links,
  };
}

export function buildProcurementVendorTicketPrefill(vendor: ProcurementVendorDetail): RaiseTicketPrefill {
  const label = vendor.vendor_code ?? vendor.company_name;

  return {
    title: `Vendor issue: ${vendor.company_name}`,
    description: [
      `Vendor ${vendor.company_name} (${vendor.vendor_code})`,
      vendor.accreditation_status ? `Accreditation: ${vendor.accreditation_status_label ?? vendor.accreditation_status}` : null,
    ]
      .filter(Boolean)
      .join("\n"),
    source_module: SOURCE_MODULE,
    source_reference_type: "vendor",
    source_reference_id: vendor.id,
    source_label: label,
    category: "procurement_vendor_issue",
    links: [procurementLink("vendor", vendor.id, vendor.company_name)],
  };
}

export function grnHasReceiptMismatches(grn: ProcurementGrnDetail): boolean {
  return (grn.mismatches?.length ?? 0) > 0 || Boolean(grn.receipt_warning);
}
