# Subscription plans, quota enforcement, and a second payment method

## 1. What we are building

Four connected pieces:

1. **Subscriptions** — every organization has exactly one subscription row. New organizations get
   `free` with `expired_at = null`. Paid plans get an expiry.
2. **Plan limits that actually bite** — documents per period, member seats, and storage are
   enforced server-side at upload and invite time.
3. **A Plan page** — shows the current plan, what it includes, how much of each limit is used,
   when it expires, and upgrade/downgrade buttons.
4. **Two ways to pay** — card via Stripe, QR via Pakasir. Both replace the current hosted
   Stripe Checkout redirect with a form we own.

**Definition of done:**

- Registering creates an organization *and* a `free` subscription in the same transaction.
- A free org is refused the 4th document upload in a calendar week, the 4th member, and any
  upload crossing 100 MB — all rejected server-side, not just hidden in the UI.
- An owner can upgrade to Pro with a card, without ever leaving our domain.
- An owner can upgrade to Pro by scanning a QR code.
- Enterprise shows a "Contact sales" action and no payment flow.
- `npm run build` passes and `docker compose exec app php artisan migrate` runs clean.
- Existing wallet top-ups still work.

**Read Section 3 and Section 4 before writing any code.** Section 3 has one decision that
changes the whole shape of the card form, and Section 4 has a security hole you will otherwise
ship.

---

## 2. Background: how this codebase already handles money

Do not design any of this from scratch. Most of the conventions already exist — copy them.

### Everything runs in Docker

`DB_HOST` is `postgres`, which only resolves on the Docker network. **Every `php artisan`
command must be prefixed**, or you get `could not translate host name "postgres"`:

```bash
docker compose exec app php artisan migrate
```

`npm` runs on the host as normal. The database is **PostgreSQL**, so `->after()` in a migration
is silently ignored — don't bother with it.

### Money is an integer count of USD cents. Always.

`wallets.balance_usd_cents` is an `unsignedBigInteger`. `wallet_transactions.amount_usd_cents`
likewise. There are no floats and no decimal dollar columns anywhere in the money path.

`$10.00` is `1000`. Keep it that way. A float will drift and the drift will show up as a penny
mismatch in someone's balance months later.

When a payment happens in a non-USD currency, the existing tables store **three** things: the
charged `amount` in that currency's smallest unit, the `exchange_rate` used, and the canonical
`wallet_amount_usd_cents`. Copy that shape — you will need it for Pakasir, which bills in IDR.

`App\Services\ExchangeRateService::usdToIdr()` already exists and returns a float or `null`.
**It can return `null`**, and `BillingTopupController` handles that by failing the request with
a validation error. Do the same — do not fall back to a hardcoded rate.

### The wallet is not the subscription

The wallet is prepaid credit that gets **debited per signature** (`wallet_transactions.type`
is `'signature'`). It is pay-as-you-go for usage.

A subscription is a recurring entitlement. These are different products and they do not share a
balance. See decision 3.4.

### What already exists for Stripe

| Thing | Where | State |
| --- | --- | --- |
| `stripe/stripe-php` v21 | `composer.json` | Installed. **No Cashier.** |
| `StripeService` | `app/Services/StripeService.php` | A 20-line wrapper exposing `->client()` |
| Webhook receiver | `app/Http/Controllers/StripeWebhookController.php` | Handles **one** event type |
| Webhook route | `routes/web.php:54`, CSRF-exempt in `VerifyCsrfToken` | Working |
| Secret + webhook secret | `config/services.php` → `services.stripe.*` | Set in `.env` |
| Publishable key | `services.stripe.key` | Set, **but never sent to the browser** |
| Top-up flow | `app/Http/Controllers/BillingTopupController.php` | **Uses hosted Stripe Checkout** |

That last row is the thing this issue replaces.

### Where things live

| What | Where |
| --- | --- |
| Billing page controller | `app/Http/Controllers/BillingController.php` |
| Billing page | `resources/js/Pages/Billing/Index.vue` |
| Billing components | `resources/js/Components/billing/` |
| Sidebar nav | `resources/js/Components/Layout/navigation.js` |
| Global Inertia props | `app/Http/Middleware/HandleInertiaRequests.php` |
| Document upload | `DocumentController@store` |
| Member invite | `InvitationController@store` |
| shadcn primitives | `resources/js/components/ui/**` (lowercase `c`) |
| App components | `resources/js/Components/**` (capital `C`) |

Both a lowercase and a capital `Components` directory exist. Watch your import paths.

### Two model conventions that will cost you an afternoon

**1. `$fillable` is a strict allowlist.** Every model here has one. `Model::create()` silently
drops any key not listed. No exception, no warning — the column just stays `NULL`. Every new
model in this issue needs its `$fillable` filled in.

**2. Organizations are UUIDs, but these new tables are not.** `organizations.id` is a `uuid`.
The `wallets` table uses `$table->id()` (auto-increment bigint) for its own PK and
`$table->foreignUuid('organization_id')` for the FK. Your new tables do exactly the same.

---

## 3. Decisions already made (do not re-litigate)

### 3.1 The card form: Stripe Elements, not raw card inputs

**Read this one properly. It is the only decision that can get the company fined.**

The task says to build our own payment form and not use Stripe Checkout or Stripe's form, and
lists `stripe-js` alongside them. There are three options and only two are legal.

| Option | What it is | Verdict |
| --- | --- | --- |
| Stripe Checkout | Redirect to a Stripe-hosted page | ❌ Correctly rejected — leaves our domain |
| Raw `<input>` for the card number, POSTed to Laravel | Our HTML, PAN hits our server | ❌ **Not viable — see below** |
| Stripe Elements | **Our** form, our layout, our submit button; the card field is a Stripe iframe | ✅ **Build this** |

Why the middle row is not viable:

- The moment a raw card number reaches our server, we leave PCI-DSS **SAQ-A** and land in
  **SAQ-D** — the full questionnaire, with quarterly scans and an annual audit. That is a
  compliance project, not a sprint task.
- Stripe also blocks it in practice. Stripe's own guidance is not to put card numbers in
  server-side API calls *even in test mode*, because the same code will not be PCI-compliant in
  production; raw-card APIs require a validated, separately-approved account.

**Stripe Elements is what "our own form" means in practice.** We control the page, the layout,
the field order, the styling, the button, the error text and the redirect. The only thing Stripe
owns is the iframe holding the digits. The user never leaves our domain and never sees Stripe
branding. It needs `@stripe/stripe-js` on the front end, which is the one piece of Stripe JS we
cannot avoid.

> **If product genuinely wants zero Stripe JavaScript**, stop and escalate before writing code.
> That path requires PCI SAQ-D certification and Stripe approval for raw-card API access. It is
> weeks of compliance work, not an implementation choice, and it cannot be decided inside this
> ticket.

