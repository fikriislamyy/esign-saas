# Fix: Pakasir QRIS payments are completely broken

## 1. What is wrong

Three bugs in the Pakasir (QRIS) payment path. Two are the same root cause: **the code reads
Pakasir's JSON one level too shallow.** The third is a missing safety check the Pakasir docs ask
for.

Right now a customer cannot pay with QRIS at all. Here is the chain:

| # | Bug | File | What the user sees |
| --- | --- | --- | --- |
| 1 | QR string always `null` | `app/Http/Controllers/SubscriptionController.php:232` | The "Pay with QRIS" page loads but the QR image never appears. Nothing to scan. |
| 2 | Webhook never marks payment paid | `app/Http/Controllers/PakasirWebhookController.php:31` | Even if they somehow paid, the page spins forever and they never get Pro. |
| 3 | Webhook trusts `order_id` alone | `app/Http/Controllers/PakasirWebhookController.php` | No visible symptom. It is a hardening gap, not a crash. |

**Bug 2 is the expensive one.** The customer's money leaves their account, Pakasir confirms it,
and our database still says `pending`. They paid and got nothing.

### Why bug 2 also freezes the page

Worth understanding before you start, because it explains the "spins forever" symptom:

1. `resources/js/Pages/Plan/Qr.vue` polls `GET /api/payments/{orderId}/status` every 3 seconds.
2. That endpoint (`app/Http/Controllers/Api/PaymentStatusController.php`) just reads
   `subscription_payments.status` from our own database. It never calls Pakasir.
3. So the *only* thing that can ever flip that row to `paid` is the webhook.
4. The webhook is bug 2. It returns early every single time.

You do not need to change `Qr.vue` or `PaymentStatusController`. They are correct. Fix the webhook
and they start working.

---

## 2. Before you start

### 2.1 Read the API contract

Source of truth: <https://pakasir.com/p/docs>

The two responses you care about. **Note the outer wrapper key on both** — that wrapper is the
entire bug:

```json
// POST /api/transactioncreate/qris
{
  "payment": {
    "project": "ezsign",
    "order_id": "SUB-1-1727000000",
    "amount": 99000,
    "fee": 1003,
    "total_payment": 100003,
    "payment_method": "qris",
    "payment_number": "00020101021226...",
    "expired_at": "2026-09-19T12:30:00+07:00"
  }
}
```

```json
// GET /api/transactiondetail
{
  "transaction": {
    "amount": 99000,
    "order_id": "SUB-1-1727000000",
    "project": "ezsign",
    "status": "completed",
    "payment_method": "qris",
    "completed_at": "2026-09-19T12:07:02.819+07:00"
  }
}
```

The webhook Pakasir POSTs to us is **flat, not wrapped**:

```json
{
  "amount": 99000,
  "order_id": "SUB-1-1727000000",
  "project": "ezsign",
  "status": "completed",
  "payment_method": "qris",
  "completed_at": "2026-09-19T12:07:02.819+07:00"
}
```

> Read that last one twice. The webhook body is flat. The API responses are wrapped. Mixing these
> up is how these bugs happened in the first place.

### 2.2 Environment

Add to `.env` (these are not set yet — the integration has never run):

```bash
PAKASIR_BASE_URL=https://app.pakasir.com
PAKASIR_PROJECT=ezsign
PAKASIR_API_KEY=<copy from the Pakasir dashboard, project detail page>
```

Then:

```bash
php artisan config:clear
```

`PAKASIR_PROJECT` is the **Slug** field on the Pakasir dashboard, not the display name. For this
project it is `ezsign` (lowercase), not `EzSign`.

### 2.3 Files you will touch

Only two:

- `app/Http/Controllers/SubscriptionController.php`
- `app/Http/Controllers/PakasirWebhookController.php`

Do not touch `PakasirService.php`, `Qr.vue`, or `PaymentStatusController.php`. They are fine.

