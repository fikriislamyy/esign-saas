# Unit test suite for services, models, composables and components

## 1. What we are building, in one paragraph

Today the project has 15 PHP feature test files (HTTP in, HTTP out) and **zero** tests for the
JavaScript side — there is no test runner in `package.json` at all. This task adds a unit test
layer underneath the feature tests: one test file per PHP service, rule, mailable, command and
model-with-logic, and one per JavaScript composable, helper and logic-bearing Vue component. It
also installs Vitest so the Vue side can be tested at all, and fixes the two things that make the
existing PHP suite show yellow and red on every run. Every test is written from the scenario
checklist in section 6, using the fixture recipes in 4.3 and the fakes in 4.4, and every file is
produced with the prompt in 4.2 — you fill in the target code, the prompt fills in the rest.

"All functions, components and modules" is triaged, not literal. Section 6 sorts every target
into a tier; Tier A and B are this issue, Tier C is deliberately skipped with a reason, Tier D
(controllers without feature tests) is listed for a follow-up so nothing is forgotten.

## 2. Things you must not do

- **Do not change the code under test.** No adding `export` to a helper so it is easier to
  reach, no extracting a method, no "small refactor". If a function is private or unexported,
  test it through the public surface that calls it, or skip it and say so. Test scaffolding
  (config files, setup files, fixtures) is not "code under test" and is fine.
- **Do not hit the network, Stripe, Pakasir, Google or CurrencyFreaks.** Every outbound call
  goes through `Http::fake()` (PHP) or `vi.mock` (JS). A test that passes only when the office
  Wi-Fi is up is a broken test.
- **Do not mock your own classes.** `WalletService` is tested with the real database, not a
  mocked `Wallet`. `Recaptcha` (the rule) is tested by faking Google's HTTP response, not by
  mocking `RecaptchaService`. Mock the boundary, not the middle.
- **Do not rewrite existing feature tests as unit tests.** `LoginOtpTest` already proves the
  HTTP flow. A unit test for `LoginOtpService` proves the Redis key behaviour the HTTP test
  cannot see (TTLs, hash format). Different questions, both stay.
- **Do not test the framework.** No test that `hasMany` returns a relation, that `$fillable`
  contains a column, or that `config()` reads a file. Test *your* decisions: `effectivePlan()`
  returning `free` for a cancelled Pro is a decision; `belongsTo` working is Laravel's.
- **Do not chase a coverage number.** The definition of done is "every Tier A/B target has a
  file with the listed scenarios green", not a percentage. Do not add assertion-free tests to
  move a bar.
- **Do not write one giant test.** One scenario per test method, named for the behaviour:
  `test_a_cancelled_pro_subscription_is_effectively_free`, not `test_effective_plan`.
- **Do not use `sleep()`.** Time is controlled with `$this->travel()` / `travelTo()` (PHP) and
  `vi.useFakeTimers()` (JS). Redis TTLs are real time — assert `TTL <= 600`, never wait for
  expiry; to simulate "60 seconds passed", delete the key (see `LoginOtpTest`).
- **Do not snapshot-test.** No `toMatchSnapshot()`. Assert the thing you care about.
- **Do not skip Phase 0.** Adding tests to a suite that is already red or yellow means nobody
  can tell whether your new test broke something.

## 3. What exists today

| Layer | Runner | Where | State |
| --- | --- | --- | --- |
| PHP feature tests | PHPUnit 10.5 via `php artisan test` | `tests/Feature/**` (15 files, 31 auth + ~28 other tests) | 1 red (`TemplateUploadTest::test_a_non_pdf_renamed_to_pdf_is_rejected`, can never pass — see Phase 0), every run shows a WARN from a double `define()` in `bootstrap/app.php` |
| PHP unit tests | same | `tests/Unit/ExampleTest.php` | one placeholder, `assertTrue(true)` |
| JS tests | **none** | — | no runner, no config, no `test` script |

Things the plan relies on, and where to see them:

| You need to | Look at | What you will see |
| --- | --- | --- |
| The PHPUnit env | [phpunit.xml:20-33](phpunit.xml#L20-L33) | `APP_ENV=testing`, `CACHE_DRIVER=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, reCAPTCHA keys blank. DB is the real Postgres in Docker (the sqlite lines are commented out). Redis is the real container |
| The base class every PHP test extends | [tests/TestCase.php](tests/TestCase.php) | `Tests\TestCase` boots the app, so `config()`, `app()`, facades all work. Add `use RefreshDatabase;` only when the test writes rows |
| A feature test that builds an org + owner by hand | [TemplateLimitTest.php:20-31](tests/Feature/TemplateLimitTest.php#L20-L31) | `Organization::create(['name' => 'Acme'])`, then `User::factory()->create([...role => 'owner'])`. There is no OrganizationFactory — this is the pattern |
| A test that fakes time on a DB row | [EmailVerificationTest.php:59](tests/Feature/Auth/EmailVerificationTest.php#L59) | `OtpVerification::where(...)->update(['expired_at' => now()->subMinute()])` — set the column, do not sleep |
| A test that uses real Redis | [LoginOtpTest.php:78-80](tests/Feature/Auth/LoginOtpTest.php#L78-L80) | `Redis::del("login_otp:{$user->id}:hash")` to simulate expiry |
| A test that faked HTTP would replace | [RecaptchaService.php:16-40](app/Services/RecaptchaService.php#L16-L40) | `Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', …)` |
| Why the Recaptcha rule needs an env trick | [Recaptcha.php:13-15](app/Rules/Recaptcha.php#L13-L15) | `if (app()->environment() === 'testing') return;` — the rule is a no-op in tests unless you change the env in the test |
| Why `ExpireSubscriptions` needs a Stripe key even for Pakasir rows | [StripeService.php:12-22](app/Services/StripeService.php#L12-L22) | The constructor throws without `services.stripe.secret`; the command type-hints it, so it is constructed on every run |
| The only factory | [UserFactory.php](database/factories/UserFactory.php) | `email_verified_at => now()`, `remember_token => random`, password `password` |
| How `@` resolves in Vite | [vite.config.js](vite.config.js) + `laravel-vite-plugin` | The Laravel plugin adds `@ → resources/js`. Vitest does not load that plugin, so `vitest.config.js` declares the alias itself (Phase 0) |
| Globals a component may touch | [app.js](resources/js/app.js) | `route()` from Ziggy is a global; `FadeIn.vue` uses `IntersectionObserver` and `matchMedia`. The Vitest setup file provides all three |

## 4. The design — done for you

### 4.1 Where each kind of test lives

| Target | Test type | Directory | Extends | `RefreshDatabase`? |
| --- | --- | --- | --- | --- |
| Pure PHP class (no DB, no HTTP) | unit | `tests/Unit/Services/`, `tests/Unit/Models/` | `Tests\TestCase` | no |
| PHP service that fakes HTTP / Cache | unit | `tests/Unit/Services/` | `Tests\TestCase` | no |
| PHP service / model method that reads or writes rows | unit | `tests/Unit/Services/`, `tests/Unit/Models/` | `Tests\TestCase` | **yes** |
| Rule, FormRequest, Mailable, Command | unit | `tests/Unit/Rules/`, `…/Requests/`, `…/Mail/`, `…/Console/` | `Tests\TestCase` | when rows are needed |
| Middleware, controller | feature | `tests/Feature/` | `Tests\TestCase` | yes |
| JS helper / composable | unit | `tests/js/lib/`, `tests/js/Composables/` | — (Vitest) | — |
| Vue component | unit | `tests/js/Components/<same path as the component>/` | — (Vitest + Test Utils) | — |

Every PHP test extends `Tests\TestCase`, even the pure ones. The placeholder
`tests/Unit/ExampleTest.php` extends PHPUnit's bare `TestCase`, which has no app — delete it in
Phase 0 so nobody copies it. Naming: `<ClassName>Test.php` mirroring the `app/` path;
`<name>.test.js` mirroring the `resources/js/` path.

### 4.2 The prompt — filled in for this project

This is the prompt you give to a model (or read yourself) for **each** target file. Sections 1,
2, 4 and 5 are already filled in. For each file you paste the target code into section 3, the
target's row from section 6 into section 5, and the fixture recipe(s) it needs from 4.3 into
section 4. Use the PHP variant for anything under `app/`, the JS variant for anything under
`resources/js/`.

#### PHP variant

````markdown
You are an expert software engineer specializing in unit testing. I am adding unit tests to an
ongoing, existing project. Write a comprehensive, clean, maintainable unit test file for the code
below, matching our existing architecture.

### 1. Project Context & Stack
- Language/Runtime: PHP 8.4, Laravel 10.
- Testing Framework: PHPUnit 10.5, run with `php artisan test`.
- Mocking/Assertion Libraries: Laravel's built-in fakes only — `Http::fake()`, `Mail::fake()`,
  `Storage::fake()`, `Event::fake()`, `$this->travel()`. The database is a real Postgres with
  `RefreshDatabase`; Redis is real. No Mockery for our own classes.
- Core Coding Standards: Arrange-Act-Assert with one blank line between the three blocks. One
  behaviour per test method. Method names are full sentences: `test_<subject>_<behaviour>`.
  No docblocks. No comments unless a line would otherwise surprise a reader.

### 2. Existing Conventions Reference
```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plan_page_receives_the_sales_link(): void
    {
        $organization = Organization::create(['name' => 'Acme']);
        $owner = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner)->get('/plan')->assertInertia(fn ($page) => $page
            ->where('salesMailto', 'mailto:sales@bebem.my.id?subject=Enterprise%20Plan%20Inquiry')
        );
    }
}
```
Every test class extends `Tests\TestCase` (namespace `Tests\Unit\...` for unit tests, mirroring
the `app/` path). Add `use RefreshDatabase;` only if the test creates rows. Set config with
`config(['key' => value])` inside the test, never by editing `.env`.

### 3. Target Code to Test
```php
[PASTE THE FILE]
```

### 4. Dependencies & Interfaces
[PASTE THE FIXTURE RECIPES THIS TARGET NEEDS FROM issue.md §4.3, AND THE FAKE FROM §4.4]

### 5. Test Requirements & Scenarios
1. Write exactly these scenarios, one test method each, in this order:
   [PASTE THE TARGET'S ROW FROM issue.md §6]
2. Fake every external call with the fake named above. Assert the fake was (or was not) called
   where the scenario says so — `Http::assertSent`, `Http::assertNothingSent`,
   `Mail::assertSent`, `Storage::disk(...)->assertMissing`.
3. Tests must be isolated and deterministic: no `sleep()`, no real dates (`travelTo` a fixed
   `Carbon` when the date matters), no order dependence between methods.
4. Do not alter or refactor the target code.

Output only the complete test file in a single ```php block. The first line is `<?php`.
````

#### JS variant

````markdown
You are an expert software engineer specializing in unit testing. I am adding unit tests to an
ongoing, existing project. Write a comprehensive, clean, maintainable unit test file for the code
below, matching our existing architecture.

### 1. Project Context & Stack
- Language/Runtime: JavaScript (ES modules, no TypeScript), Vue 3.4 `<script setup>`, Vite 5,
  Inertia.js 1.x, Node 24.
- Testing Framework: Vitest 3 with `globals: true` and the `happy-dom` environment. Vue
  components are mounted with `@vue/test-utils` 2.
- Mocking/Assertion Libraries: `vi.mock()` for modules (`@inertiajs/vue3`, `axios`,
  `pdfjs-dist`), `vi.fn()` for callbacks, `vi.useFakeTimers()` for time. A global `route(name)`
  stub and an `IntersectionObserver` stub are provided by `tests/js/setup.js` — do not
  redefine them.
- Core Coding Standards: Arrange-Act-Assert with one blank line between the blocks. One
  behaviour per `it()`. `describe` is the file or component name; `it` reads as a sentence:
  `it("renders the Enterprise button as a mailto link")`. No snapshots. No comments unless a
  line would otherwise surprise a reader.

### 2. Existing Conventions Reference
```js
import { mount } from "@vue/test-utils";
import { h } from "vue";

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { salesMailto: "mailto:sales@example.test?subject=Hi" } }),
    Link: {
        props: ["href"],
        setup: (props, { slots }) => () => h("a", { href: props.href, "data-inertia": "" }, slots.default?.()),
    },
}));

import Pricing from "@/Components/landing/Pricing.vue";

describe("Pricing", () => {
    it("renders the Enterprise button as a plain mailto link", () => {
        const wrapper = mount(Pricing);

        const link = wrapper.get('a[href^="mailto:"]');

        expect(link.attributes("data-inertia")).toBeUndefined();
        expect(link.text()).toBe("Contact sales");
    });
});
```
Imports use the `@` alias. `vi.mock` calls sit above the component import. Components that
read `usePage()` get their props through the mock, never through `global.mocks.$page`.

### 3. Target Code to Test
```vue
[PASTE THE FILE]
```

### 4. Dependencies & Interfaces
[PASTE THE MOCK RECIPE(S) THIS TARGET NEEDS FROM issue.md §4.4 (JS half)]

### 5. Test Requirements & Scenarios
1. Write exactly these scenarios, one `it()` each, in this order:
   [PASTE THE TARGET'S ROW FROM issue.md §6]
2. Mock every import that touches the network, the router, or a browser API the environment
   lacks. Assert on rendered text, attributes, emitted events and mock calls — not on internal
   refs.
3. Tests must be isolated and deterministic: fake timers for countdowns, no real fetches, no
   reliance on test order. Call `vi.useRealTimers()` in `afterEach` when fake timers were used.
4. Do not alter or refactor the target code.

Output only the complete test file in a single ```js block.
````

### 4.3 Fixture recipes (PHP)

Copy the ones you need into section 4 of the prompt. All of them assume `use RefreshDatabase;`.

```php
// Organization + owner. Organization has HasUuids; no id needed.
$organization = Organization::create(['name' => 'Acme']);
$owner = User::factory()->create(['organization_id' => $organization->id, 'role' => 'owner']);

// A member / admin: same, with 'role' => 'member' or 'admin'.

// Subscription (integer id). PlanService::subscriptionFor() creates a free one on demand;
// create one yourself only to test a non-free plan.
$organization->subscription()->create(['plan' => 'pro', 'status' => 'active', 'expired_at' => now()->addDays(30)]);

// Document (HasUuids). Status defaults to 'draft'; allowed: draft, sent, completed, expired.
$document = Document::create([
    'organization_id' => $organization->id,
    'owner_id' => $owner->id,
    'name' => 'Contract',
    'file_path' => 'documents/contract.pdf',
    'file_size' => 1024,
    'mime_type' => 'application/pdf',
]);

// Backdate a row (created_at is not fillable — update it after the fact, like EmailVerificationTest does).
Document::whereKey($document->id)->update(['created_at' => now()->subDays(10)]);

// Signer (HasUuids). token is a NOT NULL uuid column.
$signer = DocumentSigner::create([
    'document_id' => $document->id,
    'name' => 'Sam Signer',
    'email' => 'sam@example.com',
    'token' => (string) Str::uuid(),
]);

// Template (HasUuids).
Template::create([
    'organization_id' => $organization->id,
    'name' => 'NDA',
    'file_path' => 'templates/nda.pdf',
    'file_size' => 1024,
    'mime_type' => 'application/pdf',
]);

// Pending invitation (HasUuids). accepted_at null = still counts against the member limit.
Invitation::create(['organization_id' => $organization->id, 'email' => 'new@example.com', 'role' => 'member', 'token' => (string) Str::uuid()]);

// Wallet: always go through the service so the row shape is right.
$wallet = app(WalletService::class)->getOrCreateWallet($organization);

// Wallet top-up awaiting Pakasir (integer id).
$topup = WalletTopup::create([
    'wallet_id' => $wallet->id,
    'organization_id' => $organization->id,
    'currency' => 'USD',
    'amount' => 10,
    'exchange_rate' => 16000,
    'wallet_amount_usd_cents' => 1000,
    'provider' => 'pakasir',
    'order_id' => 'TOPUP-1',
    'status' => 'pending',
    'created_by' => $owner->id,
    'metadata' => ['amount_idr' => 160000],
]);

// Subscription payment awaiting Pakasir (integer id). amount is already IDR.
$payment = SubscriptionPayment::create([
    'organization_id' => $organization->id,
    'subscription_id' => $organization->subscription->id,
    'plan' => 'pro',
    'provider' => 'pakasir',
    'order_id' => 'SUB-1',
    'currency' => 'IDR',
    'amount' => 160000,
    'amount_usd_cents' => 1000,
    'exchange_rate' => 16000,
    'status' => 'pending',
]);
```

### 4.4 Fakes — which one for which boundary

| Boundary | PHP | JS |
| --- | --- | --- |
| Outbound HTTP (Google, Pakasir, CurrencyFreaks) | `Http::fake(['www.google.com/*' => Http::response([...], 200)])`; assert with `Http::assertSent(fn ($r) => $r['secret'] === 'x')` / `Http::assertNothingSent()` | `vi.mock("axios", () => ({ default: { get: vi.fn() } }))` |
| Email | `Mail::fake()` then `Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo(...))`. To test a mailable's *content*: no fake — `(new LoginOtpMail($user, '123456'))->assertSeeInHtml('123456')` and `->assertHasSubject('…')` | — |
| Files | `Storage::fake('documents')` (the disk name is `config('documents.disk')`), then `Storage::disk('documents')->assertExists / assertMissing` | — |
| Cache | Already `array` in `phpunit.xml`; each test starts empty. `Cache::flush()` if a test needs a clean second half | — |
| Redis | Real. Keys are per-UUID so tests never collide. `Redis::ttl($key)`, `Redis::get`, `Redis::del` | — |
| Time | `$this->travelTo(Carbon::parse('2026-03-11 10:00:00'))` (a Wednesday — matters for week-based quotas); `$this->travel(6)->minutes()` | `vi.useFakeTimers(); vi.advanceTimersByTime(1000)`; `afterEach(vi.useRealTimers)` |
| Stripe | Never construct a real client in a test path. The `StripeService` constructor is safe with a dummy key (`config(['services.stripe.secret' => 'sk_test_dummy'])`) — it makes no call until `->client()->…` is used. Do not test code paths that call the Stripe API (see Tier D) | — |
| Inertia | — | `vi.mock("@inertiajs/vue3", …)` providing `usePage`, `Link`, `Head`, `useForm`, `router` as the component needs (recipes below) |
| Ziggy `route()` | — | Global stub from `tests/js/setup.js`: `route("login")` → `"/login"` |
| `IntersectionObserver`, `matchMedia` | — | Stubbed in `tests/js/setup.js`; `FadeIn.vue` renders its slot immediately |
| `window.location.href = "mailto:…"` | — | `const loc = { href: "" }; vi.stubGlobal("location", loc)` then assert `loc.href` |
| `window.confirm` | — | `vi.spyOn(window, "confirm").mockReturnValue(true)` |

JS mock recipes to paste into section 4 as needed:

```js
// Inertia: page props + Link + Head (for components that only read props and link)
vi.mock("@inertiajs/vue3", async () => {
    const { h } = await import("vue");
    return {
        usePage: () => ({ props: { /* what the component reads */ } }),
        Link: { props: ["href"], setup: (p, { slots }) => () => h("a", { href: p.href, "data-inertia": "" }, slots.default?.()) },
        Head: { setup: (_, { slots }) => () => slots.default?.() },
    };
});

// Inertia: useForm + router (for pages that submit)
const post = vi.fn();
vi.mock("@inertiajs/vue3", async () => {
    const { reactive } = await import("vue");
    return {
        useForm: (fields) => reactive({ ...fields, errors: {}, processing: false, post }),
        router: { post: vi.fn(), visit: vi.fn() },
        Link: { props: ["href"], setup: (p, { slots }) => () => h("a", { href: p.href }, slots.default?.()) },
        Head: { setup: (_, { slots }) => () => slots.default?.() },
    };
});
// Then in a test: expect(post).toHaveBeenCalledWith("/login.otp.verify")   ← route() stub returns "/<name>"

// pdf.js + axios (for usePdfLoader)
vi.mock("axios", () => ({ default: { get: vi.fn() } }));
vi.mock("pdfjs-dist", () => ({ getDocument: vi.fn(() => ({ promise: Promise.resolve("PDF") })) }));
```

## 5. Step by step

Commands run inside the container: `docker exec esign-app php artisan test …`. `npm` runs on the
host.

### Phase 0 — a green suite and a JS runner

**0.1 Silence the TCPDF warning.** In [bootstrap/app.php](bootstrap/app.php) the two `define()`
calls run once per test because PHPUnit re-bootstraps the app for every test class. Guard them:

```php
defined('K_TCPDF_EXTERNAL_CONFIG') || define('K_TCPDF_EXTERNAL_CONFIG', true);
defined('K_TCPDF_THROW_EXCEPTION_ERROR') || define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
```

Run `php artisan test` — the `!` marks and the `1 warning` line are gone.

**0.2 Fix the test that cannot pass.** `TemplateUploadTest::test_a_non_pdf_renamed_to_pdf_is_rejected`
uses `UploadedFile::fake()->createWithContent('template.pdf', "\0…")`. Laravel's testing `File`
guesses the MIME type **from the file name**, so a fake named `.pdf` always reports
`application/pdf` and the `mimes:pdf` rule passes. The test needs a real `UploadedFile` built
from a temp file, so `finfo` reads the bytes:

```php
    public function test_a_non_pdf_renamed_to_pdf_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notpdf');
        file_put_contents($path, str_repeat("\0", 1024));
        $file = new \Illuminate\Http\UploadedFile($path, 'template.pdf', 'application/pdf', null, true);

        $response = $this->actingAs($this->user)->post('/templates', ['file' => $file]);

        $response->assertSessionHasErrors(['file' => 'Only PDF files can be uploaded.']);
        $this->assertDatabaseCount('templates', 0);
    }
```

[DocumentUploadTest.php:72-74](tests/Feature/DocumentUploadTest.php#L72-L74) has the identical
test with the identical fake; apply the same fix there (`contract.pdf`, `/documents`). Run the
two files; both green.

**0.3 Delete `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php`.** They assert
nothing about this project.

**0.4 Install Vitest.**

```bash
npm install --save-dev vitest@^3.2 @vue/test-utils@^2.4 happy-dom@^20
```

Add to `package.json` `scripts`:

```json
        "test": "vitest run",
        "test:watch": "vitest"
```

Create `vitest.config.js` (separate from `vite.config.js` — the Laravel plugin in there is not
wanted in tests):

```js
import { defineConfig } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import { fileURLToPath } from "node:url";

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: { "@": fileURLToPath(new URL("./resources/js", import.meta.url)) },
    },
    test: {
        environment: "happy-dom",
        globals: true,
        include: ["tests/js/**/*.test.js"],
        setupFiles: ["tests/js/setup.js"],
    },
});
```

Create `tests/js/setup.js`:

```js
import { config } from "@vue/test-utils";
import { vi } from "vitest";

globalThis.route = vi.fn((name, params) => {
    const query = params ? "?" + new URLSearchParams(params).toString() : "";
    return `/${name}${query}`;
});
config.global.mocks.route = globalThis.route;

class IntersectionObserverStub {
    constructor(callback) {
        this.callback = callback;
    }
    observe(target) {
        this.callback([{ isIntersecting: true, target }], this);
    }
    unobserve() {}
    disconnect() {}
    takeRecords() {
        return [];
    }
}
globalThis.IntersectionObserver ??= IntersectionObserverStub;

globalThis.matchMedia ??= () => ({ matches: false, addEventListener() {}, removeEventListener() {} });
```

Create the first test, `tests/js/lib/utils.test.js`, to prove the pipeline:

```js
import { cn } from "@/lib/utils";

describe("cn", () => {
    it("merges class names", () => {
        expect(cn("p-2", "text-sm")).toBe("p-2 text-sm");
    });

    it("lets the last Tailwind conflict win", () => {
        expect(cn("p-2", "p-4")).toBe("p-4");
    });

    it("drops falsy values", () => {
        expect(cn("p-2", false, null, undefined, "", "m-1")).toBe("p-2 m-1");
    });
});
```

`npm test` → 3 passed. If the `@` import fails, the alias in `vitest.config.js` is wrong; if
`describe` is undefined, `globals: true` is missing.

**0.5 Commit Phase 0 on its own** (`test: fix suite warnings and add Vitest runner`). Everything
after this is additive.

### Phase 1 — PHP, pure (no fakes, no rows)

Targets: `SigningPricingService`, `Subscription::effectivePlan()` + `config()`,
`User::isOwner/isAdmin/canManageMembers`. Scenarios in section 6, Tier A.

Worked example — `tests/Unit/Models/SubscriptionTest.php`. Note `new Subscription([...])` with
no database: the `datetime` cast on `expired_at` works without a row.

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_a_free_subscription_is_free(): void
    {
        $subscription = new Subscription(['plan' => 'free', 'status' => 'active']);

        $this->assertSame('free', $subscription->effectivePlan());
    }

    public function test_an_active_pro_subscription_with_no_expiry_is_pro(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'active', 'expired_at' => null]);

        $this->assertSame('pro', $subscription->effectivePlan());
    }

    public function test_a_cancelled_pro_subscription_is_effectively_free(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'cancelled']);

        $this->assertSame('free', $subscription->effectivePlan());
    }

    public function test_an_expired_pro_subscription_is_effectively_free(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'active', 'expired_at' => now()->subMinute()]);

        $this->assertSame('free', $subscription->effectivePlan());
    }

    public function test_a_pro_subscription_expiring_in_the_future_is_still_pro(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'active', 'expired_at' => now()->addDay()]);

        $this->assertSame('pro', $subscription->effectivePlan());
    }

    public function test_config_returns_the_effective_plans_entry(): void
    {
        $subscription = new Subscription(['plan' => 'pro', 'status' => 'cancelled']);

        $this->assertSame(config('plans.free'), $subscription->config());
    }
}
```

### Phase 2 — PHP, fakes but no rows

Targets: `RecaptchaService`, `Recaptcha` rule, `ExchangeRateService`, `PakasirService`,
`StripeService`. Each needs `Http::fake()` and/or `config([...])`.

Two traps, both from section 3:

- The `Recaptcha` rule short-circuits when `app()->environment() === 'testing'`. In its test,
  first line of every method that must reach the real path: `$this->app['env'] = 'production';`.
  Then drive the outcome with `Http::fake()`, not by mocking `RecaptchaService`. Run the rule
  with `Validator::make(['recaptcha_token' => 'tok'], ['recaptcha_token' => [new Recaptcha]])->passes()`.
- `ExchangeRateService` caches for 300 s under the `array` store. The "second call does not hit
  the API" scenario is `Http::assertSentCount(1)` after two `usdToIdr()` calls.

### Phase 3 — PHP, rows (`RefreshDatabase`)

Targets: `WalletService`, `PlanService`, `EmailVerificationOtpService`, `SigningOtpService`,
`PakasirFulfillmentService`, `LoginOtpService`, `Organization::wallet_balance_usd_cents`.

Traps:

- `PlanService::documentPeriodStart` depends on the plan's period (`week` for free, `month` for
  pro). Pin the clock: `$this->travelTo(Carbon::parse('2026-03-11 10:00'))` (a Wednesday), then
  a document backdated to Monday 09:00 counts and one backdated to Sunday does not.
- `WalletService::debit` throws `InsufficientWalletBalanceException` — assert with
  `$this->expectException(...)` **and**, in a separate test, that the balance and the transaction
  count are unchanged after the failed call (wrap the call in `try/catch` for that one).
- `PakasirFulfillmentService::fulfill` calls `PakasirService::transactionDetail`, which is HTTP.
  `Http::fake(['*/api/transactiondetail*' => Http::response(['transaction' => ['status' => 'completed', 'is_sandbox' => true]])])`.
  Set `config(['services.pakasir.project' => 'p', 'services.pakasir.api_key' => 'k'])` first.
- `LoginOtpService` writes real Redis keys. Assert the hash key starts with `$2y$` (bcrypt) and
  `Redis::ttl(...)` is `> 0 && <= 600`. Delete keys to simulate time.

### Phase 4 — PHP mail, commands, requests, middleware

Targets: the 4 mailables, `ExpireDocuments`, `ExpireSubscriptions`, `LoginRequest` throttling,
`ProfileUpdateRequest` rules, `HandleInertiaRequests` shared props, `Authenticate` /
`RedirectIfAuthenticated` redirects.

Traps:

- Mailable content tests need no `Mail::fake()`: `(new LoginOtpMail($user, '123456'))->assertSeeInHtml('123456')`,
  `->assertHasSubject('Your EZSign sign-in code')`. `SignatureRequestMail` needs a signer fixture;
  `OrganizationInvitationMail` needs an invitation fixture.
- `ExpireSubscriptions` type-hints `StripeService`, whose constructor throws without a key.
  `config(['services.stripe.secret' => 'sk_test_dummy'])` at the top of every test. Only test
  rows with `provider => 'pakasir'` (or null) — the Stripe-verification branch calls the Stripe
  API and is out of scope (Tier D).
- Commands: `$this->artisan('documents:expire')->expectsOutput('Documents expired: 2')->assertSuccessful()`.
  `Storage::fake('documents')` and `Storage::disk('documents')->put($path, 'x')` before, `assertMissing` after.
- `LoginRequest` throttling is a feature test: 5 × `POST /login` with a wrong password, then the
  6th response `assertSessionHasErrors('email')` with a message containing "Too many".
- `HandleInertiaRequests`: `GET /` as guest → `assertInertia` shows `auth.user` null, `wallet`
  null, `plan` null, `salesMailto` set; `GET /dashboard` as owner → `plan.key === 'free'`,
  `plan.label === 'Free'`, `wallet.balanceUsdCents === 0`.

### Phase 5 — JS helpers and composables

Targets: `cn` (done in Phase 0), `valueUpdater`, `useFeedback`, `withoutTransitions`,
`loadPdf`.

Worked example — `tests/js/Composables/useFeedback.test.js`. The composable keeps **module-level**
state, so it is shared between calls and between tests; reset it in `beforeEach`.

```js
import { useFeedback } from "@/Composables/useFeedback";

