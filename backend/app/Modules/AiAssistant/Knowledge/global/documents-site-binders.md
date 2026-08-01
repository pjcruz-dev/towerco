---
title: Documents and site binders
slug: documents-site-binders
module: documents
audience: tenant_user
permissions:
  - documents:view
status: published
version: 1
related_routes:
  - /documents
last_reviewed: 2026-07-17
---

# Documents and site binders

Documents stores site binders: leases, permits, contracts, and other files tied to a site, including expiry tracking.

## Prerequisites

- Documents module is enabled.
- You have `documents:view`.
- Upload or manage actions also need `documents:upload` or `documents:manage`.

## Steps

1. Open **Documents** → **Site binders**.
2. Select or search for the site.
3. Review binder sections and existing files.
4. Upload a file when you have upload permission:
   - Choose the correct document category/section.
   - Attach the file and complete required metadata (for example expiry dates).
5. Open a file to view details, versions, or request approval when that action is available.

## Expected result

The site binder shows organized documents, and new uploads appear under the correct section with the metadata you entered.

## Common errors

- **Upload button missing** — you need `documents:upload` or `documents:manage`.
- **Presign / upload failed** — retry; check file size and type restrictions.
- **Wrong site binder** — confirm site code before uploading.
- **Template settings not visible** — binder template management needs `documents:template:manage`.

## Related workflows

- Document register (controlled / ISO documents)
- E-Approval (when a document needs formal approval)
- Sites overview
- PROJECT-ONE gate approvals (site binder checklist)

## Lease package ↔ site binder

Lease documents uploaded on a rollout SAQ **lease package** are separate from the site binder until they are copied:

1. Link the rollout on **Sites → [site] → Site binder → Linked rollout**.
2. When a site candidate is selected (Documents + Sites enabled), lease files auto-copy into the binder.
3. Or use **Import lease package** on the site binder to copy on demand.

**Important:** Candidate photos and other gate/SAQ uploads stay on the rollout. Only **final** documents in required binder folders (for example SAQ Phase 1, COL, Affidavit) satisfy gate pass checks. Uploading evidence on the gate form does not fill those folders.