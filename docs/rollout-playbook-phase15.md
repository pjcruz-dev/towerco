# Rollout Playbook — Phase 15 (BTS process v3) ✅

Align TowerOS BTS (and RTB-shaped) timelines to the operational process:

**Pre-assessment (MNO) after SAQ select → MOC/COL before TSSR → Day-1 at TSSR MNO approval → RFI = site ready → Site License → Handover to Operations.**

## Delivered

- **`RolloutPlaybookV3Definition` (`3.0.0`)** — reordered BTS timeline (12 phases)
- Registry + `rollout-playbook:publish-v3`
- Milestone deriver adapts groups so explicit `pre_assessment` / `site_license` / `handover_operations` are not duplicated
- Post–Day-1 SLA scaler skips `counts_toward_sla: false` close-out phases
- Gate policy defaults + full-coverage chains for new phases
- **Process enforcement P1–P8** — guards, gate wiring, timeline readiness, work panels
- Docs: this file + roadmap + e2e notes

## Process phases (P0–P8)

| Phase | Name | Timeline / actions |
|-------|------|--------------------|
| **P0** | Program setup | Site exists → Create Project → Create BTS Rollout (link project) |
| **P1** | Endorsement | `endorsement` — Site Tracker enrolment ✅ |
| **P2** | SAQ select | `site_hunting` — ≥3 candidates → select ✅ |
| **P3** | Pre-assessment (MNO) | `pre_assessment` — selected candidate may proceed to TSSR path ✅ |
| **P4** | MOC/COL | `moc_col` — **pre–Day-1** (moved before TSSR) ✅ |
| **P5** | TSSR → Day-1 | `tssr_creation` → `tssr_mno_approval` → Record Day-1 ✅ |
| **P6** | Build readiness | `pre_construction` → `permitting` → `skom` ✅ |
| **P7** | Construction → site ready | `construction` → **Record RFI** ★ site ready ✅ |
| **P8** | Close-out | Project milestones → `site_license` → `handover_operations` ✅ |

## P1 — Endorsement (Site Tracker enrolment) ✅

| Surface | Behavior |
|---------|----------|
| Rollout create | Optional endorsement date + ref; if date set, P1 completes on create |
| Timeline banner | **P1 — Endorsement & Planning** card until date is set |
| Complete P1 | Saves date/ref → recalculates SLA anchors → **passes endorsement gate** |
| SAQ | Candidates and hunting logs blocked until P1 complete (API + UI) |

```bat
php artisan test --filter=RolloutEndorsementGuard
```

## P2 — SAQ Site Hunting (≥3 → select → gate) ✅

| Surface | Behavior |
|---------|----------|
| SAQ panel | Checklist: 3 active candidates → select one → request Site Hunting gate |
| Select API | Blocked until P1 complete **and** ≥3 non-rejected candidates |
| Site Hunting gate | Pass / formal approval request blocked until a candidate is **selected** |
| Timeline readiness | Shows need N more / select / P2 ready — request gate |

```bat
php artisan test --filter=RolloutSaqSelectGuard
```

## P3 — Pre-assessment Approval (MNO) ✅

| Surface | Behavior |
|---------|----------|
| Prerequisites | P2 Site Hunting **passed** + selected candidate |
| Timeline panel | Shows selected candidate; guides MNO → PMO gate request |
| Pre-assessment gate | Pass / approval request blocked until P2 complete |
| TSSR gates | `tssr_creation` / `tssr_mno_approval` blocked until P3 passed (when phase exists) |

```bat
php artisan test --filter=RolloutPreAssessmentGuard
```

## P4 — MOC + COL Securing (pre–Day-1) ✅

| Surface | Behavior |
|---------|----------|
| Prerequisites | P3 Pre-assessment **passed** |
| Permits panel | P4 checklist + MOC permit dates (eLAS IRR) |
| MOC/COL gate | Pass / approval request blocked until P3 complete |
| TSSR gates | Also blocked until P4 passed when `moc_col` is on the timeline |

```bat
php artisan test --filter=RolloutMocColGuard
```

## P5 — TSSR → Day-1 ✅

