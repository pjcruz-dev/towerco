---
title: Submit a Document Approval request
slug: document-approval-submit-request
module: e_approval
audience: tenant_user
permissions:
  - e_approval:submissions:create
status: published
version: 1
related_routes:
  - /e-approval/submissions/new
  - /e-approval/submissions
  - /documents/controlled
last_reviewed: 2026-07-17
---

# Submit a Document Approval request

Use this when you need to **submit** a Document Approval / ISO Document Control request (new controlled document or revision). Approvers use a different flow.

## Prerequisites

- E-Approval module is enabled.
- You have `e_approval:submissions:create` (and usually `e_approval:view`).
- The Document Control / ISO Document Control form is published for your tenant.
- Optional: Document register enabled with `documents:controlled:view` if you need the document code or current revision first.

## Steps

1. Optional — open **Document register**, search by document code or title, and note the current revision if you are revising an existing controlled document.
2. Open **E-Approval → New submission** (or **Submissions → New**).
3. Select the published **Document Control** / **ISO Document Control** form (not a different E-Approval form).
4. Complete required fields. Attach the file(s) if the form allows attachments.
5. Save as draft if you need to finish later, or **Submit** when ready.
6. Note the document number and track status under **E-Approval → Submissions**.

## Expected result

A Document Approval submission is created with a document number and enters the configured approval workflow. Approvers act from **E-Approval → Approvals**.

## Track your submission

1. Open **E-Approval → Submissions**.
2. Find your request by document number, title, form, or status (draft, in review, approved, returned).
3. Open the submission to see workflow progress, comments, attachments, and current approver step.
4. Optional — check **Notifications** for approval updates on requests you submitted.

## Common errors

- **Document Control form not listed** — form not published, wrong form family, or missing access.
- **Cannot submit** — required fields missing or validation failed; fix highlighted fields.
- **Wrong form selected** — discard the draft and start the Document Control / ISO form.
- **Returned for revision** — open the submission, update answers/files, and resubmit.

## Related workflows

- Form not listed in E-Approval
- Track an E-Approval submission
- Create an E-Approval request (general forms)
- Document register (controlled documents)
- Approve an E-Approval request (approvers only)
