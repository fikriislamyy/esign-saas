# EzSign — Product Requirements Document (v1)

**Status:** Draft for review
**Audience:** An engineer or model implementing against this codebase with no prior context
**Scope:** Full v1 of the platform

---

## How to read this document

Most of this platform is already built. A PRD that describes it as greenfield would get you
rebuilding working code, so **every requirement below carries a status marker**:

| Marker | Meaning |
| --- | --- |
| `[BUILT]` | Implemented and working. Do not rewrite. Verify before changing. |
| `[PARTIAL]` | Exists but incomplete. The gap is named. |
| `[MISSING]` | Not implemented. This is where v1 work remains. |
| `[TBD]` | A decision the business has not made. **Ask before implementing.** Do not guess. |

Where a requirement names a file, that file exists at that path today unless marked `[MISSING]`.

---

## 1. Executive Summary

### Problem Statement

Teams that need signed documents are forced to choose between international e-signature tools that
bill per seat in USD by card — which excludes businesses without international cards, and
overcharges teams that sign occasionally — and unsigned PDFs over email, which produce no audit
trail and no verified signer identity.

### Proposed Solution

A multi-tenant e-signature platform with two independent payment rails (card and QRIS) and a
hybrid pricing model: a subscription gates capacity (documents, seats, storage) while a prepaid
wallet charges **$0.10 USD per signature field** only when a signature is actually collected.
Occasional signers pay almost nothing; heavy users pay in proportion to use.

### Success Criteria

Measurable from the database without additional instrumentation:

| # | Metric | Target | How to measure |
| --- | --- | --- | --- |
| 1 | Signature completion rate | ≥ 90% of `sent` documents reach `completed` before expiry | `documents` grouped by `status` |
| 2 | Signer time-to-complete | Median ≤ 3 minutes | `document_signers.updated_at` − first `viewed` transition |
| 3 | Wallet debit accuracy | 100% — exactly one `wallet_transactions` row of type `signature` per completed signing, never zero, never two | Join `documents` → `wallet_transactions` on reference |
| 4 | Payment success rate | ≥ 98% card, ≥ 95% QRIS | `subscription_payments` + `wallet_topups` where `status = paid` ÷ total created |
| 5 | Retention safety | **Zero** documents with `status = completed` that have lost their files | Count `completed` rows whose `signed_path` object is absent from S3 |

Metric 5 is an invariant, not a target. A single violation is a P0 — see Risk R1.

**Business KPIs `[TBD]`** — conversion rate, MRR target, CAC and churn thresholds were not
specified. Do not invent them. Ask the product owner.

---

## 2. User Experience & Functionality

### 2.1 Personas

**P1 — Organization Owner.** Signs up, owns billing. The only role permitted to top up the wallet,
change plans, or remove members. `User::isOwner()`, enforced by `abort_unless($user->isOwner(), 403)`
in `SubscriptionController` and `BillingTopupController`.

**P2 — Team Member (`member` / `admin`).** Uploads documents, places signature fields, sends for
signature. Cannot touch billing. Admins additionally manage invitations.

**P3 — External Signer.** **Has no account and never creates one.** Receives a link, verifies
identity by email OTP, signs, downloads. This persona never sees the application shell. Every
decision about the signing flow should optimise for someone who has never heard of EzSign and is
mildly suspicious of the link they just received.

### 2.2 Core User Stories

---

**US-1 — Register and get an organization** `[PARTIAL]`

> As a new user, I want signing up to create my organization so I can start immediately.

Acceptance criteria:
- `[BUILT]` Registration creates `organizations`, then `users` with role `owner`, then back-fills
  `organizations.owner_id`, then creates `subscriptions` with `plan=free`.
- `[BUILT]` The wallet is **not** created here. It is created lazily on first use by
  `WalletService::getOrCreateWallet()`. Do not add a second creation path.
