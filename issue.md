# Sandbox auto-pay: complete QRIS payments automatically via `paymentsimulation`

## 1. Read this before anything else — the request as worded cannot work

The task says: *"instead of hitting transactioncreate, hit paymentsimulation."*

That literal swap is impossible. It was tested against the live sandbox:

```
POST /api/paymentsimulation   with an order_id that was never created
→ 404  {"message":"Transaksi tidak ditemukan"}     ("transaction not found")
```

`paymentsimulation` does not create anything. It marks an **existing** transaction as paid. So
`transactioncreate/qris` must stay exactly where it is. What we are actually building is:

> **Keep** creating the QR with `transactioncreate/qris`. Then, **when a sandbox flag is on**, also
> call `paymentsimulation` so the payment completes instantly without anyone scanning anything.

If you find yourself deleting the `createQris()` call, stop. You have misread the task.

### What the user will see when this is done

With the flag on: click **Show QR code** → QR appears → about three seconds later the dialog
flips to **Payment successful** on its own → wallet balance goes up / plan becomes Pro.

With the flag off: exactly what happens today. QR appears and waits for a real scan.

### Why this is worth building

Testing a QRIS payment today needs: a public tunnel (ngrok) so Pakasir can reach the webhook, a
dashboard trip to point the webhook at it, and a hand-crafted `curl` to Pakasir with the right
order id and amount. Every single time. This feature collapses all of that into one click.

---

## 2. Already done — do not redo

| Thing | State |
| --- | --- |
| `PakasirService::createQris()` and `transactionDetail()` | Work. Verified against the live sandbox. |
| Response-shape fixes (`payment` / `transaction` wrappers) | Fixed in PR #40. |
| QRIS wired into `PaymentDialog.vue` for top-up and plan | Done in PR #42. |
| `WalletTopup::$fillable` missing `provider` and `order_id` | **Fixed in commit `1ee21c8`.** Before that fix, every QRIS top-up silently saved `order_id = null`, so the webhook could never find it. If you see a top-up row with a null `order_id`, it predates the fix — ignore it and make a new one. |
| Webhook validates provider, amount, project | Done. |

Everything in this document is additive on top of the above.

---

## 3. The design in one picture

Today the only thing that can mark a QRIS order paid is the webhook:

```
Pakasir ──POST──▶ /pakasir/webhook ──▶ transactionDetail() ──▶ mark paid, credit wallet
```

Pakasir cannot reach `localhost`, so locally the webhook never fires and the dialog spins forever.

After this change there are two ways in, sharing one fulfilment path:

```
Pakasir ──POST──▶ /pakasir/webhook ─────────────┐
                                                 ├──▶ PakasirFulfillmentService::fulfill()
controller ──▶ simulatePayment() ──(flag on)─────┘        │
                                                          ├─ transactionDetail() → must say "completed"
                                                          └─ mark paid + credit wallet / activate plan
```

The important word is **sharing**. The "mark paid + credit wallet" code currently lives inside the
webhook controller. It has to move into a service so the auto-pay path can call the *same* code.
Copy-pasting it into the controllers instead would give you two places to fix every future bug,
and money-handling code is the last place you want that.

### Safety — this must never run in production

Auto-pay marks an order paid without any money changing hands. Two independent guards:

1. **An explicit env flag**, off by default: `PAKASIR_AUTO_SIMULATE=true`. Nobody sets that by
   accident on a production box.
2. **Pakasir's own word.** `transactionDetail()` returns `"is_sandbox": true` for sandbox
   projects. The auto-pay path refuses to fulfil unless that field is literally `true`. So even if
   someone did flip the flag on production with a live project, nothing gets credited.

Both guards, always. Do not remove either one because "the other one covers it."

---

## 4. Files you will touch

| File | Change |
| --- | --- |
| `config/services.php` | Add one config key |
| `.env` | Add one line (not committed) |
| `app/Services/PakasirService.php` | Add `simulatePayment()` |
| `app/Services/PakasirFulfillmentService.php` | **New file.** Fulfilment logic moves here. |
| `app/Http/Controllers/PakasirWebhookController.php` | Shrinks — delegates to the service |
| `app/Http/Controllers/SubscriptionController.php` | Three lines after `createQris()` |
| `app/Http/Controllers/BillingTopupController.php` | Three lines after `createQris()` |

