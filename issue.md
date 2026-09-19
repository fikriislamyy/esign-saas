# Interface sweep: accessibility, layout, writing, typography, colors

## 1. What we are building

Five sequential review-and-fix passes across the existing UI, then one consolidated review that
checks the whole thing holds together. No new screens, no new features, no redesign.

Each pass is owned by one skill in `.agents/skills/`. **Read this document first, not the skills.**
The skills are cross-referenced and defer to each other constantly; two of them defer to each other
in a loop that will leave you fixing nothing (Trap 1). This document resolves those handoffs and
tells you which pass owns what.

The passes, in the order you must do them:

| # | Pass | Skill | Owns |
| --- | --- | --- | --- |
| 1 | Accessibility | `better-accessibility` | Names, focus, keyboard, semantics, reduced motion |
| 2 | Layout | `better-layout` | Grouping, alignment, reading order, adaptivity |
| 3 | Writing | `better-writing` | Labels, errors, empty states, capitalization |
| 4 | Typography | `better-typography` | Scale, line-height, wrapping, tabular numbers |
| 5 | Colors | `better-colors` | Ramps, tokens, and the contrast fixes pass 1 found |
| 6 | Consolidated review | `better-interface` | Verifying 1–5 together. **Not a sixth fix pass.** |

A seventh domain, `better-ui` (surfaces, motion, icons), was completed separately and shipped in
PR #36. Do not redo it.

**Definition of done:**

- Every interactive control has an accessible name.
- Every animation is gated behind `prefers-reduced-motion`.
- Every control reachable by pointer is reachable by keyboard.
- One capitalization policy per element type, applied consistently.
- Every changing number uses tabular figures.
- Every contrast pair pass 1 flagged is measured and either fixed or documented as passing.
- `npm run build` passes.
- `better-interface` returns `Approve` with no `HIGH` findings outstanding.

**Effort:** three to four days. Pass 1 is the largest and the only one containing blockers.

---

## 2. A note on the order

The order above is not the order these skills were requested in. Two things changed.

**`better-interface` moved to the end, and is not a fix pass.** Its own description is explicit:
*"Orchestration is all it owns... Never duplicate or override their rules here."* It routes the
interface to the other skills, collects their findings and consolidates one ranked verdict. Running
it in the middle would just re-run passes you have not done yet. It belongs last, as verification.

**The rest follow the order `better-interface` prescribes**, which is not the order they were
listed in either. From its own file: *"Review in this order so foundational failures are not hidden
by polish."* Accessibility first, then layout, writing, typography, colors.

If you do colors before accessibility, you will change token values and then have to re-measure
every contrast pair you already checked. If you do writing before accessibility, you will set
accessible names and then change the visible labels they have to match. The order exists to stop
you doing work twice.

**Do not reorder the passes.** If you think one is blocked, say so and stop rather than skipping
ahead.

---

## 3. Before you write any code

### The stack

