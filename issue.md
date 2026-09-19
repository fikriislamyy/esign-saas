# Landing page SEO for "digital signature"

## 1. What we are building, in one paragraph

Make the landing page rank for **digital signature** with **small and individual business owners**
as the audience. The strategy work (which questions to answer, what searchers want, the heading
structure, the copy) is done in section 4 — you paste it, you do not invent it. The engineering
work is getting that content in front of crawlers, which today see almost nothing, and telling
them which pages are public and which are private.

Measured on the current build with `curl -s http://localhost:8000/`:

| What a crawler needs | In the raw HTML today |
| --- | --- |
| `<title>` with the keyword | `EZSign` (just the app name) |
| `<meta name="description">` | none |
| `<link rel="canonical">` | none |
| Open Graph / Twitter tags (link previews in Slack, WhatsApp, X) | none |
| `<h1>` | none — it is typed out letter by letter by JavaScript after load |
| Structured data (`application/ld+json`) | none |
| Body text | an empty `<div id="app">` plus a JSON blob |
| `robots.txt` | allows everything, including `/dashboard`, `/documents` and private `/sign/{token}` links |
| `sitemap.xml` | none |

Googlebot runs JavaScript, so the body eventually gets rendered. The head tags do not: bots that
generate link previews never run JavaScript, and Google's own snippet comes from the head. So the
head is rendered server-side in Blade (Phase 1), the H1 becomes real text (Phase 2), the new
content goes in as a Vue section (Phase 3), and robots/sitemap/noindex tell crawlers where to look
(Phase 4).

## 2. Things you must not do

- **Do not** set up Inertia SSR. It needs a Node process in production, a second Vite build and
  supervisor changes. Everything a non-JavaScript bot needs is in the head, and this plan renders
  the head in Blade. SSR is the next step *only if* Search Console later shows the body missing.
- **Do not** repeat the keyword. Three or four natural uses of "digital signature" in ~200 words
  of body copy is right. Ten is a penalty. The copy in 4.4 has the density already; paste it as-is.
- **Do not** add `Review` or `AggregateRating` structured data for the testimonials. They are
  three first-name-and-initial quotes with no company. Google treats self-serving review markup
  as spam, and there is nothing to back the quotes up.
- **Do not** claim things the product does not do. In particular: no "SOC 2", no "GDPR
  compliant", no "bank-level encryption". The copy in 4.4 claims only what the code does:
  email one-time passcode, a certificate-based signature on the finished PDF, a record of who
  signed and when.
- **Do not** put pricing figures in the new SEO section. The landing page's pricing card and
  `config/plans.php` currently disagree (Phase 5 fixes that). The SEO copy says "free to start",
  which is true, and nothing more.
- **Do not** hardcode the domain anywhere except `public/robots.txt` (which is a static file
  and has no choice). Everything else reads `config('app.url')`.
- **Do not** `noindex` the landing page. The default this plan introduces is "noindex unless
  the route passed `meta`" — the landing route passes it. Check with curl in section 11.

## 3. How the pieces already fit together