Nothing in `resources/js/`. The dialog already polls; it will simply see `paid` sooner.

---

## 5. Phase 1 — Config and env

### `config/services.php`

The `pakasir` block is at line 48. Add one key:

```php
'pakasir' => [
    'base_url' => env('PAKASIR_BASE_URL', 'https://app.pakasir.com'),
    'project' => env('PAKASIR_PROJECT'),
    'api_key' => env('PAKASIR_API_KEY'),
    'auto_simulate' => (bool) env('PAKASIR_AUTO_SIMULATE', false),
],
```

The `(bool)` cast matters. Without it, `PAKASIR_AUTO_SIMULATE=false` in `.env` arrives as the
string `"false"`, which PHP treats as truthy. Laravel's `env()` does convert the literal words
`true`/`false`, but the cast makes the intent unmissable and protects against `0`/`1`/`""`.

### `.env`

```bash
PAKASIR_AUTO_SIMULATE=true
```

Then clear config. **This project runs inside Docker** — artisan on your host shell cannot reach
the database (`could not translate host name "postgres"`). Every artisan command in this document
is run like this:

```bash
docker exec esign-app php artisan config:clear
```

---

## 6. Phase 2 — `PakasirService::simulatePayment()`

**File:** `app/Services/PakasirService.php`

Add this method after `transactionDetail()`. It is the same shape as `createQris()` with a
different path:

```php
public function simulatePayment(string $orderId, int $amountIdr): array
{
    $response = Http::asJson()
        ->post($this->baseUrl.'/api/paymentsimulation', [
            'project' => $this->project,
            'order_id' => $orderId,
            'amount' => $amountIdr,
            'api_key' => $this->apiKey,
        ]);

    $response->throw();

    return $response->json();
}
```

On success Pakasir returns `{"success": true}`. On an unknown order it returns 404, and
`->throw()` turns that into an exception — which is what you want, because it means
`createQris()` did not run first.

Do not add any sandbox checks here. This class is a thin HTTP client and should stay that way.

---

## 7. Phase 3 — `PakasirFulfillmentService` (new file)

**File:** `app/Services/PakasirFulfillmentService.php`

This is the whole file. It is the "mark paid + credit wallet / activate plan" code lifted out of
the webhook, with the two-table amount lookup folded in so callers cannot get it wrong.

```php
<?php

namespace App\Services;

use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PakasirFulfillmentService
{
    public function __construct(
        protected PakasirService $pakasir,
        protected WalletService $wallet,
    ) {}

    /**
     * The rupiah figure Pakasir was asked to charge for this record.
     *
     * subscription_payments.amount is already IDR. wallet_topups.amount is the
     * USD the user typed, so its rupiah figure lives in metadata.
     */
    public function amountIdr(SubscriptionPayment|WalletTopup $record): int
    {
        return $record instanceof SubscriptionPayment
            ? (int) $record->amount
            : (int) ($record->metadata['amount_idr'] ?? 0);
    }

    /**
     * Confirm with Pakasir that the order is completed, then mark it paid locally.
     *
     * Returns true when the record is (now) paid. Returns false when Pakasir has
     * not completed it, or when $sandboxOnly is set and Pakasir does not report
     * the transaction as sandbox. Safe to call twice: a paid record is a no-op.
     */
    public function fulfill(SubscriptionPayment|WalletTopup $record, bool $sandboxOnly = false): bool
    {
        $detail = $this->pakasir->transactionDetail($record->order_id, $this->amountIdr($record));
        $transaction = $detail['transaction'] ?? [];

        if (($transaction['status'] ?? null) !== 'completed') {
            Log::info('Pakasir transaction not completed', [
                'order_id' => $record->order_id,
                'status' => $transaction['status'] ?? null,
            ]);

            return false;
        }

        if ($sandboxOnly && ($transaction['is_sandbox'] ?? false) !== true) {
            Log::error('Refused to auto-fulfil a non-sandbox Pakasir transaction', [
                'order_id' => $record->order_id,
            ]);

            return false;
        }

        if ($record instanceof SubscriptionPayment) {
            $this->fulfillSubscription($record);
        } else {
            $this->fulfillTopup($record);
        }

        return true;
    }

    protected function fulfillSubscription(SubscriptionPayment $payment): void
    {
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
    }

    protected function fulfillTopup(WalletTopup $topup): void
    {
        DB::transaction(function () use ($topup) {
            $topup = WalletTopup::lockForUpdate()->find($topup->id);

            if ($topup->status === 'paid') {
                return;
            }

            $this->wallet->credit(
                organization: $topup->organization,
                sourceCurrency: $topup->currency,
                sourceAmount: (float) $topup->amount,
                exchangeRate: (float) $topup->exchange_rate,
                amountUsdCents: (int) $topup->wallet_amount_usd_cents,
                type: 'topup',
                description: 'Wallet top-up via QRIS',
                reference: $topup,
                createdBy: $topup->created_by,
                metadata: ['pakasir_order_id' => $topup->order_id],
            );

            $topup->update(['status' => 'paid', 'paid_at' => now()]);
        });
    }
}
```

