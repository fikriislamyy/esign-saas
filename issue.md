# Make every page responsive: mobile (< 640px), tablet (640–1024px), desktop (> 1024px)

## Goal

Every page must work at three sizes with no horizontal scrolling, no clipped text, and the
main call-to-action visible without scrolling:

| Size | Width | Layout rules |
|---|---|---|
| **Mobile** | < 640px | One column. Buttons full width. Hamburger menu. Every tappable thing ≥ 44px tall. 16px side gutters. |
| **Tablet** | 640–1024px | Two-column grids where there are several cards. Hamburger menu (the 280px sidebar would eat a third of the screen). 16px gutters. |
| **Desktop** | > 1024px | 3+ column grids. Persistent sidebar. Hover states. 32px gutters. |

In Tailwind terms: **mobile = no prefix, tablet = `sm:`, desktop = `lg:`.** Do not use `md:`
or `xl:` for layout decisions in this task — those are the wrong breakpoints (768 / 1280)
and the reason the current app is inconsistent.

The app is already about 70% responsive. This is an audit-and-fix job, not a rewrite. Most
changes are one class swap per line. Do them in order — Part A is the foundation the rest
depends on.

## Tools you need

Chrome DevTools → toggle device toolbar (Ctrl+Shift+M). Add three custom sizes and check
every page at each: **375 × 812** (mobile), **768 × 1024** (tablet), **1280 × 800** (desktop).
Also drag the width slowly from 375 to 1280 and watch for anything that jumps or overflows.

Run the app:

```bash
docker compose up -d
npm run dev
```

---

## Part A — Foundation (do this first, it fixes most pages at once)

### A1 — Sidebar: persistent only on desktop, hamburger below

Today the sidebar appears at `md` (768px). Move it to `lg`.

`resources/js/Components/Layout/AppSidebar.vue` — line ~41:

| Find | Replace |
|---|---|
| `'hidden md:flex flex-col border-r border-border/50 bg-card'` | `'hidden lg:flex flex-col border-r border-border/50 bg-card'` |

`resources/js/Components/Layout/AppHeader.vue`:

| Find | Replace |
|---|---|
| `<div class="md:hidden">` (wraps `<MobileSidebar />`) | `<div class="lg:hidden">` |
| `<div class="hidden md:block">` (wraps the desktop toggle button) | `<div class="hidden lg:block">` |
| `class="hidden min-w-0 flex-col gap-1 md:flex"` | `class="hidden min-w-0 flex-col gap-1 lg:flex"` |
| `class="hidden h-6 w-px bg-border md:block"` | `class="hidden h-6 w-px bg-border lg:block"` |

`resources/js/Components/MobileSidebar.vue`:

| Find | Replace |
|---|---|
| `class="w-[300px] p-0"` | `class="w-[85vw] max-w-[300px] p-0"` |

### A2 — Page gutters

`resources/js/Layouts/AppLayout.vue`:

| Find | Replace |
|---|---|
| `class="mx-auto w-full max-w-7xl p-6 lg:p-8"` | `class="mx-auto w-full max-w-7xl p-4 lg:p-8"` |

That gives 16px on mobile and tablet, 32px on desktop. The header already uses
`px-4 lg:px-8` — leave it.

### A3 — 44px touch targets on mobile

Do this in the shared UI primitives so every page gets it for free. Tailwind v4 supports
`max-sm:` = "only below 640px".

`resources/js/components/ui/button/index.js` — the long base class string on line ~6. Add
`max-sm:min-h-11 max-sm:min-w-11` to it. Put it right after `inline-flex`:

```
... group/button inline-flex max-sm:min-h-11 max-sm:min-w-11 shrink-0 items-center ...
```

`resources/js/components/ui/input/Input.vue` — line ~29, the class string contains `h-9`.
Change it to `h-9 max-sm:h-11`.

`resources/js/components/ui/select/SelectTrigger.vue` — line ~32, contains
`data-[size=default]:h-9`. Change it to `data-[size=default]:h-9 max-sm:data-[size=default]:h-11`.

Sidebar nav items are already `h-11` — nothing to do.

Check: at 375px every button, input and select is at least 44px tall. At 640px+ they are
back to their normal size.

### A4 — PageHeader: stack, wrap, full-width actions on mobile

`resources/js/Components/page/PageHeader.vue` — replace the whole `<template>`:

```vue
<template>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <!-- Left -->

        <div class="flex min-w-0 items-start gap-4">
            <div
                v-if="icon"
                class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-2xl border bg-muted/60 sm:flex"
            >
                <component :is="icon" class="h-7 w-7" />
            </div>

            <div class="min-w-0 space-y-1">
                <h1 class="break-words text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ title }}
                </h1>

                <p v-if="description" class="break-words text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>

        <!-- Right -->

        <div
            v-if="$slots.actions"
            class="grid gap-2 sm:flex sm:flex-wrap sm:items-center"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
```

