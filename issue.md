# Replace email verification link with a 6-digit OTP code

## Goal

Change the registration → verification flow from "click a link in your email" to
"type a 6-digit code from your email".

**Current flow**

1. User fills the register form and submits
2. User is logged in and redirected to `/verify-email`
3. Laravel sends the default "Verify Email Address" email with a signed link
4. User clicks the link → `GET /verify-email/{id}/{hash}` marks them verified
5. User is redirected to `/dashboard`

**New flow**

1. User fills the register form and submits
2. App generates a 6-digit OTP, stores it in `otp_verifications`, emails it to the user
3. User is logged in and redirected to `/verify-email`, which now shows an OTP input
4. User types the code → `POST /verify-email` checks it
5. If valid: mark email verified, redirect to `/dashboard`. If invalid/expired: show error.
6. "Resend code" button generates a new OTP and emails it again

**What stays the same:** `User implements MustVerifyEmail`, the `verified` middleware on
dashboard routes, `email_verified_at` column, the login page, the register form itself.

---

## Copy from the existing signer-OTP flow

This app **already has an OTP flow** for document signers. Copy its structure — do not
invent a new pattern.

| Existing (signer OTP) | You will create (user email OTP) |
|---|---|
| `app/Services/SigningOtpService.php` | `app/Services/EmailVerificationOtpService.php` |
| `app/Mail/SignatureRequestMail.php` | `app/Mail/EmailVerificationOtpMail.php` |
| `resources/views/emails/signature-request.blade.php` | `resources/views/emails/email-verification-otp.blade.php` |
| `resources/js/Pages/Signing/Otp.vue` | rewrite `resources/js/Pages/Auth/VerifyEmail.vue` |

Read those four existing files before you start.

## Files you will touch

| Action | Path |
|---|---|
| Create | `database/migrations/xxxx_create_otp_verifications_table.php` |
| Create | `app/Models/OtpVerification.php` |
| Create | `app/Services/EmailVerificationOtpService.php` |
| Create | `app/Mail/EmailVerificationOtpMail.php` |
| Create | `resources/views/emails/email-verification-otp.blade.php` |
| Edit | `app/Models/User.php` — override `sendEmailVerificationNotification()` |
| Edit | `routes/auth.php` — replace the signed-link route with a POST route |
| Edit | `app/Http/Controllers/Auth/VerifyEmailController.php` — verify OTP instead of signed URL |
| Edit | `app/Http/Controllers/Auth/RegisteredUserController.php` — redirect to verification page |
| Edit | `app/Http/Controllers/Auth/EmailVerificationNotificationController.php` — change status string |
| Rewrite | `resources/js/Pages/Auth/VerifyEmail.vue` |
| Edit | `tests/Feature/Auth/EmailVerificationTest.php`, `tests/Feature/Auth/RegistrationTest.php` |

---

## Step-by-step

### Step 0 — Run the app

```bash
docker compose up -d
npm run dev
```

- App: http://localhost:8000
- Mailpit (catches all outgoing email): http://localhost:8025

Every `php artisan` command below must be run **inside the container**:
`docker compose exec app php artisan ...`

### Step 1 — Migration

```bash
docker compose exec app php artisan make:migration create_otp_verifications_table
```

Edit the generated file in `database/migrations/`:

```php
public function up(): void
{
    Schema::create('otp_verifications', function (Blueprint $table) {
        $table->id();
        $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        $table->string('otp', 6);
        $table->dateTime('expired_at');
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('otp_verifications');
}
```

> **Gotcha:** `users.id` is a **UUID** (see `2014_10_12_000000_create_users_table.php`), so
> `user_id` must be `foreignUuid`, not `foreignId`. Using `foreignId` will fail on PostgreSQL.

Run it:

```bash
docker compose exec app php artisan migrate
```

### Step 2 — Model

Create `app/Models/OtpVerification.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpVerification extends Model
{
    protected $fillable = ['user_id', 'otp', 'expired_at'];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### Step 3 — Service

Create `app/Services/EmailVerificationOtpService.php`. Mirror `SigningOtpService` but store
the row in `otp_verifications`.

```php
<?php

namespace App\Services;

use App\Models\OtpVerification;
use App\Models\User;

class EmailVerificationOtpService
{
    public const EXPIRES_IN_MINUTES = 10;

    public function generate(User $user): string
    {
        $otp = (string) random_int(100000, 999999);

        // Only one active code per user.
        OtpVerification::where('user_id', $user->id)->delete();

        OtpVerification::create([
            'user_id' => $user->id,
            'otp' => $otp,
            'expired_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ]);

        return $otp;
    }

    public function verify(User $user, string $otp): bool
    {
        $record = OtpVerification::where('user_id', $user->id)
            ->where('otp', $otp)
            ->where('expired_at', '>', now())
            ->latest()
            ->first();

        if (! $record) {
            return false;
        }

        $record->delete();

        return true;
    }
}
```

### Step 4 — Mailable + email template

Create `app/Mail/EmailVerificationOtpMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $otp
    ) {}

