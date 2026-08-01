---
title: Form not listed in E-Approval
slug: e-approval-form-not-listed
module: e_approval
audience: tenant_user
permissions:
  - e_approval:view
status: published
version: 1
related_routes:
  - /e-approval/submissions/new
  - /e-approval
last_reviewed: 2026-07-20
---

# Form not listed in E-Approval

Use this when the form you need (including **Document Control** / **ISO Document Control**) does not appear under **New submission**.

## Prerequisites

- E-Approval module is enabled.
- You usually need `e_approval:view` and `e_approval:submissions:create`.

## Steps

1. Open **E-Approval → New submission** and search or filter for the form name.
2. Confirm you are looking for the correct form family (Document Control / ISO vs a different workflow form).
3. If it still is not listed, ask a tenant admin to:
   - **Publish** the form for this tenant.
   - Confirm your role has access to that form.
   - Confirm you have `e_approval:submissions:create`.
4. Do **not** start a different published form as a workaround — that creates the wrong approval workflow.

## Expected result

The correct published form appears in New submission and you can start the right request.

## Common errors

- **Document Control form not listed** — form not published, wrong form family, or missing access.
- **Form not listed** — form not published, or you lack access.
- **Wrong form selected** — discard the draft and start the correct published form.

## Related workflows

- Create an E-Approval request
- Submit a Document Approval request
- Team and access
