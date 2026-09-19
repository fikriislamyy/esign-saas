# reCAPTCHA v2 on login, register and password reset

## 1. What we are building, in one paragraph

Four guest forms get a Google reCAPTCHA v2 checkbox ("I'm not a robot"): **Login**, **Register**,
**Forgot password** (the page that asks for your email) and **Reset password** (the page you land on
from the email link). The browser shows the checkbox; when the user ticks it, Google gives the page
a token; the form sends that token to our server as an extra field called `recaptcha_token`; the
server sends that token to Google and Google replies "yes this is a real person" or "no". If Google
says no, or the token is missing, the form fails validation with a normal error message under the
checkbox, exactly like a wrong password does.

**The browser check is decoration. The server check is the security.** Anyone can skip the browser
and POST straight to `/login` with `curl`. That is why every one of the four POST endpoints must
verify the token on the backend. If you only add the widget to the Vue pages and do not touch PHP,
the feature is not done.

## 2. Things you must not do

- **Do not** use reCAPTCHA v3 or Enterprise. The task says v2, checkbox. v3 is invisible and scores
  users; it is a different product with a different API.
- **Do not** install a Composer or npm package for this. Verification is one HTTP POST; the widget is
  one `<script>` tag. A package adds an abstraction we would have to learn and maintain for ~40 lines
  of code.
- **Do not** put the secret key anywhere the browser can see it. It goes in `.env` and
  `config/services.php` only. The *site* key is public and is sent to the browser; the *secret* key
  is never sent to the browser. If you find yourself writing `recaptchaSecret` in a `.vue` file,
  stop.
- **Do not** add the check to `ConfirmPassword.vue`, `VerifyEmail.vue`, profile update or any page
  behind `auth` middleware. Those users are already logged in. The task lists three pages (with
  "password reset" covering two screens); that is the scope.
- **Do not** remove the existing rate limiter in `LoginRequest::ensureIsNotRateLimited()`. Captcha
  and rate limiting protect against different things. Both stay.
- **Do not** make existing tests pass by deleting them. Section 7 explains how to make them pass
  properly.

## 3. How the codebase already does the things you will need

Read these before writing anything. Every pattern you need already exists.