| You need to | Look at | What you will see |
| --- | --- | --- |
| Find the landing route | [routes/web.php:38-40](routes/web.php#L38-L40) | A closure inside the `guest` group: `Inertia::render('Landing')` |
| See the root HTML every page uses | [resources/views/app.blade.php](resources/views/app.blade.php) | `<title inertia>` and `@inertiaHead`; no meta tags |
| See how the client composes the tab title | [resources/js/app.js:17](resources/js/app.js#L17) | `title: (title) => \`${title} - ${appName}\`` — the client **appends** " - EZSign" |
| See the H1 | [Landing.vue:154-161](resources/js/Pages/Landing.vue#L154-L161) | `<h1><Typewriter text="Secure Digital Signing for Modern Teams" /></h1>` |
| See how a landing section is built | [LandingSection.vue](resources/js/Components/landing/LandingSection.vue) | Props `id`, `eyebrow`, `title`, `subtitle`, `muted`; renders the `<h2>` for you |
| See a section that uses it | [HowItWorks.vue](resources/js/Components/landing/HowItWorks.vue) | `<LandingSection id="how-it-works" eyebrow="…" title="…">` with `<h3>` cards inside |
| See how page-level values reach Vue | [HandleInertiaRequests.php:74](app/Http/Middleware/HandleInertiaRequests.php#L74) | `'recaptchaSiteKey' => config(...)` shared as a prop |
| See the plan limits that pricing copy must match | [config/plans.php](config/plans.php) | free: 3 documents/**week**, 3 members; pro: $10/month |
| See the FAQ data shape | [Faq.vue:5-32](resources/js/Components/landing/Faq.vue#L5-L32) | `const faqs = [{ q, a }, …]` |

**Two Inertia facts this plan depends on.**

`Inertia::render(...)->withViewData([...])` passes variables to `app.blade.php` — server-side,
in the HTML, before any JavaScript. That is how the head tags get there. It exists in the
installed `inertia-laravel` 0.6.11.

The client-side `<Head>` component only manages elements that carry the `inertia` attribute.
Meta tags we write in Blade *without* that attribute are left alone after hydration. The one
element that has it, `<title inertia>`, gets replaced by the client — so Blade must compose the
title with the same " - EZSign" suffix `app.js` adds, or the title will flicker from one string
to another.

## 4. The SEO strategy — done for you

You paste from this section. The reasoning is here so you can judge edge cases, not so you can
redo it.

### 4.1 Sub-question analysis

What a small business owner actually needs answered before they trust a digital signature tool
with a real contract. Each is mapped to where on the page it is answered.

| # | Sub-question | Answered by |
| --- | --- | --- |
| 1 | Is a digital signature legally binding for my contracts? | New section, H3 "Is a digital signature legally binding?" |
| 2 | What is the difference between a digital and an electronic signature, and which is this? | New section, H3 "Digital signature vs. electronic signature" |
| 3 | How do I know the right person signed, not whoever had the link? | Security section (retitled "How we verify who signed") |
| 4 | Can the document be changed after it is signed? Would I know? | New section, para 1; new FAQ entry |
| 5 | Does my client need an account, an app or a special device? | Existing FAQ "Do signers need an account?" |
| 6 | What does it cost, and is there a free plan? | Pricing section (facts fixed in Phase 5) |
| 7 | What files can I use? | Existing FAQ "What file types can I upload?" |
| 8 | How long does it take from upload to signed? | How-it-works section (three steps) |
| 9 | What do I get at the end — where is my proof? | New section, para 1; existing "Verify & sign" step |
| 10 | Can I reuse the same contract for every client? | Existing pricing bullet "Templates"; not expanded here |
| 11 | Where are my documents stored and who can see them? | New FAQ entry |

Excluded on purpose: the history of digital signatures, how public-key cryptography works,
vendor comparison tables, compliance acronyms. A small business owner does not need any of it to
decide, and each one dilutes the page.

### 4.2 Search intent and page structure

**"digital signature" is mixed intent, leaning informational.** Most people typing the bare
phrase want to know what one is and whether it counts. A significant minority are looking for a
tool ("digital signature software", "free digital signature", "sign PDF online"). Almost nobody is
ready to pay this second.

**Structure that serves both:** a product landing page whose first scroll answers the
informational question. Hero (commercial, keyword in H1) → "What is a digital signature?"
(informational, ~200 words, sub-questions 1, 2, 4, 9) → How it works (8) → How we verify who
signed (3) → Use cases → Pricing (6) → FAQ (5, 7, 11). The informational block sits second, not
buried at the bottom, because it is what qualifies the page for the larger share of the query.

Long-tail phrases folded in naturally, never forced: "digital signature for small business",
"legally binding", "sign a PDF online", "free to start".

### 4.3 Content blueprint

Headings only. Existing sections keep their H3s; only the ones marked *new* or *retitle* change.

```
H1  Digital Signature Software for Small Business                      (Phase 2 — replaces the typewriter)

H2  What is a digital signature?                                       (Phase 3 — new section)
    H3  Digital signature vs. electronic signature
    H3  Is a digital signature legally binding?

H2  Sign a document in three steps                                     (existing HowItWorks — unchanged)
    H3  Upload your PDF
    H3  Add signers & send
    H3  Verify & sign

H2  How we verify who signed                                           (Phase 3 — retitle from "Built for trust")
    H3  Access & Authentication                                        (existing)
    H3  Compliance & Audit                                             (existing)

H2  One tool for every agreement                                       (existing UseCases — unchanged)

H2  Start free. Scale when you're ready.                               (existing Pricing — bullets fixed in Phase 5)

H2  Frequently asked questions                                         (existing Faq — two entries added in Phase 3)
```

### 4.4 The copy

**Head strings** (go in `config/seo.php`, Phase 1). Title is 46 characters; the client appends
" - EZSign" for 55. Description is 139 characters.

```
title:        Digital Signature Software for Small Business
description:  Get contracts signed online with a legally binding digital signature. Your clients sign from an email link, no account needed. Free to start.
```

**Hero** (Phase 2). H1 then the paragraph under it.

```
H1: Digital Signature Software for Small Business

Send a contract, your client signs it in the browser, and you get back a
PDF that proves who signed and that nothing changed afterwards. No account
needed for signers. Free to start.
```

**"What is a digital signature?" section** (Phase 3). This is the drafted main-topic section:
one H2, two H3s, three short paragraphs, about 230 words. "digital signature" appears four times
in the body, which is where it should be.

```
H2: What is a digital signature?

A digital signature is a way to sign a document online so that two things
can be proven later: who signed it, and that nothing in it has changed
since. Instead of printing, signing with a pen and scanning, you send the
PDF by email. Your client opens the link, confirms their identity with a
one-time code, and signs in the browser. When everyone has signed, the PDF
is sealed with a certificate, so any PDF reader can check that it has not
been altered.

H3: Digital signature vs. electronic signature

An electronic signature is any mark that shows agreement: a typed name, a
drawn squiggle, a ticked box. A digital signature goes further. It records
who signed and when, and it locks the document so that later edits can be
detected. EZSign does both. Every signer is verified by an email code, and
every finished document carries a certificate-based digital signature and a
record of who signed it.

H3: Is a digital signature legally binding?

In most countries, yes, provided the signer's identity was verified, the
signer clearly intended to sign, and the signed document cannot be changed
without detection. EZSign records all three. A few document types still
require a notary or ink by law, such as some wills, deeds and court filings.
Check the rules where you live for those.
```

Every claim above maps to code: the one-time code is `SigningOtpService`; the certificate seal is
`$pdf->setSignature(...)` at [SigningController.php:465](app/Http/Controllers/SigningController.php#L465),
which throws if no certificate is configured, so every finished PDF has it; "who signed and when"
is `signers.signed_at`.

**Two new FAQ entries** (Phase 3):

```
q: Can a signed PDF be changed afterwards?
a: Not without it showing. When the last person signs, EZSign seals the PDF with a certificate. Open it in Adobe Reader or any PDF viewer with a signature panel and it will tell you whether the file has been modified since signing.

q: Where are my documents stored?
a: In private cloud storage that only your organization's members can reach through the app. Signers see only the document they were sent, through their own link.
```

## 5. Phase 1 — render the head tags server-side

### 5.1 New file: `config/seo.php`

```php
<?php

return [

    'landing' => [
        'title' => 'Digital Signature Software for Small Business',
        'description' => 'Get contracts signed online with a legally binding digital signature. Your clients sign from an email link, no account needed. Free to start.',
        'image' => '/storage/images/dashboard-preview-3.png',
    ],

];
```

The image is the light-theme dashboard screenshot already used on the page. Link previews want
1200×630; that file is a different shape and will be cropped. Good enough for now; if a designer
exports a proper `public/images/og-image.png` later, this is the one line to change.

### 5.2 New file: `app/Http/Controllers/LandingController.php`

Replace the route closure with a controller so the meta-building has a home.

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class LandingController extends Controller
{
    public function __invoke()
    {
        $seo = config('seo.landing');
        $base = rtrim(config('app.url'), '/');

        $meta = [
            'title' => $seo['title'],
            'description' => $seo['description'],
            'canonical' => $base.'/',
            'image' => $base.$seo['image'],
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => config('app.name'),
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'url' => $base.'/',
                'description' => $seo['description'],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'USD',
                ],
            ],
        ];

        return Inertia::render('Landing', ['meta' => $meta])
            ->withViewData(['meta' => $meta]);
    }
}
```

The same `$meta` goes two places on purpose. `withViewData` puts it in the Blade head. The page
prop puts it in Vue so `<Head :title>` can set the same title after hydration (5.5). One array,
two consumers, no drift.

`config('app.url')` rather than `url('/')`: behind Render's proxy the request scheme can read as
`http`, and a canonical that says `http://` when the site is `https://` is a wrong canonical.
`APP_URL` is deterministic. Confirm on Render that `APP_URL` is `https://<your domain>` with no
trailing slash.

### 5.3 `routes/web.php`

Replace lines 38–40:

```php
Route::get('/', LandingController::class)->name('landing');
```

Add `use App\Http\Controllers\LandingController;` with the other imports. The route stays inside
the `guest` group; Googlebot is never logged in, so it always gets the landing page.

### 5.4 `resources/views/app.blade.php`

Replace the single `<title inertia>` line with this block:

```blade
@php($meta = $meta ?? null)

@if ($meta)
    <title inertia>{{ $meta['title'] }} - {{ config('app.name') }}</title>
    <meta name="description" content="{{ $meta['description'] }}">
    <link rel="canonical" href="{{ $meta['canonical'] }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['canonical'] }}">
    <meta property="og:image" content="{{ $meta['image'] }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta['title'] }}">
    <meta name="twitter:description" content="{{ $meta['description'] }}">
    <meta name="twitter:image" content="{{ $meta['image'] }}">

    <script type="application/ld+json">@json($meta['jsonLd'])</script>
@else
    <title inertia>{{ config('app.name', 'Laravel') }}</title>
    <meta name="robots" content="noindex, nofollow">
@endif
```

Read the `@if` as a rule: **a route that passes `meta` is public; every other page is
`noindex`.** Today only the landing route passes it, so login, register, dashboard, signing links
and everything else become noindex with no per-page work. When a second public page is added (a
pricing page, a blog post), its controller passes `meta` and it is indexed.

`@json` escapes `<` as `<`, so the JSON can never close the `<script>` tag early. Do not
swap it for `{!! json_encode(...) !!}`.

The title is `{{ $meta['title'] }} - {{ config('app.name') }}` because [app.js:17](resources/js/app.js#L17)
appends " - EZSign" on the client. Blade must produce the identical string or the tab title
changes a moment after load.

### 5.5 `resources/js/Pages/Landing.vue`

Add the prop and use it in `<Head>`:

```js
defineProps({
    meta: Object,
});
```

```vue
<Head :title="meta.title" />
```

Replace the existing `<Head title="EZSign — Send, sign and track documents online" />` at
[line 89](resources/js/Pages/Landing.vue#L89). Pass the *bare* title — `app.js` adds the suffix.

### 5.6 Check

```bash
curl -s http://localhost:8000/ | grep -E '<title|name="description"|rel="canonical"|og:title|ld\+json'
curl -s http://localhost:8000/login | grep -E '<title|name="robots"'
```

First command: five matches, title reads `Digital Signature Software for Small Business - EZSign`.
Second: title `EZSign`, and `<meta name="robots" content="noindex, nofollow">`.

## 6. Phase 2 — a real H1

### 6.1 `resources/js/Pages/Landing.vue`

At [lines 154–161](resources/js/Pages/Landing.vue#L154-L161), replace:

```vue
<h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
    <Typewriter text="Secure Digital Signing for Modern Teams" />
</h1>
```

with:

```vue
<h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
    Digital Signature Software for Small Business
</h1>
```

Then replace the paragraph under it (the one beginning "Create, send, sign and manage
documents…") with the hero paragraph from 4.4:

```vue
<p class="mt-6 max-w-xl text-lg leading-8 text-muted-foreground">
    Send a contract, your client signs it in the browser, and you get back a
    PDF that proves who signed and that nothing changed afterwards. No account
    needed for signers. Free to start.
</p>
```

Why the typewriter goes: an H1 that is empty at load and fills in over two seconds is an H1 a
crawler may snapshot half-written. The H1 is the single most weighted on-page element; it is
not the place for an effect. The `<FadeIn>` wrappers around it can stay: opacity does not remove
text from the DOM.

### 6.2 Delete the component

`Typewriter.vue` has no other user (checked with grep). Remove the import at
[line 24](resources/js/Pages/Landing.vue#L24) and delete
`resources/js/Components/Typewriter.vue`. Run `grep -rn Typewriter resources/js` afterwards; it
must print nothing.

## 7. Phase 3 — the content

### 7.1 New file: `resources/js/Components/landing/WhatIsDigitalSignature.vue`

```vue
<script setup>
import LandingSection from "@/Components/landing/LandingSection.vue";
</script>

<template>
    <LandingSection
        id="what-is-a-digital-signature"
        eyebrow="The basics"
        title="What is a digital signature?"
    >
        <div class="mx-auto max-w-3xl space-y-10">
            <p class="text-lg leading-8 text-muted-foreground">
                A digital signature is a way to sign a document online so that
                two things can be proven later: who signed it, and that nothing
                in it has changed since. Instead of printing, signing with a pen
                and scanning, you send the PDF by email. Your client opens the
                link, confirms their identity with a one-time code, and signs in
                the browser. When everyone has signed, the PDF is sealed with a
                certificate, so any PDF reader can check that it has not been
                altered.
            </p>

            <div class="grid gap-8 md:grid-cols-2">
                <div>
                    <h3 class="text-lg font-semibold">
                        Digital signature vs. electronic signature
                    </h3>

                    <p class="mt-3 leading-7 text-muted-foreground">
                        An electronic signature is any mark that shows
                        agreement: a typed name, a drawn squiggle, a ticked box.
                        A digital signature goes further. It records who signed
                        and when, and it locks the document so that later edits
                        can be detected. EZSign does both. Every signer is
                        verified by an email code, and every finished document
                        carries a certificate-based digital signature and a
                        record of who signed it.
                    </p>
                </div>

                <div>
                    <h3 class="text-lg font-semibold">
                        Is a digital signature legally binding?
                    </h3>

                    <p class="mt-3 leading-7 text-muted-foreground">
                        In most countries, yes, provided the signer's identity
                        was verified, the signer clearly intended to sign, and
                        the signed document cannot be changed without detection.
                        EZSign records all three. A few document types still
                        require a notary or ink by law, such as some wills, deeds
                        and court filings. Check the rules where you live for
                        those.
                    </p>
                </div>
            </div>
        </div>
    </LandingSection>
</template>
```

`LandingSection` renders the `<h2>` from its `title` prop, so this file only writes the `<h3>`s.
Open [LandingSection.vue](resources/js/Components/landing/LandingSection.vue) once to confirm the
heading tag it uses is `h2`; the blueprint depends on it.

### 7.2 Place it in `Landing.vue`

Import it with the other landing components:

```js
import WhatIsDigitalSignature from "@/Components/landing/WhatIsDigitalSignature.vue";
```

Render it immediately **after the hero section and before `<HowItWorks />`**. Find where
`<HowItWorks />` is used in the template and put `<WhatIsDigitalSignature />` on the line above
it. Second position is deliberate (section 4.2): it answers the informational half of the query
on the first scroll.

### 7.3 Retitle the security section

In [SecurityCompliance.vue:35](resources/js/Components/landing/SecurityCompliance.vue#L35), change
`title="Built for trust"` to `title="How we verify who signed"`. Keep the subtitle. The two
existing cards stay as they are; check that their titles render as `<h3>`, and if they are
`<div>`s or `<p>`s, change the tag — the blueprint needs them as H3.

### 7.4 Two FAQ entries

In [Faq.vue](resources/js/Components/landing/Faq.vue), append to the `faqs` array, after
"Can my whole team use one account?":

```js
{
    q: "Can a signed PDF be changed afterwards?",
    a: "Not without it showing. When the last person signs, EZSign seals the PDF with a certificate. Open it in Adobe Reader or any PDF viewer with a signature panel and it will tell you whether the file has been modified since signing.",
},
{
    q: "Where are my documents stored?",
    a: "In private cloud storage that only your organization's members can reach through the app. Signers see only the document they were sent, through their own link.",
},
```

### 7.5 Build and look

```bash
npm run build
```

Open `/`. The H1 is plain text, present instantly. Scroll: the new section is second. The
security heading reads "How we verify who signed". The FAQ has eight entries.

## 8. Phase 4 — robots, sitemap, noindex

The noindex default is already live from 5.4. This phase adds the two files crawlers look for.

### 8.1 `public/robots.txt`

Replace the contents with:

```
User-agent: *
Disallow: /api/
Disallow: /billing
Disallow: /confirm-password
Disallow: /dashboard
Disallow: /documents
Disallow: /forgot-password
Disallow: /invitations/
Disallow: /login
Disallow: /members
Disallow: /pakasir/
Disallow: /payments
Disallow: /plan
Disallow: /profile
Disallow: /register
Disallow: /reset-password
Disallow: /settings
Disallow: /sign/
Disallow: /templates
Disallow: /verify-email

Sitemap: https://YOUR-DOMAIN/sitemap.xml
```

Replace `YOUR-DOMAIN` with the production host. This is the one hardcoded domain in the plan;
`robots.txt` is a static file and cannot read config.

The list is every top-level path the app serves except `/` — taken from
`php artisan route:list --method=GET`. `/sign/` matters most: those are private signing links
with a token in the URL, and a crawler that finds one in a forwarded email should not index it.

Do **not** disallow `/build/` or `/storage/`. Google needs the CSS, JavaScript and images to
render the page. Blocking them is the classic way to make a page look empty to Google.

### 8.2 Sitemap route

In `routes/web.php`, outside every middleware group (next to the `/health` route is a good
spot):

```php
Route::get('/sitemap.xml', function () {
    $base = rtrim(config('app.url'), '/');

    return response()
        ->view('sitemap', ['urls' => [$base.'/']])
        ->header('Content-Type', 'application/xml');
})->name('sitemap');
```

Outside the groups because a logged-in user (or a bot that once got a session cookie) must not be
redirected to the dashboard when fetching the sitemap.

### 8.3 New file: `resources/views/sitemap.blade.php`

```blade
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url }}</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
@endforeach
</urlset>
```

One URL today. When a second public page exists, add it to the array in 8.2. No package; a
sitemap with one entry does not need one.

### 8.4 Check

```bash
curl -s http://localhost:8000/sitemap.xml
curl -sI http://localhost:8000/sitemap.xml | grep -i content-type
```

Valid XML with one `<loc>`; `Content-Type: application/xml`.

## 9. Phase 5 — make the pricing facts true

The landing page and the plan catalogue disagree. Google's guidance on helpful content is blunt
about pages that say one thing and charge another, and a business owner who signs up on the
strength of "3 documents / month" and hits a weekly cap will not come back.

[config/plans.php](config/plans.php) is the source of truth:

| Plan | Documents | Members | Storage | Price |
| --- | --- | --- | --- | --- |
| free | 3 / **week** | **3** | 100 MB | $0 |
| pro | 100 / month | 10 | 10 GB | **$10 / month** (Stripe subscription) |
| enterprise | unlimited | unlimited | unlimited | contact |

### 9.1 `resources/js/Components/landing/Pricing.vue`

Change the Starter bullets at [line 15](resources/js/Components/landing/Pricing.vue#L15) from
`"3 documents / month", "1 user"` to:

```js
bullets: ["3 documents / week", "Up to 3 members", "Email OTP verification", "Signed PDF download"],
```

The Team and Business cards describe a credit model that `config/plans.php` does not have. That is
a product decision, not a copy fix, so leave those two cards alone and **tell the owner** they do
not match the Pro plan. Do not invent bullets for them.

### 9.2 `resources/js/Components/landing/Faq.vue`

The entry "How does pricing work?" says "There is no monthly subscription." Pro is a monthly
subscription. Replace the answer with:

```js
a: "Start on the free plan. When you need more, upgrade to a monthly plan from the Plan page. You can see exactly how many documents and members each plan includes before you pay.",
```

## 10. Phase 6 — tests

### 10.1 New file: `tests/Feature/LandingSeoTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingSeoTest extends TestCase
{
    public function test_the_landing_page_renders_seo_tags_in_the_html(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title inertia>Digital Signature Software for Small Business - EZSign</title>', false);
        $response->assertSee('<meta name="description" content="Get contracts signed online', false);
        $response->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"SoftwareApplication"', false);
        $response->assertDontSee('name="robots"', false);
    }

    public function test_pages_without_meta_are_noindex(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertDontSee('name="description"', false);
    }

    public function test_the_sitemap_lists_the_landing_page(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<loc>'.rtrim(config('app.url'), '/').'/</loc>', false);
    }
}
```

`assertSee(..., false)` — the second argument turns off HTML escaping so the assertion matches raw
tags. Without it every test here fails. `@json` writes `"@type":"SoftwareApplication"` with no
spaces, which is what the assertion looks for.

None of these tests use `RefreshDatabase`; the pages they hit touch no tables.

### 10.2 Run

```bash
docker exec esign-app php artisan test tests/Feature/LandingSeoTest.php
docker exec esign-app php artisan test
```

Three green; no new failures in the full suite.

## 11. Verification checklist

Tick each only if you saw it happen.

**Head (Phase 1):**

- [ ] `curl -s localhost:8000/ | grep '<title'` → `Digital Signature Software for Small Business - EZSign`
- [ ] Same curl shows `name="description"`, `rel="canonical"`, `og:title`, `twitter:card`, `ld+json`
- [ ] `curl -s localhost:8000/login | grep robots` → `noindex, nofollow`
- [ ] `curl -s localhost:8000/ | grep robots` → nothing
- [ ] In the browser, the tab title does not change after the page loads (no flicker)

**Body (Phases 2–3):**

- [ ] `curl -s localhost:8000/ | grep -c '<h1'` is still `0` — that is expected; the body is client-rendered. The H1 check is in the browser:
- [ ] Elements panel after load: exactly one `<h1>`, text present with no typewriter
- [ ] `grep -rn Typewriter resources/js` prints nothing
- [ ] Heading outline (browser extension "HeadingsMap", or Lighthouse → Accessibility → heading order): H1 → H2 "What is a digital signature?" → H3 ×2 → H2 "Sign a document in three steps" → … matches 4.3
- [ ] Security section heading reads "How we verify who signed"
- [ ] FAQ has eight entries

**Crawler files (Phase 4):**

- [ ] `curl localhost:8000/robots.txt` lists the Disallow lines and a `Sitemap:` line with the real domain
- [ ] `curl localhost:8000/sitemap.xml` is valid XML with one `<loc>`

**Facts (Phase 5):**

- [ ] Starter card says "3 documents / week" and "Up to 3 members"
- [ ] FAQ pricing answer no longer says "no monthly subscription"
- [ ] The owner has been told the Team/Business cards do not match `config/plans.php`

**After deploy (needs the production URL):**

- [ ] Paste the URL into Google's Rich Results Test — it reports a valid `SoftwareApplication`
- [ ] Paste the URL into a link-preview checker (or a Slack DM to yourself) — title, description and image appear
- [ ] Lighthouse SEO category ≥ 95
- [ ] Search Console → URL inspection → Test live URL → "View crawled page" shows the H1 and the "What is a digital signature?" section in the rendered HTML. If it does not, that is the signal that SSR is the next step — not before.

## 12. Common ways this goes wrong

| Symptom | Cause | Fix |
| --- | --- | --- |
| Tab title flickers from one string to another on load | Blade title and `app.js` suffix do not match | 5.4: Blade must produce `{title} - EZSign` exactly |
| Title in raw HTML is `EZSign` | `withViewData` not called, or route still uses the old closure | 5.2, 5.3 |
| `Undefined variable $meta` on `/login` | Missing `@php($meta = $meta ?? null)` | 5.4, first line of the block |
| Landing page has `noindex` | The `@if ($meta)` is inverted, or `meta` key misspelt | 5.4 |
| Canonical says `http://localhost:8000/` in production | `APP_URL` on Render is wrong | Set `APP_URL=https://<domain>` in Render's env, no trailing slash |
| Canonical says `http://` in production | Someone swapped `config('app.url')` for `url('/')` | 5.2 |
| JSON-LD invalid in Rich Results Test | `{!! json_encode !!}` used instead of `@json`, or a trailing comma in the PHP array | 5.2, 5.4 |
| `<h1>` still typed out | Only the import was removed, not the tag | 6.1 |
| `Typewriter` import error on build | Component deleted but Landing.vue still imports it | 6.2 |
| New section's `<h2>` is empty | `title` prop not passed to `LandingSection` (it always renders the tag) | 7.1 |
| Sitemap redirects to `/dashboard` when logged in | Route placed inside the `guest` or `auth` group | 8.2 |
| Sitemap served as `text/html` | `->header('Content-Type', 'application/xml')` missing | 8.2 |
| Google renders the page blank | `/build/` or `/storage/` disallowed in robots.txt | 8.1 |
| `assertSee` fails on a tag that is clearly there | Second argument `false` missing | 10.1 |
| Lighthouse says "Document does not have a meta description" on `/login` | Correct — it is noindex on purpose; run Lighthouse on `/` | — |

## 13. Definition of done

- Raw HTML of `/` contains the title, description, canonical, Open Graph, Twitter and
  `SoftwareApplication` JSON-LD from sections 4.4 and 5.2, served by Blade with no JavaScript.
- Every other page carries `<meta name="robots" content="noindex, nofollow">` by default.
- The H1 is static text: "Digital Signature Software for Small Business". `Typewriter.vue` is gone.
- The "What is a digital signature?" section sits second on the page with the copy from 4.4,
  as one H2 and two H3s.
- The security section is titled "How we verify who signed"; the FAQ has the two new entries.
- `robots.txt` disallows every app path and points at `sitemap.xml`; the sitemap serves valid XML.
- The Starter pricing card and the pricing FAQ match `config/plans.php`; the Team/Business
  mismatch has been reported to the owner.
- `LandingSeoTest` — 3 tests pass; the full suite has no new failures.
- No SSR, no new package, no hardcoded domain outside `robots.txt`, no review markup.