| Surface | Behavior |
|---------|----------|
| Prerequisites | P3+P4 passed |
| `tssr_creation` | Engineering gate (SAQ Eng → SAQ → PMO) |
| Day-1 card | Disabled until TSSR create/review passed; records TSSR approved date |
| On Day-1 | Auto-passes `tssr_mno_approval`, recalculates post–Day-1 SLA (115 WD BTS) |

```bat
php artisan test --filter=RolloutTssrDayOneGuard
```

## P6 — Build readiness (Pre-con → Permitting → SKOM) ✅

| Surface | Behavior |
|---------|----------|
| Prerequisites | Day-1 recorded (P5) |
| Sequence | `pre_construction` → `permitting` → `skom` (gates in order) |
| Construction | Blocked until P6 complete (SKOM passed when present) |
| UI | Checklist on Pre-con / Permitting / SKOM panels |

```bat
php artisan test --filter=RolloutBuildReadinessGuard
```

## P7 — Construction → Record RFI ★ site ready ✅

| Surface | Behavior |
|---------|----------|
| Prerequisites | P6 complete (SKOM passed when present) |
| Construction gate | Blocked until P6; formal chain CME → PMO → tenant admin |
| Record RFI | Blocked until P6; marks ★ site ready; closes delivery SLA |
| On RFI | Auto-passes `construction` (RFI Certificate); status → completed |
| P8 prep | Site License / Handover gates blocked until RFI; still actionable after delivery complete |

```bat
php artisan test --filter=RolloutConstructionRfiGuard
```

## P8 — Close-out (Site License → Handover) ✅

| Surface | Behavior |
|---------|----------|
| Prerequisites | P7 RFI recorded (★ site ready) |
| Sequence | Project milestones → `site_license` → `handover_operations` |
| Site License | Record executed date (auto-pass gate) or formal SAQ → PMO → tenant admin |
| Handover | Blocked until Site License passed; formal PMO → tenant admin |
| After RFI | Close-out gates remain actionable even when rollout status is `completed` |
| SLA | Both phases `counts_toward_sla: false` |

```bat
php artisan test --filter=RolloutCloseOutGuard
```


## One-screen sequence (BTS v3)

```
Site exists
  → Create Project (link site + PM)
    → Create BTS Rollout (link project)
      → Endorsement
      → SAQ: ≥3 candidates → select
      → Pre-assessment Approval (MNO)
      → MOC/COL
      → TSSR create/review → Engineering gate
      → TSSR MNO approval → Record Day-1 (TSSR date)
      → Pre-con → Permitting → SKOM
      → Construction + Energization
      → Record RFI  ★ SITE READY
      → Complete project milestones
      → Site License Processing
      → Handover to Operations
```

## Timeline keys (BTS)

| Order | phase_key | Anchor | Counts toward 115 WD SLA |
|------|-----------|--------|---------------------------|
| 1 | endorsement | endorsement | n/a (pre) |
| 2 | site_hunting | endorsement | n/a (pre) |
| 3 | pre_assessment | endorsement | n/a (pre) |
| 4 | moc_col | endorsement | n/a (pre) |
| 5 | tssr_creation | endorsement | n/a (pre) |
| 6 | tssr_mno_approval | endorsement | Day-1 trigger |
| 7 | pre_construction | tssr_approved | yes |
| 8 | permitting | tssr_approved | yes |
| 9 | skom | tssr_approved | yes |
| 10 | construction | tssr_approved | yes (through RFI) |
| 11 | site_license | tssr_approved | **no** |
| 12 | handover_operations | tssr_approved | **no** |

## Apply to a tenant

```bat
cd backend
php artisan rollout-playbook:publish-v3
php artisan tenants:sync-playbook --domain=alliance.localhost --playbook-version=3.0.0 --with-rbac
```

Existing rollouts keep their snapshot; **new** rollouts use v3 after assign (`new_rollouts_only`).

## Tests

```bat
php artisan test --filter=RolloutPlaybookV3
php artisan test --filter=RolloutPlaybookDefinitionRegistry
php artisan test --filter=RolloutPlaybookMilestoneDeriver
```

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase1.md](./rollout-playbook-phase1.md) · [rollout-gate-approvals-phase-e.md](./rollout-gate-approvals-phase-e.md)