---

## 3. Bug 1 — QR string always null

### Where

`app/Http/Controllers/SubscriptionController.php`, inside `payWithQr()`, lines 229–234.

### Current code

```php
$result = $pakasir->createQris($orderId, $amountIdr);

return Inertia::render('Plan/Qr', [
    'orderId' => $orderId,
    'amountIdr' => $amountIdr,
    'qrString' => $result['payment_number'] ?? null,
    'expiredAt' => $result['expired_at'] ?? null,
]);
```

### Why it fails

`$result` is the whole decoded response: `['payment' => ['payment_number' => '...']]`.

There is no top-level `payment_number` key, so `$result['payment_number']` is undefined, `?? null`
catches it, and `qrString` arrives at the Vue page as `null`. `Qr.vue` then skips rendering the
canvas entirely (`if (props.qrString && canvas.value)`), so the page shows no QR and no error.

**Both lines are wrong, not just the one in the bug report.** Line 233 (`expired_at`) has the
identical problem. Fix both.

### The fix

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

Pulling `$payload` out into its own variable is deliberate — it means there is one place to be
wrong instead of two.

### Verify it

```bash
php artisan tinker
```

```php
$r = app(App\Services\PakasirService::class)->createQris('TEST-'.time(), 10000);
array_keys($r);                        // expect: ["payment"]
$r['payment']['payment_number'];       // expect: a long "00020101021226..." string
```

If `array_keys($r)` gives you something other than `["payment"]`, **stop and report it** — the API
shape changed and the rest of this document is out of date.

---

## 4. Bug 2 — Webhook never marks payment paid

### Where

`app/Http/Controllers/PakasirWebhookController.php`, line 29–38.

### Current code

```php
$detail = $pakasir->transactionDetail($orderId, (int) $payment->amount);

if (($detail['status'] ?? null) !== 'completed') {
    Log::info('Pakasir transaction not completed', [
        'order_id' => $orderId,
        'status' => $detail['status'] ?? null,
    ]);

    return response()->json(['received' => true]);
}
```

### Why it fails

Same shape mistake. `$detail` is `['transaction' => ['status' => 'completed']]`. So
`$detail['status']` is always `null`, `null !== 'completed'` is always `true`, and the method
**always** returns early at line 37. The `DB::transaction()` block below it has never run in
production.

### The fix

```php
$detail = $pakasir->transactionDetail($orderId, (int) $payment->amount);

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

Keep the early return and keep the `received: true` response. Pakasir retries on non-2xx, and a
genuinely-not-yet-completed transaction is not an error — we just do not want it retried forever.

### Verify it

Because the project is in **Sandbox** mode you can fake a payment without spending money. Pakasir
exposes `POST /api/paymentsimulation` for exactly this. See section 6 for the full walkthrough.

---

## 5. Bug 3 — Webhook does not validate amount and project

### Where

`app/Http/Controllers/PakasirWebhookController.php`, between the `$payment` lookup (line 21) and
the `transactionDetail` call (line 29).

### Why it matters

The endpoint is public and CSRF-exempt (`app/Http/Middleware/VerifyCsrfToken.php` line 16).
Anyone who guesses an `order_id` can POST to it. Pakasir provides no signature header, so their
docs say plainly:

> *"Saat menerima webhook pastikan amount dan order_id sesuai dengan transaksi di sistem Anda"*
> — when receiving a webhook, make sure the amount and order_id match the transaction in your
> system.

We check `order_id`. We do not check `amount` or `project`.

**Be clear about the actual risk level:** once bug 2 is fixed, the `transactionDetail` call is a
server-to-server confirmation straight from Pakasir, and that is a *stronger* guard than a
signature. A forged webhook still cannot mark anything paid, because we go and ask Pakasir
ourselves. So this is not an open door.

What the check buys us is: we stop burning an outbound API call on every junk request, and a
mismatch gets logged loudly instead of silently. Treat it as hardening and hygiene, not as an
emergency.

### The fix

Insert this after the `if (! $payment)` block, before the `transactionDetail` call:

```php
if ($payment->provider !== 'pakasir') {
    Log::warning('Pakasir webhook for non-Pakasir payment', [
        'order_id' => $orderId,
        'provider' => $payment->provider,
    ]);

    return response()->json(['received' => true]);
}