    public function build()
    {
        return $this
            ->subject('Your EZSign verification code')
            ->view('emails.email-verification-otp')
            ->with([
                'user' => $this->user,
                'otp' => $this->otp,
            ]);
    }
}
```

Create `resources/views/emails/email-verification-otp.blade.php`. **Copy the whole file**
`resources/views/emails/signature-request.blade.php` to get the same inline styles and
outer wrapper, then replace the inner content with:

- Heading: `Verify your email`
- `Hello {{ $user->name }},`
- `Use the code below to finish creating your EZSign account. It expires in 10 minutes.`
- The `{{ $otp }}` in the same big, letter-spaced box the signature-request template uses
  (search that file for `$otp` — around line 99 — and copy that block).
- `If you did not create an account, you can ignore this email.`

Remove everything about documents/signers from the copy.

### Step 5 — Send the OTP instead of the link

Laravel sends the default link email through `User::sendEmailVerificationNotification()`
(it is triggered by the `Registered` event and by the "resend" controller). Override that one
method in `app/Models/User.php` so both places send the OTP email instead:

Add these imports at the top of `User.php`:

```php
use App\Mail\EmailVerificationOtpMail;
use App\Services\EmailVerificationOtpService;
use Illuminate\Support\Facades\Mail;
```

Add this method inside the class:

```php
public function sendEmailVerificationNotification(): void
{
    $otp = app(EmailVerificationOtpService::class)->generate($this);

    Mail::to($this->email)->send(new EmailVerificationOtpMail($this, $otp));
}
```

Also add the relationship (optional but handy):

```php
public function otpVerifications()
{
    return $this->hasMany(OtpVerification::class);
}
```

Nothing else about `Registered` / the event listener needs to change.

### Step 6 — Routes

In `routes/auth.php`, inside the `auth` middleware group, **replace** this:

```php
Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
```

with this:

```php
Route::post('verify-email', VerifyEmailController::class)
            ->middleware('throttle:6,1')
            ->name('verification.verify');
```

Keep `verification.notice` (GET `verify-email`) and `verification.send`
(POST `email/verification-notification`) exactly as they are.

### Step 7 — VerifyEmailController

Replace the whole body of `app/Http/Controllers/Auth/VerifyEmailController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\EmailVerificationOtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    public function __construct(
        private EmailVerificationOtpService $otpService
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $this->otpService->verify($user, $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => 'The code is invalid or has expired.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
    }
}
```

`EmailVerificationRequest` is no longer used — remove that import.

### Step 8 — Registration redirect

In `app/Http/Controllers/Auth/RegisteredUserController.php::store()`, change the last line:

```php
// before
return redirect(RouteServiceProvider::HOME);

// after
return redirect()->route('verification.notice');
```

(The `verified` middleware would redirect there anyway, but being explicit makes the flow
obvious and makes the test below straightforward.)

### Step 9 — Resend controller status string

In `app/Http/Controllers/Auth/EmailVerificationNotificationController.php`, change:

```php
return back()->with('status', 'verification-link-sent');
// to
return back()->with('status', 'verification-code-sent');
```

### Step 10 — Rewrite `VerifyEmail.vue`

Rewrite `resources/js/Pages/Auth/VerifyEmail.vue`. Keep the **outer look** of the current
file (`AuthLayout`, the `Card` with the round icon, title, description) and take the
**OTP input + resend button with 60-second cooldown** from `Signing/Otp.vue`.

Requirements:

- Props: `status: String`.
- Email shown from `usePage().props.auth.user.email` (same as today).
- `form = useForm({ otp: "" })`; submit posts to `route("verification.verify")`.
- `Input` with `inputmode="numeric"`, `maxlength="6"`, `autocomplete="one-time-code"`,
  `placeholder="000000"`, class `text-center text-2xl tracking-[0.5em]`.
- Show `form.errors.otp` under the input in `text-sm text-destructive text-center`.
- Submit button disabled while `form.processing || form.otp.length !== 6`.
- Resend button: `resendForm.post(route("verification.send"))`, then start a 60 s cooldown
  (copy the `resendCooldown` logic from `Signing/Otp.vue` verbatim).
- Green success box when `status === "verification-code-sent"` with text
  "A new code has been sent to your email."
- Keep the existing **Sign out** link at the bottom.
- Text: title "Verify your email", description "Enter the 6-digit code we sent to
  **{email}**. It expires in 10 minutes."

Skeleton to fill in:

```vue
<script setup>
import { computed, ref } from "vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import { MailCheck, Loader2, RefreshCcw, LogOut, ShieldCheck } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const props = defineProps({ status: String });

const page = usePage();
const email = computed(() => page.props.auth.user.email);

const form = useForm({ otp: "" });
const resendForm = useForm({});
const resendCooldown = ref(0);

const verify = () => form.post(route("verification.verify"));

const resend = () => {
    // copy from Signing/Otp.vue, but post to route("verification.send")
};

const codeSent = computed(() => props.status === "verification-code-sent");
</script>

