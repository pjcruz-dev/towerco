---
title: Purchase order (PO) workflow
slug: procurement-po-workflow
module: procurement_one
audience: tenant_user
permissions:
  - procurement_one:view
status: published
version: 1
related_routes:
  - /procurement/pos
  - /procurement
last_reviewed: 2026-07-24
---

# Purchase order (PO) workflow

A purchase order commits your organisation to buy goods or services from a vendor at agreed prices. POs are raised against approved requisitions and then tracked to delivery.

## Prerequisites

- Procurement-One is enabled for your tenant.
- You have `procurement_one:view` to open POs, and the relevant create/manage permission to raise or edit them.

## Create a purchase order

1. Open **Procurement → Purchase orders**.
2. Choose **New PO** and select the vendor.
3. Add line items (description, quantity, unit price) — totals calculate automatically.
4. Set delivery date and any terms, then save. Depending on tenant settings the PO may route through E-Approval before it can be sent.

## PO lifecycle

1. **Draft** — editable; not yet committed.
2. **Approved** — passed E-Approval (if required).
3. **Sent** — issued to the vendor.
4. **Partially received / Received** — goods recorded via Goods Receipt (GRN).
5. **Closed / Cancelled** — completed or withdrawn.

## Track delivery and issues

- The PO detail page shows delivery status and any linked GRNs.
- If a delivery is late, the PO shows a **delivery delay** banner. Use **Raise ticket** to open a tracked issue (pre-filled with the PO reference) so operations can follow up with the vendor.
- Tickets raised from a PO appear in the **Related tickets** panel on the PO.

## Common errors

- **Cannot send PO** — it may still be in draft or awaiting approval.
- **Totals look wrong** — check line item quantities and unit prices; taxes follow tenant settings.

## Related workflows

- Goods receipt (GRN) — record deliveries against the PO.
- E-Approval — approve POs above threshold.
- Ticketing — raise and track delivery or vendor issues.
