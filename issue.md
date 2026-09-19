# Implement QRIS payments in the unified PaymentDialog

## 1. What we are building

`PaymentDialog.vue` already has a **Card** tab and a **QRIS** tab. The QRIS tab is a placeholder
with a `TODO`. The job is to make it real, for **both** things the dialog is used for:

- **Top up wallet** (`mode="topup"`) — user types a dollar amount
- **Upgrade to Pro** (`mode="plan"`) — fixed monthly price

When the user picks QRIS and presses the button, a QR code appears **inside the dialog**. They scan
it with an Indonesian banking or e-wallet app, and the dialog flips to a success state on its own.

The user must never leave the dialog. That is the whole point of the refactor.

---

## 2. What already exists

Read this table before writing anything. Roughly half the work is already done, and two files are
traps that look useful but are not.

| Thing | Where | State |
| --- | --- | --- |
| Pakasir API client | `app/Services/PakasirService.php` | **Works.** `createQris()` and `transactionDetail()`. Do not modify. |
| Plan QR backend | `SubscriptionController::payWithQr()` | Works, but renders a **full page**. We need JSON. |
| Plan QR route | `routes/web.php:264` — `POST /plan/qr` | Keep the route, change what it returns. |
| Pakasir webhook | `app/Http/Controllers/PakasirWebhookController.php` | Works for **plan payments only**. Needs to handle top-ups. |
| Status endpoint | `app/Http/Controllers/Api/PaymentStatusController.php` | **Broken — see section 3.** Also plan-only. |
| Top-up backend | `app/Http/Controllers/BillingTopupController.php` | **Stripe only.** No QRIS branch. |
| Top-up DB columns | `wallet_topups.provider`, `wallet_topups.order_id` | **Already migrated.** No new migration needed. |
| Wallet crediting | `WalletService::credit()` | Works. Mirror how `StripeWebhookController::handlePaymentIntentSucceeded()` calls it. |
| `qrcode` npm package | `package.json` | **Already installed.** Used by `Plan/Qr.vue`. |
| Full-page QR view | `resources/js/Pages/Plan/Qr.vue` | Becomes dead once the dialog works. **Delete in phase 6.** |
| Standalone QR dialog | `resources/js/Components/plan/QrPaymentDialog.vue` | **Orphaned — imported by nothing.** Plan-only, navigates away. Do not build on it. **Delete in phase 6.** |

> `QrPaymentDialog.vue` will look like a head start. It is not. It only handles the plan case, it
> navigates away instead of staying in the dialog, and nothing imports it. Read it for the IDR
> formatting and the "QRIS does not auto-renew" warning copy, then delete it.

---

## 3. Blocker: fix this first or nothing will work

`Plan/Qr.vue` polls `GET /api/payments/{orderId}/status`. That route lives in `routes/api.php`
behind `auth:sanctum`. But in `app/Http/Kernel.php:45`, the middleware that makes session cookies
work on API routes is **commented out**:

```php
'api' => [
    // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

So every poll returns **401**. And `checkStatus()` swallows it:

```js
} catch {
    // Transient failures are expected while polling; keep waiting.
}
```

The page polls forever, silently, and never notices the payment. If you build the dialog on this
endpoint without fixing it, you will get a QR code that works, a webhook that fires correctly, a
database row that says `paid` — and a dialog that spins until the user gives up. You will lose
hours to it.

### The fix: move the route to `web.php`

Do **not** uncomment the Sanctum middleware. That changes CSRF behaviour for every API route and is
a much bigger blast radius than this feature needs.

Delete the route from `routes/api.php`:

```php
// DELETE this line
Route::get('/payments/{orderId}/status', [PaymentStatusController::class, 'show']);
```

Also delete the now-unused `use App\Http\Controllers\Api\PaymentStatusController;` import at the
top of that file.

Add it to `routes/web.php`, inside the existing `Route::middleware('auth', 'verified')->group()`
that starts at line 96 — put it next to the plan routes around line 264:

```php
Route::get('/payments/{orderId}/status', [PaymentStatusController::class, 'show'])
    ->name('payments.status');
