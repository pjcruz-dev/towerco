---
title: Check a ticket status
slug: ticketing-check-status
module: ticketing
audience: tenant_user
permissions:
  - ticketing:view
status: published
version: 1
related_routes:
  - /ticketing
  - /ticketing/tickets
last_reviewed: 2026-07-20
---

# Check a ticket status

Use this when you have a ticket number (for example **TKT-00004**) and want its current status.

## Prerequisites

- Ticketing module is enabled.
- You have `ticketing:view`.
- Non-managers usually only see tickets they requested or are assigned to.

## Steps

1. Open **Ticketing → Tickets**.
2. Search by ticket number (e.g. `TKT-00004`) or title.
3. Open the ticket to see status, priority, assignee, and comments.
4. You can also ask Ask TowerOS: “What is the status of TKT-00004?”

## Expected result

You see whether the ticket is open, in progress, resolved, or closed, and who owns it.

## Common errors

- **Ticket not found** — wrong number, or you do not have access (not requester/assignee and not a manager).
- **Only see your own tickets** — expected without `ticketing:tickets:manage`.

## Related workflows

- Ticketing basics
- Notifications
