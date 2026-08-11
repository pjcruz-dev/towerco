import type { ProcurementGrnDetail } from "@/modules/procurement-one/types";
import { buildProcurementGrnMismatchTicketPrefill } from "@/lib/procurement-one/ticket-prefill";
import { buildRaiseTicketUrl } from "@/lib/ticketing/raise-ticket";

export {
  buildProcurementGrnMismatchTicketPrefill,
  buildProcurementGrnTicketPrefill,
  grnHasReceiptMismatches,
} from "@/lib/procurement-one/ticket-prefill";

export function buildProcurementGrnMismatchTicketUrl(grn: ProcurementGrnDetail): string {
  return buildRaiseTicketUrl(buildProcurementGrnMismatchTicketPrefill(grn));
}
