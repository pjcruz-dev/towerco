---
title: Create an E-Approval request
slug: e-approval-create-request
module: e_approval
audience: tenant_user
permissions:
  - e_approval:submissions:create
status: published
version: 1
related_routes:
  - /e-approval
  - /e-approval/submissions
  - /e-approval/submissions/new
last_reviewed: 2026-07-17
---

# Create an E-Approval request

Use E-Approval to submit forms for review (cash advances, procurement-related forms, and other tenant workflows).

For **Document Approval / ISO Document Control** submissions specifically, follow **Submit a Document Approval request** instead of this general guide.

## Prerequisites

- E-Approval module is enabled.
- You have `e_approval:submissions:create` (and usually `e_approval:view`).
- The form you need is published.

## Steps

1. Open **E-Approval**.
2. Choose **New submission** / **Submissions → New**.
3. Select the published form you need.
4. Complete required fields. Attach files if the form allows attachments.
5. Save as draft if you need to finish later, or submit when ready.
6. Note the document number after submit and track status under **Submissions**.

## Expected result

A submission is created with a document number and moves into the configured approval workflow.

## Track your submission

1. Open **E-Approval → Submissions**.
2. Locate your request by document number or status.
3. Open it to review progress, comments, and attachments.

## Common errors

- **Form not listed** — form not published, or you lack access.
- **Cannot submit** — required fields missing or validation failed; fix highlighted fields.
- **Returned for revision** — open the submission, update answers, and resubmit.
- **Wrong form** — cancel/discard draft and start the correct published form.

## Related workflows

- Form not listed in E-Approval
- Track an E-Approval submission
- Submit a Document Approval request
- Approve an E-Approval request (approvers only)
- Document register
- Notifications