Storing `card_last_four`, `card_exp_month` and `card_exp_year` — what the task asks for — **is**
allowed under PCI and is safe. What must never touch our database, logs, or error reports is the
full card number (PAN) or the CVC. Elements gives us the last four and expiry back after
tokenisation, so we get the table the task asks for without ever holding the sensitive part.

### 3.2 Stripe auto-renews. Pakasir cannot.

This is not a preference, it is what the rails support.

- **Stripe** stores the card and charges it again on its own schedule. We create a real
  `Subscription` object and let Stripe drive renewal and retries.
- **Pakasir QRIS is a one-time push payment.** The customer scans and approves a single
  transfer. There is no stored credential and nothing to charge later. Recurring QRIS billing
  does not exist.

So the two providers produce genuinely different subscriptions:

| | Stripe | Pakasir |
| --- | --- | --- |
| Renewal | Automatic, by Stripe | **Manual** — user pays again |
| `expired_at` | Synced from the Stripe period end | `now() + 30 days` at payment |
| On expiry | Stripe retries, then cancels | We downgrade to `free` |

The `subscriptions` table therefore needs a `provider` column. The Plan page must tell a Pakasir
user their plan will **not** renew itself, or they will be silently downgraded and rightly
annoyed.

### 3.3 Plan limits live in `config/plans.php`

The limits are needed in at least five places: upload, invite, storage check, the Plan page, and
the upgrade endpoint. They go in one config file — the same pattern as the `config/countries.php`
added in the previous ticket.

Do not scatter `if ($plan === 'free') { $max = 3; }` through controllers. Read the config.

### 3.4 Subscription charges do not touch the wallet

Do not credit the wallet and then debit it to pay for a plan. The wallet is prepaid
signature credit; mixing the two means a refunded subscription has to claw credit back out of a
balance the user may have already spent, and the balance stops reconciling.

Subscription payments are their own records in their own table and go straight to the provider.

### 3.5 There is a `subscription_payments` table, even though the task did not ask for one

The task lists three tables. A fourth is genuinely required, for a concrete reason:

Pakasir's API identifies a transaction by `order_id` **and** `amount` — there is no opaque
payment ID it hands back for us to store. To verify a webhook (Section 4, trap 1) we must send
back the exact `order_id` and `amount` we used. That needs a durable row per payment attempt,
written *before* redirecting the user. There is nowhere else to put it: `subscriptions` holds
one row per org and would be overwritten on the next renewal.

It also gives the Plan page a payment history for free.

### 3.6 One subscription row per organization, mutated in place

`organization_id` gets a **unique** index. Upgrades and downgrades update the existing row.
History lives in `subscription_payments`.

This matches what the task describes — registration creates *the* subscription — and it makes
every read a simple `$organization->subscription` with no "which one is current?" logic.

### 3.7 Enterprise is not self-serve

`enterprise` has no price in config and no checkout path. The Plan page shows "Contact sales"
and opens a `mailto:`. Someone sets the plan manually via tinker after the deal closes.

Its limits are `null`, meaning unlimited — see the helper in Section 7.2, which must treat
`null` as "no limit" rather than as zero.

### 3.8 Naming conflict you need to know about

The task says the `plan` column holds **`free, pro, enterprise`**, but then describes the paid
tier as **"premium ($10/month)"**.

**The database value is `pro`.** The UI label is whatever product wants. This issue uses `pro`
in code and "Pro" in the interface throughout. If product insists on the word "Premium" in the
UI, change the `label` in `config/plans.php` and nothing else.

### 3.9 "Alter wallet table" means the top-up *flow*, not the balance table

`wallets` holds one balance per org and needs no change.

`wallet_topups` needs two new columns (`provider`, `order_id`) so top-ups can go through Pakasir
too. It **already has** a nullable, indexed `stripe_payment_intent_id`, so the table is most of
the way there — the previous author anticipated this. See Section 6.3.

---

## 4. The five traps

Each was found by reading the code or the provider docs. Each fails quietly.

### Trap 1 — the Pakasir webhook is unsigned, and will pay for plans it shouldn't

Pakasir's webhook POSTs exactly six fields:

```
amount, order_id, project, status, payment_method, completed_at
```

**There is no signature, no HMAC, and no shared secret in that payload.** Compare Stripe, which
signs every webhook with `Stripe-Signature` and is verified in the existing
`StripeWebhookController` via `Webhook::constructEvent()`.

The webhook URL is public. Anyone who can guess it and an `order_id` can POST
`{"status": "completed"}` and get a free Pro subscription. If you write the obvious handler —
read `status`, mark paid — you have shipped exactly that.

**The webhook is a hint that something happened, never evidence of what.** On every call, throw
the body away except for `order_id`, and ask Pakasir directly:

```
GET https://app.pakasir.com/api/transactiondetail?project=…&order_id=…&amount=…&api_key=…
```

Grant the plan only if *that* response says completed. Pakasir's own docs recommend this. Code
is in Section 8.2.

### Trap 2 — the Stripe webhook silently swallows every event you are about to add

`StripeWebhookController@handle` has this, about 100 lines in:

```php
if ($event->type !== 'checkout.session.completed') {
    Log::info('… EVENT IGNORED', ['event_type' => $event->type]);

    return response()->json(['received' => true]);
}
```

It returns **200 OK**. So when you add subscription billing, Stripe will send
`invoice.paid`, fire-and-forget, see a 200, and show a green tick in the dashboard. Your
handler never runs. The subscription never activates. The dashboard says everything is fine.

You will lose an afternoon to this. Restructure the method into a `switch` **first** — Section
8.1 — before writing any subscription logic.

### Trap 3 — the document limit has two different windows

Read the requirement carefully:

- Free: 3 documents **per week**
- Pro: 100 documents **per month**

These are not the same period. If you hardcode `startOfMonth()` the free plan becomes 3 per
month — a quarter of the intended allowance — and nobody notices until a customer complains
they cannot upload.

The period is part of the plan, so it lives in the config next to the number, and the checker
reads it. See Sections 5 and 7.2.

### Trap 4 — the member limit has to count invitations that nobody has accepted yet

`InvitationController@store` creates a row in `invitations`. The user does not exist yet.

If the limit only counts `organization->users()`, a 3-seat org can send 50 invitations while
technically holding 2 members, and end up with 52 when they are all accepted. The cap is
enforced at a moment that never arrives.

Count **accepted members plus pending invitations**. Section 7.3.

### Trap 5 — the publishable key never reaches the browser