| You need to | Look at | What you will see |
| --- | --- | --- |
| Read a key from `.env` | [config/services.php](config/services.php) `pakasir` block | `env('PAKASIR_API_KEY')` under a service name, with a `(bool)` cast for flags |
| Send a public key to every Vue page | [app/Http/Middleware/HandleInertiaRequests.php](app/Http/Middleware/HandleInertiaRequests.php) `share()` | `'stripeKey' => config('services.stripe.key')` — read it in Vue via `usePage().props.stripeKey` |
| Call an external HTTP API from PHP | [app/Services/PakasirService.php](app/Services/PakasirService.php) | `Http::asJson()->post(...)`, then `->json()` |
| Fail a form with a message under one field | [app/Http/Requests/Auth/LoginRequest.php](app/Http/Requests/Auth/LoginRequest.php) `authenticate()` | `throw ValidationException::withMessages(['email' => '...'])` |
| Show a validation error in Vue | [resources/js/Pages/Auth/Login.vue](resources/js/Pages/Auth/Login.vue) | `<p v-if="form.errors.email" class="text-sm text-destructive">` |
| Know whether dark mode is on | [resources/js/Pages/Landing.vue:57](resources/js/Pages/Landing.vue#L57) | `document.documentElement.classList.contains("dark")` |

The four POST endpoints and where they validate:

| Form | Route (routes/auth.php) | Validates in |
| --- | --- | --- |
| Login | `POST /login` | `LoginRequest::rules()` (a FormRequest class) |
| Register | `POST /register` | `RegisteredUserController::store()`, inline `$request->validate([...])` |
| Forgot password | `POST /forgot-password` | `PasswordResetLinkController::store()`, inline |
| Reset password | `POST /reset-password` | `NewPasswordController::store()`, inline |

Login is different from the other three. Keep that in mind in Phase 3.

## 4. Get the keys (do this first, it takes five minutes)

1. Go to the Google reCAPTCHA admin console (search "recaptcha admin console").
2. Create a site. Type: **reCAPTCHA v2**, sub-type **"I'm not a robot" Checkbox**.
3. Domains: add `localhost` and the production domain.
4. You get two strings: a **site key** and a **secret key**.
5. Put them in `.env`:

```
RECAPTCHA_SITE_KEY=6Lxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
RECAPTCHA_SECRET_KEY=6Lyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy
```

For local development without real keys, Google publishes test keys that always pass. Search
"recaptcha test keys" in Google's own FAQ; the site key starts with `6LeIxAcTAAAAAJcZ` and the
secret with `6LeIxAcTAAAAAGG-`. They render a widget that says "for testing purposes only" and
always verify as success. Fine for local, never for production.

`.env` is not committed. There is no `.env.example` in this repo, so tell whoever deploys that these
two variables are now required.

## 5. Phase 1 — config and a service (backend, no UI yet)

### 5.1 `config/services.php`

Add a block next to `pakasir`:

```php
'recaptcha' => [
    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
],
```

Nothing else in the app reads `env()` directly and neither should this. Always go through
`config('services.recaptcha.site_key')`.

### 5.2 New file: `app/Services/RecaptchaService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function isConfigured(): bool
    {
        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.secret_key'));
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (blank($token)) {
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $token,
            'remoteip' => $ip,
        ]);

        if (! $response->successful()) {
            Log::warning('reCAPTCHA siteverify request failed', [
                'status' => $response->status(),
            ]);

            return false;
        }

        $body = $response->json();

        if (($body['success'] ?? false) !== true) {
            Log::info('reCAPTCHA rejected a token', [
                'error-codes' => $body['error-codes'] ?? [],
            ]);
        }

        return ($body['success'] ?? false) === true;
    }
}
```

Three things to notice:

- `asForm()`, not `asJson()`. Google's endpoint wants `application/x-www-form-urlencoded`. This
  is the opposite of Pakasir. If you copy `PakasirService` and keep `asJson()`, Google will always
  return `{"success": false, "error-codes": ["missing-input-secret"]}` and you will spend an hour
  wondering why.
- A network failure returns `false`, not an exception. If Google is down, the user sees "please
  complete the captcha" and can retry. We do not want a 500 page on login.
- The `Log::info` on rejection is deliberate. A rejected token is normal (expired, reused, bot).
  You want to be able to see the `error-codes` when debugging, and the most common one,
  `timeout-or-duplicate`, tells you the user waited more than two minutes or the token was sent
  twice. See section 8.

### 5.3 Check it works before touching anything else

```bash
docker exec esign-app php artisan config:clear
docker exec esign-app php artisan tinker --execute="
  var_dump(app(App\Services\RecaptchaService::class)->isConfigured());
  var_dump(app(App\Services\RecaptchaService::class)->verify('garbage'));
"
```

You want `bool(true)` then `bool(false)`. The second one proves you reached Google and it said no.
If the first is `false`, your `.env` is wrong. If the second throws, read the error; it is almost
always a typo in the URL.

## 6. Phase 2 — a validation rule the four endpoints can share

We need to say "this field must contain a token Google accepts" in four places. A custom rule
class does that in one place.

### 6.1 New file: `app/Rules/Recaptcha.php`

There is no `app/Rules` folder yet. Create it.

```php
<?php

namespace App\Rules;