Three things to notice, because they are the difference between "works" and "works safely":

- **`lockForUpdate()` + the `status === 'paid'` early return** is the idempotency guard. Pakasir
  retries webhooks, and with auto-pay on, the webhook *and* the controller may both try to fulfil
  the same order. This guarantees the wallet is credited exactly once.
- **`$sandboxOnly` is checked against `=== true`**, not truthiness. A missing field or a string
  `"true"` must not pass. Money.
- **The two `fulfill*` methods are lifted verbatim** from the current webhook. If you are tempted
  to "improve" them while moving, don't. Move first, verify, then improve in a separate PR.

Laravel resolves the two constructor dependencies automatically — no service-provider registration
is needed.

---

## 8. Phase 4 — Webhook delegates to the service

**File:** `app/Http/Controllers/PakasirWebhookController.php`

The webhook keeps everything about *trusting an inbound request* (order lookup, provider/amount/
project validation) and hands the *fulfilment* to the service. Replace the file with this:

```php
<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use App\Services\PakasirFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PakasirWebhookController extends Controller
{
    public function handle(Request $request, PakasirFulfillmentService $fulfillment)
    {
        $orderId = $request->input('order_id');

        if (! $orderId) {
            return response()->json(['error' => 'order_id missing'], 400);
        }

        $record = SubscriptionPayment::where('order_id', $orderId)->first()
            ?? WalletTopup::where('order_id', $orderId)->first();

        if (! $record) {
            Log::warning('Pakasir webhook for unknown order', ['order_id' => $orderId]);

            return response()->json(['received' => true]);
        }

        if ($record->provider !== 'pakasir') {
            Log::warning('Pakasir webhook for non-Pakasir payment', [
                'order_id' => $orderId,
                'provider' => $record->provider,
            ]);

            return response()->json(['received' => true]);
        }

        $expectedIdr = $fulfillment->amountIdr($record);

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

        $fulfillment->fulfill($record);

        return response()->json(['received' => true]);
    }
}
```

What changed: `use App\Services\PakasirService`, `DB`, and `WalletService` imports are gone; the
two `DB::transaction` blocks are gone; `transactionDetail()` is no longer called here. The file
drops from ~130 lines to ~60. The behaviour for real webhooks is **identical** — every guard is
still there in the same order and still returns `received: true`.

Note the webhook calls `fulfill($record)` with `$sandboxOnly` left at its default `false`. In
production the webhook fulfils real, non-sandbox transactions. Only the auto-pay path passes
`true`.

---

## 9. Phase 5 — Auto-pay in the two controllers

Same three lines in both places, inserted **after** `createQris()` and **before** the
`return response()->json([...])`.

### `SubscriptionController::payWithQr()` — after line 237

