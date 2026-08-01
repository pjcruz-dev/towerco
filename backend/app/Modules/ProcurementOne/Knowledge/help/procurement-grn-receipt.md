---
title: Goods receipt (GRN) and mismatches
slug: procurement-grn-receipt
module: procurement_one
audience: tenant_user
permissions:
  - procurement_one:view
status: published
version: 1
related_routes:
  - /procurement/grns
  - /procurement/pos
last_reviewed: 2026-07-24
---

# Goods receipt (GRN) and mismatches

A goods receipt note (GRN) records what was actually delivered against a purchase order. It is how the system knows a PO is partially or fully received.

## Prerequisites

- Procurement-One is enabled for your tenant.
- You have `procurement_one:view` to open GRNs, and the relevant create/manage permission to record receipts.

## Record a receipt

1. Open the purchase order, or go to **Procurement → Goods receipt**.
2. Choose **New GRN** against the PO.
3. For each line, enter the **quantity received** (this can be less than ordered for a partial delivery).
4. Note any damage or discrepancy, then save. The PO status updates to **Partially received** or **Received**.

## Handling a mismatch

A mismatch is when the received quantity or item does not match the PO (short delivery, over delivery, wrong item, or damage).

1. Record the actual quantities on the GRN so stock stays accurate.
2. On the GRN detail page, a **mismatch alert** appears with a CTA.
3. Use **Raise ticket** on the mismatch alert to open a tracked issue. It is pre-filled with the GRN reference and the `Procurement — GRN mismatch` category so the right team picks it up.
4. Tickets raised from the GRN show in the **Related tickets** panel on the GRN.

## Common errors

- **Cannot create GRN** — the PO may not be approved/sent yet, or you lack receipt permission.
- **PO still shows outstanding** — remaining quantities have not been received; record another GRN when the balance arrives.

## Related workflows

- Purchase order (PO) — the order a GRN receives against.
- Ticketing — raise and track GRN mismatches and vendor issues.
