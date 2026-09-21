# Email OTP as a second step on every login

## 1. What we are building, in one paragraph

Today `POST /login` with the right password logs the user in. After this change it does not.
Instead it remembers *who* passed the password check in the session, generates a 6-digit code,
stores a hash of that code in **Redis with a 10-minute expiry**, emails the code, and sends the
browser to a new page `/login/otp`. Only when the user types the right code does the app call
`Auth::login()`. Resending the code is rate limited two ways: once per 60 seconds, and at most 5
sends per 10 minutes. Typing the wrong code 5 times burns the code and the user must request a
new one. Nothing else about the app changes: registration, password reset, the `auth` middleware,
and every test that uses `actingAs()` stay exactly as they are.

```
 POST /login  ──password ok──▶ session: login_otp = {user_id, remember}
                              redis:   login_otp:{user_id}:hash  (TTL 600 s)
                              mail:    "Your sign-in code: 123456"
                              302 ──▶ GET /login/otp  (shows the code form)

 POST /login/otp {otp} ──code ok──▶ Auth::login(user, remember) ──▶ 302 /dashboard
                       ──wrong───▶ attempts+1, error under the input (5 strikes = code deleted)

 POST /login/otp/resend ──not in cooldown, under the cap──▶ new code, new mail, status banner
                        ──too soon / too many──────────────▶ error "wait N seconds"
```

## 2. Things you must not do

- **Do not log the user in before the code is verified.** No `Auth::attempt()` and no
  `Auth::login()` anywhere in the password step. The whole design rests on the user being a
  *guest* until `/login/otp` succeeds; that is what keeps every `auth`-protected route safe
  without adding a single new middleware.
- **Do not add a middleware to the `auth` route groups.** That is the other way to build 2FA
  ("log in, then block until verified"). It would break the 33 `actingAs()` calls across 9 test
  files, and it is not this plan.
- **Do not store the plain code in Redis.** Store `Hash::make($otp)` and compare with
  `Hash::check()`, exactly like [SigningOtpService](app/Services/SigningOtpService.php) does.
  If Redis is ever read by the wrong person, hashes are useless to them; plain codes are a login.
- **Do not put the code anywhere except the email.** Not in the session, not in a cookie, not in
  the URL, not in an Inertia prop, not in a `Log::info()`. The test gets the code by calling the
  service directly, not by reading it from the response.
- **Do not use the `Cache` facade.** The default cache driver in this project is `file`
  ([.env](.env) `CACHE_DRIVER="file"`). The task says Redis; use
  `Illuminate\Support\Facades\Redis`. Do not change `CACHE_DRIVER` or `SESSION_DRIVER` "while you
  are at it" — that is a deployment change with its own risks.
- **Do not reuse `EmailVerificationOtpMail`.** Its body says "finish creating your EZSign
  account", which is wrong for a login. Make a new mailable and template (they are copies with
  different words).
- **Do not add "skip", "trust this device", or "remember this browser for 30 days".** The task
  is *every* login. Those are separate features.
- **Do not add reCAPTCHA to the OTP page.** The password step already has it; the OTP page is
  protected by the code itself and by the attempt limit.
- **Do not touch tests that use `actingAs()`.** They deliberately skip the login form. Only
  [AuthenticationTest.php](tests/Feature/Auth/AuthenticationTest.php) posts to `/login`, and it is
  updated in Phase 6.

## 3. How the pieces already fit together

