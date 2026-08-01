---
title: Team and Access for workspace users
slug: team-and-access
module: team_access
audience: tenant_user
permissions:
  - user:manage
status: published
version: 1
related_routes:
  - /users
  - /users/roles
last_reviewed: 2026-07-17
---

# Team and Access for workspace users

Team & Access lets tenant administrators manage users and roles inside your workspace (not platform / TowerOS operator consoles).

## Prerequisites

- You are a tenant administrator or have `user:manage` / `role:manage`.
- This guide is for tenant workspace administration only.

## Steps

1. Open **Team & Access → Users**.
2. Invite or create a user with name, email, and initial role.
3. Assign the smallest role set the person needs (module viewer / contributor / approver tiers).
4. Open **Roles & permissions** to review what each role can do.
5. Deactivate users who leave instead of sharing accounts.
6. Use session revoke actions when a device or session must be ended immediately (if available to your role).

## Expected result

Users can sign in with the correct roles and only see modules and actions their permissions allow.

## Common errors

- **User cannot see a module** — role lacks permission, or the module is disabled for the tenant.
- **Too many permissions** — prefer module-specific roles over broad admin roles.
- **Impersonation unavailable** — requires `user:impersonate` and policy controls.

## Related workflows

- Permissions and why a page may be missing
- Getting started with TowerOS
