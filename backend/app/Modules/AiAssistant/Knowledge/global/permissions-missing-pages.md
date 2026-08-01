---
title: Permissions and why a page may be missing
slug: permissions-missing-pages
module: core
audience: tenant_user
permissions: []
status: published
version: 1
related_routes:
  - /dashboard
  - /users/roles
last_reviewed: 2026-07-17
---

# Permissions and why a page may be missing

If a menu item, button, or page is missing, TowerOS is usually hiding it because of module enablement or your role permissions.

## Prerequisites

- You can sign in.
- For role changes, a tenant admin with Team & Access access is required.

## Steps

1. Confirm you are on the correct tenant URL.
2. Check whether the module appears for colleagues who do similar work.
3. Ask a tenant admin to verify:
   - The module is enabled for the tenant.
   - Your roles include the needed permission (for example `ticketing:view`).
4. Sign out and back in after role changes so permissions refresh.
5. Retry the page from the sidebar or command palette.

## Expected result

After the correct module enablement and role assignment, the page and actions appear for your user.

## Common errors

- **Module enabled but still hidden** — permission missing on your role.
- **Can view but cannot create** — create/manage permission not granted.
- **Works for admin only** — `tenant_admin` has broad access; assign a narrower role that still includes the needed permission.
- **Page opens then forbids an action** — navigation permission and action permission can differ.

## Related workflows

- Team and Access for workspace users
- Common troubleshooting
- Getting started with TowerOS