```php
$result = $pakasir->createQris($orderId, $amountIdr);

if (config('services.pakasir.auto_simulate')) {
    $pakasir->simulatePayment($orderId, $amountIdr);
    app(PakasirFulfillmentService::class)->fulfill($payment, sandboxOnly: true);
}

$payload = $result['payment'] ?? [];
```

Add the import at the top of the file:

```php
use App\Services\PakasirFulfillmentService;
```

`$payment` is the `SubscriptionPayment` created a few lines above. It already has the
`order_id`, so the service can look it up at Pakasir.

### `BillingTopupController::store()` — inside the `if ($isQris)` branch, after line 233

```php
$result = app(\App\Services\PakasirService::class)->createQris($orderId, $amountIdr);

if (config('services.pakasir.auto_simulate')) {
    app(\App\Services\PakasirService::class)->simulatePayment($orderId, $amountIdr);
    app(\App\Services\PakasirFulfillmentService::class)->fulfill($topup, sandboxOnly: true);
}

$payload = $result['payment'] ?? [];
```

`$topup` was updated with `provider`, `order_id` and `metadata.amount_idr` just above this, so
`fulfill()` can find the rupiah figure. That update only works because of the `$fillable` fix in
section 2 — it is why that fix is a prerequisite.

### Why the order matters

The QR **must** be created before the simulation (section 1). And fulfilment runs **before** the
JSON is returned, so by the time the dialog renders the QR and fires its first status poll three
seconds later, the row already says `paid`. That is what produces the "QR flashes, then success"
experience.

---

## 10. Verification

All artisan commands go through Docker. Log tail:

```bash
docker exec esign-app tail -f storage/logs/laravel.log
```

### 10.1 Flag on — the feature

1. `.env`: `PAKASIR_AUTO_SIMULATE=true`, then `docker exec esign-app php artisan config:clear`.
2. Log in as an organization **owner** (both endpoints are owner-only).
3. Billing → **Top Up Wallet** → **QRIS** → enter `10` → **Show QR code**.
4. QR appears. Within ~3 seconds the dialog flips to **Payment successful**.
5. Close. Wallet balance has gone up by ~$10.
6. Repeat from the Plan page → **Upgrade** → **QRIS**. Plan shows **Pro / active** afterwards.

Database check:

```bash
docker exec esign-app php artisan tinker --execute="
\$t = App\Models\WalletTopup::latest()->first();
echo 'order_id: '.\$t->order_id.PHP_EOL;
echo 'provider: '.\$t->provider.PHP_EOL;
echo 'status:   '.\$t->status.PHP_EOL;
echo 'balance:  '.\$t->wallet->balance_usd_cents.PHP_EOL;
"
```

`order_id` starts with `TOP-`, `provider` is `pakasir`, `status` is `paid`. If `order_id` is
null, you are looking at a row from before the `$fillable` fix — make a fresh top-up.

### 10.2 Flag off — nothing changed

1. `.env`: `PAKASIR_AUTO_SIMULATE=false`, `config:clear`.
2. Show a QR. It stays on **Waiting for payment**. This is correct — it is today's behaviour.
3. Confirm the webhook path still works end-to-end without the flag. Grab the order id and
   amount from the row, then simulate at Pakasir and deliver the webhook by hand:

```bash
# 1) tell Pakasir the order is paid
curl -s -X POST https://app.pakasir.com/api/paymentsimulation \
  -H "Content-Type: application/json" \
  -d '{"project":"ezsign","order_id":"TOP-...","amount":17813,"api_key":"<your key>"}'

# 2) deliver the webhook Pakasir would have sent
curl -s -X POST http://localhost:8000/pakasir/webhook \
  -H "Content-Type: application/json" \
  -d '{"order_id":"TOP-...","amount":17813,"project":"ezsign","status":"completed"}'
```

The dialog flips to success on its next poll. This proves the refactored webhook still fulfils.
`/pakasir/webhook` is CSRF-exempt (`VerifyCsrfToken::$except`) so the plain `curl` is accepted.