describe("useFeedback", () => {
    beforeEach(() => {
        const { hideLoading, closeFeedback, closeConfirmation } = useFeedback();
        hideLoading();
        closeFeedback();
        closeConfirmation();
    });

    it("shares one state between callers", () => {
        const a = useFeedback();
        const b = useFeedback();

        a.showLoading("Saving…");

        expect(b.loading.value).toBe(true);
        expect(b.loadingText.value).toBe("Saving…");
    });

    it("showSuccess opens a success dialog with the given copy", () => {
        const feedback = useFeedback();

        feedback.showSuccess("Saved", "Done", "OK");

        expect(feedback.feedbackOpen.value).toBe(true);
        expect(feedback.feedbackType.value).toBe("success");
        expect(feedback.feedbackTitle.value).toBe("Done");
        expect(feedback.feedbackMessage.value).toBe("Saved");
        expect(feedback.feedbackButtonText.value).toBe("OK");
    });

    it("showError uses its defaults", () => {
        const feedback = useFeedback();

        feedback.showError("Boom");

        expect(feedback.feedbackType.value).toBe("error");
        expect(feedback.feedbackTitle.value).toBe("Something went wrong");
        expect(feedback.feedbackButtonText.value).toBe("Close");
    });

    it("confirmAction runs the stored callback once and closes", async () => {
        const feedback = useFeedback();
        const onConfirm = vi.fn();
        feedback.showConfirmation({ onConfirm });

        await feedback.confirmAction();
        await feedback.confirmAction();

        expect(onConfirm).toHaveBeenCalledTimes(1);
        expect(feedback.confirmationOpen.value).toBe(false);
    });

    it("closeConfirmation discards the callback", async () => {
        const feedback = useFeedback();
        const onConfirm = vi.fn();
        feedback.showConfirmation({ onConfirm });

        feedback.closeConfirmation();
        await feedback.confirmAction();

        expect(onConfirm).not.toHaveBeenCalled();
    });
});
```

### Phase 6 — Vue components

Targets: Tier A and B components in section 6. Start with `Pricing.vue` (the reference snippet
in 4.2 is its first test) and `LandingSection.vue`, then the props-in/text-out components, then
the two with timers and forms (`LoginOtp.vue`, `PlanPickerDialog.vue`).

Traps:

- Reka UI `Dialog` teleports to `body`. For `PlanPickerDialog`, mount with
  `props: { open: true }` and `attachTo: document.body`, then query `document.body` (not
  `wrapper`) for the cards. `wrapper.unmount()` in `afterEach`.
- `LoginOtp.vue` starts a `setInterval` in `onMounted`. `vi.useFakeTimers()` **before**
  `mount`, `await vi.advanceTimersByTimeAsync(1000)` to tick, and read the button text.
- Components that only receive props and render text (`StatCard`, `MemberRoleBadge`,
  `LandingSection`, `Faq`) need no mocks at all: `mount(X, { props })` and assert `text()`.

### Phase 7 — Tier D follow-up issue

Do not write these here. Create a GitHub issue titled "Feature tests for uncovered controllers"
and paste the Tier D rows from section 6 into it. Its top three (the webhooks and cross-org
authorization) are the most valuable tests in this whole plan, but they need a Stripe event
fixture and a Pakasir signature scheme that this issue does not cover.

### Phase 8 — wire it into the routine

Add to `package.json`: `"test:all": "npm test && docker exec esign-app php artisan test"`.
Add a `## Testing` section to `README.md` with the two commands. Run `npm run test:all` — all
green, no warnings.