`config/services.php` has `'key' => env('STRIPE_KEY')`, and `.env` sets it. But nothing passes
it to the front end — `HandleInertiaRequests::share()` currently shares only `auth` and
`wallet`.

Stripe Elements cannot initialise without it. Symptom: `loadStripe(undefined)` and a card form
that renders an empty box with no console error worth reading. Fix is one line, Section 9.1.

The **publishable** key (`pk_…`) is designed to be public — shipping it to the browser is
correct. The **secret** key (`sk_…`) must never leave the server. Do not "fix" this by sharing
`services.stripe.secret`.

---

## 5. Phase 1 — Plan definitions

**Create `config/plans.php`.** Everything downstream reads this file.

```php
<?php

/*
 | Plan catalogue. Single source of truth for pricing and limits.
 |
 | - Prices are USD cents (integers), matching the wallet convention.
 | - A `null` limit means unlimited.
 | - `documents.period` is 'week' or 'month' and differs per plan — see trap 3.
 | - `stripe_price_id` comes from the Stripe Dashboard (Products → Add product).
 |   Create the recurring monthly price there and paste the `price_…` id into .env.
 */

return [

    'free' => [
        'label' => 'Free',
        'description' => 'For trying things out.',
        'price_usd_cents' => 0,
        'stripe_price_id' => null,
        'self_serve' => true,
        'limits' => [
            'documents' => ['limit' => 3, 'period' => 'week'],
            'members' => 3,
            'storage_bytes' => 100 * 1024 * 1024,        // 100 MB
        ],
    ],

    'pro' => [
        'label' => 'Pro',
        'description' => 'For small teams sending contracts regularly.',
        'price_usd_cents' => 1000,                        // $10.00
        'stripe_price_id' => env('STRIPE_PRICE_PRO'),
        'self_serve' => true,
        'limits' => [
            'documents' => ['limit' => 100, 'period' => 'month'],
            'members' => 10,
            'storage_bytes' => 10 * 1024 * 1024 * 1024,   // 10 GB
        ],
    ],

    'enterprise' => [
        'label' => 'Enterprise',
        'description' => 'Custom limits and terms. Talk to us.',
        'price_usd_cents' => 5000,                        // "from $50" — display only
        'stripe_price_id' => null,
        'self_serve' => false,                            // decision 3.7
        'contact_email' => 'sales@bebem.my.id',
        'limits' => [
            'documents' => ['limit' => null, 'period' => 'month'],
            'members' => null,
            'storage_bytes' => null,
        ],
    ],

];
```

Add to `.env` and `.env.example`:

```
STRIPE_PRICE_PRO=price_xxxxxxxxxxxxx

PAKASIR_BASE_URL=https://app.pakasir.com
PAKASIR_PROJECT=your-project-slug
PAKASIR_API_KEY=your-api-key
```

And extend `config/services.php` — keep the existing `stripe` block, add one below it:

```php
    'pakasir' => [
        'base_url' => env('PAKASIR_BASE_URL', 'https://app.pakasir.com'),
        'project' => env('PAKASIR_PROJECT'),
        'api_key' => env('PAKASIR_API_KEY'),
    ],
```

> Config is cached in production. After editing either file:
> `docker compose exec app php artisan config:clear`

---

## 6. Phase 2 — Database

Three new tables and one alter. Create each with:

```bash
docker compose exec app php artisan make:migration create_subscriptions_table
```

