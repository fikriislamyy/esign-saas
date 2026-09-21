# Make every "Contact sales" button open the mail client

## 1. What we are building, in one paragraph

There are three "Contact sales" buttons: one on the landing page pricing card, two on the Plan
page (the current-plan card, and the Enterprise card inside the "Upgrade Plan" dialog). Clicking
any of them must open the visitor's email client with the sales address filled in and the
subject **Enterprise Plan Inquiry**. Today the landing page button does nothing (see 1.1), and
the two Plan page buttons work but build the link by hand with a different subject. After this
change all three read one ready-made `mailto:` link that the server builds from
[config/plans.php](config/plans.php), so the address and the subject each live in exactly one
place.

The address is `enterprise.contact_email` in `config/plans.php`, currently `sales@bebem.my.id`.
If the product owner gives you a different one, change that single line and nothing else.

### 1.1 Why the landing page button is broken

[Pricing.vue:60-64](resources/js/Components/landing/Pricing.vue#L60-L64) wraps every plan
button in Inertia's `<Link>`. `<Link>` intercepts every plain left click, calls
`preventDefault()`, and asks Inertia to load the `href` over XHR. Inertia's `shouldIntercept`
checks for modifier keys and right clicks — it never looks at the URL scheme — so
`mailto:sales@bebem.my.id` is fetched as if it were a page, the request fails, and the user
sees nothing. A `mailto:` link must be a plain `<a>`.

## 2. Things you must not do

- **Do not hardcode the address or the subject in any `.vue` file.** Both come from
  `config/plans.php` through one shared prop. Three buttons with three copies of the string is
  exactly the bug we are fixing.
- **Do not use `no-reply@bebem.my.id`.** It was the landing page address until recently.
  Inquiries sent there are lost.
- **Do not use `urlencode()` for the subject.** It turns spaces into `+`, and several mail
  clients show the plus signs literally. Use `rawurlencode()`, which gives `%20`.
- **Do not put a `mailto:` in an Inertia `<Link>`.** See 1.1.
- **Do not add a contact form, a modal, or a backend endpoint.** The task is a mail-client
  redirect. Nothing is stored and nothing is sent by the server.
- **Do not change the other two landing page buttons** ("Get started", "Subscribe"). They are
  internal routes and `<Link>` is correct for them.

## 3. How the pieces already fit together

| You need to | Look at | What you will see |
| --- | --- | --- |
| The address today | [config/plans.php:49](config/plans.php#L49) | `'contact_email' => 'sales@bebem.my.id'` under `enterprise` |
| Values every page receives | [HandleInertiaRequests.php:73-74](app/Http/Middleware/HandleInertiaRequests.php#L73-L74) | `'stripeKey' => config(...)`, `'recaptchaSiteKey' => config(...)` — the pattern to copy |
| The landing page button | [Pricing.vue:26-32](resources/js/Components/landing/Pricing.vue#L26-L32) and [:60-64](resources/js/Components/landing/Pricing.vue#L60-L64) | `href: "mailto:sales@bebem.my.id"` inside a `<Link>` |
| Plan page button #1 and #2 | [Plan/Index.vue:209-213](resources/js/Pages/Plan/Index.vue#L209-L213) and the two `@click="contactSales"` buttons at [:335](resources/js/Pages/Plan/Index.vue#L335) and [:359](resources/js/Pages/Plan/Index.vue#L359) | `window.location.href = \`mailto:${email}?subject=Enterprise plan enquiry\`` |
| The Enterprise card in the dialog | [PlanPickerDialog.vue:86-92](resources/js/Components/plan/PlanPickerDialog.vue#L86-L92) | Same hand-built link, same wrong subject |
| A `<Button>` rendered as a link | [Button.vue](resources/js/components/ui/button/Button.vue) | `as` prop, default `"button"`. `<Button as="a" href="…">` renders an `<a>` styled as a button. [BreadcrumbLink.vue](resources/js/components/ui/breadcrumb/BreadcrumbLink.vue) is the same `Primitive` with `as` defaulting to `"a"` — proof the pattern works here |
| Reading a shared prop in a template | [Settings/Organization.vue:48](resources/js/Pages/Settings/Organization.vue#L48) | `$page.props.flash?.success` — `$page` is available in every template |
| A test that inspects Inertia props | [TemplateLimitTest.php:109-114](tests/Feature/TemplateLimitTest.php#L109-L114) | `->assertInertia(fn ($page) => $page->where('key', value))` |

## 4. The design — done for you

One shared prop, built once, read three times.

```
config/plans.php
  enterprise.contact_email   = sales@bebem.my.id          (exists)
  enterprise.contact_subject = Enterprise Plan Inquiry     (new)
          │
          ▼
HandleInertiaRequests::share()
  salesMailto = "mailto:sales@bebem.my.id?subject=Enterprise%20Plan%20Inquiry"
          │
          ├──▶ Landing  Pricing.vue          <Button as="a" :href="salesMailto">
          ├──▶ Plan     Index.vue  (×2)      <Button as="a" :href="$page.props.salesMailto">
          └──▶ Plan     PlanPickerDialog.vue window.location.href = page.props.salesMailto
```

Why an `<a>` and not a click handler on the two visible buttons: a link to a mail client *is* a
link. As an anchor it works with middle-click, right-click → copy address, keyboard, and
screen readers, and there is no JavaScript to get wrong. The dialog keeps its click handler
because its button sits in a `v-for` shared by all three plans; one line there is simpler than
a branch in the loop.

Why a shared prop and not a prop from each controller: the landing page is a guest page and the
Plan page is an owner page — two controllers would each need the same two lines. The middleware
already shares `stripeKey` and `recaptchaSiteKey` the same way, and a config read per request
costs nothing.

## 5. Step by step

Commands run inside the app container: prefix with `docker exec esign-app`.

### Phase 1 — the link, built once (backend)

**[config/plans.php](config/plans.php)** — add one line under `enterprise`, right after
`contact_email`:

```php
        'contact_email' => 'sales@bebem.my.id',
        'contact_subject' => 'Enterprise Plan Inquiry',
```

**[app/Http/Middleware/HandleInertiaRequests.php](app/Http/Middleware/HandleInertiaRequests.php)** —
in `share()`, add one entry after `'recaptchaSiteKey'`:

```php
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'salesMailto' => 'mailto:'.config('plans.enterprise.contact_email')
                .'?subject='.rawurlencode(config('plans.enterprise.contact_subject')),
```

**Create `tests/Feature/SalesContactTest.php`:**

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

    private function expectedLink(): string
    {
        return 'mailto:'.config('plans.enterprise.contact_email').'?subject=Enterprise%20Plan%20Inquiry';
    }

    public function test_the_landing_page_receives_the_sales_link(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('salesMailto', $this->expectedLink())
        );
    }

    public function test_the_plan_page_receives_the_sales_link(): void
    {
        $organization = Organization::create(['name' => 'Acme']);
        $owner = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner)->get('/plan')->assertInertia(fn ($page) => $page
            ->where('salesMailto', $this->expectedLink())
        );
    }
}
```

The subject is spelled out in the test on purpose (`%20`, capital P and I). If someone later
swaps `rawurlencode` for `urlencode`, or "Inquiry" for "enquiry", this fails.

Run: `docker exec esign-app php artisan test tests/Feature/SalesContactTest.php` — 2 passed.

### Phase 2 — the landing page

**[resources/js/Components/landing/Pricing.vue](resources/js/Components/landing/Pricing.vue)**

In `<script setup>`, add `usePage` to the Inertia import and read the link:

```js
import { Link, usePage } from "@inertiajs/vue3";
// …
const salesMailto = usePage().props.salesMailto;
```

In the `plans` array, change the Enterprise button to use it and mark it external:

```js
        button: { text: "Contact sales", variant: "outline", href: salesMailto, external: true },
```

(`salesMailto` must be declared **above** the `plans` array, since the array reads it.)

In the template, replace the single `<Link>…</Link>` block with a branch:

```vue
                        <Button
                            v-if="plan.button.external"
                            as="a"
                            :href="plan.button.href"
                            :variant="plan.button.variant"
                            class="w-full"
                        >
                            {{ plan.button.text }}
                        </Button>

                        <Link v-else :href="plan.button.href" class="w-full">
                            <Button :variant="plan.button.variant" class="w-full">
                                {{ plan.button.text }}
                            </Button>
                        </Link>
```

Run `npm run build`, open http://localhost:8000/#pricing, and click "Contact sales". Your mail
client opens (or the browser asks which app to use) with the address and subject filled in. In
DevTools → Elements, the button is now `<a href="mailto:…" data-slot="button">`, not
`<a><button>`.

### Phase 3 — the Plan page

**[resources/js/Pages/Plan/Index.vue](resources/js/Pages/Plan/Index.vue)**

Delete the `contactSales()` function (the three lines at
[209-213](resources/js/Pages/Plan/Index.vue#L209-L213)).

Replace **both** buttons that had `@click="contactSales"` (one in the `pro` branch, one in the
`v-else` branch) with an anchor. Keep the icon and the classes:

```vue
                                <Button
                                    as="a"
                                    :href="$page.props.salesMailto"
                                    variant="outline"
                                    class="w-full gap-2 sm:min-w-48"
                                >
                                    <Mail class="h-4 w-4" />
                                    Contact sales
                                </Button>
```

Then `grep -n "contactSales" resources/js/Pages/Plan/Index.vue` must print nothing.

### Phase 4 — the Enterprise card in the Upgrade dialog

**[resources/js/Components/plan/PlanPickerDialog.vue](resources/js/Components/plan/PlanPickerDialog.vue)**

`page` is already `usePage()` in this file. Replace the `enterprise` branch of `choose()`:

```js
    if (key === "enterprise") {
        window.location.href = page.props.salesMailto;
        return;
    }
```

The old `contact_email` lookup and its `if` guard go away — the prop is always present.

### Phase 5 — build and verify

```bash
npm run build
docker exec esign-app php artisan test tests/Feature/SalesContactTest.php tests/Feature/LandingSeoTest.php
grep -rn "Enterprise plan enquiry\|mailto:sales\|mailto:no-reply" resources/js
```

The grep must print nothing: no hand-built links remain.

## 6. Manual checks before you open the PR

Log in as an organization **owner** for checks 3–5 (only owners can open `/plan`).

| # | Do | Expect |
| --- | --- | --- |
| 1 | http://localhost:8000/#pricing → "Contact sales" | Mail client opens, To: `sales@bebem.my.id`, Subject: `Enterprise Plan Inquiry` (spaces, not `+` or `%20`) |
| 2 | Same button, right-click → "Copy email address" (or hover and read the status bar) | Shows the address — proves it is a real link |
| 3 | `/plan` on a **Free** org → "Upgrade Plan" → Enterprise card → "Contact sales" | Mail client opens with the same address and subject; the dialog stays open behind it |
| 4 | `/plan` on a **Pro** org → "Contact sales" in the plan card | Same |
| 5 | Set the org to enterprise in the DB (`UPDATE subscriptions SET plan='enterprise' WHERE organization_id='…'`), reload `/plan` → "Contact sales" | Same. Set it back to `free` afterwards |
| 6 | View page source on `/` and search `salesMailto` | `"salesMailto":"mailto:sales@bebem.my.id?subject=Enterprise%20Plan%20Inquiry"` |
| 7 | Change `contact_subject` in `config/plans.php` to `Test`, `php artisan config:clear`, reload `/` and click | Subject is now `Test` on the landing page **and** on `/plan` — proves one source. Change it back |

## 7. Definition of done

- [ ] `SalesContactTest` passes (2 tests); `LandingSeoTest` still passes
- [ ] All three buttons open the mail client with `Enterprise Plan Inquiry` as the subject
- [ ] `grep -rn "mailto:" resources/js` matches nothing — the link string exists only in the middleware
- [ ] `grep -rn "contactSales\|contact_email" resources/js` matches nothing
- [ ] The landing page Enterprise button renders as `<a href="mailto:…">`, not inside a `<Link>`
- [ ] The two other landing page buttons still navigate with Inertia (no full page reload when clicking "Get started")
- [ ] `npm run build` succeeds

## 8. Files you will touch

| File | Change |
| --- | --- |
| `config/plans.php` | +1 line: `contact_subject` |
| `app/Http/Middleware/HandleInertiaRequests.php` | +2 lines: `salesMailto` shared prop |
| `tests/Feature/SalesContactTest.php` | new, 2 tests |
| `resources/js/Components/landing/Pricing.vue` | read the prop; Enterprise button becomes `<Button as="a">` |
| `resources/js/Pages/Plan/Index.vue` | delete `contactSales()`; two buttons become `<Button as="a">` |
| `resources/js/Components/plan/PlanPickerDialog.vue` | one line in `choose()` |

Six files, about 40 lines net. One commit. Suggested title:
`fix(plans): open the mail client from every Contact sales button`.