use App\Services\RecaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Recaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(RecaptchaService::class)->verify($value, request()->ip())) {
            $fail('Please confirm you are not a robot.');
        }
    }
}
```

That is the whole class. Now `'recaptcha_token' => ['required', new Recaptcha]` works anywhere
`$request->validate()` or `rules()` is used, and the failure lands in `form.errors.recaptcha_token`
on the Vue side like any other field.

The message says "confirm you are not a robot", not "invalid token", because the user reading it
is a person who ticked a box that then expired. Tell them what to do, not what went wrong.

## 7. Phase 3 — verify on all four endpoints, and keep the tests green

### 7.1 The test problem, explained before you hit it

`tests/Feature/Auth/` has tests that POST to `/login`, `/register`, `/forgot-password` and
`/reset-password` with no captcha token. The moment you add the rule, every one of them fails with
a validation error. There are two wrong fixes and one right one.

Wrong: delete the tests. Wrong: add a real token to the tests (there is no such thing; tokens come
from a browser session with Google).

Right: the rule is skipped when the app is not configured for reCAPTCHA, and the test environment
is not configured. Change `Recaptcha::validate()`:

```php
public function validate(string $attribute, mixed $value, Closure $fail): void
{
    $service = app(RecaptchaService::class);

    if (! $service->isConfigured()) {
        return;
    }

    if (! $service->verify($value, request()->ip())) {
        $fail('Please confirm you are not a robot.');
    }
}
```

and drop `'required'` from the rule list; use `'nullable'` instead, so a missing field is accepted
when the check is off and rejected by `verify()` (blank → false) when it is on.

Then make sure the test environment really is unconfigured. **Laravel does read `.env` during
tests**; `phpunit.xml` only overrides the variables it explicitly names. Your `.env` has the real
keys in it from section 4, so without an override `isConfigured()` returns `true` in tests and
every existing auth test breaks. Add two lines to the `<php>` block in `phpunit.xml`:

```xml
<env name="RECAPTCHA_SITE_KEY" value=""/>
<env name="RECAPTCHA_SECRET_KEY" value=""/>
```

An empty string is `blank()`, so `isConfigured()` is `false`, the rule is a no-op, and the
existing tests pass unchanged. This is the same reason `MAIL_MAILER` and `SESSION_DRIVER` are
overridden there: the test run must not depend on what happens to be in your local `.env`.

This also means: **if production is deployed without the keys, the captcha silently turns off.**
That is a real trade-off. The alternative, failing every login when misconfigured, is worse for a
document-signing product where locked-out users cannot sign contracts. To make the trade-off
visible instead of silent, add this to `AuthenticatedSessionController::create()` (the GET that
renders the login page):

```php
if (! app(RecaptchaService::class)->isConfigured() && app()->isProduction()) {
    Log::error('reCAPTCHA is not configured; auth forms are unprotected', [
        'env_keys' => ['RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY'],
    ]);
}
```

It is the same pattern as the `QRIS blocked: Pakasir is not configured` log in
`SubscriptionController`: a loud line in the log the first time someone opens the page, rather
than a silent gap.

### 7.2 Login — `app/Http/Requests/Auth/LoginRequest.php`

```php
use App\Rules\Recaptcha;

public function rules(): array
{
    return [
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
        'recaptcha_token' => ['nullable', 'string', new Recaptcha],
    ];
}
```

Nothing else in this file changes. `authenticate()` runs after `rules()` pass, so a bot with no
token never reaches `Auth::attempt()` and never burns a rate-limit slot.

### 7.3 Register — `app/Http/Controllers/Auth/RegisteredUserController.php`

Add `use App\Rules\Recaptcha;` at the top, then in the `$request->validate([...])` array add:

```php
'recaptcha_token' => ['nullable', 'string', new Recaptcha],
```

Put it last, after `'password'`. Order does not affect behaviour, but the array reads top-to-bottom
as the form does and the captcha is at the bottom of the form.

### 7.4 Forgot password — `app/Http/Controllers/Auth/PasswordResetLinkController.php`

Same `use` line, then:

```php
$request->validate([
    'email' => 'required|email',
    'recaptcha_token' => ['nullable', 'string', new Recaptcha],
]);
```

This one matters more than it looks. Without it, `/forgot-password` is an open endpoint that sends
an email to any address you name, as fast as you can POST. That is a spam vector against
arbitrary third parties using our sender reputation.

### 7.5 Reset password — `app/Http/Controllers/Auth/NewPasswordController.php`

Same `use` line, then:

```php
$request->validate([
    'token' => 'required',
    'email' => 'required|email',
    'password' => ['required', 'confirmed', Rules\Password::defaults()],
    'recaptcha_token' => ['nullable', 'string', new Recaptcha],
]);
```

Note this file already imports `Illuminate\Validation\Rules` under the alias `Rules`, so the
`Rules\Password::defaults()` line stays as-is and your new import is a separate
`use App\Rules\Recaptcha;`. Two different namespaces that both happen to contain the word "Rules".
Do not "tidy" one into the other.

### 7.6 Run the tests now, before any Vue

```bash
docker exec esign-app php artisan test tests/Feature/Auth
```

Everything that passed before must still pass. If `RegistrationTest` or `PasswordResetTest` fails
with a `recaptcha_token` error, `isConfigured()` is returning `true` in the test env, which means
you skipped the `phpunit.xml` override in 7.1.

## 8. Phase 4 — the widget (frontend)

### 8.1 Share the site key with every page

In `HandleInertiaRequests::share()`, next to `'stripeKey'`:

```php
'recaptchaSiteKey' => config('services.recaptcha.site_key'),
```

This is the *site* key. It is meant to be public; Google's own instructions tell you to paste it
into HTML. The *secret* key is not shared and must never be.

### 8.2 Load Google's script once

In `resources/views/app.blade.php`, inside `<head>`, before `@vite`:

```blade
@if (config('services.recaptcha.site_key'))
    <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>