| You need to | Look at | What you will see |
| --- | --- | --- |
| The login routes | [routes/auth.php:14-24](routes/auth.php#L14-L24) | `GET/POST login` inside `Route::middleware('guest')`. Your three new routes go in this same group |
| What `POST /login` does now | [AuthenticatedSessionController.php:39-46](app/Http/Controllers/Auth/AuthenticatedSessionController.php#L39-L46) | `$request->authenticate()` then `regenerate()` then `redirect()->intended(HOME)` |
| Where the password is actually checked | [LoginRequest.php:44-56](app/Http/Requests/Auth/LoginRequest.php#L44-L56) | `Auth::attempt(...)` — this is the line that logs the user in and must change |
| An OTP service that uses `Hash::make` and a 5-attempt cap | [SigningOtpService.php](app/Services/SigningOtpService.php) | Same rules we want, but stored in a DB row. Ours goes in Redis |
| An OTP service with a 10-minute constant | [EmailVerificationOtpService.php](app/Services/EmailVerificationOtpService.php) | `public const EXPIRES_IN_MINUTES = 10;` — copy the naming style |
| A mailable that carries `$user` and `$otp` | [EmailVerificationOtpMail.php](app/Mail/EmailVerificationOtpMail.php) | 20 lines; copy it, rename, change subject and view |
| The email HTML you will copy | [email-verification-otp.blade.php](resources/views/emails/email-verification-otp.blade.php) | Inline-styled table-free HTML with the code in big digits |
| A controller that validates a 6-digit code | [VerifyEmailController.php](app/Http/Controllers/Auth/VerifyEmailController.php) | `'otp' => ['required', 'digits:6']` and `ValidationException::withMessages(['otp' => …])` |
| The Vue page you will copy | [VerifyEmail.vue](resources/js/Pages/Auth/VerifyEmail.vue) | Code input, verify button, resend button with a 60 s countdown, status banner |
| Where `HOME` lives | [RouteServiceProvider.php:20](app/Providers/RouteServiceProvider.php#L20) | `public const HOME = '/dashboard';` |
| Redis connection config | [config/database.php:122-140](config/database.php#L122-L140) | client `phpredis`, prefix `ezsign_database_` added to every key automatically |
| Redis in Docker | [docker-compose.yml:46-53](docker-compose.yml#L46-L53) and [docker/php/Dockerfile:20-21](docker/php/Dockerfile#L20-L21) | Container `esign-redis`; `pecl install redis` in the PHP image. `.env` has `REDIS_HOST="redis"` |
| The existing login test | [AuthenticationTest.php:21-32](tests/Feature/Auth/AuthenticationTest.php#L21-L32) | Asserts `assertAuthenticated()` after `POST /login`. That assertion becomes wrong and is rewritten |

**Three framework facts this plan depends on.**

1. `Auth::validate($credentials)` checks a password **without** logging anyone in, and
   `Auth::getLastAttempted()` then returns the matching user. Both exist on Laravel 10's
   `SessionGuard` ([vendor: SessionGuard.php:277](vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php#L277)).
2. `Auth::login($user, $remember)` does everything `Auth::attempt()` did after the password check:
   sets the session, issues the remember cookie when `$remember` is true, fires the `Login` event.
3. The `Redis` facade with phpredis passes commands straight through: `Redis::setex($key, $ttl,
   $value)`, `Redis::get`, `Redis::incr`, `Redis::expire`, `Redis::ttl`, `Redis::del`. The key
   prefix from `config/database.php` is applied for you — write `login_otp:…`, never the prefix.
   Always import it as `use Illuminate\Support\Facades\Redis;` — inside a namespace a bare
   `Redis` does not resolve, and in tinker a bare `\Redis` is phpredis's own class, not the facade.

## 4. The design — done for you

Paste from this section. The reasoning is here so you can judge edge cases, not so you can redo it.

### 4.1 Redis keys

All keys are per user, all expire on their own. Nothing needs a cleanup job.

| Key | Value | TTL | Written by | Read by |
| --- | --- | --- | --- | --- |
| `login_otp:{user_id}:hash` | `Hash::make($otp)` | 600 s (10 min) | `generate()` | `verify()` |
| `login_otp:{user_id}:attempts` | integer, wrong-code count | 600 s | `verify()` (`INCR`) | `verify()` |
| `login_otp:{user_id}:cooldown` | `1` | 60 s | `generate()` | `retryAfter()` |
| `login_otp:{user_id}:sends` | integer, codes sent in this window | 600 s from the **first** send | `generate()` (`INCR`) | `retryAfter()` |

`generate()` deletes `attempts` (a new code gets a fresh 5 tries) but **not** `sends` or
`cooldown` — those are the rate limit and must survive a regenerate.

### 4.2 Session key

```php
session('login_otp') === ['user_id' => '9c1e…', 'remember' => false]
```

Written by `AuthenticatedSessionController::store()` after the password passes. Read by every
method of `LoginOtpController`. Forgotten the moment the code is verified. If it is absent, every
OTP route redirects to `/login` — that is the only "are you allowed here" check the OTP page needs.

### 4.3 Rate limits, all of them

| What | Limit | Where enforced | Already exists? |
| --- | --- | --- | --- |
| Wrong password | 5 per email+IP, then lockout | `LoginRequest::ensureIsNotRateLimited()` | Yes, untouched |
| Wrong code | 5 per code, then the code is deleted | `LoginOtpService::verify()` via `attempts` key | New |
| Resend too soon | 1 per 60 s per user | `LoginOtpService::retryAfter()` via `cooldown` key | New |
| Resend too often | 5 per 10 min per user | `LoginOtpService::retryAfter()` via `sends` key | New |
| First send on `POST /login` | Same cooldown and cap as resend | `store()` calls `retryAfter()` before sending | New |

That last row matters. `LoginRequest` only counts *failed* passwords, so without it someone who
knows a password could `POST /login` in a loop and email-bomb the account. With it, a correct
password inside the cooldown just lands on the OTP page — the code sent a moment ago is still
valid, so nothing is lost.

25 guesses per 10 minutes (5 codes × 5 tries) against a 6-digit code is a 0.0025% chance. Good
enough; do not add more.

### 4.4 Why a code sent to the same inbox also verifies the email

Users who registered but never verified their email would otherwise get **two** codes on login:
ours, then the `verified` middleware's on `/verify-email`. A login code proves inbox access,
which is exactly what email verification proves. So on a successful code, if
`! $user->hasVerifiedEmail()`, mark it verified and fire the `Verified` event — the same four lines
[VerifyEmailController.php:37-39](app/Http/Controllers/Auth/VerifyEmailController.php#L37-L39)
already runs. Registration itself still goes through `/verify-email` (it never touches `/login`).

### 4.5 Copy

Email subject: **Your EZSign sign-in code**

Email body, in order: heading "Confirm it's you" · "Hello {name}," · "Someone just signed in to
EZSign with your password. Enter the code below to finish signing in. It expires in 10 minutes." ·
the code · "Enter this code on the sign-in page to continue." · "If this wasn't you, change your
password now — someone else knows it."

Page title: **Check your email** · description: "Enter the 6-digit code we sent to
**{email}**. It expires in 10 minutes." · button: "Verify and sign in" · resend: "Resend code" /
"Resend in {n}s" · secondary link: "Use a different account" → `/login` · banner after resend:
"New code sent" / "Check your inbox for the latest code."

Error messages (the user reads these, keep them exact):
- wrong or expired code: `The code is invalid or has expired. Request a new one below.`
- resend blocked: `Please wait {n} seconds before requesting another code.`

## 5. Step by step

Work in this order. Each phase ends with something you can run. Commands run inside the app
container: prefix them with `docker exec esign-app` (or `docker exec -it esign-app bash` once).

### Phase 0 — prove Redis is reachable (5 minutes)

```bash
docker exec esign-app php -r "echo extension_loaded('redis') ? 'phpredis OK' : 'phpredis MISSING', PHP_EOL;"
docker exec esign-app php artisan tinker --execute "var_dump(Illuminate\Support\Facades\Redis::connection()->ping());"
```

Expected: `phpredis OK` and `bool(true)` (or the string `+PONG`). If the first line says MISSING,
rebuild the image (`docker compose build app`). If the second fails, `docker compose up -d redis`.
Do not continue until both pass — every later phase and every test needs this.

### Phase 1 — the service (Redis only, no HTTP)

Create `app/Services/LoginOtpService.php`:

```php
<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class LoginOtpService
{
    public const EXPIRES_IN_SECONDS = 600;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_SENDS_PER_WINDOW = 5;
    public const MAX_VERIFY_ATTEMPTS = 5;

    public function send(User $user): void
    {
        $otp = $this->generate($user);

        Mail::to($user->email)->send(new LoginOtpMail($user, $otp));
    }

    public function generate(User $user): string
    {
        $otp = (string) random_int(100000, 999999);

        Redis::setex($this->key($user, 'hash'), self::EXPIRES_IN_SECONDS, Hash::make($otp));
        Redis::del($this->key($user, 'attempts'));
        Redis::setex($this->key($user, 'cooldown'), self::RESEND_COOLDOWN_SECONDS, 1);

        if ((int) Redis::incr($this->key($user, 'sends')) === 1) {
            Redis::expire($this->key($user, 'sends'), self::EXPIRES_IN_SECONDS);
        }

        return $otp;
    }

    public function verify(User $user, string $otp): bool
    {
        $hash = Redis::get($this->key($user, 'hash'));

        if (! $hash) {
            return false;
        }

        $attempts = (int) Redis::incr($this->key($user, 'attempts'));
        Redis::expire($this->key($user, 'attempts'), self::EXPIRES_IN_SECONDS);

        if ($attempts > self::MAX_VERIFY_ATTEMPTS) {
            $this->forget($user);

            return false;
        }

        if (! Hash::check($otp, $hash)) {
            return false;
        }

        $this->forget($user);

        return true;
    }

    // 0 when a code may be sent now, otherwise seconds until it may.
    public function retryAfter(User $user): int
    {
        $cooldown = (int) Redis::ttl($this->key($user, 'cooldown'));

        if ($cooldown > 0) {
            return $cooldown;
        }

        if ((int) Redis::get($this->key($user, 'sends')) >= self::MAX_SENDS_PER_WINDOW) {
            return max(1, (int) Redis::ttl($this->key($user, 'sends')));
        }

        return 0;
    }

    private function forget(User $user): void
    {
        Redis::del($this->key($user, 'hash'), $this->key($user, 'attempts'));
    }

    private function key(User $user, string $suffix): string
    {
        return "login_otp:{$user->id}:{$suffix}";
    }
}
```

Notes for reading it:
- `Redis::ttl` returns `-2` for a missing key and `-1` for a key with no expiry; `> 0` covers both.
- `verify()` counts the attempt **before** comparing, so a wrong 5th guess and a right 6th guess
  both fail. That is the point.
- `send()` is the only method controllers should call to deliver a code. `generate()` is public
  so tests can get the code without reading an email.

Quick check (the mailable does not exist yet, so only `generate`/`verify`):

```bash
docker exec esign-app php artisan tinker --execute "
\$u = App\Models\User::first();
\$s = app(App\Services\LoginOtpService::class);
\$code = \$s->generate(\$u);
var_dump(\$s->verify(\$u, '000000'), \$s->verify(\$u, \$code), \$s->verify(\$u, \$code), \$s->retryAfter(\$u));
"
```

Expected: `false, true, false, <a number near 60>`. Then look at the keys:

```bash
docker exec esign-redis redis-cli --scan --pattern '*login_otp*'
docker exec esign-redis redis-cli TTL "ezsign_database_login_otp:<paste-the-uuid>:cooldown"
```

You should see `cooldown` and `sends` (the hash was deleted by the successful verify). The
prefix `ezsign_database_` is `Str::slug(APP_NAME).'_database_'` — if `APP_NAME` differs, the
`--scan` output shows you the real one.

### Phase 2 — the email

Create `app/Mail/LoginOtpMail.php` — a copy of
[EmailVerificationOtpMail.php](app/Mail/EmailVerificationOtpMail.php) with three edits: the class
name, the subject `'Your EZSign sign-in code'`, and the view `'emails.login-otp'`.

Create `resources/views/emails/login-otp.blade.php` — a copy of
[email-verification-otp.blade.php](resources/views/emails/email-verification-otp.blade.php) with
the text replaced by the copy in 4.5. Six strings change: `<title>`, the `<h2>`, the first
paragraph, the small label above the code, the line under the code, and the last paragraph. Keep
every `style=""` attribute as it is.

Check it renders:

```bash
docker exec esign-app php artisan tinker --execute "
\$u = App\Models\User::first();
Illuminate\Support\Facades\Mail::to(\$u->email)->send(new App\Mail\LoginOtpMail(\$u, '123456'));
echo 'sent';
"
```

Open Mailpit at http://localhost:8025 and read it. Subject, heading, the "change your password"
line, and `123456` in big digits.

### Phase 3 — the controller and routes

Create `app/Http/Controllers/Auth/LoginOtpController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\LoginOtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginOtpController extends Controller
{
    public function __construct(
        private LoginOtpService $otp
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/LoginOtp', [
            'email' => $user->email,
            'status' => session('status'),
            'resendAfter' => $this->otp->retryAfter($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $this->otp->verify($user, $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => 'The code is invalid or has expired. Request a new one below.',
            ]);
        }

        $remember = (bool) $request->session()->get('login_otp.remember');
        $request->session()->forget('login_otp');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $wait = $this->otp->retryAfter($user);

        if ($wait > 0) {
            throw ValidationException::withMessages([
                'otp' => "Please wait {$wait} seconds before requesting another code.",
            ]);
        }

        $this->otp->send($user);

        return back()->with('status', 'login-code-sent');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('login_otp.user_id');

        return $id ? User::find($id) : null;
    }
}
```

Add the routes to [routes/auth.php](routes/auth.php) inside the existing
`Route::middleware('guest')->group(...)`, right after the two `login` lines:

```php
    Route::get('login/otp', [LoginOtpController::class, 'create'])
                ->name('login.otp');

    Route::post('login/otp', [LoginOtpController::class, 'store'])
                ->name('login.otp.verify');

    Route::post('login/otp/resend', [LoginOtpController::class, 'resend'])
                ->name('login.otp.resend');
```

and the import at the top: `use App\Http\Controllers\Auth\LoginOtpController;`

Why `guest`: [RedirectIfAuthenticated](app/Http/Middleware/RedirectIfAuthenticated.php) sends
anyone already logged in to `/dashboard`, which is exactly right for an OTP page. Why no
`throttle:` middleware: the service already limits per user, and per-IP throttling would punish
an office sharing one IP.

Check: `docker exec esign-app php artisan route:list --name=login.otp` lists three routes.

### Phase 4 — stop logging in at the password step

**[LoginRequest.php](app/Http/Requests/Auth/LoginRequest.php)** — change `authenticate()` to check
without logging in and to return the user:

```php
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return Auth::getLastAttempted();
    }
```

Add `use App\Models\User;` to the imports. The `remember` value is no longer read here; the
controller reads it.

**[AuthenticatedSessionController.php](app/Http/Controllers/Auth/AuthenticatedSessionController.php)** —
replace `store()`:

```php
    public function store(LoginRequest $request, LoginOtpService $otp): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->put('login_otp', [
            'user_id' => $user->id,
            'remember' => $request->boolean('remember'),
        ]);

        if ($otp->retryAfter($user) === 0) {
            $otp->send($user);
        }

        return redirect()->route('login.otp');
    }
```

Add `use App\Services\LoginOtpService;`. The old `$request->session()->regenerate()` and
`redirect()->intended(...)` move to `LoginOtpController::store()` — they belong after the
*real* login. `redirect()->intended()` still works there because Laravel stores the intended URL
in the session, and the session survives the OTP step.

Check by hand: `npm run build`, open http://localhost:8000/login, sign in. You must land on
`/login/otp`, **not** the dashboard, and a code must be in Mailpit. Visiting
http://localhost:8000/dashboard now must bounce you to `/login` — you are still a guest.

### Phase 5 — the page

Create `resources/js/Pages/Auth/LoginOtp.vue`. It is
[VerifyEmail.vue](resources/js/Pages/Auth/VerifyEmail.vue) with the email coming from a prop
(there is no logged-in user to read it from), different route names, a countdown that starts at
the server's `resendAfter`, a visible error for a blocked resend, and "Use a different account"
instead of "Sign out":

```vue
<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthLayout from "@/Layouts/AuthLayout.vue";
import { ShieldCheck, MailCheck, RefreshCcw, ArrowLeft } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

const props = defineProps({
    email: String,
    status: String,
    resendAfter: Number,
});

const form = useForm({ otp: "" });
const resendForm = useForm({});
const resendCooldown = ref(0);
let timer = null;

const startCooldown = (seconds) => {
    clearInterval(timer);
    resendCooldown.value = seconds;

    if (seconds <= 0) {
        return;
    }

    timer = setInterval(() => {
        resendCooldown.value--;

        if (resendCooldown.value <= 0) {
            clearInterval(timer);
        }
    }, 1000);
};

onMounted(() => startCooldown(props.resendAfter ?? 0));
onBeforeUnmount(() => clearInterval(timer));

const verify = () => form.post(route("login.otp.verify"));

const resend = () => {
    if (resendCooldown.value > 0) {
        return;
    }

    resendForm.post(route("login.otp.resend"), {
        preserveScroll: true,
        onSuccess: () => startCooldown(60),
    });
};

const codeSent = computed(() => props.status === "login-code-sent");
</script>

<template>
    <Head title="Check your email" />

    <AuthLayout>
        <Card class="w-full max-w-md rounded-2xl border shadow-xl bg-background/95">
            <CardHeader class="items-center text-center space-y-5">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-primary/10">
                    <ShieldCheck class="h-10 w-10 text-accent-ink" />
                </div>

                <div>
                    <CardTitle class="text-3xl font-bold">
                        Check your email
                    </CardTitle>

                    <CardDescription class="mt-2 text-base">
                        Enter the 6-digit code we sent to <span class="font-semibold text-foreground">{{ email }}</span>. It expires in 10 minutes.
                    </CardDescription>
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <div
                    v-if="codeSent"
                    class="rounded-xl border border-emerald-200 bg-pulse-green/15 p-4"
                >
                    <div class="flex gap-3">
                        <MailCheck class="mt-0.5 h-5 w-5 text-pulse-green" />

                        <div>
                            <p class="font-medium text-pulse-green">
                                New code sent
                            </p>

                            <p class="mt-1 text-sm text-pulse-green">
                                Check your inbox for the latest code.
                            </p>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="verify" class="space-y-4">
                    <div class="space-y-2">
                        <Input
                            v-model="form.otp"
                            inputmode="numeric"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="000000"
                            autofocus
                            class="text-center text-2xl tracking-[0.5em]"
                        />

                        <p
                            v-if="form.errors.otp"
                            class="text-sm text-destructive text-center"
                        >
                            {{ form.errors.otp }}
                        </p>
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing || form.otp.length !== 6"
                    >
                        Verify and sign in
                    </Button>
                </form>

                <div class="text-center">
                    <p class="text-sm text-muted-foreground">
                        Didn't receive the code?
                    </p>

                    <Button
                        variant="ghost"
                        class="mt-1"
                        :disabled="resendForm.processing || resendCooldown > 0"
                        @click="resend"
                    >
                        <RefreshCcw class="mr-2 h-4 w-4" />

                        {{
                            resendCooldown > 0
                                ? `Resend in ${resendCooldown}s`
                                : "Resend code"
                        }}
                    </Button>

                    <p
                        v-if="resendForm.errors.otp"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ resendForm.errors.otp }}
                    </p>
                </div>

                <Link
                    :href="route('login')"
                    class="flex w-full items-center justify-center rounded-lg border py-2.5 text-sm font-medium transition hover:bg-muted"
                >
                    <ArrowLeft class="mr-2 h-4 w-4" />

                    Use a different account
                </Link>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
```

Two things that are easy to miss:
- Errors from `resendForm.post(...)` land in `resendForm.errors`, not `form.errors`. The
  original `VerifyEmail.vue` never shows them; this page does, under the resend button.
- The countdown starts from `resendAfter` (the server's real number) so the button is disabled
  correctly even after a page refresh. After a successful resend it restarts at 60, which is
  `RESEND_COOLDOWN_SECONDS`. If you change one, change the other.

`route('login.otp.verify')` works because [app.blade.php:33](resources/views/app.blade.php#L33)
has `@routes` — Ziggy picks up new routes on the next page load, no generation step.

[Login.vue](resources/js/Pages/Auth/Login.vue) needs **no change**. Inertia follows the redirect
to `/login/otp` and renders the new page.

Run `npm run build` and do the browser checks in section 6 before writing tests.

### Phase 6 — tests

**Fix the one test that is now wrong.** In
[AuthenticationTest.php](tests/Feature/Auth/AuthenticationTest.php), replace
`test_users_can_authenticate_using_the_login_screen` with:

```php
    public function test_a_correct_password_sends_a_code_instead_of_logging_in(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login/otp');
        $response->assertSessionHas('login_otp.user_id', $user->id);
        Mail::assertSent(LoginOtpMail::class, fn ($mail) => $mail->hasTo($user->email));
    }
```

Add `use App\Mail\LoginOtpMail;` and `use Illuminate\Support\Facades\Mail;`. Leave the other
three tests alone.

**Create `tests/Feature/Auth/LoginOtpTest.php`.** Each test starts by putting the user in the
"password passed" state with `withSession(['login_otp' => [...]])` and getting a code from the
service — the same way `EmailVerificationTest` calls `generate()` instead of reading an email.
Tests talk to the real Redis in Docker; user ids are random UUIDs, so tests never collide with
each other or with your dev session.

```php
<?php

namespace Tests\Feature\Auth;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\LoginOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LoginOtpTest extends TestCase
{
    use RefreshDatabase;

    private function pending(User $user, bool $remember = false): static
    {
        return $this->withSession([
            'login_otp' => ['user_id' => $user->id, 'remember' => $remember],
        ]);
    }

    public function test_the_otp_page_redirects_to_login_when_nothing_is_pending(): void
    {
        $this->get('/login/otp')->assertRedirect('/login');
        $this->post('/login/otp', ['otp' => '123456'])->assertRedirect('/login');
        $this->post('/login/otp/resend')->assertRedirect('/login');
    }

    public function test_the_otp_page_renders_for_a_pending_login(): void
    {
        $user = User::factory()->create();

        $this->pending($user)->get('/login/otp')->assertOk();
    }

    public function test_a_correct_code_logs_the_user_in(): void
    {
        $user = User::factory()->create();
        $code = app(LoginOtpService::class)->generate($user);

        $response = $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(RouteServiceProvider::HOME);
        $response->assertSessionMissing('login_otp');
    }

    public function test_remember_me_survives_the_otp_step(): void
    {
        // The factory fills remember_token with a random string, so start from null.
        $user = User::factory()->create(['remember_token' => null]);
        $code = app(LoginOtpService::class)->generate($user);

        $this->pending($user, remember: true)->post('/login/otp', ['otp' => $code]);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create();
        app(LoginOtpService::class)->generate($user);

        $response = $this->pending($user)->post('/login/otp', ['otp' => '000000']);

        $this->assertGuest();
        $response->assertSessionHasErrors('otp');
        $response->assertSessionHas('login_otp.user_id', $user->id);
    }

    public function test_a_code_that_is_gone_from_redis_is_rejected(): void
    {
        $user = User::factory()->create();
        $code = app(LoginOtpService::class)->generate($user);
        Redis::del("login_otp:{$user->id}:hash");

        $response = $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertGuest();
        $response->assertSessionHasErrors('otp');
    }

    public function test_five_wrong_guesses_burn_the_code(): void
    {
        $user = User::factory()->create();
        $code = app(LoginOtpService::class)->generate($user);

        for ($i = 0; $i < 5; $i++) {
            $this->pending($user)->post('/login/otp', ['otp' => '000000']);
        }

        $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertGuest();
    }

    public function test_resend_is_blocked_inside_the_cooldown(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(LoginOtpService::class)->generate($user);

        $response = $this->pending($user)->post('/login/otp/resend');

        $response->assertSessionHasErrors('otp');
        Mail::assertNothingSent();
    }

    public function test_resend_sends_a_new_code_after_the_cooldown(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        app(LoginOtpService::class)->generate($user);
        Redis::del("login_otp:{$user->id}:cooldown");

        $response = $this->pending($user)->post('/login/otp/resend');

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'login-code-sent');
        Mail::assertSent(LoginOtpMail::class, 1);
    }

    public function test_resend_is_blocked_after_five_sends(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $service = app(LoginOtpService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->generate($user);
        }
        Redis::del("login_otp:{$user->id}:cooldown");

        $response = $this->pending($user)->post('/login/otp/resend');

        $response->assertSessionHasErrors('otp');
        Mail::assertNothingSent();
    }

    public function test_a_correct_code_also_verifies_an_unverified_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $code = app(LoginOtpService::class)->generate($user);

        $this->pending($user)->post('/login/otp', ['otp' => $code]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_a_logged_in_user_is_sent_away_from_the_otp_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login/otp')->assertRedirect(RouteServiceProvider::HOME);
    }
}
```

Why `Redis::del(...)` in two tests: cooldown and expiry are real clock time in Redis, so
`travel()` cannot fake them. Deleting the key is the honest equivalent of "60 seconds passed".
The prefix is added by the connection, so the test writes the bare key.

Run:

```bash
docker exec esign-app php artisan test tests/Feature/Auth
docker exec esign-app php artisan test
```

All of `tests/Feature/Auth` must pass. In the full run, the only failures allowed are ones that
already fail on `main` before your branch (check with `git stash` if unsure — at the time of
writing there are 4 pre-existing failures in `TemplateUploadTest` and a TCPDF warning; none of
them touch auth).

## 6. Manual checks before you open the PR

Run `npm run build` first. Mailpit is at http://localhost:8025.

| # | Do | Expect |
| --- | --- | --- |
| 1 | Log in with a correct password | Land on `/login/otp`, page shows your email, resend button reads "Resend in ~60s" |
| 2 | Open a new tab → http://localhost:8000/dashboard | Redirected to `/login`. You are not logged in yet |
| 3 | Mailpit | One email, subject "Your EZSign sign-in code", code in big digits, "change your password" line present |
| 4 | Type a wrong code | Red text "The code is invalid or has expired. Request a new one below." Still on the page |
| 5 | Type the right code | Dashboard. Refresh: still logged in |
| 6 | Log out, log in again with **Remember me** ticked, pass the code, close the browser fully, reopen | Still logged in (remember cookie set at the OTP step) |
| 7 | Log out, log in, then refresh the OTP page | Countdown continues from the real remaining seconds, not from 60 |
| 8 | Wait for the countdown to hit 0, click Resend | Green "New code sent" banner, a second email in Mailpit, countdown restarts at 60 |
| 9 | Right after step 8: `docker exec esign-redis redis-cli --scan --pattern '*login_otp*'`, then `TTL` the `cooldown` key and `GET` the `sends` key | `cooldown` TTL is close to 60 and counting down; `sends` is `2`. (The refusal itself is covered by `test_resend_is_blocked_inside_the_cooldown` — the button is disabled in the UI, so you cannot trigger it by hand) |
| 10 | Enter a wrong code 5 times, then the right one | Rejected. Resend, use the new code → logged in |
| 11 | `docker exec esign-redis redis-cli --scan --pattern '*login_otp*'` after step 5 | No `hash` or `attempts` key for your user; `cooldown` (if under 60 s) and `sends` may remain and expire on their own |
| 12 | Register a brand-new account | Unchanged: straight to `/verify-email` with **one** email (the registration code). No login code |
| 13 | Log out, log in as an account whose email is **not** verified, pass the code | Dashboard directly, no `/verify-email` page, `email_verified_at` now set |
| 14 | While on `/login/otp`, click "Use a different account", log in as someone else | Their code, their email on the page, their dashboard |

## 7. Definition of done

- [ ] Phase 0 commands both succeed on a fresh `docker compose up`
- [ ] `POST /login` with a correct password leaves the user a guest (`assertGuest()` in the test)
- [ ] `login_otp:{id}:hash` in Redis is a bcrypt hash (`$2y$…`), never six digits, with `TTL` ≤ 600
- [ ] Resend blocked inside 60 s and after 5 sends; both covered by tests
- [ ] 5 wrong guesses invalidate the code; covered by a test
- [ ] `remember` still works; covered by a test
- [ ] Unverified users are verified by the login code; covered by a test
- [ ] `tests/Feature/Auth` green; full suite has no new failures
- [ ] All 14 manual checks pass
- [ ] `grep -rn "Auth::attempt" app/` returns nothing
- [ ] The code never appears in `storage/logs/laravel.log`, the session, or an Inertia prop
- [ ] No changes to `.env`, `config/cache.php`, `config/session.php`, or any `auth`-group route

## 8. Files you will touch

| File | Change |
| --- | --- |
| `app/Services/LoginOtpService.php` | new |
| `app/Mail/LoginOtpMail.php` | new |
| `resources/views/emails/login-otp.blade.php` | new |
| `app/Http/Controllers/Auth/LoginOtpController.php` | new |
| `resources/js/Pages/Auth/LoginOtp.vue` | new |
| `tests/Feature/Auth/LoginOtpTest.php` | new |
| `routes/auth.php` | +3 routes, +1 import |
| `app/Http/Requests/Auth/LoginRequest.php` | `authenticate()` uses `Auth::validate`, returns `User` |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | `store()` sends a code instead of logging in |
| `tests/Feature/Auth/AuthenticationTest.php` | one test rewritten |

Ten files, roughly 450 lines, most of it copy-and-edit. One PR, one commit is fine. Suggested
title: `feat(auth): require an emailed one-time code on every login`. In the PR body, list which
of the 14 manual checks you ran and paste the `redis-cli TTL` output from check 11.