<template>
    <Head title="Verify Email" />
    <AuthLayout>
        <Card class="w-full max-w-md rounded-2xl border shadow-xl bg-background/95">
            <!-- header: same as current file -->
            <CardContent class="space-y-6">
                <!-- green "code sent" box (v-if="codeSent") -->
                <form @submit.prevent="verify" class="space-y-4">
                    <!-- Input + error + submit button -->
                </form>
                <!-- resend button with cooldown -->
                <!-- Sign out link: same as current file -->
            </CardContent>
        </Card>
    </AuthLayout>
</template>
```

### Step 11 — Update tests

`tests/Feature/Auth/EmailVerificationTest.php` — delete the two signed-URL tests
(`test_email_can_be_verified`, `test_email_is_not_verified_with_invalid_hash`) and add:

```php
public function test_email_can_be_verified_with_valid_otp(): void
{
    $user = User::factory()->create(['email_verified_at' => null]);
    Event::fake();

    $otp = app(EmailVerificationOtpService::class)->generate($user);

    $response = $this->actingAs($user)->post('/verify-email', ['otp' => $otp]);

    Event::assertDispatched(Verified::class);
    $this->assertTrue($user->fresh()->hasVerifiedEmail());
    $response->assertRedirect(RouteServiceProvider::HOME.'?verified=1');
}

public function test_email_is_not_verified_with_wrong_otp(): void
{
    $user = User::factory()->create(['email_verified_at' => null]);

    app(EmailVerificationOtpService::class)->generate($user);

    $response = $this->actingAs($user)->post('/verify-email', ['otp' => '000000']);

    $response->assertSessionHasErrors('otp');
    $this->assertFalse($user->fresh()->hasVerifiedEmail());
}

public function test_email_is_not_verified_with_expired_otp(): void
{
    $user = User::factory()->create(['email_verified_at' => null]);

    $otp = app(EmailVerificationOtpService::class)->generate($user);
    OtpVerification::where('user_id', $user->id)->update(['expired_at' => now()->subMinute()]);

    $response = $this->actingAs($user)->post('/verify-email', ['otp' => $otp]);

    $response->assertSessionHasErrors('otp');
    $this->assertFalse($user->fresh()->hasVerifiedEmail());
}

public function test_otp_email_is_sent_on_registration(): void
{
    Mail::fake();

    $this->post('/register', [
        'organization_name' => 'Test Organization',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Mail::assertSent(EmailVerificationOtpMail::class, fn ($mail) => $mail->hasTo('test@example.com'));
    $this->assertDatabaseCount('otp_verifications', 1);
}
```

Add the needed `use` lines: `App\Models\OtpVerification`, `App\Services\EmailVerificationOtpService`,
`App\Mail\EmailVerificationOtpMail`, `Illuminate\Support\Facades\Mail`. Remove the now-unused
`URL` import.

`tests/Feature/Auth/RegistrationTest.php` — in `test_new_users_can_register`, change
`$response->assertRedirect(RouteServiceProvider::HOME);` to
`$response->assertRedirect(route('verification.notice'));`. Add `Mail::fake();` at the top of
that test so it doesn't try to talk to SMTP.

Run:

```bash
docker compose exec app php artisan test --filter=Auth
```

All Auth tests must pass.

---

## Manual verification (do this before opening the PR)

1. Go to http://localhost:8000/register and create an account.
2. You should land on `/verify-email` with a 6-digit input.
3. Open http://localhost:8025 — an email "Your EZSign verification code" must be there.
4. Type a **wrong** code → red error "The code is invalid or has expired."
5. Type the **right** code → redirected to `/dashboard`.
6. Register another account, click **Resend code** → a second email arrives, button shows a
   60 s countdown, and only the newest code works.
7. Check the page in dark mode too.

## Rules / gotchas

- `users.id` is a UUID → use `foreignUuid('user_id')` in the migration.
- Do **not** remove `MustVerifyEmail` from `User` or the `verified` middleware from
  `routes/web.php` — the whole point is to keep that gate and only change how it's satisfied.
- Do **not** add packages.
- Run `php artisan` **inside the container** (`docker compose exec app ...`); the host PHP is
  8.4 and the lock file requires 8.3.
- `route("verification.verify")` now has no parameters — in Vue call it with no args.
- If Vite is not running, run `npm run build` so the new `VerifyEmail.vue` is served.
- Frontend import paths: `@/components/ui/...` (lowercase) for primitives,
  `@/Layouts/...` and `@/Pages/...` (capitalised) for the rest — match existing files.

## Definition of done

- [ ] Migration + `OtpVerification` model exist; `otp_verifications` table matches the spec
      (`id`, `user_id`, `otp` string(6), `expired_at`, `created_at`, `updated_at`)
- [ ] Registering sends an OTP email (visible in Mailpit), not a link email
- [ ] `/verify-email` shows an OTP input; correct code → dashboard; wrong/expired → error
- [ ] Resend works with a 60 s cooldown and invalidates the previous code
- [ ] Old `GET /verify-email/{id}/{hash}` route is gone
- [ ] `php artisan test --filter=Auth` passes
- [ ] One commit on branch `feature/otp-email-verification`:

```
feat: verify email with a 6-digit OTP instead of a signed link

Store codes in otp_verifications, email them on registration and resend,
and replace the signed-URL verification route with a POST that checks the code.
```