| | |
| --- | --- |
| Framework | Vue 3 (`<script setup>`) + Inertia.js |
| Styling | Tailwind v4, CSS-first config in `resources/css/app.css` |
| Components | shadcn-vue primitives in `resources/js/components/ui/` |
| Icons | `lucide-vue-next` (single library as of PR #36) |
| Motion library | **None.** Do not add one. |
| Localization | **None.** No `lang/` directory, no i18n package, no RTL. See decision 4.5. |

### Where things live

| Thing | Path |
| --- | --- |
| Design tokens and theme blocks | `resources/css/app.css` |
| **The design system you must not contradict** | `DESIGN.md` |
| App components (capital C) | `resources/js/Components/` |
| shadcn-vue primitives (lowercase c) | `resources/js/components/ui/` |
| Pages | `resources/js/Pages/` |
| Layouts | `resources/js/Layouts/` |
| The six skills | `.agents/skills/better-*/` |

Each skill has supporting files beside its `SKILL.md` — `contrast.md`, `focus-and-keyboard.md`,
`spacing-and-sizing.md` and so on. The `SKILL.md` states each rule; the supporting file holds the
recipe. **Read the supporting file before implementing a rule, not after.**

### How to verify

```bash
npm run build          # must pass after every pass
npm run dev            # then open http://localhost:8000
```

Every skill's **Verification** section distinguishes checks you can run from source and checks that
need a browser. Run both. Anything you cannot run is reported as **Not verified** — never silently
assumed to pass. That phrasing matters; the skills all require it.

---

## 4. Decisions already made (do not re-litigate)

### 4.1 This document is the authorization to change colors

Both color-touching skills refuse to change colors on their own:

- `better-accessibility`: *"use `better-colors` to measure the rendered pair. When it fails, report
  the pair and the requirement it misses, and **leave the colors alone unless asked**."*
- `better-colors`: *"When a pair fails, report the pair, its measured value and the threshold it
  misses, **then leave the colors alone. They are a design decision. Change them only when
  asked**."*

Read literally, you would run both passes and fix nothing. **You are being asked.** This document
is that request. Pass 5 has authority to change token values to fix a measured, failing pair.

Two constraints on how:
- **Fix contrast by changing lightness, not hue.** `better-colors` is explicit: *"Contrast fixed by
  changing hue"* is in its mistakes table. Lightness is the channel contrast responds to.
- **Re-measure after every change**, and update the ratio comment in `app.css` beside the token.

### 4.2 `DESIGN.md` still wins, exactly as in PR #36

The same three carve-outs from the previous pass stand, and nothing in these five skills overrides
them:

- **Hairline borders, not shadows,** for surface separation.
- **Four radii only** — 12px cards, 6px buttons/inputs, 4px badges, 9999px pills.
- **Acid lime `#e4f222` is the only chromatic action color**, one primary action per view.

`better-colors` agrees with the last one independently (*"Fill exactly one action per view"*), so
that is reinforcement rather than conflict. If a skill's guidance collides with `DESIGN.md`, the
design system wins and you note it in the pass report.

### 4.3 Do not migrate color notation

`app.css` uses space-separated HSL (`--card-foreground: 223 9% 15%`) with the hex in a comment.
That is the shadcn convention and it is used consistently — 45 `hsl(`, only 3 `rgb(`.

`better-colors` is explicit: *"A consistent hex system beats hex with `oklch()` scattered through
it"* and *"An isolated `oklch()` value dropped into a hex codebase → keep the established notation
unless a migration is in scope."* **A migration is not in scope.** Keep writing HSL.

### 4.4 Things that are already correct — leave them alone

Verified during the audit for this document. Do not "fix" these:

| Already correct | Where | Why it is right |
| --- | --- | --- |
| `-webkit-font-smoothing: antialiased` on the root | `app.css:200`, `app.blade.php:19` | Applied once on the root, never per component, exactly as `better-typography` requires |
| Inputs at `text-base md:text-sm` | `components/ui/input/Input.vue:29` | The recommended fix for iOS Safari's zoom-on-focus. Changing it reintroduces the bug |
| One theme switching mechanism | `app.css:155` uses `.dark`, with no `prefers-color-scheme` block | `better-colors` warns against mixing the two. This does not mix them |
| Semantic token tier | `app.css` `--background`, `--card-foreground`, `--accent-ink` | Named by role, not by hue or first use, which is what `better-colors` asks for |
| Documented contrast ratios | e.g. `--primary-foreground` notes `16.2:1 on lime` | Better than most codebases. Extend the habit; do not remove it |

### 4.5 Skip the logical-properties conversion

`better-layout` asks for `padding-inline-start` over `padding-left`. The codebase has **130
physical properties and zero logical ones**.

Convert none of them. The rule exists for localizable layouts, and this project has no `lang/`
directory, no i18n package, no RTL support and no plan for one. Converting 130 class names buys
nothing today and risks visual regressions across every screen.

**Note it as follow-up work** in your pass 2 report: *"130 physical properties; convert as part of
i18n work, not before."* If localization is ever scheduled, that conversion becomes a prerequisite
and should be its own task.

### 4.6 Report, do not pad

Every skill says the same thing in its own words: *"A short report from a real inspection beats a
long one padded to look thorough."* `better-interface` caps at 15 findings and says *"Never pad to
reach the cap; a short review or no findings is a valid result."*

Do not invent findings to fill a table. Do not report a preference as a defect — `better-interface`
draws the line as *"evidence, not taste"*, and a density or voice you merely disagree with is not a
finding.

---

## 5. The traps

### Trap 1 — the contrast deadlock

Covered in decision 4.1. Two skills each defer contrast to the other. Resolution: pass 1 identifies
*which ratio applies*, pass 5 *measures and fixes*. Neither does both. See the handoff artifact in
Trap 2.

### Trap 2 — pass 1 produces an artifact pass 5 consumes

When pass 1 finds a suspect contrast pair, it does **not** fix it. It appends a row to a file you
create at `docs/contrast-debt.md`:

| Foreground token | Background actually rendered on | Required ratio | Where |
| --- | --- | --- | --- |
| `--muted-foreground` | `--card` | 4.5:1 (body text) | `Components/billing/WalletCard.vue:69` |

"Background actually rendered on" means the surface behind the text, not the page background —
including opacity and anything beneath it. Getting this wrong is the most common way a contrast
report ends up measuring a pair that never appears on screen.

Pass 5 works through that file, measures each pair, fixes what fails and deletes the file when the
table is empty. **If you skip the file, pass 5 has nothing to work from and the deadlock wins.**

### Trap 3 — pass 3 can silently break pass 1

`better-accessibility` requires that *"visible label text must appear in the accessible name."* If
pass 1 sets `aria-label="Delete document"` and pass 3 rewrites the visible label to "Remove", the
two now disagree and a screen-reader user hears something nobody can see.

**At the end of pass 3, re-check every label you changed against its accessible name.** This is the
only backwards dependency in the sequence and it is why writing comes after accessibility rather
than before.

### Trap 4 — remove ARIA rather than add it

`better-accessibility`: *"The first rule of ARIA: don't use ARIA when a native element exists"* and
*"No ARIA is better than bad ARIA."*

`better-interface` ranks fixes cheapest-first: **delete** > **use the platform** > **reuse a
project token** > **correct the value** > **add**. It then says: *"A fix written at step 5 where
step 1 was available is its own finding."*

Translated: a `<div @click>` with `role="button"`, `tabindex="0"` and a keydown handler bolted on is
the *wrong* fix. The right fix is `<button>`. You will hit this in Trap 5.

### Trap 5 — `Dropdown.vue` needs rewriting, not patching

`resources/js/Components/Dropdown.vue:49` is `<div @click="open = !open">`. It is a dropdown trigger
that cannot be reached or operated by keyboard, has no role and announces no state.

This fires the escalation trigger *"A control or path reachable by pointer but not by keyboard"* and
is `HIGH` on sight. Per Trap 4, fix it with a real `<button>` carrying `aria-expanded`, not by
adding ARIA to the div. The overlay `<div>` on line 54 is a click-catcher with no semantics; it
needs `aria-hidden="true"` and must not be focusable.

Check whether the shadcn-vue `DropdownMenu` primitive already in `components/ui/dropdown-menu/`
can replace this component outright. Deleting it beats fixing it.

### Trap 6 — reduced motion is not one media query at the bottom of a file

There are currently **zero** `prefers-reduced-motion` guards in the codebase, and motion now exists
in three separate places: `FadeIn.vue` and `SlideIn.vue` (used on nearly every page), the theme
toggle icon cross-fade added in PR #36, and the hover and press transitions on buttons, cards and
list rows.

`better-accessibility` asks you to *"wrap motion in `@media (prefers-reduced-motion: no-preference)`
so it is opt-in"* and, under reduced motion, *"replace slides and scales with opacity crossfades"* —
not to remove all feedback. A control that stops responding entirely is a worse outcome than one
that animates.

`FadeIn.vue` is the important one: it starts elements at `opacity: 0` and reveals them on scroll. If
you disable its transition without also forcing the visible state, **content stays invisible
forever.** Test that specifically.

### Trap 7 — 22 of 23 icon-only buttons have no accessible name

`grep -rn 'size="icon' resources/js --include="*.vue"` returns 23 results; 22 have no `aria-label`.
Each is a button whose entire content is an SVG, so a screen reader announces "button" and nothing
else.

This is the first escalation trigger in `better-interface`'s list and is `HIGH`. It is also one root
cause, not 22 findings — `better-interface` says *"One root cause is one finding. List every
confirmed location in the same row."* Report it once with 22 locations.

Name them for the **action**, not the glyph: `aria-label="Search"`, never
`aria-label="magnifying glass"`.

### Trap 8 — eleven pages render two `<h1>` elements

This one is confirmed, not suspected. Trace it:

- `Layouts/AppLayout.vue:50` renders `<AppHeader>`.
- `Components/Layout/AppHeader.vue:94` contains `<h1 class="truncate text-xl font-semibold">`.
- Eleven pages *also* render `<PageHeader>`, which carries its own
  `<h1 class="break-words text-2xl font-bold sm:text-3xl">` at
  `Components/page/PageHeader.vue:33`.

So `Pages/Documents/Index.vue` — and ten others — produce **two `<h1>` elements on one page**, and
the second is visually *larger* than the first. `better-accessibility` is explicit: *"Give the page
one `<h1>`."*

```bash
grep -rl "PageHeader" resources/js/Pages/   # 11 files, each one a double-h1
```

**Decide once, centrally, then apply to all eleven.** Two defensible fixes:

1. `PageHeader`'s heading becomes `<h2>`, keeping `AppHeader`'s as the page `<h1>`. Cheapest, and
   correct if the layout title is the real page title.
2. `AppHeader`'s heading stops being an `<h1>` (a `<div>` or `<p>` is fine — it is layout chrome
   repeating the breadcrumb title), leaving `PageHeader` as the page `<h1>`. Better if the layout
   title is decorative.

