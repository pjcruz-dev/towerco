const SUBMISSION_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export type CashAdvanceBlockLink = {
  href: string;
  label: string;
};

/** Link target from the cash-advance block error, or null when the payload is not that error. */
export function cashAdvanceBlockLinkFromFields(
  fields: Record<string, string>,
): CashAdvanceBlockLink | null {
  const id = fields._link_submission_id?.trim() ?? "";
  const kind = fields._link_kind?.trim() ?? "";
  const documentNo = fields._link_document_no?.trim() ?? "";
  if (!SUBMISSION_ID.test(id)) {
    return null;
  }

  const named = documentNo !== "" ? ` ${documentNo}` : "";
  if (kind === "liquidation") {
    return { href: `/e-approval/submissions/${id}`, label: `Open pending liquidation${named}` };
  }
  if (kind === "cash_advance") {
    return {
      href: `/e-approval/submissions/${id}`,
      label: `Open cash advance pending liquidation${named}`,
    };
  }
  if (kind === "for_return") {
    return { href: `/e-approval/submissions/${id}`, label: `Open cash advance for return${named}` };
  }

  return null;
}
