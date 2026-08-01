---
title: Procurement-One overview
slug: procurement-one-overview
module: procurement_one
audience: tenant_user
permissions:
  - procurement_one:view
status: published
version: 1
related_routes:
  - /procurement
  - /procurement/settings
last_reviewed: 2026-07-17
---

# Procurement-One overview

Procurement-One manages sourcing and purchasing: vendors, requests for quotation (RFQ), purchase orders (PO), goods receipt, budgets, and inventory.

## Prerequisites

- Procurement-One module is enabled for your tenant.
- You have `procurement_one:view` (and additional permissions for create/manage actions).

## What you can do

1. **Vendors** — register and manage suppliers.
2. **RFQ** — request quotes from vendors and compare bids.
3. **Purchase orders** — raise POs against approved requisitions.
4. **Goods receipt (GRN)** — record deliveries against a PO.
5. **Budgets & inventory** — track budget lines and stock movements.

## Common tasks

- **Create a document**: open **Procurement**, choose the document type, and complete required fields.
- **Configure module settings**: tenant admins use **Procurement → Settings** for document types, numbering, and export policies.

## Common errors

- **Page not visible** — the module is disabled, or you lack `procurement_one:view`.
- **Cannot create** — you need the relevant create/manage permission for that document type.

## Related workflows

- E-Approval (for procurement-related approvals)
- Finance-One (for AP invoices and payments)
