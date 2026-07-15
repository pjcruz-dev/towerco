# Procurement-One — RFQ vendor portal

Vendors invited to an RFQ can receive email with a **secure link** to submit quotations online. Internal staff can still **Capture quotation** for offline quotes.

Related: [e-approval-email.md](./e-approval-email.md) (platform mail transport)

---

## Phase 1 (core portal)

### Enable per tenant

1. **Procurement → Settings** — ensure `vendor_portal_enabled` is on in the RFQ scoring policy (default: **on**).
2. Vendors need **email** in registry `contact_json` (`email`, `contact_email`, or `primary_email`).
3. Platform mail configured (`TOWEROS_NOTIFICATIONS_MAIL_MAILER=smtp` or `ses`) — same as E-Approval.

Email templates live under procurement **vendor email templates**:

| Event | Template key | Auto flag |
|-------|--------------|-----------|
| Invite | `rfq_invited` | `auto_on_rfq_invite` |
| Publish | `rfq_published` | `auto_on_rfq_publish` |

Variables: `{{rfq_document_no}}`, `{{rfq_title}}`, `{{vendor_name}}`, `{{closes_at}}`, `{{quote_url}}`, `{{brand}}`

### Workflow

| Step | Action | Email |
|------|--------|-------|
| Draft RFQ | Invite vendors | `rfq_invited` (optional auto) |
| Publish | Open bidding | `rfq_published` (optional auto) |
| Vendor | Opens `{{quote_url}}`, submits prices | — |
| Open RFQ | Bid appears in comparison matrix | Buyer in-app (+ optional email) |
| Close → Award → PO | Unchanged | `rfq_closed` to vendors; PO vendor email (existing) |

**Resend:** RFQ detail → Invited vendors → **Resend email** (rotates access token)

### Public URL

```
https://{tenant-host}/public/procurement/rfq-quotes/{access_token}
```

Public API (no auth):

| Method | Path |
|--------|------|
| GET | `/api/v1/public/procurement/rfq-quotes/{token}` |
| POST | `/api/v1/public/procurement/rfq-quotes/{token}/bids` |

Tenancy resolves from hostname or `X-Tenant-Domain`. Rate limit: `procurement-public` (default 30/min per IP + token).

### Security

- Token is scoped to **one vendor + one RFQ** (stored as hash only; plain token encrypted for reminder reuse).
- Valid while RFQ is `open` and before `bidding_closes_at`.
- Target unit prices are **not** exposed on the public form.
- RFQ lines **sync from linked PR** while draft/open.

---

## Phase 2 (history, reminders, buyer alerts)

### Bid version history

Every quotation submit or revision creates an immutable **version** row (`procurement_rfq_bid_versions`). Buyers can review history on the RFQ detail page when a vendor revised their quote.

API: `GET /api/v1/procurement-one/rfqs/{rfq}/bids/{bid}/versions`

### Bid attachments

Vendors may attach up to **5 files** (10 MB each) on the public quote form. Buyers download via:

`GET /api/v1/procurement-one/rfqs/{rfq}/bids/{bid}/attachments/{attachment}/download`

### Buyer notifications

RFQ scoring policy flags:

- `notify_buyer_on_bid` — in-app alert to RFQ requestor (default: on)
- `notify_buyer_email` — email to requestor when a bid is received or revised (default: on)

### Vendor reminder & close emails

| Event | Template | Auto flag | Trigger |
|-------|----------|-----------|---------|
| Reminder | `rfq_reminder` | `auto_on_rfq_reminder` | Scheduled command |
| Closed | `rfq_closed` | `auto_on_rfq_close` | Manual close bidding |

Reminder variables include `{{days_until_close}}`.

Scheduled job (daily 08:30 server time):

```bash
php artisan procurement:rfq-reminders
```

Configure reminder thresholds via `PROCUREMENT_RFQ_REMINDER_DAYS=3,1` (days before `bidding_closes_at`). Reminders go only to invited vendors who have **not** submitted yet.

### Quotation revisions

While RFQ is **open**, vendors can **update** their quotation (latest version wins in the comparison matrix). Locked after close or award.

---

## Local development

- Mailtrap captures invitation emails — open the **Submit quotation** link from the message body.
- `QUEUE_CONNECTION=sync` is fine for local smoke tests.
- Run tenant migrations after deploy: `php artisan tenants:migrate`

---

## Phase 3 (auto-close + vendor inbox lite)

### Auto-close at deadline

When `auto_close_at_deadline` is on in RFQ scoring policy (default: **on**), open RFQs with `bidding_closes_at` in the past are closed automatically.

Scheduled job (every 5 minutes):

```bash
php artisan procurement:rfq-auto-close
```

On auto-close:
- RFQ status → `closed` (audit action `auto_closed`)
- Vendors receive `rfq_closed` email (if enabled)
- Buyer receives in-app + email alert to review bids and award

Manual **Close bidding** also notifies the buyer to proceed with award.

### Vendor inbox (token portal)

Lightweight supplier inbox without full VENDOR-ONE login. Each vendor gets a durable inbox link (`{{inbox_url}}` in invite/publish/reminder emails).

```
https://{tenant-host}/public/procurement/vendor-inbox/{access_token}
```

Public API:

| Method | Path |
|--------|------|
| GET | `/api/v1/public/procurement/vendor-inbox/{token}` |

Lists all RFQ invitations for that supplier with status and **Submit quotation** links for open RFQs.

Toggle with `vendor_inbox_enabled` in RFQ scoring policy (default: **on**).

---

## Future (full VENDOR-ONE)

- Supplier accounts with password / SSO
- Persistent inbox across tenants (marketplace)