## 6. Targets and scenarios

Tier A = must, Tier B = should, Tier C = skipped on purpose, Tier D = follow-up issue. "Fake"
names what goes in section 4 of the prompt. Each scenario becomes one test method, in order.

### 6.1 PHP

| Tier | Target | Fake / fixtures | Scenarios (one test each) |
| --- | --- | --- | --- |
| A | `Services/SigningPricingService` | none | `pricePerSignaturePlot` is 10 · `calculateCost(0)` is 0 · `calculateCost(3)` is 30 · `calculateCost(1000)` is 10000 |
| A | `Models/Subscription::effectivePlan / config` | none | see Phase 1 worked example (6 tests) · enterprise active is `enterprise` |
| A | `Models/User` roles | none (`new User(['role' => …])`) | owner: `isOwner` true, `isAdmin` false, `canManageMembers` true · admin: false / true / true · member: false / false / false · null role: all false |
| A | `Models/Organization::wallet_balance_usd_cents` | rows; `WalletService` | no wallet → 0 · wallet with 1500 → 1500 |
| A | `Services/RecaptchaService` | `Http::fake`, `config` | `isConfigured` true with both keys · false with site key blank · false with secret blank · `verify(null)` false and `assertNothingSent` · `verify('')` false · Google 500 → false · `success:false` → false · `success:true` → true · the POST carries `secret`, `response`, `remoteip` (`Http::assertSent`) |
| A | `Rules/Recaptcha` | `$this->app['env'] = 'production'`, `config`, `Http::fake` | in `testing` env the rule passes without HTTP · not configured → passes without HTTP · configured + Google `success:false` → fails with "Please confirm you are not a robot." · configured + `success:true` → passes |
| A | `Services/ExchangeRateService` | `Http::fake`, `config(['services.currencyfreaks.key' => …])` | no key → `usdToIdr` null and `assertNothingSent` · rate `16000` → `16000.0` · second call in 300 s → `assertSentCount(1)` · HTTP 500 → null · `rates.IDR` missing → null · `rates.IDR` = `0` → null · non-numeric → null · `convertUsdToIdr(10)` with rate 16000 → 160000.0 · `convertUsdToIdr` with no key → null |
| A | `Services/PakasirService` | `Http::fake`, `config(['services.pakasir.*'])` | `isConfigured` true/false (project blank, key blank) · `baseUrl` trailing slash is trimmed (assert the URL sent) · default base URL when config null · `createQris` POSTs JSON `project/order_id/amount/api_key` to `/api/transactioncreate/qris` and returns the JSON body · `createQris` on 500 throws `RequestException` · `transactionDetail` GETs `/api/transactiondetail` with the four query params · `simulatePayment` POSTs to `/api/paymentsimulation` |
| A | `Services/StripeService` | `config` | secret missing → `RuntimeException` "Stripe secret key is not configured." · secret set → `client()` is a `StripeClient` |
| A | `Services/WalletService` | rows | `getOrCreateWallet` creates with balance 0 · second call returns the same row (count stays 1) · `getBalance` new org → 0 · `credit` raises balance and returns a transaction with `balance_before 0`, `balance_after 1000`, `source_currency` upper-cased, `type` default `topup` · `credit` with a `reference` model stores `reference_type` + `reference_id` · `debit` lowers balance, `type` default `signature` · `debit` more than balance throws `InsufficientWalletBalanceException` · after the failed debit balance and transaction count are unchanged · `debit` exactly the balance succeeds (balance 0) |
| A | `Services/PlanService` | rows, `travelTo` | `subscriptionFor` creates a free active row when none · returns the existing row · `limits` free vs pro (documents 3/week vs 100/month) · `documentPeriodStart` free → `startOfWeek`, pro → `startOfMonth` · `documentsUsed` counts a doc from Monday, ignores one from last Sunday (clock pinned to a Wednesday) · `storageUsedBytes` sums `file_size` and ignores `expired` · `membersUsed` = users + pending invitations, accepted invitation ignored · `canUploadDocument` free with 2 docs true, with 3 false, enterprise with 50 true (`null` limit) · `canAddMember` at limit false · `canAddTemplate` with 5 false · `canStore` exactly to the limit true, one byte over false · `usage()` has keys `documents.used/limit/period`, `templates`, `members`, `storage` |
| A | `Services/EmailVerificationOtpService` | rows | `generate` returns 6 digits · stores one row expiring in 10 min · second `generate` replaces the first (count 1) · `verify` correct → true and row deleted · wrong → false, row kept · expired (update `expired_at` past) → false · verify twice → second false |
| A | `Services/SigningOtpService` | rows (document + signer) | `generate` stores a bcrypt hash, not the code · sets `otp_expires_at` ≈ +5 min, `otp_attempts 0`, `otp_verified_at null`, `otp_last_sent_at` now · `verify` no hash → false · wrong → false and `otp_attempts` 1 · correct → true and `otp_verified_at` set · expired → false · 5 wrong then correct → false · `isVerified` false before verify · true after · false after `travel(6)->minutes()` |
| A | `Services/LoginOtpService` | real Redis, `Mail::fake` | `generate` returns 6 digits · hash key starts `$2y$` · hash TTL `> 0 && <= 600` · cooldown TTL `<= 60` · `sends` is 1 then 2 · `verify` correct → true and hash + attempts keys gone · wrong → false, attempts 1 · 6th attempt false even when correct · missing hash → false · `retryAfter` right after generate is `> 0` · after `del cooldown` → 0 · after 5 generates + `del cooldown` → `> 0` · `send` mails `LoginOtpMail` to the user |
| A | `Services/PakasirFulfillmentService` | rows, `Http::fake`, `config` | `amountIdr` payment → `amount` · topup → `metadata.amount_idr` · topup without metadata → 0 · `fulfill` status not completed → false, record untouched · `sandboxOnly` and `is_sandbox` false → false · completed payment → true, `status paid`, `paid_at` set, subscription `plan pro / active / provider pakasir / expired_at ≈ +30 d` · completed topup → true, wallet credited 1000, transaction `reference_type` is the topup, topup `paid` · calling `fulfill` twice credits once |
| A | `Mail/LoginOtpMail` | user | subject "Your EZSign sign-in code" · HTML contains the code · contains the user's name · contains "change your password" |
| A | `Mail/EmailVerificationOtpMail` | user | subject "Your EZSign verification code" · contains code · contains name |
| B | `Mail/SignatureRequestMail` | signer fixture | subject (read it from the class) · contains the OTP · contains the signing link with the signer's token |
| B | `Mail/OrganizationInvitationMail` | invitation fixture | subject includes the organization name · body contains the accept link with the token |
| A | `Console/ExpireDocuments` | rows, `Storage::fake('documents')` | draft 4 days old → `expired`, file missing · sent 4 days old → expired · completed 4 days old → untouched · draft 2 days old → untouched · `--days=1` expires a 2-day-old draft · `--dry-run` changes nothing, prints "would expire" · a document whose file is already gone still expires · output "Documents expired: N" |
| A | `Console/ExpireSubscriptions` | rows, `config(['services.stripe.secret' => 'sk_test_dummy'])` | pakasir pro expired yesterday → `plan free / status cancelled / cancelled_at` set · pro expiring tomorrow → untouched · free with past `expired_at` → untouched · `expired_at null` → untouched · `--dry-run` changes nothing · output "Subscriptions downgraded: N" |
| A | `Requests/LoginRequest` throttle (feature) | rows | 5 wrong passwords then a 6th attempt → `email` error containing "Too many" · a correct password after 4 wrong ones still proceeds to `/login/otp` and clears the counter |
| B | `Requests/ProfileUpdateRequest` (feature via `PATCH /profile`) | rows | email already used by another user → `email` error · own current email → accepted · uppercase email → `email` error (lowercase rule) · name over 255 → error |
| A | `Middleware/HandleInertiaRequests` (feature) | rows | guest `/` → `auth.user` null, `wallet` null, `plan` null, `salesMailto` present, `recaptchaSiteKey` present · owner `/dashboard` → `plan.key 'free'`, `plan.label 'Free'`, `wallet.balanceUsdCents 0` · owner on pro → `plan.key 'pro'` |
| A | `Middleware/Authenticate`, `RedirectIfAuthenticated` (feature) | rows | guest `GET /dashboard` → redirect `/login` · guest `GET /documents` → `/login` · logged-in `GET /login` → `/dashboard` · logged-in `GET /register` → `/dashboard` |
| C | Models: relationships, `$fillable`, `$casts` | — | framework — skip |
| C | `Providers/*`, `Http/Kernel`, `Console/Kernel`, `Exceptions/Handler` | — | wiring, exercised by every feature test — skip |
| C | `Middleware/TrimStrings`, `EncryptCookies`, `TrustProxies`, `VerifyCsrfToken`, `ValidateSignature` | — | framework classes with only a property override — skip |
| C | `ExpireSubscriptions::stillActiveAtStripe` | — | calls the Stripe API; see Tier D |
| D | `StripeWebhookController` | needs a signed event fixture | missing signature → 400 · bad signature → 400 · `invoice.paid` activates the subscription · `customer.subscription.deleted` cancels it · replayed event is idempotent |
| D | `PakasirWebhookController` | needs the signature scheme | missing `order_id` → 400 · unknown order → 404 · completed payment fulfils once · a second delivery is a no-op |
| D | Cross-org authorization | rows for two orgs | `GET /documents/{other org's doc}` → 403/404 · `POST /documents/{id}/send` on another org → 403 · `PATCH /members/{user}/role` as member → 403 · `GET /plan` as member → 403 · `DELETE /templates/{other org}` → 403 |
| D | `SigningController` flow | rows, `Mail::fake` | `GET /sign/{token}` unknown → 404 · shows OTP page before verification · wrong OTP → error · verified → signing page · `finish` on an unverified signer → 403 · completed document `pdf` downloads · expired document → 410 |
| D | `SubscriptionController`, `BillingController`, `BillingTopupController`, `PaymentStatusController` | rows, `Http::fake` | index 403 for non-owner · `downgrade` sets free/cancelled · `payWithQr` creates a pending payment and returns the QR payload · `payments.status` returns `paid` after fulfilment |
| D | `InvitationController`, `InvitationAcceptController`, `MembersController`, `OrganizationSettingsController`, `DashboardController`, `DocumentSignerController`, `DocumentSignatureFieldController`, `TemplateSignatureFieldController` | rows | one happy path + one authorization failure each |

