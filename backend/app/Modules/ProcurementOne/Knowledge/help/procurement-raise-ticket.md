---
title: Raise a ticket from procurement
slug: procurement-raise-ticket
module: procurement_one
audience: tenant_user
permissions:
  - procurement_one:view
status: published
version: 1
related_routes:
  - /procurement/pos
  - /procurement/grns
  - /procurement/vendors
last_reviewed: 2026-07-24
---

# Raise a ticket from procurement

When a delivery is late, an invoice is disputed, or a receipt does not match the order, raise a ticket directly from the procurement record so the issue is tracked and linked back to its source.

## Prerequisites

- Procurement-One and Ticketing are both enabled for your tenant.
- You have `procurement_one:view` and `ticketing:tickets:create`.

## How to raise a ticket

1. Open the relevant record — a **purchase order**, **goods receipt (GRN)**, **AP invoice**, or **vendor**.
2. Use the **Raise ticket** button in the header, or the specific CTA on a delivery-delay or mismatch alert.
3. The new-ticket form opens pre-filled with:
   - a title and description referencing the source record,
   - a suggested **category** (for example *Procurement — delivery delay* or *Procurement — GRN mismatch*),
   - a link back to the source record.
4. Adjust details if needed, then submit. The ticket is created and linked to the source.

## Where linked tickets appear

- Each procurement record shows a **Related tickets** panel listing tickets raised from it.
- On the ticket, a link takes you back to the source PO, GRN, invoice, or vendor.

## Categories

If the suggested category is not available, a tenant admin can apply the **Procurement-One category pack** in **Ticketing → Settings**, which adds delivery delay, vendor issue, invoice dispute, GRN mismatch, approval delay, contract, and general categories.

## Related workflows

- Purchase order (PO) workflow — delivery tracking.
- Goods receipt (GRN) and mismatches — recording deliveries.
- Ticketing basics — statuses, assignment, and SLA.