### 6.1 `subscriptions`

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();

    // Unique: one subscription per organization, mutated in place (decision 3.6).
    $table->foreignUuid('organization_id')
        ->unique()
        ->constrained()
        ->cascadeOnDelete();

    $table->string('plan', 20)->default('free');

    // active | past_due | cancelled — mirrors Stripe's lifecycle.
    $table->string('status', 20)->default('active');

    // stripe | pakasir. Null on free — nobody paid for it (decision 3.2).
    $table->string('provider', 20)->nullable();

    $table->string('stripe_customer_id')->nullable()->index();
    $table->string('stripe_subscription_id')->nullable()->unique();

    $table->timestamp('subscribed_at')->nullable();

    // Null means "never expires" — that is the free plan, as specified.
    $table->timestamp('expired_at')->nullable();

    $table->timestamp('cancelled_at')->nullable();

    $table->timestamps();

    $table->index(['plan', 'status']);
    $table->index('expired_at');
});
```

Why the extra columns beyond what the task listed:

- **`status`** — a Stripe card can fail on renewal. The subscription is then `past_due`: still
  `pro`, not yet dead, Stripe is retrying. Without this you must either cut them off instantly
  or let them run free forever.
- **`provider`** — decides whether renewal is automatic. Decision 3.2.
- **`stripe_customer_id` / `stripe_subscription_id`** — needed to cancel, upgrade, or match an
  incoming webhook back to a row.
- **`subscribed_at`** — the task asks the Plan page to show "subscribed from".

### 6.2 `cards_info`

```php
Schema::create('cards_info', function (Blueprint $table) {
    $table->id();

    $table->foreignUuid('organization_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('card_brand', 20)->nullable();        // visa, mastercard…
    $table->string('card_last_four', 4);
    $table->unsignedSmallInteger('card_exp_month');
    $table->unsignedSmallInteger('card_exp_year');

    // The Stripe token. This is what we charge — we never hold the real number.
    $table->string('stripe_payment_method_id')->unique();

    $table->boolean('is_default')->default(false);

    $table->timestamps();

    $table->index(['organization_id', 'is_default']);
});
```

**There is deliberately no column for the card number or the CVC.** Last four and expiry are
PCI-safe to store; the PAN and CVC are not, and Elements never hands them to us anyway
(decision 3.1). If you ever find yourself adding a `card_number` column, stop.

`unsignedSmallInteger` for the year holds `2031` fine and rejects nonsense.

### 6.3 `subscription_payments`

One row per payment attempt, written **before** the user is sent to pay. See decision 3.5.

```php
Schema::create('subscription_payments', function (Blueprint $table) {
    $table->id();

    $table->foreignUuid('organization_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('subscription_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->string('plan', 20);
    $table->string('provider', 20);                      // stripe | pakasir

    // Our reference. Pakasir's API is keyed on this, so it must be unique
    // and must exist before we call them (trap 1).
    $table->string('order_id')->unique();

    $table->string('currency', 3);

    // Amount actually charged, in that currency's smallest unit.
    $table->unsignedBigInteger('amount');

    // Canonical USD cents — same convention as the wallet tables.
    $table->unsignedBigInteger('amount_usd_cents');

    $table->decimal('exchange_rate', 18, 8)->nullable();

    $table->string('status', 30)->default('pending');    // pending | paid | failed | expired

    $table->string('stripe_payment_intent_id')->nullable()->index();
    $table->string('stripe_invoice_id')->nullable()->index();

    $table->timestamp('paid_at')->nullable();

    $table->json('metadata')->nullable();

    $table->timestamps();

    $table->index(['organization_id', 'created_at']);
    $table->index(['status', 'created_at']);
});
```

### 6.4 Alter `wallet_topups`

Two columns, so top-ups can use Pakasir too (decision 3.9):

```php
Schema::table('wallet_topups', function (Blueprint $table) {
    $table->string('provider', 20)->default('stripe');
    $table->string('order_id')->nullable()->unique();
});
```

`default('stripe')` backfills existing rows correctly — every top-up so far went through Stripe.

Run everything:

```bash
docker compose exec app php artisan migrate
```

### 6.5 Checkpoint

```bash
docker compose exec app php artisan tinker --execute="echo implode(',', Schema::getColumnListing('subscriptions'));"
```

Do not continue until that prints the columns.

---

## 7. Phase 3 — Models and the plan gate

### 7.1 The models

**`app/Models/Subscription.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id',
        'plan',
        'status',
        'provider',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscribed_at',
        'expired_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The plan whose limits actually apply right now.
     *
     * A lapsed paid plan falls back to free rather than staying paid —
     * this is what stops an expired Pakasir subscription running forever
     * (decision 3.2).
     */
    public function effectivePlan(): string
    {
        if ($this->plan === 'free') {
            return 'free';
        }

        if ($this->status === 'cancelled') {
            return 'free';
        }

        if ($this->expired_at && $this->expired_at->isPast()) {
            return 'free';
        }

        return $this->plan;
    }

    public function config(): array
    {
        return config('plans.'.$this->effectivePlan());
    }
}
```

Note `effectivePlan()` deliberately still returns the paid plan when `status` is `past_due` —
Stripe is mid-retry and cutting the customer off during a retry window is hostile.

**`app/Models/CardInfo.php`** — the table name does not follow Eloquent's guess, so set it:

```php
class CardInfo extends Model
{
    protected $table = 'cards_info';

    protected $fillable = [
        'organization_id',
        'card_brand',
        'card_last_four',
        'card_exp_month',
        'card_exp_year',
        'stripe_payment_method_id',
        'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
```

**`app/Models/SubscriptionPayment.php`** — same shape, `$fillable` covering every column from
6.3 except `id` and the timestamps, and `casts` for `paid_at` (`datetime`) and `metadata`
(`array`).

**Add four relations to `Organization`** (`app/Models/Organization.php`), next to the existing
`wallet()`.

`Organization` currently has `owner`, `users`, `documents`, `templates` and `wallet` — and
**nothing else**. All four below are missing, including `invitations()`, which the member-limit
check in 7.2 depends on. Add all four now or that check fatals with
`Call to undefined method`:

```php
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function cards()
    {
        return $this->hasMany(CardInfo::class);
    }

    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }
```

`HasOne` is already imported at the top of the file. `Invitation` is in `App\Models`, same
namespace, so it needs no import.

### 7.2 `PlanService` — the one place limits are evaluated

**Create `app/Services/PlanService.php`.** Every gate calls this; no controller does its own
arithmetic.

```php
<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Subscription;
use Carbon\Carbon;

class PlanService
{
    public function subscriptionFor(Organization $organization): Subscription
    {
        return $organization->subscription()->firstOrCreate(
            ['organization_id' => $organization->id],
            ['plan' => 'free', 'status' => 'active', 'expired_at' => null],
        );
    }

    public function limits(Organization $organization): array
    {
        return $this->subscriptionFor($organization)->config()['limits'];
    }

    /**
     * Start of the current quota window. The period comes from the plan,
     * never from a hardcoded month — see trap 3.
     */
    public function documentPeriodStart(Organization $organization): Carbon
    {
        $period = $this->limits($organization)['documents']['period'];

        return $period === 'week'
            ? Carbon::now()->startOfWeek()
            : Carbon::now()->startOfMonth();
    }

    public function documentsUsed(Organization $organization): int
    {
        return Document::query()
            ->where('organization_id', $organization->id)
            ->where('created_at', '>=', $this->documentPeriodStart($organization))
            ->count();
    }

    public function storageUsedBytes(Organization $organization): int
    {
        return (int) Document::query()
            ->where('organization_id', $organization->id)
            ->sum('file_size');
    }

    /**
     * Accepted members plus invitations still outstanding — see trap 4.
     */
    public function membersUsed(Organization $organization): int
    {
        return $organization->users()->count()
            + $organization->invitations()->whereNull('accepted_at')->count();
    }

    public function canUploadDocument(Organization $organization): bool
    {
        $limit = $this->limits($organization)['documents']['limit'];

        // null means unlimited (enterprise) — not zero. Decision 3.7.
        return $limit === null
            || $this->documentsUsed($organization) < $limit;
    }

    public function canAddMember(Organization $organization): bool
    {
        $limit = $this->limits($organization)['members'];

        return $limit === null
            || $this->membersUsed($organization) < $limit;
    }

    public function canStore(Organization $organization, int $bytes): bool
    {
        $limit = $this->limits($organization)['storage_bytes'];

        return $limit === null
            || ($this->storageUsedBytes($organization) + $bytes) <= $limit;
    }

    /** Shape consumed by the Plan page and the shared Inertia props. */
    public function usage(Organization $organization): array
    {
        $limits = $this->limits($organization);

        return [
            'documents' => [
                'used' => $this->documentsUsed($organization),
                'limit' => $limits['documents']['limit'],
                'period' => $limits['documents']['period'],
            ],
            'members' => [
                'used' => $this->membersUsed($organization),
                'limit' => $limits['members'],
            ],
            'storage' => [
                'used' => $this->storageUsedBytes($organization),
                'limit' => $limits['storage_bytes'],
            ],
        ];
    }
}
```

`membersUsed()` needs the `invitations()` relation added in 7.1. The `invitations` table does
have an `accepted_at` column, so `whereNull('accepted_at')` correctly means "still outstanding".

Every `null` check is written as `=== null` rather than a falsy test on purpose. `0` is a real
limit that means "none allowed"; `null` means unlimited. A truthy check collapses them and
hands enterprise customers a zero quota.

### 7.3 Create the free subscription at registration

`Organization::create()` appears in **exactly one place** in the codebase —
`RegisteredUserController@store`. That is the only hook needed. Invited users join an existing
organization, which already has a subscription, so the invitation path needs no change.

In `app/Http/Controllers/Auth/RegisteredUserController.php`, after the `$organization->update([...])`
call that sets `owner_id`:

```php
        $organization->subscription()->create([
            'plan' => 'free',
            'status' => 'active',
            'expired_at' => null,
        ]);
```

> **Note for whoever picks this up after PR #28:** that PR touches the same method to add phone
> and country. Rebase before editing, or you will be resolving a conflict in the middle of a
> Stripe integration.

### 7.4 Enforce the gates

**Documents** — `DocumentController@store`, immediately after `$request->validate([...])` and
**before** `$file->store(...)` (do not write the file and then reject it):

```php
        $organization = $request->user()->organization;
        $planService = app(\App\Services\PlanService::class);

        if (! $planService->canUploadDocument($organization)) {
            $limits = $planService->limits($organization)['documents'];

            return back()->withErrors([
                'file' => "Your plan allows {$limits['limit']} documents per {$limits['period']}. Upgrade to upload more.",
            ]);
        }

        if (! $planService->canStore($organization, $request->file('file')->getSize())) {
            return back()->withErrors([
                'file' => 'This upload would exceed your plan\'s storage limit.',
            ]);
        }
```

**Members** — `InvitationController@store`, right after the existing `abort_unless(...canManageMembers())`:

```php
        $planService = app(\App\Services\PlanService::class);
        $organization = $request->user()->organization;

        if (! $planService->canAddMember($organization)) {
            return back()->withErrors([
                'email' => 'You have reached the member limit for your plan. Upgrade to invite more people.',
            ]);
        }
```

`back()->withErrors()` is the established pattern here — `BillingTopupController` uses it, and
Inertia surfaces it on `form.errors.<field>` with no extra work.

### 7.5 Share the plan with every page

`app/Http/Middleware/HandleInertiaRequests.php`, inside the existing `array_merge(parent::share(...))`,
alongside `wallet`:

```php
            'plan' => $organization
                ? [
                    'key' => $organization->subscription?->effectivePlan() ?? 'free',
                    'label' => config('plans.'.($organization->subscription?->effectivePlan() ?? 'free').'.label'),
                    'expiredAt' => $organization->subscription?->expired_at,
                ]
                : null,

            'stripeKey' => config('services.stripe.key'),   // trap 5
```

`stripeKey` is the publishable `pk_…`. **Never** share `services.stripe.secret`.

---

## 8. Phase 4 — Webhooks

Do this **before** the payment flows. If the webhook is wrong, the payment flows appear to work
and grant nothing, and you will debug the wrong half.

### 8.1 Restructure the Stripe webhook first (trap 2)

In `StripeWebhookController@handle`, replace the early-return block with a `switch`. Keep the
signature verification above it exactly as it is — it is correct.

```php
        switch ($event->type) {
            case 'checkout.session.completed':
                return $this->handleCheckoutCompleted($event->data->object);

            case 'invoice.paid':
                return $this->handleInvoicePaid($event->data->object);

            case 'invoice.payment_failed':
                return $this->handleInvoiceFailed($event->data->object);

            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($event->data->object);

            default:
                Log::info('Stripe event ignored', ['type' => $event->type]);

                return response()->json(['received' => true]);
        }
```

Move the whole existing top-up body into `handleCheckoutCompleted()` **unchanged**. It works and
the wallet flow still depends on it.

The new handlers, keeping the same `DB::transaction` + `lockForUpdate()` idempotency shape the
existing code already uses:

```php
    protected function handleInvoicePaid($invoice)
    {
        $stripeSubscriptionId = $invoice->subscription ?? null;

        if (! $stripeSubscriptionId) {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($invoice, $stripeSubscriptionId) {
            $subscription = Subscription::query()
                ->lockForUpdate()
                ->where('stripe_subscription_id', $stripeSubscriptionId)
                ->first();

            if (! $subscription) {
                Log::warning('Stripe invoice for unknown subscription', [
                    'stripe_subscription_id' => $stripeSubscriptionId,
                ]);

                return;
            }

            // Stripe owns the period end. Trust it over any date we compute.
            $subscription->update([
                'status' => 'active',
                'expired_at' => Carbon::createFromTimestamp($invoice->period_end),
            ]);

            SubscriptionPayment::where('stripe_invoice_id', $invoice->id)
                ->update(['status' => 'paid', 'paid_at' => now()]);
        });

        return response()->json(['received' => true]);
    }
```

`invoice.payment_failed` sets `status = 'past_due'` and leaves `plan` alone.
`customer.subscription.deleted` sets `plan = 'free'`, `status = 'cancelled'`, `cancelled_at = now()`.

**Add the events in the Stripe Dashboard** (Developers → Webhooks → your endpoint → Select
events). A handler for an event Stripe was never told to send is dead code.

### 8.2 The Pakasir webhook — never trust the body (trap 1)

**Create `app/Services/PakasirService.php`:**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PakasirService
{
    protected string $baseUrl;
    protected string $project;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.pakasir.base_url'), '/');
        $this->project = config('services.pakasir.project');
        $this->apiKey = config('services.pakasir.api_key');
    }

    /** Create a QRIS transaction. Returns the decoded body. */
    public function createQris(string $orderId, int $amountIdr): array
    {
        $response = Http::asJson()
            ->post($this->baseUrl.'/api/transactioncreate/qris', [
                'project' => $this->project,
                'order_id' => $orderId,
                'amount' => $amountIdr,
                'api_key' => $this->apiKey,
            ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Ask Pakasir what actually happened. This — not the webhook body —
     * is what decides whether a plan gets granted. See trap 1.
     */
    public function transactionDetail(string $orderId, int $amountIdr): array
    {
        $response = Http::get($this->baseUrl.'/api/transactiondetail', [
            'project' => $this->project,
            'order_id' => $orderId,
            'amount' => $amountIdr,
            'api_key' => $this->apiKey,
        ]);

        $response->throw();

        return $response->json();
    }
}
```

**Create `app/Http/Controllers/PakasirWebhookController.php`:**

```php
    public function handle(Request $request, PakasirService $pakasir)
    {
        // The ONLY field we take from the request body. Everything else in it
        // is attacker-controlled — the payload carries no signature (trap 1).
        $orderId = $request->input('order_id');

        if (! $orderId) {
            return response()->json(['error' => 'order_id missing'], 400);
        }

        $payment = SubscriptionPayment::where('order_id', $orderId)->first();

        if (! $payment) {
            Log::warning('Pakasir webhook for unknown order', ['order_id' => $orderId]);

            return response()->json(['received' => true]);
        }

        // Amount comes from OUR record, not from the request — otherwise a
        // caller could look up a different transaction by varying it.
        $detail = $pakasir->transactionDetail($orderId, (int) $payment->amount);

        if (($detail['status'] ?? null) !== 'completed') {
            Log::info('Pakasir transaction not completed', [
                'order_id' => $orderId,
                'status' => $detail['status'] ?? null,
            ]);

            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($payment) {
            $payment = SubscriptionPayment::lockForUpdate()->find($payment->id);

            if ($payment->status === 'paid') {
                return;                       // already handled
            }

            $payment->update(['status' => 'paid', 'paid_at' => now()]);

            $payment->organization->subscription->update([
                'plan' => $payment->plan,
                'status' => 'active',
                'provider' => 'pakasir',
                'subscribed_at' => now(),
                // QRIS cannot auto-renew, so the expiry is ours to set (decision 3.2).
                'expired_at' => now()->addDays(30),
            ]);
        });

        return response()->json(['received' => true]);
    }
```

Verify the exact success string (`completed` vs `paid`) against a real sandbox transaction
before shipping — if it is wrong, every payment silently fails to grant.

**Register the route** in `routes/web.php`, next to the existing Stripe webhook at line 54 and
**outside** the `auth` group:

```php
Route::post('/pakasir/webhook', [PakasirWebhookController::class, 'handle'])
    ->name('pakasir.webhook');
```

**And exempt it from CSRF** in `app/Http/Middleware/VerifyCsrfToken.php` — the array already
contains `'stripe/webhook'`:

```php
    protected $except = [
        'stripe/webhook',
        'pakasir/webhook',
    ];
```

Forgetting this gives a 419 that never reaches your controller, and Pakasir will not tell you.

---

## 9. Phase 5 — The Stripe card form

### 9.1 Front-end dependency

```bash
npm install @stripe/stripe-js
```

This is the one piece of Stripe JavaScript the integration needs, and the reason is decision
3.1 — read it if you skipped ahead. Trap 5 covers getting the key to the browser; it is the
`stripeKey` line added in Section 7.5.

### 9.2 Server: start the subscription

**Create `app/Http/Controllers/SubscriptionController.php`.** Owner-only, matching
`BillingController`'s existing guard.

`checkout()` creates (or reuses) the Stripe Customer and returns a **SetupIntent** client
secret. A SetupIntent — not a PaymentIntent — because we are saving a card for recurring use;
the first charge comes from the subscription itself:

```php
    public function checkout(Request $request, StripeService $stripe)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $organization = $user->organization;
        $subscription = app(PlanService::class)->subscriptionFor($organization);

        $customerId = $subscription->stripe_customer_id;

        if (! $customerId) {
            $customer = $stripe->client()->customers->create([
                'email' => $user->email,
                'name' => $organization->name,
                'metadata' => ['organization_id' => $organization->id],
            ]);

            $customerId = $customer->id;
            $subscription->update(['stripe_customer_id' => $customerId]);
        }

        $setupIntent = $stripe->client()->setupIntents->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
        ]);

        return response()->json(['clientSecret' => $setupIntent->client_secret]);
    }
```

`subscribe()` runs after the browser has confirmed the card. It receives only the
`payment_method` **id** — never card data:

```php
    public function subscribe(Request $request, StripeService $stripe)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $validated = $request->validate([
            'plan' => ['required', Rule::in(['pro'])],          // enterprise is not self-serve
            'payment_method' => ['required', 'string'],
        ]);

        $organization = $user->organization;
        $subscription = app(PlanService::class)->subscriptionFor($organization);
        $planConfig = config('plans.'.$validated['plan']);

        abort_unless($planConfig['stripe_price_id'], 500, 'Stripe price is not configured.');

        $client = $stripe->client();

        $client->paymentMethods->attach($validated['payment_method'], [
            'customer' => $subscription->stripe_customer_id,
        ]);

        $client->customers->update($subscription->stripe_customer_id, [
            'invoice_settings' => [
                'default_payment_method' => $validated['payment_method'],
            ],
        ]);

        $stripeSubscription = $client->subscriptions->create([
            'customer' => $subscription->stripe_customer_id,
            'items' => [['price' => $planConfig['stripe_price_id']]],
            'metadata' => [
                'organization_id' => $organization->id,
                'plan' => $validated['plan'],
            ],
        ]);

        // Store the safe fragments the task asked for — never the PAN (decision 3.1).
        $pm = $client->paymentMethods->retrieve($validated['payment_method']);

        CardInfo::updateOrCreate(
            ['stripe_payment_method_id' => $pm->id],
            [
                'organization_id' => $organization->id,
                'card_brand' => $pm->card->brand,
                'card_last_four' => $pm->card->last4,
                'card_exp_month' => $pm->card->exp_month,
                'card_exp_year' => $pm->card->exp_year,
                'is_default' => true,
            ],
        );

        $subscription->update([
            'plan' => $validated['plan'],
            'status' => 'active',
            'provider' => 'stripe',
            'stripe_subscription_id' => $stripeSubscription->id,
            'subscribed_at' => now(),
            'expired_at' => Carbon::createFromTimestamp(
                $stripeSubscription->current_period_end
            ),
        ]);

        return redirect()->route('plan.index');
    }
```

The `invoice.paid` webhook will arrive moments later and set the same fields. That is
intentional and safe — both paths write the same values, and the webhook is what keeps every
*subsequent* month correct.

### 9.3 Client: our form, Stripe's iframe

**Create `resources/js/Components/plan/CardPaymentDialog.vue`.** Our layout, our `Dialog`, our
`Button`; only `#card-element` is Stripe's.

```js
import { loadStripe } from "@stripe/stripe-js";
import { usePage, router } from "@inertiajs/vue3";

const page = usePage();
const cardError = ref("");
const processing = ref(false);

let stripe = null;
let cardElement = null;

const mountCard = async () => {
    stripe = await loadStripe(page.props.stripeKey);   // trap 5

    // Styled to match our tokens — this is why it doesn't look like Stripe's form.
    cardElement = stripe.elements().create("card", {
        style: {
            base: {
                color: "#e5e7eb",
                fontFamily: "Inter, sans-serif",
                fontSize: "14px",
                "::placeholder": { color: "#6b7280" },
            },
            invalid: { color: "#ef4444" },
        },
    });

    cardElement.mount("#card-element");
};

const submit = async () => {
    processing.value = true;
    cardError.value = "";

    const { clientSecret } = await fetch(route("plan.checkout"), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
    }).then((r) => r.json());

    const { setupIntent, error } = await stripe.confirmCardSetup(clientSecret, {
        payment_method: { card: cardElement },
    });

    if (error) {
        cardError.value = error.message;
        processing.value = false;
        return;
    }

    router.post(route("plan.subscribe"), {
        plan: "pro",
        payment_method: setupIntent.payment_method,
    });
};
```

`onMounted(mountCard)`, and in the template a `<div id="card-element" />` inside a bordered
wrapper styled like `Input`, with `cardError` rendered in `text-destructive` below it.

The card digits go from the iframe straight to Stripe. They never enter our Vue state, our
network tab, or our server.

### 9.4 Routes

In `routes/web.php`, inside the existing `Route::middleware('auth', 'verified')` group:

```php
    Route::get('/plan', [SubscriptionController::class, 'index'])->name('plan.index');
    Route::post('/plan/checkout', [SubscriptionController::class, 'checkout'])->name('plan.checkout');
    Route::post('/plan/subscribe', [SubscriptionController::class, 'subscribe'])->name('plan.subscribe');
    Route::post('/plan/cancel', [SubscriptionController::class, 'cancel'])->name('plan.cancel');
```

---

## 10. Phase 6 — Pakasir QR

### 10.1 Server

`payWithQr()` converts the USD price to IDR, writes the payment row **first**, then asks
Pakasir for a QR string:

```php
    public function payWithQr(Request $request, PakasirService $pakasir)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $validated = $request->validate([
            'plan' => ['required', Rule::in(['pro'])],
        ]);

        $organization = $user->organization;
        $subscription = app(PlanService::class)->subscriptionFor($organization);
        $planConfig = config('plans.'.$validated['plan']);

        $rate = app(ExchangeRateService::class)->usdToIdr();

        // Same failure mode the top-up controller already handles — do not
        // invent a fallback rate.
        if (! $rate || $rate <= 0) {
            return back()->withErrors([
                'plan' => 'The USD/IDR exchange rate is unavailable. Please try again shortly.',
            ]);
        }

        $amountIdr = (int) round(($planConfig['price_usd_cents'] / 100) * $rate);

        $orderId = 'SUB-'.$organization->id.'-'.now()->timestamp;

        $payment = SubscriptionPayment::create([
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'plan' => $validated['plan'],
            'provider' => 'pakasir',
            'order_id' => $orderId,
            'currency' => 'IDR',
            'amount' => $amountIdr,
            'amount_usd_cents' => $planConfig['price_usd_cents'],
            'exchange_rate' => $rate,
            'status' => 'pending',
        ]);

        $result = $pakasir->createQris($orderId, $amountIdr);

        return Inertia::render('Plan/Qr', [
            'orderId' => $orderId,
            'amountIdr' => $amountIdr,
            'qrString' => $result['payment_number'] ?? null,
            'expiredAt' => $result['expired_at'] ?? null,
        ]);
    }
```

The row is written **before** the API call on purpose: if Pakasir responds and we crash before
saving, the webhook arrives for an `order_id` we have no record of and the customer has paid for
nothing.

### 10.2 Client

Pakasir returns the QR as a **string**, not an image — it is our job to render it:

```bash
npm install qrcode
```

```js
import QRCode from "qrcode";

onMounted(() => {
    QRCode.toCanvas(document.getElementById("qr-canvas"), props.qrString, {
        width: 260,
        margin: 2,
    });
});
```

Poll for completion while the page is open, since the webhook lands server-side:

```js
const poll = setInterval(() => {
    router.reload({ only: ["plan"] });
}, 5000);

onUnmounted(() => clearInterval(poll));
```

Show the IDR amount as text next to the QR — some banking apps require the payer to confirm it.

State plainly on this screen that the plan **will not renew automatically** and must be paid
again in 30 days (decision 3.2). A user who assumes auto-renewal and silently drops to free has
a legitimate complaint.

---

## 11. Phase 7 — The Plan page

`SubscriptionController@index`:

```php
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isOwner(), 403);

        $organization = $user->organization;
        $planService = app(PlanService::class);
        $subscription = $planService->subscriptionFor($organization);

        return Inertia::render('Plan/Index', [
            'subscription' => [
                'plan' => $subscription->effectivePlan(),
                'status' => $subscription->status,
                'provider' => $subscription->provider,
                'subscribedAt' => $subscription->subscribed_at,
                'expiredAt' => $subscription->expired_at,
                'autoRenews' => $subscription->provider === 'stripe',
            ],
            'usage' => $planService->usage($organization),
            'plans' => config('plans'),
            'card' => $organization->cards()->where('is_default', true)->first(),
            'payments' => $organization->subscriptionPayments()
                ->latest()->limit(10)->get(),
        ]);
    }
```

**Create `resources/js/Pages/Plan/Index.vue`.** Copy the page shell from
`resources/js/Pages/Billing/Index.vue` — `AppLayout`, `PageHeader`, `PageSection`, `FadeIn`.
Do not invent a new layout.

Four sections:

1. **Current plan** — label, price, status badge, "Subscribed since", "Expires". For a Pakasir
   subscription, replace "Renews automatically" with a clear "Renew before {date}" and a pay
   button.
2. **Usage** — three bars. `resources/js/components/ui/progress` already exists; use it.
   Render `null` limits as "Unlimited" and **do not** compute a percentage from `null` — it
   produces `NaN` and a bar that renders at a random width.
3. **Plans** — three cards from the `plans` prop. The current one is marked and its button
   disabled. Cheaper plans say "Downgrade", dearer say "Upgrade". Enterprise shows "Contact
   sales" opening `mailto:` from `contact_email`.
4. **Payment method** — `•••• {{ card.card_last_four }}` and
   `{{ card.card_exp_month }}/{{ card.card_exp_year }}`, or an empty state.

Add to the sidebar in `resources/js/Components/Layout/navigation.js`, directly after the
`Billing` entry — it already supports the flag:

```js
            {
                title: "Plan",
                icon: Gem,
                route: "plan.index",
                ownerOnly: true,
            },
```

Import `Gem` from `lucide-vue-next` alongside the existing icons.

---

## 12. Verification

### Setup

```bash
docker compose ps
docker compose exec app php artisan migrate
docker compose exec app php artisan config:clear
npm install && npm run build
```

In the **Stripe Dashboard (test mode)**: create a Pro product with a recurring $10/month price,
put the `price_…` id in `STRIPE_PRICE_PRO`, and add `invoice.paid`,
`invoice.payment_failed` and `customer.subscription.deleted` to the webhook endpoint.

For local webhooks: `stripe listen --forward-to localhost:8000/stripe/webhook`.

### Registration

- [ ] Register a new workspace
- [ ] `subscriptions` has one row for it: `plan=free`, `status=active`, `expired_at` **NULL**

```bash
docker compose exec app php artisan tinker --execute="\$s = App\Models\Subscription::latest()->first(); echo \$s->plan.' | '.\$s->status.' | '.var_export(\$s->expired_at, true);"
```

### Limits (as a free org)

- [ ] Upload 3 documents — all succeed
- [ ] Upload a 4th **in the same calendar week** — rejected with the plan message
- [ ] Confirm the window is weekly, not monthly (trap 3): set a document's `created_at` to 8
      days ago in tinker and confirm the allowance frees up
- [ ] Invite until 3 members total, then invite once more — rejected
- [ ] With 1 member and 2 **pending** invitations, a 3rd invite is rejected (trap 4) — this is
      the one most likely to be wrong
- [ ] Upload a file that would push total `file_size` past 100 MB — rejected

### Stripe upgrade

- [ ] `/plan` shows Free with three usage bars
- [ ] "Upgrade to Pro" opens **our** dialog — no redirect, URL stays on our domain
- [ ] Card field renders and is styled like the rest of the form
- [ ] `4242 4242 4242 4242`, any future expiry, any CVC → succeeds
- [ ] `4000 0000 0000 0002` (declined) → error shown in the dialog, plan unchanged
- [ ] After success: `plan=pro`, `provider=stripe`, `expired_at` ≈ one month out
- [ ] `cards_info` has one row with the right last four and expiry
- [ ] **Confirm no PAN anywhere:** `grep -r "4242424242" storage/logs/` returns nothing
- [ ] Limits lift immediately — a 4th document now uploads

### Stripe webhook (trap 2)

- [ ] `stripe trigger invoice.paid` → handler runs, `expired_at` moves
- [ ] The log shows the handler firing, **not** `EVENT IGNORED`

### Pakasir

- [ ] "Pay with QR" renders a scannable QR and shows the IDR amount
- [ ] A `subscription_payments` row exists with `status=pending` before paying
- [ ] Use the sandbox `POST /api/paymentsimulation` to complete it
- [ ] Webhook arrives → row flips to `paid`, plan becomes `pro`, `expired_at` = +30 days
- [ ] The page states clearly that this does not auto-renew

**Then attack it (trap 1).** With a pending payment, forge the webhook:

```bash
curl -X POST http://localhost:8000/pakasir/webhook \
  -H "Content-Type: application/json" \
  -d '{"order_id":"<a real pending order_id>","status":"completed","amount":150000}'
```

- [ ] The plan is **NOT** granted
- [ ] The log shows the transaction was re-checked against Pakasir and came back not-completed

**If this grants the plan, the verification step is the deliverable — stop and fix it.**

### Nothing else broke

- [ ] An existing wallet top-up still completes end to end
- [ ] `checkout.session.completed` still credits the wallet
- [ ] A non-owner gets 403 on `/plan` and sees no sidebar entry
- [ ] Both light and dark themes render the Plan page correctly
- [ ] At ~375px the plan cards and usage bars stack without horizontal scroll

---

## 13. Guardrails

**Security and money**

- **Never store a card number or CVC.** Not in a column, not in a log, not in `metadata`.
- **Never send `services.stripe.secret` to the browser.** Only `services.stripe.key` (`pk_…`).
- **Never trust the Pakasir webhook body** beyond `order_id`. Re-verify via
  `/api/transactiondetail`. See trap 1.
- **Never take the charge amount from a request.** Read it from `config/plans.php`, server-side.
- **Do not skip the CSRF exemption** for `pakasir/webhook` — you will get a silent 419.
- **Do not use floats for money.** Integer USD cents, matching the existing wallet tables.
- **Do not invent a fallback exchange rate** when `usdToIdr()` returns `null`. Fail the request,
  as `BillingTopupController` already does.

**Architecture**

- **Do not add Laravel Cashier.** `stripe/stripe-php` is already here and the existing webhook
  is hand-rolled; Cashier brings its own tables and conventions that will fight this schema.
- **Do not put subscription charges through the wallet.** Decision 3.4.
- **Do not build recurring Pakasir billing.** QRIS cannot do it. Decision 3.2.
- **Do not hardcode limits in controllers.** Read `config/plans.php`.
- **Do not treat `null` limits as zero.** `null` is unlimited; `0` is none. Use `=== null`.
- **Do not hardcode `startOfMonth()`** for the document window. Trap 3.
- **Do not add a self-serve enterprise checkout.** Decision 3.7.
- **Do not rename the `plan` column values.** They are `free`, `pro`, `enterprise`. Decision 3.8.

**Process**

- **Do not run `php artisan` from the host shell.** Prefix `docker compose exec app`.
- **Do not run `migrate:fresh`.** These migrations are additive; `fresh` drops every table.
- **Do not skip Section 8.1.** Restructure the Stripe webhook before writing payment code, or
  you will debug the wrong half of the system.
- **Rebase on PR #28 before editing `RegisteredUserController`.** Both touch `store()`.
- Commit per phase. One large commit makes a regression impossible to bisect.

---

## 14. Commits and PR

```
feat: add plan catalogue config with limits and pricing
feat: add subscriptions, cards_info and subscription_payments tables
feat: add Subscription, CardInfo and SubscriptionPayment models
feat: create a free subscription when an organization is registered
feat: enforce document, member and storage limits per plan
refactor: route Stripe webhook events through a switch
feat: handle subscription lifecycle events from Stripe
feat: subscribe with a card using Stripe Elements
feat: pay for a plan with a Pakasir QRIS transaction
feat: add the Plan page with usage and upgrade actions
```

Open one PR: **"feat: subscription plans with card and QR payment"**.

In the description, include:

- Screenshots of the Plan page (free and Pro), the card dialog, and the QR screen
- The tinker output showing a fresh registration with `free | active | NULL`
- **The forged-webhook curl from Section 12 and its rejection.** This is the single most
  important thing a reviewer needs to see; a diff alone cannot show that the verification
  round-trip is wired up.

Call out decision 3.1 explicitly in the description. A reviewer who sees `@stripe/stripe-js` in
`package.json` will reasonably ask why, given the task said not to use Stripe's form — the
answer is that Elements is our form with a Stripe-owned iframe for the digits, and the
alternative is PCI SAQ-D.

Also flag decision 3.2 for product: **Pakasir subscriptions do not auto-renew.** Somebody needs
to decide whether expiring customers get a reminder email, and that is not in this ticket.

---

## 15. Follow-up work this ticket deliberately leaves out

Raise these as separate tickets rather than growing this one:

- **A scheduled job to downgrade lapsed subscriptions.** `effectivePlan()` computes the fallback
  on read, so limits are enforced correctly the moment a plan lapses. But `plan` in the database
  still says `pro`, so reporting will overcount paid customers.
- **Renewal reminder emails**, especially for Pakasir (decision 3.2).
- **Proration on upgrade.** Right now upgrading mid-cycle charges a full month.
- **Downgrade when already over the new limit** — an org with 8 members dropping to Free
  (3 seats). Blocking and refunding are both defensible; product should choose.
- **Deleting a document does not free storage.** `DocumentController@destroy` removes the row
  but leaves the file on disk, so `SUM(file_size)` drops while real usage does not.
