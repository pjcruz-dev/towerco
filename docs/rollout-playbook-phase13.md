# Rollout Playbook — Phase 13 (Field mobile UX)

Touch-first SAQ and CME workflows for field engineers.

**Status: ✅ Complete**

## Goals

- Usable on **phone viewport** without horizontal scroll
- **Camera capture** and **GPS** on candidate scouting
- **Offline draft** queue (sync when back online)

Depends on **Phase 7** file upload pipeline.

## Frontend

### 1. Responsive layouts

| Screen | Change |
|--------|--------|
| SAQ tab | Single-column forms, 44px+ touch targets, sticky action bar, mobile candidate cards |
| CME tab | Single-column daily report, large numeric inputs, mobile report cards |
| Rollout detail | Horizontally scrollable tab bar; **Sync drafts** button when pending |

### 2. Camera & GPS

| Feature | Implementation |
|---------|----------------|
| Camera | `FileUploadField` with `capture="environment"` + Phase 7 upload |
| GPS | `useGeolocation` hook → prefill lat/lng on candidate form |
| Permission UX | Clear errors when GPS denied |

### 3. Offline drafts

| Item | Detail |
|------|--------|
| Storage | `localStorage` key `toweros:rollout-drafts:{tenantId}` |
| Scope | Candidate create, hunting log, CME report (not gate/RFI) |
| Sync | On reconnect + manual **Sync drafts**; `client_draft_id` dedupes retries |

## Backend

- `client_draft_id` nullable UUID on `site_candidates`, `site_hunting_daily_logs`, `cme_daily_reports`
- Create endpoints return **200** with existing resource when draft ID matches (idempotent sync)

## Tests

```bat
php artisan test --filter=RolloutClientDraftIdempotency
```

Frontend E2E (Playwright mobile smoke) deferred — no Playwright harness in repo yet.

## Verify locally

1. Chrome DevTools → iPhone 14 → SAQ add candidate with **Take photo**
2. **Use my location** fills coordinates on Alliance rollout
3. Airplane mode → **Save draft offline** → online → **Sync drafts** succeeds once

See also: [project-one-roadmap.md](./project-one-roadmap.md) · [rollout-playbook-phase7.md](./rollout-playbook-phase7.md)
