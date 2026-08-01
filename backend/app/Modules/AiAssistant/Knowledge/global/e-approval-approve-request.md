---
title: Approve an E-Approval request
slug: e-approval-approve-request
module: e_approval
audience: tenant_user
permissions:
  - e_approval:approve
status: published
version: 1
related_routes:
  - /e-approval/approvals?awaiting_me=1
  - /e-approval/submissions
last_reviewed: 2026-07-17
---

# Approve an E-Approval request

This guide is for **approvers only**. If you need to **submit** a Document Approval or other form, use **Submit a Document Approval request** or **Create an E-Approval request** instead.

Approvers review submissions assigned to them and decide (approve, return, or reject according to policy).

## Prerequisites

- E-Approval module is enabled.
- You have `e_approval:approve`.
- A submission is waiting in your approval inbox (or you are a delegated approver).

## Steps

1. Open **E-Approval → Approvals** (Awaiting me).
2. Or open the item from **Notifications**.
3. Review form answers, attachments, and prior comments.
4. Decide:
   - Approve to advance the workflow.
   - Return / reject with clear remarks when the request needs changes or cannot proceed.
5. Confirm the decision and verify the submission status updates.

## Expected result

The approval step is recorded, the submitter is notified, and the workflow moves to the next step or completes.

## Common errors

- **Empty inbox** — nothing assigned to you; check filters or delegation.
- **Decision buttons missing** — you are not the current approver, or you lack `e_approval:approve`.
- **Cannot open attachment** — permission or file availability issue; ask the requestor to re-attach if needed.

## Related workflows

- Submit a Document Approval request
- Create an E-Approval request
- Notifications
- Common troubleshooting