@endif
```

`render=explicit` means "do not auto-render on any element with class `g-recaptcha`; I will call
`grecaptcha.render()` myself." We want that because Inertia swaps pages without reloading, and the
auto-render only runs once on initial load. Explicit rendering lets each Vue page mount its own
widget when it appears.

The `@if` means a dev without keys gets no script tag, no console errors, and forms that work
because the backend rule is off too.

### 8.3 New component: `resources/js/Components/RecaptchaField.vue`

One component used by all four pages. It renders the widget, hands the token to the parent through
`v-model`, resets itself when the form fails so the user gets a fresh checkbox, and shows the
validation error.

```vue
<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    modelValue: { type: String, default: "" },
    error: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const siteKey = usePage().props.recaptchaSiteKey;
const container = ref(null);
let widgetId = null;

const theme = () =>
    document.documentElement.classList.contains("dark") ? "dark" : "light";

const render = () => {
    if (!siteKey || !window.grecaptcha?.render || !container.value) return;

    widgetId = window.grecaptcha.render(container.value, {
        sitekey: siteKey,
        theme: theme(),
        callback: (token) => emit("update:modelValue", token),
        "expired-callback": () => emit("update:modelValue", ""),
        "error-callback": () => emit("update:modelValue", ""),
    });
};

const reset = () => {
    if (widgetId !== null && window.grecaptcha?.reset) {
        window.grecaptcha.reset(widgetId);
    }
    emit("update:modelValue", "");
};

defineExpose({ reset });

onMounted(() => {
    if (!siteKey) return;

    if (window.grecaptcha?.render) {
        render();
        return;
    }

    // Script tag is async; poll briefly until it lands.
    const timer = setInterval(() => {
        if (window.grecaptcha?.render) {
            clearInterval(timer);
            render();
        }
    }, 100);

    onBeforeUnmount(() => clearInterval(timer));
});

watch(
    () => props.error,
    (message) => {
        if (message) reset();
    },
);
</script>

<template>
    <div v-if="siteKey" class="space-y-2">
        <div ref="container" class="flex justify-center"></div>

        <p v-if="error" class="text-center text-sm text-destructive">
            {{ error }}
        </p>
    </div>
</template>
```

Why each piece exists:

- **`v-if="siteKey"` on the root.** No key, no widget, no space taken. Matches the backend
  behaviour where no key means no check. A dev without keys sees exactly the forms they see today.
- **`grecaptcha.render()` into a `ref`, not `class="g-recaptcha"`.** Explicit render, see 8.2.
- **`theme()` reads the `.dark` class** on `<html>`, the same check `Landing.vue` makes. The
  widget does not re-theme after mount if the user toggles the theme while on the login page. That
  is acceptable; do not add a watcher for it.
- **Three callbacks.** `callback` fires when the user passes and gives the token.
  `expired-callback` fires two minutes later if they have not submitted; the token is now useless
  so we clear it. `error-callback` fires on network trouble. All three keep `modelValue` honest:
  it holds a token only when there is a live one.
- **`watch(props.error)` → `reset()`.** Tokens are single-use. If the form fails for *any*
  reason (wrong password, taken email), the token was already consumed by our server's
  `siteverify` call. Without a reset the user fixes the password, resubmits, and gets
  "please confirm you are not a robot" with a still-ticked box. Confusing. With the reset, the box
  clears and they tick it again. See 8.5 for the one gap this leaves.
- **`defineExpose({ reset })`.** Lets a parent call `recaptcha.value.reset()` explicitly. Used in
  8.5.
- **The poll in `onMounted`.** The `<script async defer>` may not have finished when the Vue page
  mounts, especially on a cold load straight to `/login`. Polling every 100 ms until `grecaptcha`
  exists is simpler than the `onload=` callback approach and survives Inertia navigation, where the
  script is already loaded and the poll exits on its first tick.

### 8.4 Wire it into the four pages

The change is the same shape on each page. Shown for `Login.vue`; repeat for the others.

**Script block.** Add the import, add the field to `useForm`, add a ref, reset on any error:

```js
import RecaptchaField from "@/Components/RecaptchaField.vue";