if ((int) $request->input('amount') !== (int) $payment->amount) {
    Log::warning('Pakasir webhook amount mismatch', [
        'order_id' => $orderId,
        'expected' => (int) $payment->amount,
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

### Three details that matter

**Cast both sides of the amount comparison to `int`.** JSON numbers can decode as int or string
depending on the sender. `"99000" !== 99000` is `true` in PHP and would reject every valid
webhook. `(int) "99000" !== (int) 99000` is `false`, which is what we want.

**Return 200, not 4xx, on a mismatch.** A non-2xx tells Pakasir to retry, and retrying a request
that will never pass is just noise in their queue and ours. We log the warning and accept the
delivery.

**The `provider` check exists because `order_id` is nullable.** Stripe payments leave `order_id`
as `null` (see `database/migrations/2026_09_18_235802_make_order_id_nullable_on_subscription_payments.php`),
so they will not collide today. The check is there so that stays true if someone later adds a
third provider that does set `order_id`.

---

## 6. End-to-end verification

Do this after all three fixes. This is the only way to know the whole chain works.

### 6.1 Expose your local server

Pakasir cannot reach `http://localhost:8000`. You need a public tunnel:

```bash
ngrok http 8000
```

Copy the `https://<something>.ngrok-free.app` URL.

### 6.2 Point Pakasir at it

On the Pakasir dashboard → your project → **Edit Proyek** → set **Webhook URL** to:

```
https://<something>.ngrok-free.app/pakasir/webhook
```

The path is `/pakasir/webhook` — defined in `routes/web.php` line 61. Save.

### 6.3 Run the flow

1. `php artisan serve` and log in as an organization **owner** (`payWithQr` is owner-only —
   non-owners get a 403 and you will waste time wondering why).
2. Go to the Plan page and start a QRIS payment for the Pro plan.
3. **Checkpoint for bug 1:** a QR code is visible on screen. If the page is blank where the QR
   should be, bug 1 is not fixed. Open the browser devtools Network tab, find the Inertia
   response, and confirm `qrString` is a long string and not `null`.
4. Note the `orderId` shown on the page.
5. Trigger the sandbox payment simulation — `POST https://app.pakasir.com/api/paymentsimulation`
   with your project slug, that `order_id`, the amount, and your API key. (Check the docs page for
   the exact body; sandbox-only endpoint.)
6. **Checkpoint for bug 2:** within about 3 seconds the page should stop spinning and redirect you
   to the Plan page, now showing Pro as active.

### 6.4 Confirm in the database

```bash
php artisan tinker
```

```php
$p = App\Models\SubscriptionPayment::latest()->first();
$p->status;                            // expect: "paid"
$p->paid_at;                           // expect: a timestamp, not null
$p->organization->subscription->plan;   // expect: "pro"
$p->organization->subscription->status; // expect: "active"
```

### 6.5 Confirm bug 3

Send a deliberately wrong webhook by hand and check it is rejected:

```bash
curl -X POST https://<something>.ngrok-free.app/pakasir/webhook \
  -H "Content-Type: application/json" \
  -d '{"order_id":"<a real order id>","amount":1,"project":"ezsign","status":"completed"}'
```

Expect `{"received":true}` in the response **and** a `Pakasir webhook amount mismatch` line in
`storage/logs/laravel.log`. The payment row must stay `pending`.

### 6.6 Watch the logs throughout

```bash
tail -f storage/logs/laravel.log
```

---

## 7. Tests (recommended)

The project uses PHPUnit with tests in `tests/Feature/`. There is no Pakasir coverage yet, which
is part of why these bugs shipped. Add `tests/Feature/PakasirWebhookTest.php`.

Use `Http::fake()` so the tests never hit the real API:

```php
Http::fake([
    '*/api/transactiondetail*' => Http::response([
        'transaction' => ['status' => 'completed', 'order_id' => 'SUB-1-123', 'amount' => 99000],
    ]),
]);
```

Cover at least these four cases:

| Test | Setup | Expect |
| --- | --- | --- |
| Marks payment paid | valid webhook, faked `completed` response | `status === 'paid'`, subscription active |
| Ignores incomplete | faked response with `status: 'pending'` | payment stays `pending` |
| Rejects amount mismatch | webhook `amount` ≠ stored amount | payment stays `pending`, warning logged |
| Is idempotent | fire the same valid webhook twice | `paid_at` does not move on the second call |

That last one matters — Pakasir retries, so duplicate deliveries are normal, not hypothetical. The
existing `lockForUpdate()` + `if ($payment->status === 'paid') return;` guard inside
`DB::transaction()` already handles it. The test is there to keep it handled.

Run with:

```bash
php artisan test --filter=PakasirWebhookTest
```

---

## 8. Traps

**Do not "fix" `PakasirService.php` by unwrapping there.** It is tempting to make
`createQris()` return `$response->json()['payment']`. Do not. The service's job is to return what
the API returned. Unwrapping inside it hides the shape from the caller and means the next person
reading the docs alongside the code sees a mismatch. Unwrap at the call site, which is what
sections 3 and 4 do.

**Do not add a webhook signature check.** There is a "Webhook Secret" field visible on the Pakasir
dashboard, but the documented webhook carries no signature header to verify it against, and
nothing in this codebase reads it. Leave it alone. If you think it is needed, ask first.

**Do not change the `received: true` responses to error codes.** Covered in section 5, but it is
the most likely thing to get "improved" by accident.

**Do not touch `Qr.vue`.** The polling logic, the 3-second interval, and the silent `catch` are all
intentional. The page looks broken because of bug 1 and bug 2, not because of anything in the Vue
file.

**Watch out for stale config.** `config('services.pakasir.project')` reads from a cached config in
some environments. If your project check rejects everything, run `php artisan config:clear` before
debugging anything else.

---

## 9. Definition of done

- [ ] `SubscriptionController.php` reads `$result['payment']['payment_number']` **and**
      `$result['payment']['expired_at']`
- [ ] `PakasirWebhookController.php` reads `$detail['transaction']['status']`
- [ ] Webhook validates `provider`, `amount`, and `project` before calling `transactionDetail`
- [ ] All three mismatch branches log a warning and return 200
- [ ] Amount comparison casts both sides to `int`
- [ ] A QR code visibly renders on the Pay with QRIS page
- [ ] A simulated sandbox payment flips the row to `paid` and the subscription to `pro` / `active`
- [ ] A hand-crafted wrong-amount webhook is rejected and logged
- [ ] `php artisan test` passes
- [ ] No changes to `PakasirService.php`, `Qr.vue`, or `PaymentStatusController.php`

---

## 10. Scope

Three bug fixes and optionally the tests in section 7. Nothing else.

Things you will notice and should leave alone:

- The QRIS tab in `resources/js/Components/payment/PaymentDialog.vue` still has a `TODO` and is not
  wired to this flow. Separate task.
- `payWithQr` creates the `SubscriptionPayment` row *before* calling Pakasir, so a failed
  `createQris()` leaves an orphaned `pending` row. Real, but out of scope. Mention it in the PR
  description and move on.
- `expired_at` is hardcoded to `now()->addDays(30)` in the webhook rather than coming from the plan
  config. Out of scope.

If a fix seems to need more than the two files in section 2.3, stop and ask before continuing.