Check whether both actually render visible text on the same screen. If they do, you have a visual
duplication to raise as well — but that is a design question, so report it rather than fixing it
unilaterally.

Fix the level by changing the **element**, never by restyling. `better-typography` says the same
thing from its side: *"Heading element picked for its default size → choose semantics first, then
set the size in CSS."*

Once the double-`h1` is resolved, walk the rest of the outline. The codebase has 4 `<h2>` against
11 `<h3>`, so some pages are also jumping a level on the way down.

---

## 6. Pass 1 — Accessibility

The largest pass and the only one containing blockers. Load `.agents/skills/better-accessibility/`
and read `focus-and-keyboard.md`, `semantics-and-aria.md` and `motion-and-zoom.md` before starting.

Work through these in order. The first three are `HIGH`.

### 6.1 Accessible names on icon-only buttons (HIGH)

```bash
grep -rn 'size="icon' resources/js --include="*.vue" | grep -vi "aria-label"
```

22 results. Add `aria-label` describing the action to each. Where the button already has a tooltip
or adjacent visible text, make sure the accessible name contains that text.

### 6.2 Keyboard access for `Dropdown.vue` (HIGH)

See Trap 5. Prefer replacing the component with the existing shadcn-vue `DropdownMenu`.

### 6.3 Reduced motion (HIGH)