const recaptcha = ref(null);

const form = useForm({
    email: "",
    password: "",
    remember: false,
    recaptcha_token: "",
});

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
        onError: () => recaptcha.value?.reset(),
    });
};
```

**Template.** Place it directly above the submit `<Button>`, inside the `<form>`:

```vue
<RecaptchaField
    ref="recaptcha"
    v-model="form.recaptcha_token"
    :error="form.errors.recaptcha_token"
/>
```

Per page:

| Page | `useForm` gets | `form.post` route | Place it above |
| --- | --- | --- | --- |
| `Login.vue` | `recaptcha_token: ""` | `login` | the "Sign In" button |
| `Register.vue` | `recaptcha_token: ""` | `register` | the "Create workspace" button |
| `ForgotPassword.vue` | `recaptcha_token: ""` | `password.email` | the send-link button |
| `ResetPassword.vue` | `recaptcha_token: ""` | `password.store` | the reset button |

`Register.vue` already has an `onFinish` that resets the password fields; add `onError` next to
it, do not replace it. `ForgotPassword.vue` currently calls `form.post(route("password.email"))`
with no options object; give it one with just `onError`.

### 8.5 Why `onError` on the page *and* `watch(error)` in the component

The component's watch only fires when `form.errors.recaptcha_token` *changes*. Scenario: user
submits with a wrong password. Server consumed the token during `siteverify` (success), then
`Auth::attempt` failed. The error that comes back is on `email`, not `recaptcha_token`, so the
component's watch does not fire. But the token is spent. The page-level `onError` catches this:
any error at all → reset the widget.

The watch is still needed for the case where `recaptcha_token` errors on two consecutive submits
with the same message: `onError` fires both times, but belt-and-braces costs one line.

### 8.6 Do not disable the submit button when the box is unticked

It is tempting to write `:disabled="form.processing || !form.recaptcha_token"`. Do not. Two
reasons. First, when the site key is absent the field is always `""` and the button would be
permanently dead. Second, a disabled button gives no feedback; a user who forgot the box clicks,
nothing happens, and they do not know why. Let them submit, let the server reject, let the error
appear under the checkbox. That is what every other field on the form does.

### 8.7 Build and look at it

```bash
docker exec esign-app npm run build
```

Then open `/login` in the browser. You should see the checkbox between the "Remember me" row and
the "Sign In" button, themed to match. Tick it, sign in, it works. Reload, do not tick it, sign
in, you get "Please confirm you are not a robot." under the box.

## 9. Phase 5 — tests for the rule itself

The existing tests prove the check is off when unconfigured. Add one file proving it is on when
configured. Nothing in `tests/` uses `Http::fake()` yet; this is how it works.

New file: `tests/Feature/Auth/RecaptchaTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
        ]);
    }

    public function test_login_is_rejected_without_a_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('recaptcha_token');
        $this->assertGuest();
    }

    public function test_login_is_rejected_when_google_says_no(): void
    {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'recaptcha_token' => 'anything',
        ]);

        $response->assertSessionHasErrors('recaptcha_token');
        $this->assertGuest();
    }

    public function test_login_succeeds_when_google_says_yes(): void
    {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'recaptcha_token' => 'anything',
        ]);

        $this->assertAuthenticated();

        Http::assertSent(fn ($request) =>
            $request['secret'] === 'test-secret-key'
            && $request['response'] === 'anything'
        );
    }

    public function test_forgot_password_is_rejected_without_a_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHasErrors('recaptcha_token');
    }

    public function test_the_secret_is_never_shared_with_the_page(): void
    {
        $response = $this->get('/login');

        $response->assertInertia(fn ($page) => $page
            ->where('recaptchaSiteKey', 'test-site-key')
            ->missing('recaptchaSecretKey')
        );

        $response->assertDontSee('test-secret-key');
    }
}
```

The last test is the one that matters most. It fails the build if anyone ever shares the secret
by accident. `config([...])` in `setUp` turns the check on for this file only; every other test
file still runs with it off.

`Http::fake()` with a URL pattern intercepts only that host; nothing else in the request is faked.
`Http::assertSent` proves we actually sent Google the secret and the token, with the right field
names, which is the thing `asForm()` vs `asJson()` gets wrong silently.

```bash
docker exec esign-app php artisan test tests/Feature/Auth
```

All green, including the six new ones.

## 10. Verification checklist

Do every line. Tick it only if you saw it happen.

**Backend, no browser:**

- [ ] `tinker`: `verify('garbage')` returns `false`, no exception
- [ ] `php artisan test tests/Feature/Auth` — all pass, including `RecaptchaTest`
- [ ] `curl -X POST localhost:8000/login -d 'email=x@x.com&password=x'` with keys set → response
      redirects back with a `recaptcha_token` error in the session (check with a follow-up GET or
      look for the 302 and no auth cookie)
- [ ] Remove both keys from `.env`, `config:clear`, same curl → no `recaptcha_token` error, only
      the normal "these credentials do not match" one. The check is off when unconfigured.
- [ ] Put the keys back. `config:clear`.

**Browser, keys set:**

- [ ] `/login`: checkbox visible, matches theme. Toggle theme, reload, still matches.
- [ ] `/login`: submit unticked → "Please confirm you are not a robot." under the box
- [ ] `/login`: tick, submit wrong password → "credentials do not match" **and the box has reset
      to unticked**. This is 8.5; if the box stays ticked, `onError` is missing.
- [ ] `/login`: tick, submit correct password → logged in
- [ ] `/register`: same three checks
- [ ] `/forgot-password`: same three checks (use a real email in the DB for the success case)
- [ ] `/reset-password/{token}`: request a real reset email, follow the link, same three checks
- [ ] Navigate `/login` → "Create one" → `/register` → "Sign in" → `/login` without reloading.
      Widget renders on each page every time. This is the Inertia case from 8.2.
- [ ] Tick the box, wait 2+ minutes without submitting, submit → error under the box, box reset.
      This is `expired-callback`.
- [ ] Open DevTools → Sources, search all JS for the secret key. **It must not be there.**

**Browser, keys removed:**

- [ ] `config:clear`, rebuild, `/login`: no checkbox, no space where it was, no console errors,
      login works.

## 11. Common ways this goes wrong

| Symptom | Cause | Fix |
| --- | --- | --- |
| Google always returns `missing-input-secret` | `Http::asJson()` instead of `asForm()` | 5.2 |
| Widget never appears, no error | Script tag missing or `siteKey` prop is `null` | 8.1, 8.2; check `usePage().props.recaptchaSiteKey` in DevTools |
| Widget appears on cold load but not after Inertia navigation | Used `class="g-recaptcha"` auto-render instead of `grecaptcha.render()` | 8.3 |
| "Please confirm you are not a robot" after fixing a wrong password, box still ticked | Missing `onError: () => recaptcha.value?.reset()` | 8.4, 8.5 |
| Same error, but `error-codes: ["timeout-or-duplicate"]` in the log | Token reused or older than 2 min | Working as intended; the reset should have cleared it |
| `RegistrationTest` fails with `recaptcha_token` error | Real keys from `.env` leak into the test run | Add the two empty `<env>` overrides to `phpunit.xml`; 7.1 |
| Submit button permanently disabled on local | Did 8.6 anyway | Remove the `!form.recaptcha_token` condition |
| `Rules\Password` undefined after adding the import | Replaced `Illuminate\Validation\Rules` with `App\Rules` | 7.5; they are separate `use` lines |
| Widget is light in dark mode | `theme()` reads before `.dark` class is applied | It is read at mount; check `app.js` `useColorMode` runs before page mount (it does) |

## 12. Definition of done

- The four POST endpoints reject requests without a valid token when keys are configured.
- The four endpoints behave as today when keys are not configured, and a production boot without
  keys writes an error to the log.
- The secret key is not in any response body, shared prop, or built JS asset. `RecaptchaTest`
  proves this.
- All tests in `tests/Feature/Auth` pass.
- Every line in section 10 is ticked.
- No new Composer or npm dependency.
