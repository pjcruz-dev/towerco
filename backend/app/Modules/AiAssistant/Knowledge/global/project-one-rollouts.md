---
title: Project-One and rollouts
slug: project-one-rollouts
module: project_one
audience: tenant_user
permissions:
  - project_one:view
status: published
version: 1
related_routes:
  - /project-one
  - /project-one/rollouts
  - /project-one/projects
  - /project-one/gate-approvals?awaiting_me=1
last_reviewed: 2026-07-17
---

# Project-One and rollouts

Project-One manages projects and rollout programs (search rings, phases, and gate approvals) for site delivery work.

## Prerequisites

- Project-One module is enabled.
- You have `project_one:view` (and `project_one:rollout:view` for rollouts).
- Gate decisions may require `project_one:rollout:gate:approve`.

## Steps

1. Open **Project-One** for the overview.
2. Open **Rollouts** to find a rollout by reference or search ring name.
3. Open a rollout to review status, phases, and linked site candidates.
4. Use **Gate approvals** (Awaiting me) when you must approve a phase gate.
5. Open **Projects** for project-level records linked to sites when needed.

## Expected result

You can locate a rollout or project, understand its status, and complete gate actions assigned to you.

## Common errors

- **Rollouts menu missing** — need `project_one:rollout:view` or module not enabled.
- **Cannot edit rollout** — manage permissions required (`project_one:rollout:manage` / related).
- **Gate decision unavailable** — you are not the current approver, or lack gate approve permission.

## Related workflows

- Sites overview
- Documents (lease package / site binders)
- E-Approval (when forms are used alongside rollout gates)
- Notifications