### 6.2 JavaScript

| Tier | Target | Mock | Scenarios (one `it` each) |
| --- | --- | --- | --- |
| A | `lib/utils.js` `cn` | none | done in Phase 0 |
| A | `Components/data-table/utils.ts` `valueUpdater` | none | a function updater receives the current value and its return is stored · a plain value is stored as-is · works with a `ref` holding an object |
| A | `Composables/useFeedback.js` | none | see Phase 5 worked example · `showConfirmation` stores title/message/confirm/cancel text · `showConfirmation()` with no args uses the defaults · `closeFeedback` closes without touching the confirmation dialog |
| A | `lib/theme-transitions.js` `withoutTransitions` | `vi.stubGlobal("requestAnimationFrame", cb => cb())` | calls `apply` once · a `<style>` with `transition:none` is in `document.head` while `apply` runs (assert inside the callback) · the style is removed after two animation frames · reads `document.body.offsetHeight` (spy with `vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get')`) |
| A | `Composables/usePdfLoader.js` `loadPdf` | `axios`, `pdfjs-dist` | GETs the given URL · decodes `data.data` from base64 into a `Uint8Array` with the right bytes (`btoa("hello")` → `[104,101,108,108,111]`) · returns `getDocument(...).promise` · an axios rejection propagates |
| A | `Components/landing/Pricing.vue` | Inertia (`usePage`, `Link`) | renders three plan names · Enterprise renders `<a href="mailto:…">` from `usePage` without the `data-inertia` marker · Starter and Pro render through `Link` with `/register` and `/plan` · "Most popular" badge only on Pro · each plan lists its four bullets |
| A | `Components/landing/LandingSection.vue` | none | renders `title` in an `h2` · renders `eyebrow` only when given · renders `subtitle` only when given · applies the muted class when `muted` · renders the default slot · sets `id` on the `section` |
| A | `Components/landing/Faq.vue` | none | renders 8 `details` · each `summary` shows the question · answers are present in the DOM |
| A | `Components/landing/WhatIsDigitalSignature.vue` | none | renders the two `h3` headings · section id is `what-is-a-digital-signature` |
| A | `Components/common/StatCard.vue` | none | renders label and value · renders the icon slot / prop (read the component first for exact props) |
| A | `Components/members/MemberRoleBadge.vue` | none | one test per role → expected label and variant class · unknown role falls back |
| A | `Components/ui/resource-count/ResourceCount.vue` | none | `used/limit` renders "2 / 5" · `limit null` renders unlimited wording · at or over limit applies the warning variant (read the component for the exact class) |
| A | `Components/page/PageHeader.vue`, `PageSection.vue` | none | title/description props render · actions slot renders |
| B | `Pages/Auth/LoginOtp.vue` | Inertia (`useForm`, `Link`, `Head`), fake timers | shows the email prop · countdown starts at `resendAfter` and the resend button is disabled · after `resendAfter` seconds the button reads "Resend code" and is enabled · verify button disabled until 6 characters · submitting calls `post("/login.otp.verify")` · clicking resend calls `post("/login.otp.resend")` and restarts the countdown at 60 (drive `onSuccess` from the `post` mock) · `status === "login-code-sent"` shows the "New code sent" banner · `resendForm.errors.otp` renders under the resend button · "Use a different account" links to `/login` |
| B | `Pages/Auth/VerifyEmail.vue` | Inertia (`usePage` with `auth.user.email`, `useForm`, `Link`) | shows the user's email · verify disabled until 6 digits · submit posts to `/verification.verify` · resend posts and starts a 60 s cooldown · banner on `status === "verification-code-sent"` |
| B | `Components/plan/PlanPickerDialog.vue` | Inertia (`usePage` with `plans` = `config/plans.php` shape, `router`), `window.confirm`, `location` stub; `attachTo: document.body` | renders three cards · prices "$0", "$10", "$50" · "/month" only when price > 0 · benefits text "3 documents per week", "Unlimited members", "10 GB storage" · current plan shows "Current" badge and a disabled "Current plan" button · Pro click emits `select` with `"pro"` · Free click with `confirm` false does nothing · Free click with `confirm` true calls `router.post("/plan.downgrade")` · Enterprise click sets `location.href` to `salesMailto` |
| B | `Components/FileDropzone.vue` | none | drop event with a file emits it · click opens the hidden input (spy on `click`) · rejects wrong type if the component validates (read it first) |
| B | `Components/dashboard/DashboardFilter.vue` | Inertia `router` if used | changing the select emits / calls router with the new value |
| B | `Components/billing/TransactionTable.vue`, `WalletCard.vue`, `BillingStats.vue` | none | formatted amounts (cents → "$10.00") · empty state text when no rows · credit vs debit sign / colour |
| B | `Components/documents/columns.js`, `templates/columns.js`, `members/columns.js` | `h` from vue | via the exported `columns` array: the status cell's variant per status (invoke `cell({ row })` with a fake row and inspect the vnode props) · size cell formats bytes · the members `createColumns` respects `canManage` (read it first) |
| B | `Components/Layout/navigation.js` | none | every item has `title`, `icon`, `route` · no duplicate routes |
| C | `Pages/**` other than the two auth pages | — | covered by PHP feature tests; mostly wiring |
| C | `documents/prepare/PdfCanvas.vue`, `signing/SigningCanvas.vue`, `signing/SignatureDialog.vue` | — | wrap `pdfjs` / `signature_pad` canvas APIs; happy-dom has no canvas — skip |
| C | `dashboard/ActivityChart.vue`, `StatusChart.vue`, `landing/DashboardPreviewCarousel.vue` | — | ApexCharts / animation wrappers, no logic of ours |
| C | `Components/ui/**`, `components/ui/**` | — | shadcn/reka wrappers — not our code |
| C | `animations/FadeIn.vue`, `SlideIn.vue`, `ThemeToggle.vue`, `RecaptchaField.vue` | — | browser-API driven (observer, `matchMedia`, grecaptcha script); stubbed in setup, not tested |
| C | `data-table/DataTable*.vue` | — | thin wrappers over TanStack table |

