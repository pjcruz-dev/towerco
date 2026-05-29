# Rollout policy & approvals — roadmap

Locked decisions from product review:

- **One rollout policy bundle** per tenant (playbook + timeline + approvals + SLA) — assign in one action (Phase B).
- **Timeline gates only** for formal approval (not the 19-row milestone grid as source of truth).
- **Hybrid admin**: platform defaults + tenant override approvers / enable-disable.
- **Reject** → gate stays `pending`, resubmit allowed.
- **Email**: Microsoft 365 SMTP (non-legacy) / SES on AWS production.

---

## Phase A — Gate approval MVP ✅ (this release)

**Goal:** Operational value on the existing v2 timeline without a platform editor.

| Deliverable | Status |
|-------------|--------|
| Multi-step approval engine | Done |
| Pilot 5 gates + default chains | Done |
| Queued email notifications | Done |
| Reject → pending + resubmit | Done |
| Tenant gate policy overrides | Done |
| Gate approvals inbox UI | Done |
| Block direct Passed without approval | Done |
| RBAC `project_one:rollout:gate:approve` | Done |

**Not in Phase A:** platform timeline reorder, custom phases, escalation reminders.

---

## Phase B — Rollout policy bundle editor (platform) ✅

**Goal:** Super admin publishes one **Rollout policy** per tenant assignment.

| Deliverable | Status |
|-------------|--------|
| Platform `/platform/playbooks` → **Rollout policy** editor | Done |
| Reorder timeline phases (up/down) | Done |
| Hide standard phases per pack | Done |
| Edit working-day segments + SLA validation | Done |
| Edit default approval chains per gate | Done |
| **One-click assign** bundle to tenant | Done |
| CLI: `rollout:policy:publish`, `tenants:assign-rollout-policy`, `tenants:sync-playbook --policy=` | Done |

See [rollout-gate-approvals-phase-b.md](./rollout-gate-approvals-phase-b.md).

---

## Phase C — Custom phases in platform catalog ✅

**Goal:** Super admin adds tenant-applicable phases (e.g. LGU clearance, finance capex release, MNO DOA confirmation).

| Deliverable | Status |
|-------------|--------|
| Add phase CRUD in platform catalog | Done |
| `counts_toward_sla` flag per phase | Done |
| Snapshot into tenant on assign | Done |
| New rollouts instantiate custom phases | Done |

See [rollout-gate-approvals-phase-c.md](./rollout-gate-approvals-phase-c.md).

---

## Phase D — Operational polish ✅

| Deliverable | Status |
|-------------|--------|
| Pending approval dashboard widget | Done |
| Escalation email after N working days | Done |
| Delegation / acting approver | Done |
| Optional Amazon SES as platform default on ECS | Done (mailer config wired) |
| Audit export for gate approval history | Done |
| Realtime inbox refresh (Echo) | Done |

See [rollout-gate-approvals-phase-d.md](./rollout-gate-approvals-phase-d.md).

---

## Phase E — Milestone grid alignment ✅

Derive or sync the 19-row milestone cycle grid from the unified timeline policy so operators see one source of truth.

| Deliverable | Status |
|-------------|--------|
| Derive milestones from `timeline_templates` | Done |
| Segment map (coarse timeline → 19 PMO rows) | Done |
| Custom phases appear in milestone grid | Done |
| Dynamic post–Day-1 pivot from timeline | Done |
| Cache derived targets on policy assign | Done |
| Frontend timeline pivot via `day_one` anchor | Done |

See [rollout-gate-approvals-phase-e.md](./rollout-gate-approvals-phase-e.md).

---

## Quick reference — who configures what

| Capability | Super admin | Tenant admin |
|------------|-------------|--------------|
| Publish playbook version | Phase B+ | — |
| Reorder / add phases | Phase B/C | — |
| Default approval chains | Phase B | Override only (Phase A ✅) |
| Enable/disable gate approval | Defaults | Phase A ✅ |
| Approve on live rollout | — | Role in chain |
| Email SMTP / SES | Platform env | — |