See Trap 6. Three surfaces: `Components/animations/FadeIn.vue`, `Components/animations/SlideIn.vue`,
and the shared transitions in `components/ui/button/index.js` plus the card and row hovers.

Verify by enabling "Reduce motion" in your OS accessibility settings, reloading, and confirming
every page still shows all of its content.

### 6.4 `:focus-visible`, not bare `:focus` (MEDIUM)

```bash
grep -rnE "[\" ]focus:[a-z-]" resources/js --include="*.vue" --include="*.js"
```

10 results, against 33 already using `focus-visible:`. Convert the 10 so mouse users stop seeing
focus rings on click. Keep `focus:` only where the ring genuinely should show for pointer users too
— which is rare.

### 6.5 Heading outline — the double `<h1>` (MEDIUM, 11 pages)

See Trap 8. Make the central decision first, apply it to all eleven pages, then walk the rest of
the outline in `resources/js/Pages/` and confirm it descends without gaps.

### 6.6 Skip link (MEDIUM)

There is no skip link anywhere, and `AppLayout` renders a sidebar and a header before its `<main>`.
Keyboard users tab through the whole nav on every page.

The target already exists at `Layouts/AppLayout.vue:58`. Add a "Skip to content" link as the first
focusable element in that layout, visually hidden until focused, pointing at that `<main>`.

Give it an `id` to target, and confirm `AppLayout` renders exactly one `<main>`. Note that
`Pages/Signing/Show.vue:481`, `Pages/Signing/Completed.vue` and `Pages/Errors/403.vue` each render
their own `<main>` — those are standalone pages outside `AppLayout`, so they do not conflict, but
check they are not nested inside it before you add anything.

### 6.7 Build the contrast debt file

See Trap 2. Create `docs/contrast-debt.md` and record every suspect pair. Do not fix any of them.

