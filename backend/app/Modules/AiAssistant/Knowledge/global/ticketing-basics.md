---
title: Ticketing basics
slug: ticketing-basics
module: ticketing
audience: tenant_user
permissions:
  - ticketing:view
status: published
version: 1
related_routes:
  - /ticketing
  - /ticketing/tickets
  - /ticketing/tickets/new
last_reviewed: 2026-07-17
---

# Ticketing basics

Ticketing tracks operational work requests, issues, and follow-ups across TowerOS modules.

## Prerequisites

- Ticketing module is enabled.
- You have `ticketing:view`.
- Creating tickets requires `ticketing:tickets:create`.
- Managing / assigning tickets requires `ticketing:tickets:manage`.

## Steps

1. Open **Ticketing**.
2. To create a ticket (if allowed):
   - Choose **New ticket**.
   - Enter title and description.
   - Set category / source links when relevant.
   - Submit.
3. Open **Tickets** to search and filter your tickets.
4. Add comments or attachments on the ticket detail page when available.
5. Track status until the ticket is resolved or closed.

## Expected result

A ticket is created with a ticket number, appears in the list, and can be updated as work progresses.

## Common errors

- **New ticket not available** — missing `ticketing:tickets:create`.
- **Only see your own tickets** — expected for non-managers; managers with manage permission see more.
- **Cannot assign** — assignment usually requires `ticketing:tickets:manage`.

## Related workflows

- Check a ticket status
- Notifications
- Sites / Project-One (link context when the ticket relates to a site or rollout)
- Common troubleshooting