What this does: title shrinks and wraps on mobile instead of overflowing; the icon box is
hidden on mobile to save space; the actions container is a **grid** on mobile, which
stretches every button to full width automatically, and becomes a normal flex row at `sm`.

### A5 — Tables must scroll sideways, never clip

`resources/js/Components/data-table/DataTable.vue` — line ~115:

| Find | Replace |
|---|---|
| `class="hidden md:block rounded-xl border overflow-hidden"` | `class="hidden md:block rounded-xl border overflow-x-auto"` |

(`md` is fine here — the card view below 768px is a content decision, not a layout
breakpoint. The point is that if the table is wider than its container it scrolls inside
the card instead of pushing the whole page sideways.)

Do the same in `resources/js/Components/billing/TransactionTable.vue` if it has its own
`overflow-hidden` wrapper around a `<table>`.

**Stop here and check all pages at 375 / 768 / 1280.** Most of the remaining issues below
are about grids and specific components.

---

## Part B — Grids: tablet = 2 columns, desktop = 3+

For every grid below, the rule is `sm:grid-cols-2 lg:grid-cols-N`. One-line class swaps:

| File | Find | Replace |
|---|---|---|
| `Components/dashboard/DashboardStats.vue` | `md:grid-cols-2 xl:grid-cols-4` | `sm:grid-cols-2 lg:grid-cols-4` |
| `Components/members/MemberStats.vue` | `sm:grid-cols-2 xl:grid-cols-4` | `sm:grid-cols-2 lg:grid-cols-4` |
| `Components/billing/BillingStats.vue` | `sm:grid-cols-2 xl:grid-cols-3` | `sm:grid-cols-2 lg:grid-cols-3` |
| `Pages/Landing.vue` (~line 290) | `md:grid-cols-2 lg:grid-cols-4` | `sm:grid-cols-2 lg:grid-cols-4` |
| `Components/landing/Pricing.vue` | `md:grid-cols-3` | `sm:grid-cols-2 lg:grid-cols-3` |
| `Components/landing/Testimonials.vue` | `md:grid-cols-3` | `sm:grid-cols-2 lg:grid-cols-3` |
| `Components/landing/HowItWorks.vue` | `md:grid-cols-3` | `sm:grid-cols-2 lg:grid-cols-3` |
| `Components/landing/SecurityCompliance.vue` | `md:grid-cols-2` | `sm:grid-cols-2` |
| `Pages/Documents/Show.vue` (~line 168) | `grid gap-6 lg:grid-cols-5` | `grid gap-6 sm:grid-cols-2 lg:grid-cols-5` |

Leave these alone — they are already correct: `UseCases.vue`, `Dashboard.vue`
(`lg:grid-cols-3`), both `Prepare.vue` pages (`lg:grid-cols-4`), `DocumentInfo.vue` /
`TemplateInfo.vue` (`sm:grid-cols-2`).

Note: with three cards on a 2-column tablet grid the third card sits alone on the second
row. That's acceptable.

---

## Part C — Page-by-page fixes

### C1 — Landing page (`resources/js/Pages/Landing.vue`)

**Hamburger menu.** The nav links are `hidden … md:flex` and simply vanish on mobile.
Create `resources/js/Components/landing/LandingMobileNav.vue`:

```vue
<script setup>
import { ref } from "vue";
import { Link } from "@inertiajs/vue3";
import { Menu } from "lucide-vue-next";

import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";

const open = ref(false);

const links = [
    { label: "How it works", href: "#how-it-works" },
    { label: "Security", href: "#security" },
    { label: "Pricing", href: "#pricing" },
    { label: "FAQ", href: "#faq" },
];
</script>

<template>
    <Sheet v-model:open="open">
        <SheetTrigger as-child>
            <Button variant="ghost" size="icon" aria-label="Open menu">
                <Menu class="h-5 w-5" />
            </Button>
        </SheetTrigger>

        <SheetContent side="right" class="flex w-[85vw] max-w-[320px] flex-col gap-2 p-6">
            <a
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="flex h-11 items-center rounded-lg px-3 text-base hover:bg-muted"
                @click="open = false"
            >
                {{ link.label }}
            </a>

            <div class="mt-4 grid gap-2 border-t pt-4">
                <Button variant="outline" as-child>
                    <Link href="/login">Sign In</Link>
                </Button>

                <Button as-child>
                    <Link href="/register">Get Started</Link>
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>
```

Then in `Landing.vue`:

1. Import it: `import LandingMobileNav from "@/Components/landing/LandingMobileNav.vue";`
2. Header container (~line 103): `px-6` → `px-4 lg:px-8`.
3. Desktop nav (~line 115): `md:flex` → `lg:flex`.
4. Replace the header's right-hand button block (the `<div class="flex items-center gap-2">`
   with Sign In / Get Started) with:

```vue
<div class="flex items-center gap-2">
    <Link href="/login" class="hidden sm:block">
        <Button variant="ghost"> Sign In </Button>
    </Link>

    <Link href="/register">
        <Button> Get Started </Button>
    </Link>

    <div class="lg:hidden">
        <LandingMobileNav />
    </div>
</div>
```

**Hero above the fold.** ~line 136 onward:

| Find | Replace |
|---|---|
| `class="mx-auto max-w-7xl px-6 py-24"` (hero section) | `class="mx-auto max-w-7xl px-4 py-12 sm:py-20 lg:px-8 lg:py-24"` |
| `class="grid items-center gap-16 lg:grid-cols-2"` | `class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16"` |
| `class="text-5xl font-extrabold tracking-tight lg:text-6xl"` | `class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl"` |
| `class="mt-8 flex flex-wrap gap-4"` (the CTA row) | `class="mt-8 grid gap-3 sm:flex sm:flex-wrap sm:gap-4"` |

Check: at 375 × 812 the "Get Started" hero button is visible without scrolling.

**Every other section** in `Landing.vue` and in `Components/landing/*.vue` that uses
`px-6`: change to `px-4 lg:px-8`. (`grep -n "px-6" resources/js/Pages/Landing.vue resources/js/Components/landing/*.vue`
lists them.) Footer (~line 365): `md:flex-row` → `sm:flex-row`.

### C2 — Documents / Templates Show pages

`resources/js/Components/documents/DocumentActions.vue`:

| Find | Replace |
|---|---|
| `<div class="flex flex-col items-end gap-2">` | `<div class="flex flex-col gap-2 sm:items-end">` |
| `<div class="flex flex-wrap justify-end gap-2">` | `<div class="grid gap-2 sm:flex sm:flex-wrap sm:justify-end">` |
| `class="max-w-sm text-right text-sm text-destructive"` | `class="max-w-sm text-sm text-destructive sm:text-right"` |

`resources/js/Pages/Templates/Show.vue` — the actions slot:

| Find | Replace |
|---|---|
| `<div class="flex flex-wrap justify-end gap-2">` | `<div class="grid gap-2 sm:flex sm:flex-wrap sm:justify-end">` |

Both pages build the title as `name + ' - Document Details'`. Long file names now wrap
thanks to A4; nothing more to do.

### C3 — Documents / Templates Index

`resources/js/Components/documents/UploadCard.vue` — the submit button has
`class="min-w-[180px]"`. Change to `class="w-full sm:w-auto sm:min-w-[180px]"`. Check the
row it sits in stacks on mobile (`flex-col sm:flex-row`); if it doesn't, make it.

### C4 — Prepare pages (Documents and Templates)

The two-button header (Back / Finish) is handled by A4. Two toolbar checks:

`resources/js/Components/documents/prepare/PrepareToolbar.vue` already wraps
(`flex-wrap`). Confirm at 375px that the page navigator and zoom controls sit on two rows
and nothing overflows. If the zoom "120%" box overflows, shrink `gap-4` → `gap-2 sm:gap-4`.