- `[BUILT]` Email verification uses an OTP code, not a magic link (`EmailVerificationOtpService`).
- `[MISSING]` **These four writes are not wrapped in a transaction.**
  `RegisteredUserController` performs them sequentially, so a failure partway leaves an
  organization with no owner, or an organization with no subscription — and the latter means
  `PlanService` has no row to read limits from. Wrap the whole block in `DB::transaction()`.
  See Risk R9.

---

**US-2 — Upload a document** `[BUILT]`

> As a team member, I want to upload a PDF so I can prepare it for signature.

Acceptance criteria:
- Accepts PDF. Stored on the S3 `documents` disk, never the local filesystem.
- Rejected server-side if the organization is at its document limit for the period, or if the
  upload would cross the storage limit — `PlanService::canUploadDocument()` and `canStore()`.
- Rejection is enforced in the controller, not merely hidden in the UI.
- `documents.status` starts at `draft`.

---

**US-3 — Place signature fields** `[BUILT]`

> As a team member, I want to drag signature fields onto the PDF and assign each to a signer.

Acceptance criteria:
- Fields are positioned per page with coordinates (`document_signature_fields`).
- Each field is assigned to exactly one `document_signers` row.
- A document with zero fields cannot be sent (`DocumentController` returns a validation error).

---

**US-4 — Send for signature, paying from the wallet** `[BUILT]`

> As an owner, I want to know the cost before sending, and to be blocked if I cannot cover it.

Acceptance criteria:
- Cost = `signature_field_count × 10` USD cents (`SigningPricingService`).
- The wallet balance is checked **before** send. Insufficient balance blocks the send and states
  the shortfall in USD.
- Status moves `draft` → `sent`; each signer gets a tokenised link.
- **The debit happens at signing completion, not at send** (`SigningController`). Sending reserves
  nothing. See Risk R4.

---

**US-5 — Sign as an external party** `[BUILT]`

> As a signer, I want to open a link, prove it is me, sign, and get my copy.

Acceptance criteria:
- `/sign/{token}` where token is a UUIDv4 — unguessable, single-signer scoped.
- Email OTP required before any signature is applied (`SigningOtpService`). The token alone is not
  sufficient authority to sign.
- Signer statuses: `pending` → `viewed` → `signed`, or `declined`.
- When the final signer signs, the document becomes `completed` and `signed_path` is written.
- The signer can download their copy without an account (`/sign/{token}/pdf`).

---

**US-6 — Reuse a template** `[BUILT]`

> As a team member, I want to save a prepared document as a template so recurring contracts do not
> need re-preparing.

Acceptance criteria:
- Templates carry their own signature field layout (`template_signature_fields`).
- Creating a document from a template copies the field layout, not a reference to it.

---

**US-7 — Invite teammates** `[BUILT]`

> As an owner or admin, I want to invite colleagues by email.

Acceptance criteria:
- Blocked when the organization is at its seat limit. **Pending invitations count against the
  limit** — otherwise an org can invite unlimited people and accept them later
  (`PlanService::membersUsed()`).
- Invitation token is `Str::random(64)`, single use.

---

**US-8 — Top up the wallet** `[PARTIAL]`

> As an owner, I want to add funds by card or QRIS.

Acceptance criteria:
- `[BUILT]` Card: Stripe PaymentIntent confirmed in-page via Stripe Elements. The browser never
  leaves the domain and card data never reaches our servers.
- `[BUILT]` The wallet is credited by the `payment_intent.succeeded` webhook, never by the client.
  The client-side success view must not be treated as proof of payment.