```

And import it at the top of `routes/web.php`:

```php
use App\Http\Controllers\Api\PaymentStatusController;
```

Leave the controller file in `app/Http/Controllers/Api/` — moving it is churn for no benefit.

The frontend URL stays almost the same, minus the `/api` prefix: `/payments/{orderId}/status`.

---

## 4. How the pieces fit together

Same shape for both modes. Learn it once:

```
1. User picks QRIS, presses the button
2. POST to the backend (plan.qr or billing.topups.store)
3. Backend: create a pending DB row with a unique order_id
4. Backend: call PakasirService::createQris()
5. Backend: return JSON { orderId, amountIdr, qrString, expiredAt }
6. Dialog: render qrString to a <canvas> with the qrcode package
7. Dialog: poll GET /payments/{orderId}/status every 3 seconds
8. User scans and pays
9. Pakasir POSTs our webhook -> we verify -> mark row paid, credit wallet / activate plan
10. The next poll sees "paid" -> dialog shows success, stops polling
```

Two order-id prefixes, so the webhook can tell them apart:

- `SUB-{organizationId}-{timestamp}` — plan payment (already used by `payWithQr`)
- `TOP-{organizationId}-{timestamp}` — wallet top-up (you will add this)

### The amount trap — read this twice

Pakasir only deals in **whole rupiah**. Both the webhook and `transactionDetail()` need the exact
IDR figure we charged. Where that figure lives differs per table:

| Table | Where the IDR amount lives | Why |
| --- | --- | --- |
| `subscription_payments` | the `amount` column | `payWithQr` stores `currency: 'IDR'`, `amount: $amountIdr` |
| `wallet_topups` | `metadata['amount_idr']` — **you must write it** | The `amount` column holds the **USD** figure the user typed |

If you compare the webhook's IDR amount against `wallet_topups.amount`, every top-up webhook will
be rejected as a mismatch, because you will be comparing `150000` against `10`. This is the single
most likely way to get this feature wrong.

---

## 5. Phase 1 — Plan QRIS returns JSON

**File:** `app/Http/Controllers/SubscriptionController.php`, `payWithQr()`, around lines 227–237.

Everything above the `createQris()` call stays exactly as it is. Only the return changes.

### Current

```php
$result = $pakasir->createQris($orderId, $amountIdr);

$payload = $result['payment'] ?? [];

return Inertia::render('Plan/Qr', [
    'orderId' => $orderId,
    'amountIdr' => $amountIdr,
    'qrString' => $payload['payment_number'] ?? null,
    'expiredAt' => $payload['expired_at'] ?? null,
]);
```

### Replace with

```php
$result = $pakasir->createQris($orderId, $amountIdr);

$payload = $result['payment'] ?? [];