`resources/js/Components/documents/prepare/PdfCanvas.vue` — `h-[75vh]` is fine. Confirm the
canvas scrolls inside its box on mobile (it's `overflow-auto`), and the page itself doesn't
scroll sideways.

The sticky sidebar card (`sticky top-6` in `PrepareSidebar.vue` / `TemplatePrepareSidebar.vue`)
stacks above the canvas below `lg`. Change `sticky top-6` → `lg:sticky lg:top-6` so it
doesn't stick while stacked.

### C5 — Members

`resources/js/Components/members/InviteMemberCard.vue` uses
`grid gap-4 lg:grid-cols-[1fr_220px_auto]`. Below `lg` it stacks: email, role, button. That's
correct. Just make sure the button has `w-full lg:w-auto`.

`resources/js/Pages/Members/MemberRoleAction.vue` and `MemberRoleDialog.vue`: open the
dialog at 375px — the dialog footer buttons must stack. If they're in a `flex` row, change
to `grid gap-2 sm:flex sm:justify-end`.

### C6 — Billing

`resources/js/Components/billing/TopUpDialog.vue` — open it at 375px. The amount tabs
(`grid w-full grid-cols-2`) are fine. Check the confirm button is full width on mobile
(`w-full sm:w-auto`).

`resources/js/Components/billing/TransactionTable.vue` — has its own mobile card layout with
`grid grid-cols-2 gap-3 text-xs`. Confirm nothing overflows at 375px with a long
description; add `break-words min-w-0` to the description cell if it does.

### C7 — Dashboard

`resources/js/Components/dashboard/DashboardFilter.vue` — `<SelectTrigger class="w-[180px]">`
→ `class="w-full sm:w-[180px]"`, and make sure its parent row is `flex-col sm:flex-row`.

`resources/js/Components/dashboard/ActivityChart.vue` — the chart is inside
`grid min-w-0 grid-cols-1 gap-5 sm:grid-cols-3`. Confirm the chart canvas itself has
`min-w-0 w-full` and doesn't force horizontal scroll at 375px.

### C8 — Settings / Profile

`resources/js/Pages/Profile/Edit.vue`, the three `Partials/*.vue`, and
`Pages/Settings/Organization.vue`: every form's save button should be `w-full sm:w-auto`.
Check each form at 375px: inputs full width, no side scroll, button full width.

### C9 — Auth pages

`resources/js/Layouts/AuthLayout.vue` already hides the left panel below `lg`. Check the
right panel's padding is `p-4 sm:p-8` (not a fixed `p-14`), and that the form card is
`w-full max-w-md`. Buttons on Login / Register / ForgotPassword / ResetPassword /
VerifyEmail / ConfirmPassword are already `w-full` — confirm, don't assume.

### C10 — Signing pages

`resources/js/Pages/Signing/Show.vue` — the "Finish" button block at ~line 513 is already
`flex-col … sm:flex-row`. Make the button `w-full sm:w-auto`.

`resources/js/Components/documents/signing/SigningToolbar.vue` — the `min-w-[90px]` /
`min-w-[120px]` boxes: confirm the toolbar wraps at 375px. If not, add `flex-wrap` to the
toolbar row.

### C11 — Errors / Invitations

`Pages/Errors/403.vue` and `Pages/Invitations/Accept.vue`: the decorative
`h-[500px] w-[500px]` blur circle is inside `overflow-hidden`; leave it. Check the CTA button
is `w-full sm:w-auto`.

---

## Part D — Verification checklist

Run through **every** page in this list at 375, 768 and 1280. For each: no horizontal
scrollbar on `<body>`, no clipped text, primary CTA visible without scrolling, every button
≥ 44px tall at 375.

```
/                       landing
/login  /register  /forgot-password  /verify-email
/dashboard
/documents  /documents/{id}  /documents/{id}/prepare
/templates  /templates/{id}  /templates/{id}/prepare
/members
/billing
/profile  /settings/organization
/sign/{token}  (get a token from document_signers table)
```

At **768 (tablet)** specifically: hamburger shows, no sidebar; stat cards are 2-up;
Documents Show has info + preparation side by side.

At **1280 (desktop)** specifically: sidebar visible and collapsible; stat cards 3–4 up;
hover a table row and a sidebar item — hover background appears.

Finally:

```bash
npm run build
```

---

## Gotchas

- **Don't use `md:` or `xl:` for anything you touch.** Mobile / `sm:` / `lg:` only. If an
  existing `md:` decides content (like the table vs. cards switch in `DataTable.vue`) leave
  it; if it decides layout, move it.
- **Full-width buttons on mobile = put them in a `grid`,** not `w-full` on every button.
  `grid gap-2 sm:flex` stretches children automatically and turns into a row at 640.
- **`max-sm:` is "mobile only".** It's how A3 makes touch targets bigger without touching
  tablet and desktop.
- Never fix overflow with `overflow-hidden` on a page or card — that hides the bug. Find the
  element with a fixed width or missing `min-w-0` and fix that. Flex children need `min-w-0`
  to be allowed to shrink.
- Text that can be long (document names, emails) needs `break-words` and a parent with
  `min-w-0`.
- Don't restyle anything — colours, spacing scale, radii all stay. This task is only about
  what happens at different widths.

## Definition of done

- [ ] Part A applied: sidebar at `lg`, gutters `p-4 lg:p-8`, 44px touch targets below 640,
      PageHeader stacks with full-width actions, tables scroll inside their card
- [ ] Part B applied: every listed grid is `sm:grid-cols-2 lg:grid-cols-N`
- [ ] Landing has a working hamburger menu below `lg` with all four links + Sign In /
      Get Started
- [ ] Every page in Part D passes at 375 / 768 / 1280 with no horizontal scroll and the
      CTA above the fold
- [ ] No new `md:` / `xl:` layout classes introduced
- [ ] `npm run build` passes
- [ ] One commit on branch `feature/responsive-layouts`:

```
feat: make all pages responsive across mobile, tablet and desktop

Standardise on sm (640) / lg (1024) breakpoints: sidebar becomes a
hamburger below lg, grids go 1 / 2 / 3+ columns, page actions stack
full-width on mobile, and all controls meet a 44px touch target below sm.
```
