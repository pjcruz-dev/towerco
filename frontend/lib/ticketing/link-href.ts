import type { TicketingLinkRow } from "@/modules/ticketing/types";

export function ticketingLinkHref(link: Pick<TicketingLinkRow, "link_module" | "link_type" | "link_id">): string | null {
  if (link.link_module === "e_approval" && link.link_type === "submission") {
    return `/e-approval/submissions/${link.link_id}`;
  }

  if (link.link_module === "project_one") {
    if (link.link_type === "rollout") {
      return `/project-one/rollouts/${link.link_id}`;
    }
    if (link.link_type === "project") {
      return `/project-one/projects/${link.link_id}`;
    }
  }

  if (link.link_module === "sites" && link.link_type === "site") {
    return `/sites/${link.link_id}`;
  }

  if (link.link_module === "tower_one" && link.link_type === "tower") {
    return `/tower-one/towers/${link.link_id}`;
  }

  if (link.link_module === "asset_one" && link.link_type === "asset") {
    return `/asset-one/assets/${link.link_id}`;
  }

  if (link.link_module === "procurement_one") {
    if (link.link_type === "purchase_requisition") {
      return `/procurement/prs/${link.link_id}`;
    }
    if (link.link_type === "purchase_order") {
      return `/procurement/pos/${link.link_id}`;
    }
    if (link.link_type === "goods_receipt") {
      return `/procurement/grns/${link.link_id}`;
    }
    if (link.link_type === "ap_invoice") {
      return `/procurement/ap-invoices/${link.link_id}`;
    }
    if (link.link_type === "vendor") {
      return `/procurement/vendors/${link.link_id}`;
    }
  }

  return null;
}