Start with `--muted-foreground` in the dark theme (`app.css:174`): every other token in that file
carries a measured ratio comment and this one does not, which means nobody has checked it.

### Checkpoint

```bash
npm run build
grep -rn 'size="icon' resources/js --include="*.vue" | grep -vci "aria-label"   # expect 0
grep -rc "prefers-reduced-motion" resources/css/app.css                          # expect >0
```

Then in a browser: tab through one full flow — log in, open a document, send it — without touching
the mouse. Every stop must show a visible focus ring and every control must be reachable.

---

## 7. Pass 2 — Layout

Load `.agents/skills/better-layout/` and read `grouping-and-alignment.md` and
`spacing-and-adaptivity.md`.

Per decision 4.5, **skip the logical-properties conversion entirely.** What remains:

### 7.1 Grouping by space

The rule is exact: *"The gap between groups must be at least 2× the gap within one (`8px`
intra-group to `16px`+ inter-group)."* Audit the dense screens first — Dashboard, Billing, Document
Show — and check that related fields sit closer together than unrelated ones.

Where a separator line is doing work that space could do, delete the line. `better-interface` ranks
deletion as the cheapest fix; `better-layout` says separators come last, *"only where space alone
can't carry the structure."*

### 7.2 Controls distinct from content

*"A control styled like the static text beside it does not read as a control."* Check ghost buttons
and text-only links sitting inline with body copy.

### 7.3 Fixed sizes on text containers

```bash
grep -rnE "\b(w|h)-\[[0-9]+px\]" resources/js --include="*.vue"
```

28 results. For each, decide whether it wraps text. `better-layout`: *"Put no fixed width or height
on a text container, and let rows wrap."* Convert to `min-height` or `max-width` where text is
involved; leave genuinely fixed geometry (icons, avatars, canvas elements) alone.

### 7.4 Progressive disclosure cues

*"Content hidden with zero cue may as well not exist."* Check anything behind a collapse, an
accordion, or a horizontal scroll area — particularly the document list and the signing toolbar.
Either let the next item peek `16–32px` past the scroll edge or show a disclosure control.

### 7.5 Narrow-width and zoom

`better-layout` verification requires every supported width plus 200% zoom. The escalation trigger
is *"Content or a control clipped, overlapped, or unreachable at 320px width or 200% zoom"* — treat
anything you find there as `HIGH`.

Test at 320px specifically. It is narrower than an iPhone SE and it is where fixed widths break.

### Checkpoint

`npm run build`, then walk the three densest screens at 320px, at 1280px and at 200% zoom. Nothing
clipped, nothing overlapping, no horizontal scrollbar.

---

## 8. Pass 3 — Writing

Load `.agents/skills/better-writing/`. It has no supporting files — the `SKILL.md` is complete.

Source alone is enough for this pass; no browser check is required.

### 8.1 Pick one capitalization policy (MEDIUM, systemic)

The codebase mixes both. Title Case: `Use Template`, `Sign In`, `Upgrade Plan`, `Confirm Password`,
`File Size`, `Uploaded At`. Sentence case: `Card details`, `Available balance`, `Your role`,
`Signing progress`, `Most popular`.

`better-writing`: *"Pick title case or sentence case per element type, then apply it to every
instance of that type. Sentence case is the safer default... 'Save Changes' beside 'Discard changes'
reads as sloppiness."*

**Use sentence case**, per the skill's own default. Apply per element type — buttons, table headers,
labels, headings — and be consistent within each type. Change the copy in the template, not with
`text-transform`; the skill wants text stored in natural case.

### 8.2 "Click here" (HIGH — it fails two rules at once)

`resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue:70`:

```
Click here to re-send the verification email.
```

This breaks *"Links describe their destination"* (link text must make sense out of context, because
screen-reader users navigate by a list of links) and the device-verb rule at the same time. The
skill names this exact string as the canonical failure.

Replace with a link whose text is the action: `Resend verification email`.

### 8.3 Device verbs

`resources/js/Components/documents/signing/SigningField.vue:66` reads `Click to Sign`. The signing
flow is used on touch devices. *"Match the input device: 'tap' on touch, 'click' with a pointer,
'select' when both are possible."*

Both are possible here, so neither "click" nor "tap" is right. Use `Add signature` — verb-first, no
device verb, and it survives 8.1's capitalization rule.

### 8.4 Verb-first buttons and consequence-repeating confirmations