return response()->json([
    'orderId' => $orderId,
    'amountIdr' => $amountIdr,
    'qrString' => $payload['payment_number'] ?? null,
    'expiredAt' => $payload['expired_at'] ?? null,
]);
```

That is the entire change — `Inertia::render('Plan/Qr', [...])` becomes `response()->json([...])`.

### Also: the error return

The exchange-rate failure earlier in this method (around line 205) still uses
`back()->withErrors([...])`. `back()` is an Inertia redirect, and the dialog calls this with
`axios`, so it will not produce a readable error. Change it to:

```php
return response()->json([
    'message' => 'The USD/IDR exchange rate is unavailable. Please try again shortly.',
], 422);
```

The dialog reads `error.response.data.message`, which is exactly what the existing `catch` in
`PaymentDialog.submit()` already does.

Leave the `use Inertia\Inertia;` import alone — `index()` still uses it.

---

## 6. Phase 2 — Top-up QRIS

**File:** `app/Http/Controllers/BillingTopupController.php`

This controller already computes the wallet credit, the FX rate and the pending `WalletTopup` row.
Reuse all of it. You are adding one branch just before the Stripe section.

### 6.1 Accept a `method` field

In the `$request->validate([...])` block around line 37, add:

```php
'method' => [
    'nullable',
    'string',
    'in:card,qris',
],
```

Nullable, so existing card callers that send no `method` keep working.

### 6.2 Add the QRIS branch

Insert this **after** the `$topup` is created (after the `Log::info('...TOPUP CREATED', ...)` call
around line 189) and **before** the `try {` that builds the Stripe PaymentIntent:

```php
if (($validated['method'] ?? 'card') === 'qris') {
    $rate = $this->exchangeRateService->usdToIdr();

    if (! $rate || $rate <= 0) {
        return response()->json([
            'message' => 'The USD/IDR exchange rate is unavailable. Please try again shortly.',
        ], 422);
    }

    $amountIdr = (int) round(($walletAmountUsdCents / 100) * $rate);
    $orderId = 'TOP-'.$organization->id.'-'.now()->timestamp;

    $topup->update([
        'provider' => 'pakasir',
        'order_id' => $orderId,
        // The webhook and transactionDetail() both need the exact rupiah figure.
        // wallet_topups.amount holds USD, so it cannot answer that.
        'metadata' => array_merge($topup->metadata ?? [], [
            'amount_idr' => $amountIdr,
        ]),
    ]);

    $result = app(\App\Services\PakasirService::class)->createQris($orderId, $amountIdr);
    $payload = $result['payment'] ?? [];

    return response()->json([
        'orderId' => $orderId,
        'amountIdr' => $amountIdr,
        'qrString' => $payload['payment_number'] ?? null,
        'expiredAt' => $payload['expired_at'] ?? null,
    ]);
}
```

`$walletAmountUsdCents` is already in scope — it is computed further up and is the canonical
USD-cent value whether the user typed USD or IDR. Derive the rupiah figure from it rather than from
`$sourceAmount`, so both input currencies behave the same.

Keep the comment above `metadata`. It is the non-obvious constraint from section 4.

---

## 7. Phase 3 — Webhook handles top-ups

**File:** `app/Http/Controllers/PakasirWebhookController.php`

Today this only looks in `subscription_payments`. A top-up webhook arrives, finds no row, logs
"unknown order", and the user's money sits in a `pending` row forever.

### 7.1 Imports

```php
use App\Models\WalletTopup;
use App\Services\WalletService;
```

### 7.2 Find the record in either table

Replace the `$payment = SubscriptionPayment::where(...)` lookup and its `if (! $payment)` guard
with:

```php
$payment = SubscriptionPayment::where('order_id', $orderId)->first();
$topup = $payment ? null : WalletTopup::where('order_id', $orderId)->first();

$record = $payment ?? $topup;

if (! $record) {
    Log::warning('Pakasir webhook for unknown order', ['order_id' => $orderId]);

    return response()->json(['received' => true]);
}

// subscription_payments.amount is already IDR. wallet_topups.amount is USD,
// so the rupiah figure lives in metadata (see BillingTopupController).
$expectedIdr = $payment
    ? (int) $payment->amount
    : (int) ($topup->metadata['amount_idr'] ?? 0);
```

### 7.3 Update the validation guards

The three guards (provider, amount, project) currently reference `$payment`. Point them at
`$record` and `$expectedIdr`:

```php
if ($record->provider !== 'pakasir') {
    Log::warning('Pakasir webhook for non-Pakasir payment', [
        'order_id' => $orderId,
        'provider' => $record->provider,
    ]);

    return response()->json(['received' => true]);
}

if ((int) $request->input('amount') !== $expectedIdr) {
    Log::warning('Pakasir webhook amount mismatch', [
        'order_id' => $orderId,
        'expected' => $expectedIdr,
        'received' => $request->input('amount'),
    ]);

    return response()->json(['received' => true]);
}

if ($request->input('project') !== config('services.pakasir.project')) {
    Log::warning('Pakasir webhook project mismatch', [
        'order_id' => $orderId,
        'expected' => config('services.pakasir.project'),
        'received' => $request->input('project'),
    ]);

    return response()->json(['received' => true]);
}
```

### 7.4 Confirm with Pakasir using the rupiah figure

```php
$detail = $pakasir->transactionDetail($orderId, $expectedIdr);

$transaction = $detail['transaction'] ?? [];
$status = $transaction['status'] ?? null;

if ($status !== 'completed') {
    Log::info('Pakasir transaction not completed', [
        'order_id' => $orderId,
        'status' => $status,
    ]);

    return response()->json(['received' => true]);
}
```

### 7.5 Branch the fulfilment

The existing `DB::transaction(...)` block handles the plan case. Wrap it so top-ups take the wallet
path instead:

```php
if ($payment) {
    DB::transaction(function () use ($payment) {
        $payment = SubscriptionPayment::lockForUpdate()->find($payment->id);

        if ($payment->status === 'paid') {
            return;
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        $payment->organization->subscription->update([
            'plan' => $payment->plan,
            'status' => 'active',
            'provider' => 'pakasir',
            'subscribed_at' => now(),
            'expired_at' => now()->addDays(30),
        ]);
    });

    return response()->json(['received' => true]);
}

DB::transaction(function () use ($topup, $orderId) {
    $topup = WalletTopup::lockForUpdate()->find($topup->id);

    if ($topup->status === 'paid') {
        return;
    }

    app(WalletService::class)->credit(
        organization: $topup->organization,
        sourceCurrency: $topup->currency,
        sourceAmount: (float) $topup->amount,
        exchangeRate: (float) $topup->exchange_rate,
        amountUsdCents: (int) $topup->wallet_amount_usd_cents,
        type: 'topup',
        description: 'Wallet top-up via QRIS',
        reference: $topup,
        createdBy: $topup->created_by,
        metadata: ['pakasir_order_id' => $orderId],
    );

    $topup->update(['status' => 'paid', 'paid_at' => now()]);
});

return response()->json(['received' => true]);
```

The `status === 'paid'` early return inside the lock is the idempotency guard. Pakasir retries
webhooks, so double delivery is normal, not hypothetical. Credit once or you hand out free money.

This mirrors `StripeWebhookController::handlePaymentIntentSucceeded()` (around line 291) almost
exactly. If you are unsure about a `credit()` argument, read that method.

---

## 8. Phase 4 — Status endpoint covers top-ups

**File:** `app/Http/Controllers/Api/PaymentStatusController.php`

It currently only checks `SubscriptionPayment`, so a `TOP-` order id 404s and the dialog polls
forever.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use Illuminate\Http\Request;

class PaymentStatusController extends Controller
{
    public function show(Request $request, string $orderId)
    {
        $record = SubscriptionPayment::where('order_id', $orderId)->first()
            ?? WalletTopup::where('order_id', $orderId)->first();

        abort_unless(
            $record && $record->organization_id === $request->user()?->organization_id,
            404
        );

        return response()->json([
            'status' => $record->status,
            'paid_at' => $record->paid_at,
        ]);
    }
}
```

Keep the `organization_id` check. It is what stops one org polling another org's order ids.

---

## 9. Phase 5 — The dialog

**File:** `resources/js/Components/payment/PaymentDialog.vue`

### 9.1 Imports and state

Add to the imports:

```js
import QRCode from "qrcode";
```

Add alongside the existing refs:

```js
const qrCanvas = ref(null);
const qrString = ref("");
const qrOrderId = ref("");
const qrAmountIdr = ref(0);
const qrLoading = ref(false);

let qrPollTimer = null;
```

And a formatter:

```js
const formattedIdr = computed(() =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(qrAmountIdr.value),
);
```

### 9.2 Generating the QR

```js
async function generateQr() {
    if (qrLoading.value) {
        return;
    }

    qrLoading.value = true;
    error.value = "";

    try {
        const { data } =
            props.mode === "topup"
                ? await window.axios.post(route("billing.topups.store"), {
                      currency: "USD",
                      amount: numericAmount.value,
                      method: "qris",
                  })
                : await window.axios.post(route("plan.qr"), {
                      plan: props.planKey ?? "pro",
                  });

        if (!data.qrString) {
            fail("The payment provider did not return a QR code. Please try again.");
            return;
        }

        qrString.value = data.qrString;
        qrOrderId.value = data.orderId;
        qrAmountIdr.value = data.amountIdr;

        await nextTick();

        // Fixed black-on-white: a QR needs maximum contrast to scan, so it
        // keeps its own colours rather than following the theme.
        await QRCode.toCanvas(qrCanvas.value, data.qrString, {
            width: 240,
            margin: 1,
            color: { dark: "#08090a", light: "#ffffff" },
        });

        startQrPolling();
    } catch (requestError) {
        fail(requestError.response?.data?.message);
    } finally {
        qrLoading.value = false;
    }
}
```

`nextTick()` matters. The canvas sits behind `v-if`, so it does not exist in the DOM until Vue has
re-rendered. Without the await, `qrCanvas.value` is `null` and nothing draws.

### 9.3 Polling

```js
function startQrPolling() {
    stopQrPolling();
    qrPollTimer = window.setInterval(checkQrStatus, 3000);
}

function stopQrPolling() {
    if (qrPollTimer) {
        window.clearInterval(qrPollTimer);
        qrPollTimer = null;
    }
}

async function checkQrStatus() {
    try {
        const { data } = await window.axios.get(
            `/payments/${qrOrderId.value}/status`,
        );

        if (data.status === "paid") {
            stopQrPolling();

            succeed(
                props.mode === "topup"
                    ? `We received ${formattedIdr.value}. Your balance is updated.`
                    : `You are now on the ${plan.value?.label ?? "Pro"} plan for the next 30 days.`,
            );
        }
    } catch {
        // Transient failures are expected while polling; keep waiting.
    }
}
```

Note the URL has **no `/api` prefix** — that is the phase 3 route move.

### 9.4 Cleanup

The existing `watch(open, ...)` resets card state. Add the QR state to both branches, and stop the
timer:

```js
watch(open, (isOpen) => {
    if (isOpen) {
        error.value = "";
        amount.value = "";
        method.value = "card";
        status.value = "form";
        resultMessage.value = "";
        qrString.value = "";
        qrOrderId.value = "";
        qrAmountIdr.value = 0;
        mountCard();
        return;
    }

    stopQrPolling();

    if (status.value === "success" && props.mode === "topup") {
        router.reload({ only: ["wallet", "stats", "transactions"] });
    }

    processing.value = false;
    unmountCard();
});
```

Extend the unmount hook too — `onBeforeUnmount(unmountCard)` becomes:

```js
onBeforeUnmount(() => {
    unmountCard();
    stopQrPolling();
});
```

A timer that outlives the dialog keeps polling a dead order id forever. Easy to miss, and it will
not show up in manual testing.

### 9.5 The QRIS tab markup

Replace the placeholder block (the `<div v-show="method === 'qris'" ...>` with the `TODO` copy,
around lines 418–426) with three states: before generating, after generating, and the always-on
renewal warning for plan mode.

```html
<div v-show="method === 'qris'" class="space-y-4">
    <!-- Before generating -->
    <template v-if="!qrString">
        <div v-if="mode === 'topup'" class="space-y-2">
            <Label for="qris-amount">Amount</Label>

            <div class="relative">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                    $
                </span>

                <Input
                    id="qris-amount"
                    v-model="amount"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="10.00"
                    class="pl-8"
                    :disabled="qrLoading"
                />
            </div>

            <p class="text-xs text-muted-foreground">
                You pay in rupiah at today's rate. Your wallet is credited in US dollars.
            </p>
        </div>

        <div v-else class="rounded-lg border bg-card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-muted-foreground">Plan</p>
                    <p class="mt-1 text-lg font-semibold">{{ plan?.label }}</p>
                </div>

                <div class="text-right">
                    <p class="text-xs text-muted-foreground">Price</p>
                    <p class="mt-1 text-lg font-semibold">{{ formattedPrice }}/month</p>
                </div>
            </div>
        </div>
    </template>

    <!-- After generating -->
    <div v-else class="flex flex-col items-center gap-4 py-2">
        <div class="text-center">
            <p class="text-xs text-muted-foreground">Amount due</p>
            <p class="mt-1 text-2xl font-bold tracking-tight">{{ formattedIdr }}</p>
        </div>

        <div class="rounded-xl border bg-white p-4">
            <canvas ref="qrCanvas" />
        </div>

        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2 class="h-4 w-4 animate-spin" />
            <span>Waiting for payment</span>
        </div>
    </div>

    <!-- Renewal warning, plan mode only -->
    <div v-if="mode === 'plan'" class="flex gap-3 rounded-lg border bg-muted/30 p-3">
        <TriangleAlert class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground" />

        <div class="space-y-1">
            <p class="text-xs font-medium">QRIS does not renew automatically</p>
            <p class="text-xs leading-5 text-muted-foreground">
                This one-off payment covers 30 days. You will need to pay again before it
                expires, or your organization drops back to the Free plan.
            </p>
        </div>
    </div>

    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
</div>
```

`TriangleAlert`, `Loader2` and `QrCode` are already imported at the top of the file.

### 9.6 The footer button

The footer currently hard-disables the submit button on the QRIS tab
(`:disabled="processing || method === 'qris' || !cardReady"`). That is what stops QRIS from
working. Replace the `v-else` footer template with a branch per tab:

```html
<template v-else>
    <Button
        type="button"
        variant="outline"
        :disabled="processing || qrLoading"
        @click="open = false"
    >
        Cancel
    </Button>

    <!-- QRIS -->
    <Button
        v-if="method === 'qris'"
        type="button"
        class="gap-2"
        :disabled="qrLoading || !!qrString || (mode === 'topup' && numericAmount <= 0)"
        @click="generateQr"
    >
        <Loader2 v-if="qrLoading" class="h-4 w-4 animate-spin" />
        <QrCode v-else class="h-4 w-4" />

        {{ qrLoading ? "Generating..." : qrString ? "Waiting for payment" : "Show QR code" }}
    </Button>

    <!-- Card -->
    <Button
        v-else
        type="button"
        class="gap-2"
        :disabled="processing || !cardReady"
        @click="submit"
    >
        <Loader2 v-if="processing" class="h-4 w-4 animate-spin" />
        <CreditCard v-else class="h-4 w-4" />

        {{ processing ? "Processing..." : "Continue" }}
    </Button>
</template>
```

The card button keeps `!cardReady` in its disabled condition. The QRIS button must **not** — the
Stripe card element has nothing to do with QRIS, and gating on it would leave the QRIS tab dead
whenever Stripe is slow or misconfigured.

---

## 10. Phase 6 — Delete the dead files

Once the dialog works end to end:

```bash
rm resources/js/Components/plan/QrPaymentDialog.vue
rm resources/js/Pages/Plan/Qr.vue
```

Then confirm nothing references them:

```bash
grep -rn "QrPaymentDialog\|Plan/Qr" resources/js/ app/
```

Expected output: nothing. If `Plan/Qr` still appears in `SubscriptionController.php`, phase 1 was
not finished.

Do this **last**. Keeping `Plan/Qr.vue` around while you work gives you a reference implementation
of the canvas rendering and polling that is known to work.

---

## 11. Verification

### 11.1 Setup

`.env` needs:

```bash
PAKASIR_BASE_URL=https://app.pakasir.com
PAKASIR_PROJECT=ezsign
PAKASIR_API_KEY=<from the Pakasir dashboard>
```

Then `php artisan config:clear`.

Pakasir cannot reach localhost, so tunnel it:

```bash
ngrok http 8000
```

Set the webhook URL on the Pakasir dashboard (project → **Edit Proyek**) to
`https://<your-tunnel>.ngrok-free.app/pakasir/webhook`.

Keep a log tail open the whole time:

```bash
tail -f storage/logs/laravel.log
```

### 11.2 Top-up flow

1. Log in as an organization **owner**. Both endpoints are owner-only; anyone else gets a 403 and
   you will waste time wondering why the button does nothing.
2. Billing page → **Top Up Wallet** → **QRIS** tab.
3. Enter `10`, press **Show QR code**.
4. A QR renders inside the dialog. The rupiah amount above it looks sane for $10.
5. Note the order id (`TOP-...`) from the network response.
6. Trigger the sandbox simulation: `POST https://app.pakasir.com/api/paymentsimulation` with your
   project slug, that order id, the rupiah amount and your API key. Check the docs page for the
   exact body.
7. Within ~3 seconds the dialog flips to the success state.
8. Close it. The wallet balance has gone up by about $10.

### 11.3 Plan flow

Same, from the Plan page → **Upgrade** → **QRIS** tab. Order id starts with `SUB-`. After payment
the plan shows **Pro / active**.

### 11.4 Database check

```bash
php artisan tinker
```

```php
$t = App\Models\WalletTopup::latest()->first();
$t->status;                  // "paid"
$t->provider;                // "pakasir"
$t->order_id;                // "TOP-..."
$t->metadata['amount_idr'];  // the rupiah figure — must not be null
$t->wallet->balance_usd_cents;
```

If `metadata['amount_idr']` is null, phase 2 is wrong and the webhook will have rejected the
payment as an amount mismatch. Check the log for `Pakasir webhook amount mismatch`.

### 11.5 Idempotency

Fire the same webhook twice by hand and confirm the wallet is credited **once**:

```bash
curl -X POST https://<your-tunnel>.ngrok-free.app/pakasir/webhook \
  -H "Content-Type: application/json" \
  -d '{"order_id":"TOP-...","amount":<rupiah>,"project":"ezsign","status":"completed"}'
```

Run it twice. `balance_usd_cents` must be identical after the second call. This is the test that
protects real money — do not skip it.

### 11.6 Card regression

Run one card top-up and one card plan upgrade. Phase 2 touched the shared validation block and
phase 5 rewrote the footer, so both card paths need a look before you call this done.

### 11.7 Build

```bash
npm run build
php artisan test
```

---

## 12. Traps

**The 401 polling trap.** Section 3. If the dialog spins forever but the database says `paid`, this
is why. Check the browser Network tab for 401s on the status endpoint.

**The IDR-vs-USD amount trap.** Section 4. If top-up webhooks log `Pakasir webhook amount mismatch`,
you are comparing rupiah against dollars.

**Do not gate the QRIS button on `cardReady`.** Section 9.6.

**Do not modify `PakasirService.php`.** It returns the API response as-is, on purpose. The wrapper
keys (`payment`, `transaction`) get unwrapped at the call site so the code reads the same shape the
docs show. Unwrapping in the service hides that from every caller.

**Do not uncomment the Sanctum middleware** as a shortcut for section 3. It changes CSRF handling
for every API route.

**Rounding.** `(int) round(...)` for rupiah, always. Pakasir rejects decimals, and a float that
arrives as `149999.99999` will not match the webhook's `150000`.

**`nextTick()` before drawing the canvas.** Section 9.2.

**Reuse the top-up amount logic.** `BillingTopupController` already handles USD and IDR input, the
FX lookup and the wallet-credit maths. Add a branch, do not write a parallel path.

---

## 13. Definition of done

- [ ] Status route moved from `routes/api.php` to `routes/web.php`; polling returns 200, not 401
- [ ] `payWithQr()` returns JSON; its error path returns a 422 with a `message`
- [ ] `BillingTopupController` accepts `method: "qris"` and returns the QR payload
- [ ] `wallet_topups.metadata['amount_idr']` is written on every QRIS top-up
- [ ] Webhook resolves orders from both `subscription_payments` and `wallet_topups`
- [ ] Webhook credits the wallet for `TOP-` orders and activates the plan for `SUB-` orders
- [ ] `PaymentStatusController` resolves both tables
- [ ] QR renders **inside** the dialog; the user never navigates away
- [ ] Dialog flips to success within ~3s of payment, in both modes
- [ ] Polling stops on success, on close, and on unmount
- [ ] Duplicate webhook credits the wallet exactly once
- [ ] Card top-up and card plan upgrade still work
- [ ] `QrPaymentDialog.vue` and `Pages/Plan/Qr.vue` deleted, no references remain
- [ ] `npm run build` and `php artisan test` pass

---

## 14. Scope

The six phases above. Nothing else.

Leave alone:

- **Stripe.** Untouched, except for confirming the card path still works.
- **QR expiry countdown.** `expiredAt` comes back from the API and is returned to the dialog, but
  nothing renders it. Wiring up a countdown is a separate task.
- **`BillingTopupController`'s logging style.** Heavier than the rest of the codebase. Not yours to
  clean up in this PR.
- **Plan expiry.** QRIS plans get 30 days from `now()` in the webhook, hardcoded rather than read
  from plan config. Known, out of scope.

If a step seems to need a new migration, stop and re-read section 2 — `provider` and `order_id`
already exist on `wallet_topups`. If you still think you need one, ask before writing it.