### 10.3 Idempotency — protects real money

With the flag on, make one top-up (auto-fulfilled). Then deliver the webhook for that same order
by hand, as in 10.2 step 2. Run it twice. `balance_usd_cents` must not move on either call.

### 10.4 The sandbox guard

This is hard to test without a live project, so test the code path directly:

```bash
docker exec esign-app php artisan tinker --execute="
\$t = App\Models\WalletTopup::where('status','pending')->whereNotNull('order_id')->latest()->first();
\$svc = app(App\Services\PakasirFulfillmentService::class);
var_dump(\$svc->fulfill(\$t, sandboxOnly: true));
"
```

Against the sandbox project this prints `bool(true)` (Pakasir reports `is_sandbox: true`). Now
read the `is_sandbox` check in `fulfill()` and convince yourself that a response without that
field, or with `"is_sandbox": "true"` as a string, returns `false` and logs the refusal.

### 10.5 Build and syntax

```bash
docker exec esign-app php -l app/Services/PakasirFulfillmentService.php
docker exec esign-app php -l app/Http/Controllers/PakasirWebhookController.php
npm run build
```

`php artisan test` currently fails on every test with the `postgres` hostname error regardless of
your changes — it is a test-environment problem, not yours. Do not chase it in this PR.

---

## 11. Traps

**Deleting `createQris()`.** Section 1. The simulation needs the transaction to exist.

**Putting the sandbox check in `PakasirService`.** That class is a dumb HTTP client. The
safety logic belongs in the fulfilment service, where the `transactionDetail()` response is
actually inspected.

**Copy-pasting the fulfilment into the controllers** instead of extracting the service. You will
end up with three copies of money-handling code. The refactor in phases 3–4 is not optional.

**Calling `fulfill()` without `sandboxOnly: true` from the controllers.** The webhook is the only
caller that should fulfil non-sandbox transactions.

**Running artisan on the host.** `php artisan ...` on your machine cannot reach the database.
Always `docker exec esign-app php artisan ...`.

**Forgetting `config:clear`** after touching `.env`. Symptoms: the flag appears to do nothing.

**Truthiness on `is_sandbox`.** It is `=== true`. Not `if ($transaction['is_sandbox'])`.

**Changing the webhook's guards while moving code.** Phase 4 is a move, not a rewrite. If the
provider / amount / project checks are not byte-for-byte what they were, you have drifted.

---

## 12. Definition of done

- [ ] `config('services.pakasir.auto_simulate')` exists, defaults to `false`, is cast to bool
- [ ] `PakasirService::simulatePayment()` exists and throws on a 4xx
- [ ] `PakasirFulfillmentService` exists with `amountIdr()` and `fulfill()`
- [ ] Webhook delegates to the service; no `DB::transaction` remains in the controller
- [ ] Webhook behaviour for real deliveries is unchanged (10.2 passes)
- [ ] Both controllers auto-simulate + fulfil when the flag is on, with `sandboxOnly: true`
- [ ] `createQris()` is still called first in both controllers
- [ ] Flag on: dialog flips to success within ~3s for top-up and for plan
- [ ] Flag off: dialog waits, exactly as before
- [ ] Duplicate fulfilment credits the wallet exactly once (10.3)
- [ ] `npm run build` passes; both new/changed PHP files pass `php -l`
- [ ] `PAKASIR_AUTO_SIMULATE` is **not** set to `true` in any committed file

---

## 13. Scope

Five phases above. Nothing else.

Leave alone:

- **The dialog.** No "sandbox mode" banner, no different success copy. The flip to success is
  the signal. A banner is a reasonable follow-up, not this PR.
- **`expired_at` / QR countdown.** Still unrendered. Separate task.
- **The `postgres` test failures.** Environment, not code.
- **ngrok / webhook URL on the Pakasir dashboard.** Auto-pay exists precisely so you do not need
  them for local testing. Leave whatever is configured there as-is.
- **Stripe.** Untouched.

If you think the feature needs a change to `PaymentDialog.vue`, re-read section 9's last
paragraph. It should not.