## 7. Definition of done

- [ ] Phase 0 committed separately; `php artisan test` shows no `!` and no `WARN`; `npm test` runs
- [ ] `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php` are gone
- [ ] Every Tier A row in 6.1 and 6.2 has a test file at the path 4.1 prescribes, with every listed scenario as its own test, all green
- [ ] Every Tier B row is done, or the PR description names which ones were left and why
- [ ] `grep -rn "sleep(" tests/` and `grep -rn "toMatchSnapshot" tests/js` both print nothing
- [ ] `grep -rn "Mockery\|->mock(\|partialMock" tests/Unit` prints nothing (no mocking of our own classes)
- [ ] No test file imports from `node_modules/@inertiajs` directly or reads `window.location` without a stub
- [ ] `git diff --stat main -- app/ resources/js/` shows **only** `bootstrap/app.php` (the guard) — nothing under `app/` or `resources/js/` changed
- [ ] A Tier D follow-up issue exists with the rows from 6.1 pasted in
- [ ] `README.md` has a Testing section with `npm test` and `docker exec esign-app php artisan test`

## 8. Files you will create or touch

| File | Change |
| --- | --- |
| `bootstrap/app.php` | 2 lines: guard the `define()`s |
| `tests/Feature/TemplateUploadTest.php`, `DocumentUploadTest.php` | fix the renamed-PDF test (real `UploadedFile`) |
| `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php` | delete |
| `package.json`, `package-lock.json` | Vitest deps + `test`, `test:watch`, `test:all` scripts |
| `vitest.config.js`, `tests/js/setup.js` | new |
| `tests/Unit/Services/*Test.php` (11 files) | new |
| `tests/Unit/Models/SubscriptionTest.php`, `UserTest.php`, `OrganizationTest.php` | new |
| `tests/Unit/Rules/RecaptchaTest.php` | new |
| `tests/Unit/Mail/*MailTest.php` (4) | new |
| `tests/Unit/Console/ExpireDocumentsTest.php`, `ExpireSubscriptionsTest.php` | new |
| `tests/Feature/LoginThrottleTest.php`, `ProfileUpdateValidationTest.php`, `SharedInertiaPropsTest.php`, `AuthRedirectsTest.php` | new |
| `tests/js/lib/*.test.js` (2), `tests/js/Composables/*.test.js` (2), `tests/js/Components/**/*.test.js` (~15), `tests/js/Pages/Auth/*.test.js` (2) | new |
| `README.md` | Testing section |

Roughly 45 new test files. Work in the phase order; commit at the end of each phase with
`test(<phase>): …` so a reviewer can read one phase at a time. Suggested PR title:
`test: add unit test layer for services, models, composables and components`.
