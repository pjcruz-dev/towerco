---
title: Document register (controlled documents)
slug: document-register
module: document_register
audience: tenant_user
permissions:
  - documents:controlled:view
status: published
version: 1
related_routes:
  - /documents/controlled
last_reviewed: 2026-07-17
---

# Document register (controlled documents)

The document register is the ISO-style master list of controlled documents. Use it to find the approved revision. To **submit** a new controlled document or revision for approval, use **Submit a Document Approval request** (E-Approval Document Control form).

## Prerequisites

- Document register module is enabled.
- You have `documents:controlled:view`.
- Creating or managing controlled documents may require additional controlled-document permissions.

## Steps

1. Open **Document register**.
2. Search by document code or title.
3. Open a controlled document to see status, current revision, and department.
4. If you need a new document or revision, start **E-Approval → New submission** and choose the Document Control / ISO form.
5. Download or stream the published revision only when your role allows it.

## Expected result

You locate the correct controlled document and know which revision is current and whether it is published or obsolete.

## Common errors

- **Register missing from sidebar** — module disabled or missing `documents:controlled:view`.
- **Cannot create / import** — needs create, manage, or import permissions.
- **Obsolete document** — do not use obsolete revisions for operational work; request a new revision if needed.

## Related workflows

- Submit a Document Approval request
- Documents and site binders
- Approve an E-Approval request (approvers only)
