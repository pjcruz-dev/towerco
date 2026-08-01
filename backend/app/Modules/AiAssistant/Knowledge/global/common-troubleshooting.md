---
title: Common troubleshooting
slug: common-troubleshooting
module: core
audience: tenant_user
permissions: []
status: published
version: 1
related_routes:
  - /dashboard
  - /notifications
last_reviewed: 2026-07-17
---

# Common troubleshooting

Quick checks for frequent workspace issues. This guide is for end users—not platform operators.

## Prerequisites

- Access to your tenant sign-in page.
- Ability to contact a tenant administrator when permissions or modules must change.

## Steps

1. **Cannot sign in**
   - Confirm tenant hostname (not the platform console URL).
   - Reset password through your tenant’s supported flow, or ask an admin.
   - Complete MFA if prompted.
2. **Page or module missing**
   - See “Permissions and why a page may be missing”.
3. **No notifications**
   - Confirm you have permission for the related module (E-Approval, rollouts, etc.).
   - Refresh the page; check Notification center filters.
4. **Upload or save failed**
   - Retry once; check required fields and file size/type.
   - If it keeps failing, note the time and ask an admin to check status.
5. **Stale data after a role change**
   - Sign out and sign in again.
6. **Wrong records**
   - Confirm filters and search terms; clear filters and retry.

## Expected result

Most issues resolve after correcting URL, permissions, MFA, or form validation. Remaining issues can be escalated to a tenant admin with clear steps to reproduce.

## Common errors

- Using the platform `/platform` host for tenant work.
- Assuming a missing button is a bug when it is a permission restriction.
- Sharing passwords instead of requesting a proper user account.

## Related workflows

- Permissions and why a page may be missing
- Getting started with TowerOS
- Command palette and navigation