- `[BUILT]` Minimum $0.50 (Stripe's floor).
- `[MISSING]` **QRIS top-up.** `PaymentDialog.vue` renders a QRIS tab that displays a placeholder
  and refuses to submit. `PakasirService` exists but is wired only to the plan-purchase flow. This
  is the single largest functional gap in v1.

---

**US-9 — Subscribe to a paid plan** `[PARTIAL]`

> As an owner, I want to upgrade so my organization gets higher limits.

Acceptance criteria:
- `[BUILT]` Card: SetupIntent → attach payment method → create Stripe subscription. Auto-renews.
- `[BUILT]` QRIS: one-off payment via Pakasir, manual renewal. Pakasir cannot auto-renew; the UI
  must not imply otherwise.
- `[BUILT]` Enterprise is not self-serve — it shows "Contact sales" and no payment flow.
- `[MISSING]` **No guard against double-subscribing.** `SubscriptionController::subscribe()` does
  not check for an existing active subscription. Upgrading twice creates two Stripe subscriptions
  and bills the customer twice. See Risk R2. **This must be fixed before live keys.**

---

**US-10 — Automatic cleanup** `[BUILT]`

> As the platform, I want unsigned documents and lapsed plans cleaned up without human action.

Acceptance criteria:
- `documents:expire` — `draft`/`sent` documents older than `config('documents.retention_days')`
  become `expired` and lose their S3 objects. **`completed` documents are never touched.**
- `subscriptions:expire` — lapsed paid plans return to free, but a subscription still active at
  Stripe is skipped and logged.
- Expired documents stop counting toward the storage quota.
- Both are idempotent and support `--dry-run`.

---

**US-11 — Handle limit overflow on downgrade** `[MISSING]`

> As the platform, I want a defined behaviour when a downgrade puts an org over its new limits.

An org on Pro with 10 members and 500 MB that drops to Free (3 members, 100 MB) currently keeps
everything. `canAddMember()` blocks new invites, but nothing addresses existing members or files,
so limits leak indefinitely.

Recommended behaviour, **pending product sign-off `[TBD]`**:
- Existing members are never auto-removed. Choosing *which* seven people to cut is a decision no
  scheduled job should make unattended.
- Existing files are never auto-deleted. Document retention already drains storage over time.
- New invites and uploads are refused until the org is back under limit — already the behaviour.
- The overflow is made **visible**: a banner on Members and Plan reading "10 of 3 seats — upgrade
  or remove 7 to invite again."

Do not implement a harsher variant without explicit approval.

### 2.3 Non-Goals for v1

Naming these protects the timeline. Do not build them.

- Native mobile applications. The web UI must work at 375px; that is the whole mobile story.
- Bulk send / mail merge.
- In-person ("kiosk") signing on a shared device.
- SSO, SAML, SCIM.
- One user belonging to multiple organizations. `users.organization_id` is a single column and v1
  keeps it that way.
- Qualified//eIDAS-grade certificate signing. v1 produces an audit trail, not a cryptographic
  certificate. `[TBD]` — confirm this is acceptable in the target jurisdiction **before launch**.
- Editing a document after it has been sent.
- Per-plan retention windows. Retention is one global value in `config/documents.php`.

---

## 3. AI System Requirements

**Not applicable to v1.** This platform contains no model inference, no embeddings, no retrieval
and no agentic behaviour. The schema section is retained deliberately so its absence is a recorded
decision rather than an oversight.

If AI features are proposed later (clause extraction, document summarisation, smart field
placement), they require their own PRD covering evaluation strategy, ground-truth datasets and
failure modes. Do not bolt them onto this one.

---

## 4. Technical Specifications

### 4.1 Stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 10, PHP 8.3 |
| Frontend | Vue 3 (`<script setup>`) + Inertia.js 1.3 |
| UI | Tailwind + shadcn-vue primitives |
| Database | PostgreSQL |
| Cache / locks | Redis |
| Document storage | S3-compatible (`documents` disk) |
| Card payments | Stripe (API `2026-08-26.dahlia`) |
| QRIS payments | Pakasir |
| FX rates | currencyfreaks.com |
| Local environment | Docker Compose |

### 4.2 Data Model

```
organizations (uuid)
├── users (uuid)              role: owner | admin | member
├── invitations               token Str::random(64), counts toward seat limit
├── subscriptions  (1:1)      plan, status, provider, expired_at, stripe_*
│   └── subscription_payments order_id (Pakasir only, nullable), stripe_invoice_id
├── cards_info                brand, last4, expiry — never the PAN
├── wallets        (1:1)      balance_usd_cents (integer)
│   ├── wallet_transactions   credit | debit, running balance_after
│   └── wallet_topups         pending → paid
├── documents (uuid)          draft | sent | completed | cancelled | expired
│   ├── document_signers      pending | viewed | signed | declined, token uuid
│   └── document_signature_fields
└── templates (uuid)
    └── template_signature_fields
```

**Two conventions that will cost you an afternoon if you miss them:**

1. **Money is always an integer count of USD cents.** Never a float. IDR amounts are converted at
   the recorded `exchange_rate` and stored alongside the USD cents figure. Every payment row keeps
   both the source amount and the USD cents it resolved to.
2. **The wallet is not the subscription.** Subscription charges never touch the wallet, and wallet
   top-ups never affect the plan. They are separate ledgers with separate payment flows. Do not
   "simplify" them together.

### 4.3 Effective plan resolution

`Subscription::effectivePlan()` is the single source of truth for which limits apply. It downgrades
to `free` when the plan is cancelled *or* `expired_at` is in the past, regardless of what the `plan`
column says.

**Always read limits through `PlanService`, never from `subscriptions.plan` directly.** The stored
column can legitimately lag reality between the expiry moment and the next scheduler run.

### 4.4 Integration Points

| Integration | Direction | Failure behaviour required |
| --- | --- | --- |
| Stripe PaymentIntent | Outbound + webhook | Wallet credited **only** by `payment_intent.succeeded`, under `lockForUpdate` with a status check so retries cannot double-credit |
| Stripe Subscriptions | Outbound + webhook | `invoice.paid` advances `expired_at`; `invoice.payment_failed` sets `past_due` |
| Pakasir QRIS | Outbound + webhook | **The webhook body is never trusted.** `PakasirWebhookController` re-fetches the transaction from Pakasir and acts only on a `completed` status. Preserve this. |
| S3 | Outbound | Disk is configured `'throw' => false`; `delete()` returns a boolean and never raises. Check the return value. |
| currencyfreaks.com | Outbound | 5s timeout, **already observed failing in production logs**. A failed rate must block the IDR transaction with a clear message, never fall back to a guessed rate. |
| SMTP | Outbound | Carries signing links and OTPs. Delivery failure silently blocks signing — see Risk R6. |

### 4.5 Security & Privacy

`[BUILT]` and to be preserved:

- **Card data never reaches our servers.** Stripe Elements renders the input in a Stripe-hosted
  iframe. We store brand, last four and expiry only. Any change that puts a raw card field in our
  DOM breaks PCI SAQ-A eligibility and must be rejected in review.
- **Signing tokens** are UUIDv4 (122 bits of entropy), scoped to a single signer.
- **Token alone cannot sign.** Email OTP is required first, so a forwarded or leaked link does not
  grant signing authority.
- **Tenant isolation** is enforced per request via
  `abort_unless($document->organization_id === $request->user()->organization_id, 403)`. Every new
  document-scoped endpoint must repeat this check. There is no global scope doing it for you.
- **Billing actions are owner-only**, enforced server-side.
- **Webhook authenticity**: Stripe by signature verification; Pakasir by re-fetching from source.

`[TBD]` — unresolved and needed before handling real customer contracts:

- Data residency requirements for the target jurisdiction.
- Retention/deletion obligations, and whether a 3-day default conflicts with them.
- Whether a GDPR-style "delete all my data" request is in scope for v1.
- Whether the audit trail meets local evidentiary standards for a disputed signature.

---

## 5. Risks & Roadmap

### 5.1 Technical Risks

**R1 — Retention deleting signed contracts. Severity: critical.**
`documents:expire` filters on `whereIn('status', ['draft', 'sent'])`. If someone widens that filter,
executed contracts are destroyed with no recovery path. Mitigation: the status filter is the
safety mechanism — any change to it requires explicit review, and Success Metric 5 must be
monitored.

**R2 — Double subscription. Severity: high. Unmitigated.**
`subscribe()` has no guard against an existing active subscription, so a double-click or a retry
creates two Stripe subscriptions and bills twice. Fix before live keys.

**R3 — Stripe API version drift. Severity: high. Occurred twice.**
Stripe relocated `current_period_end` onto subscription items, and the invoice's subscription
reference to `invoice.parent.subscription_details`. Both old paths returned `null` silently rather
than erroring — one wrote an epoch-0 expiry that made a paid subscription read as lapsed, the other
left payments stuck on `pending`. Mitigation: never dereference a Stripe field without verifying it
exists in the pinned API version; prefer failing loudly over a null default.

**R4 — Wallet debit timing. Severity: medium. Needs verification.**
Balance is checked at send, but debited at signing completion. Between those events the balance can
be spent elsewhere, so a signature may complete against an insufficient balance. Verify what
`SigningController` does when the debit fails after the signature is applied. A signature applied
without a matching debit violates Success Metric 3.

**R5 — FX dependency. Severity: medium.**
`currencyfreaks.com` is a single unreplicated dependency with a 5s timeout, already timing out in
`storage/logs/laravel.log`. Every IDR-denominated flow — which is every QRIS flow — stops when it
does. Mitigation: consider a short-lived cached rate with an explicit staleness bound. Never guess
a rate.

**R6 — Email deliverability. Severity: medium.**
Signing links and OTPs go by email. A spam-foldered OTP is indistinguishable to the user from a
broken product, and the signer has no account through which to recover. Mitigation: monitor
`viewed`-without-`signed` rates as a proxy.

**R7 — Untested scheduler. Severity: low.**
The scheduler process (`schedule:work`) is newly added to both Compose and supervisord and has not
run in production. Verify it survives a restart and that `onOneServer()` locking behaves under more
than one container.

**R9 — Non-transactional registration. Severity: medium. Unmitigated.**
`RegisteredUserController` writes the organization, user, owner back-reference and subscription as
four separate statements with no enclosing transaction. A failure or constraint violation partway
through leaves a half-created tenant. The worst shape is an organization with no `subscriptions`
row, because `PlanService::subscriptionFor()` would then create one on read — masking the problem
until someone audits why a tenant has no signup record. Mitigation: one `DB::transaction()` around
the block. Cheap fix, and it belongs in v1.

**R8 — `env()` outside config. Severity: low.**
`DocumentController` reads the disk via `env('DOCUMENTS_DISK')`. Under `php artisan config:cache`
this returns `null` and file operations silently target the wrong disk. Harmless today because
config is not cached; a landmine the first time someone caches it.

### 5.2 Phased Rollout

**MVP — shipped.** Auth and organizations, document upload, field placement, tokenised signing with
OTP, templates, members and invitations, wallet with card top-up, plans with card and QRIS
purchase, plan limit enforcement, scheduled cleanup.

**v1 — remaining work, in priority order:**

1. **R2 double-subscribe guard.** Smallest change, largest financial exposure. Do this first.
2. **QRIS wallet top-up** (US-8). The largest functional gap; completes the promise that a customer
   can use the platform without an international card.
3. **R4 verification** and a fix if the debit can fail post-signature.
4. **R9 registration transaction.** A few lines, removes a class of half-created tenants.
5. **Overflow visibility** (US-11), pending the `[TBD]` sign-off.
6. **R5** cached FX rate with a staleness bound.

**v1.1 and later — not scoped here:** per-plan retention windows, expiry notifications to signers,
bulk send, richer audit certificates.

### 5.3 Definition of Done for v1

- All five Success Criteria measurable and met.
- R2 fixed; R4 verified; R9 fixed.
- A customer can complete top-up → send → sign → download using **only** QRIS, never touching a
  card.
- All `[TBD]` items either resolved or explicitly deferred with the product owner's agreement.

---

## Appendix — Open questions for the product owner

These block parts of the spec. Do not resolve them by guessing.

1. Business KPIs: conversion, MRR, churn targets.
2. Is a non-certificate audit trail legally sufficient in the target jurisdiction?
3. Data residency and retention obligations.
4. Is the 3-day default retention correct, or should it vary by plan?
5. Confirm the recommended overflow behaviour in US-11.
6. Is `$0.10` per signature field final, and should it differ by plan?