Audit every `<Button>` label. *"A button label starts with a verb naming the action."* And for
destructive dialogs: *"A confirmation button repeats the consequence, so the dialog is answerable
without reading the body. 'Delete this project?' offers `Delete project` and `Cancel`."*

Check the document delete and the plan downgrade flows specifically. A bare `Yes` / `Confirm` on
either is a finding.

### 8.5 Errors state the fix

*"An error is an instruction, and it belongs beside the field that failed."* No "oops", no blame,
no exclamation marks. Audit the validation messages in the auth forms, the upload flow and the
payment dialog.

The payment errors are worth particular attention — a failed card charge must say what to do next,
not just that it failed.

### 8.6 Empty states point forward

*"An empty state says what this place is, how to fill it and offers one clear next action."* Check
the documents list, the templates list, the members list and the transactions table. A bare "No
results." is a finding.

### 8.7 Re-check accessible names

See Trap 3. Every visible label you changed in this pass must still appear in the accessible name
pass 1 set.

### Checkpoint

No build needed for the copy itself, but run `npm run build` anyway to catch template syntax
errors. Then re-run the pass 1 name check:

```bash
grep -rn 'size="icon' resources/js --include="*.vue" | grep -vci "aria-label"   # still 0
```

---

## 9. Pass 4 — Typography

Load `.agents/skills/better-typography/` and read `css-cheat-sheet.md` (it maps every declaration
to its Tailwind equivalent) and `spacing-and-sizing.md`.

Per decision 4.4, **do not touch `Input.vue`'s `text-base md:text-sm` or the root `antialiased`.**

### 9.1 Tabular numbers on changing values

*"Digits have different widths by default, so timers, counters and prices shift the layout as they
update."*

Already correct in `Components/billing/BillingStats.vue`, `Components/billing/TransactionTable.vue`,
`Components/Layout/AppHeader.vue` and `components/ui/chart/ChartTooltipContent.vue`.

**Missing from `Components/billing/WalletCard.vue`** — which renders the wallet balance, the most
prominent changing number in the product. Add `tabular-nums`. Then audit the dashboard stat cards
and the plan usage meters for the same gap.

Use `font-variant-numeric: tabular-nums` (Tailwind: `tabular-nums`), not
`font-feature-settings: "tnum" 1` — *"When a CSS property exists, use it."*

### 9.2 Line-height by role

*"Headings tighter, around `1.1`. Body copy `1.5` to `1.6`."* And the constraint people miss:
*"Anything that wraps to three or more lines needs at least `1.4`, even in a height-constrained
row."*

Check card descriptions and table cells for `leading-none` or `leading-tight` on text that wraps.

### 9.3 Wrapping

Four declarations, four jobs — apply the right one:

- `text-wrap: balance` on headings (Tailwind `text-balance`)
- `text-wrap: pretty` on descriptions (Tailwind `text-pretty`)
- `overflow-wrap: break-word` where a long ID, filename or email could escape its container
- `white-space: nowrap` on badges and short labels where a break looks broken

Document filenames and signer emails are the real risk here — both are user-supplied and
unbounded.

### 9.4 Truncation must stay recoverable

18 files use `truncate` or `line-clamp`. *"Truncation hides content. When the missing text matters,
keep the full value reachable in a tooltip or an expanded view."*

The escalation trigger is *"Truncated content with no way to reach the full value"* — `HIGH`. Check
document titles and signer names specifically; a truncated filename with no tooltip means the user
cannot tell two documents apart.

### 9.5 Heading sizes descend with level

Pairs with 6.5 above. Once the semantic levels are correct, confirm the visual sizes descend with
them, so *"a visually subordinate heading never overpowers its parent."*

Trap 8 is the live example: `PageHeader`'s heading renders at `text-2xl sm:text-3xl` while
`AppHeader`'s renders at `text-xl`, so today the subordinate heading is the bigger one.

### 9.6 Bold weights violate `DESIGN.md` (MEDIUM, systemic — 37 locations)

```bash
grep -rno "font-bold\|font-extrabold\|font-black" resources/js --include="*.vue"
```

37 results. `DESIGN.md` is unambiguous:

> **Weights:** 300, 400, 510, 590

> Do not use bold weights (700+) — Linear's type scale caps at weight 590, the system deliberately
> avoids heavy display weights

Tailwind's `font-bold` is 700, `font-extrabold` is 800, `font-black` is 900. All 37 are outside the
system. This is a `DESIGN.md` violation, so it is not a taste call — per decision 4.2, the design
system wins.

**Do not naively find-and-replace `font-bold` → `font-semibold`.** Tailwind's default ladder does
not contain the project's weights: `font-medium` is 500 and `font-semibold` is 600, while
`DESIGN.md` asks for 510 and 590. Both Tailwind steps are wrong, and 600 is still above the 590
cap.

The correct fix is to add the four design-system weights as custom utilities in the `@theme` block
of `app.css`, then map each of the 37 sites onto the intended step. That is a small token addition
followed by a mechanical pass — steps 5 then 4 on `better-interface`'s ladder, which is right here
because no cheaper option exists.

If adding weight utilities looks like it is expanding beyond this pass, stop and raise it. A
half-migrated weight scale is worse than the current state.

### Checkpoint

`npm run build`, then resize the viewport slowly through the full range on the documents list and a
document detail page. You are looking for wrapping, widows and truncation at real content lengths —
which is why this check needs a browser and real data, not a source read.

---

## 10. Pass 5 — Colors

Load `.agents/skills/better-colors/` and read `contrast.md` and `token-naming.md`.

This is the **lightest** pass. The audit for this document found the color system in good shape:
semantic tokens named by role, one switching mechanism, consistent notation, and contrast ratios
already documented in comments. Per decision 4.4, most of it is already right.

Per decisions 4.1 and 4.3: you **may** change values to fix measured contrast failures; you **may
not** migrate notation.

### 10.1 Work the contrast debt file

Open `docs/contrast-debt.md` from pass 1. For each row:

1. Measure the pair as it actually renders — including opacity and anything behind it.
2. Compare against the required ratio.
3. If it passes, record the measured value as a comment beside the token in `app.css` and delete
   the row.
4. If it fails, **adjust lightness, not hue** (decision 4.1), re-measure, and update the comment.

*"Never report a contrast value you did not measure, and never estimate a color you could
compute."* Use a real contrast tool or DevTools, not judgment.

Delete `docs/contrast-debt.md` when the table is empty.

### 10.2 Raw hex in components

```bash
grep -rnoE "#[0-9a-fA-F]{6}" resources/js --include="*.vue"
```

8 results in three files:

- `Pages/Plan/Qr.vue:65` — `#08090a` and `#ffffff`. Both exist as tokens (Void and Paper). Replace
  with the tokens. **Check first whether the QR renderer needs literal hex**; some QR libraries
  cannot read CSS variables, and if so this is a legitimate exception to document, not a defect.
- `Components/dashboard/ActivityChart.vue` and `Components/dashboard/StatusChart.vue` — chart
  series colors (`#3b82f6`, `#6366f1`, `#02b8cc`, `#27a644`). See 10.3.

Also note `#3b82f6` and `#3B82F6` appear in the same file in different cases. Whatever you decide,
make the casing consistent.

### 10.3 Chart colors are a genuine judgment call

The chart palette is four chromatic hues that exist nowhere in `DESIGN.md`, which states *"Do not
introduce additional chromatic accent colors as actions."*

They are not actions — they are categorical data encodings, and charts legitimately need hues that
are distinguishable from each other. `better-colors` allows this: *"A second accent hue earns its
place only when two things must be distinguishable at a glance."*

**Do not delete them.** Do promote them from raw hex into named tokens in `app.css`
(`--chart-1`…`--chart-4`) so they are themeable and appear once rather than scattered. Verify each
is distinguishable from the acid-lime accent so a data series never reads as an action.

This project has a `dataviz` skill available. If the chart work grows beyond renaming these four
values, stop and raise it as separate work.

### 10.4 Token roles

*"Never borrow a token because its value is right today."* Check for a separator token used as a
text color, or a `--muted` surface token used as text. If a role has no token, add one rather than
borrowing.

### Checkpoint

```bash
npm run build
ls docs/contrast-debt.md 2>/dev/null && echo "STILL OPEN — pass 5 is not done"
grep -rnoE "#[0-9a-fA-F]{6}" resources/js --include="*.vue" | wc -l   # expect 0, or documented exceptions
```

Then toggle between light and dark and confirm every screen still reads correctly in both.

---

## 11. Pass 6 — Consolidated review

Load `.agents/skills/better-interface/` and read `review-format.md`.

This is **not** a sixth fix pass. It is the review that checks passes 1–5 hold together.

`better-interface` is read-only by default: *"Treat a review request as read-only. Do not edit
source unless the user also asks you to implement the findings."* You have been asked — so fix what
it surfaces, but keep the report as the change scope and re-run verification afterwards.

Follow its process:

1. **Resolve and state the scope.** The whole application. If that is too large to inspect
   credibly, narrow to one complete flow — *"the entry path every user must pass through"* — and
   state the boundary and what it excluded. Never imply you reviewed surfaces you did not.
2. **Recon.** Name the framework, tokens, viewports and the project docs you read. `DESIGN.md` is
   the one that matters here; name it.
3. **Run each domain skill in the prescribed order**, including `better-ui`, which shipped in
   PR #36 and should now come back clean.
4. **Rank by user impact** using the shared severity scale, with the escalation triggers as
   automatic `HIGH`.
5. **Consolidate.** One root cause is one finding, with every location in the same row. Cap at 15.
6. **Report in the format in `review-format.md`**, and end with `Block` if any `HIGH` remains,
   `Approve` otherwise.

Mark any domain you could not inspect as `Not reviewed` and name the skill. Do not claim holistic
coverage you did not achieve.

### One thing this pass cannot do

`better-interface` explicitly refuses change-scoped review: *"A request naming a branch, pull
request, commit range, or uncommitted changes is a change review, not a screen review."* That
belongs to the `interface-review` skill, which is user-invoked and cannot be started from inside
another skill.

So: review the **screens**, not the diff. If a diff review of this work is wanted, the user runs
`interface-review` themselves.

---

## 12. Verification

Before opening a pull request.

**Automated**

1. `npm run build` passes.
2. `grep -rn 'size="icon' resources/js --include="*.vue" | grep -vci "aria-label"` → `0`
3. `grep -c "prefers-reduced-motion" resources/css/app.css` → greater than `0`
4. `grep -rnE "[\" ]focus:[a-z-]" resources/js --include="*.vue" | wc -l` → `0`, or each survivor
   justified
5. `docs/contrast-debt.md` no longer exists
6. `grep -rnoE "#[0-9a-fA-F]{6}" resources/js --include="*.vue" | wc -l` → `0`, or documented
   exceptions only
7. `grep -rno "font-bold\|font-extrabold\|font-black" resources/js --include="*.vue" | wc -l` → `0`
8. No page renders two `<h1>`. Check the eleven `PageHeader` pages specifically:
   `grep -rl "PageHeader" resources/js/Pages/`

**In a browser**

9. Complete a full flow — log in, upload, prepare, send — using only the keyboard. Every stop shows
   a visible focus ring.
10. With OS "Reduce motion" enabled, every page still shows all of its content. `FadeIn`-wrapped
   sections are visible, not stuck at `opacity: 0`.
11. Every screen at 320px: nothing clipped, nothing overlapping, no horizontal scrollbar.
12. Every screen at 200% zoom: same.
13. Dark and light both read correctly; no contrast pair looks washed out.
14. Wallet balance and dashboard counters do not shift horizontally as their values change.
15. A truncated document title exposes its full value on hover or focus.
16. The dropdown opens, closes with Escape, and returns focus to its trigger.

**Report honestly.** Anything you could not run is **Not verified**. Every skill requires that
phrasing, and a false pass is worse than an acknowledged gap.

---

## 13. Out of scope

Note these and move on.

- **`better-ui`** — surfaces, motion values, icons, press feedback. Shipped in PR #36.
- **The 130 physical→logical property conversions.** Decision 4.5. Revisit if i18n is scheduled.
- **Color notation migration** to `oklch()`. Decision 4.3.
- **Chart redesign.** Promote the four colors to tokens (10.3); anything beyond that is `dataviz`
  work and a separate task.
- **`interface-review`** on the branch or PR. User-invoked only; `better-interface` cannot start it.
- **The `initialValue` disagreement** between `ThemeToggle.vue` (`"dark"`) and `app.js`
  (`"system"`). Carried over from the PR #36 spec, still unfixed, still a behaviour bug rather than
  an interface one.
- **New features, new screens, redesigns.** If a fix requires one, stop and raise it.
