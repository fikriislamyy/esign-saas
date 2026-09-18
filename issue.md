# Refactor the app UI to match DESIGN.md (Linear — "midnight precision instrument")

## 1. What we are doing and why

`DESIGN.md` at the repo root describes the visual language we want: near-black surfaces
(`#08090a`), paper-white type at tight tracking, hairline borders doing the work shadows
usually do, a compact 8/12/24/96 spacing ladder, and **one** electric acid-lime accent
(`#e4f222`) used as a flashlight — small, high-contrast, one per view.

The app today looks nothing like that. It is a light-first shadcn default: near-black
primary, `rounded-md` everything, `font-bold` headings, and about 30 files that hardcode
Tailwind palette colours instead of using theme tokens.

**Most of this is a token swap, not a rewrite.** The app is Tailwind v4 + shadcn-vue, where
every colour flows from a handful of CSS variables in one file. Change those and ~90% of
the app re-skins itself. But there is **one genuine hazard** that is not a token swap, and
it is the reason this issue is long: see decision 3.1. Read it before you touch anything.

**Definition of done:**

- Both themes render correctly; dark is the default a new visitor lands on.
- Acid lime appears on filled primary actions only. It never appears as text, icon, border,
  or focus ring on a light surface.
- No font weight above 590 renders anywhere.
- Radii are 2 / 4 / 6 / 12 / 9999px — nothing else.
- No hardcoded `-gray-`, `-emerald-`, `-indigo-` etc. classes remain in app code.
- `npm run build` passes.
- No functional/behavioural change. This is a visual refactor only.

---

## 2. Start here: revert the previous attempt

An earlier pass at this issue targeted a **different** `DESIGN.md` (a light, lavender-accented
spec that has since been replaced wholesale). Three commits on `main` implement that dead
spec — lavender `#918df6` primary, 9999px pill buttons, 16px cards, mint/amber/sky accents.
They are **not pushed**, and almost every value in them is wrong for the current spec.

Revert them before starting:

```bash
git log --oneline -4        # confirm the three refactor(ui) commits sit on top
git reset --hard c8ed6b7    # the merge commit below them
```

> Run `git status` first and stash anything uncommitted you want to keep. `DESIGN.md` itself
> must survive the reset — if `git log -1 -- DESIGN.md` shows it was introduced by one of the
> reverted commits, save a copy first and restore it afterward.

If for any reason you cannot reset, every file those commits touched is also touched by this
issue, so you can overwrite them in place — but verify each value against Section 12 rather
than assuming what is there is correct.

---

## 3. Background: how theming works in this repo

**Read this section before touching anything.** If you understand it, the rest is easy.

### The one file that matters

`resources/css/app.css` is the entire theme. It has three parts:

1. **`@theme inline { ... }`** — declares Tailwind utilities. Writing
   `--color-primary: hsl(var(--primary));` here generates `bg-primary`, `text-primary`,
   `border-primary`, etc. This block maps *utility names* to *variables*.
2. **`:root { ... }`** — the default-theme **values**, as bare HSL triples
   (`222.2 47.4% 11.2%`), not `hsl(...)` functions. The `hsl()` wrapper lives in `@theme`.
3. **`.dark { ... }`** — the same variables with dark-mode values.

So `bg-primary` → `--color-primary` → `hsl(var(--primary))` → `hsl(222.2 47.4% 11.2%)`.

> **This is why the format matters.** If you write `--primary: #e4f222;` in `:root`, it
> becomes `hsl(#e4f222)`, which is invalid, and the colour silently disappears. Values in
> `:root` and `.dark` **must** be bare `H S% L%` triples. Section 12 has every conversion
> done for you — do not convert hex to HSL yourself.

### Tailwind v4 specifics

This project uses Tailwind **v4**. There is no `tailwind.config.js` — do not look for one,
and ignore v3 answers you find online. You add design tokens by declaring CSS variables
inside `@theme`, and Tailwind generates utilities from the variable's *namespace prefix*:

| You declare in `@theme`      | Tailwind generates        |
| ---------------------------- | ------------------------- |
| `--color-acid-lime: ...`     | `bg-acid-lime`, `text-acid-lime`, `border-acid-lime` |
| `--radius-md: 6px`           | `rounded-md`              |
| `--font-weight-bold: 590`    | redefines `font-bold`     |
| `--text-body: 16px`          | `text-body`               |

**Redefining a namespace key changes what the existing utility means everywhere.** That is
the mechanism behind decisions 3.2 and 3.3 — it lets us fix ~190 call sites by editing four
lines instead of editing 190 files. Font sizes can carry line-height and tracking:

```css
--text-body: 16px;
--text-body--line-height: 1.5;
--text-body--letter-spacing: -0.010em;
```

### Where components live

- `resources/js/components/ui/**` — shadcn primitives. **Lowercase `components`.**
  Editing one file here changes every page.
- `resources/js/Components/**` — app-specific components. **Capital `C`.**
- `resources/js/Layouts/**` — page shells.
- `resources/js/Pages/**` — Inertia pages.

Both a lowercase and a capital `Components` directory exist. That is pre-existing and not
yours to fix — just be careful with import paths.

Button and Badge styles are **not** in the `.vue` file — they are `cva()` definitions in
`index.js` next to it (`components/ui/button/index.js`). The `.vue` file just applies them.

---

## 4. Decisions already made (do not re-litigate these)

The reasoning is included so you can handle edge cases sensibly.

### 4.1 The accent split — read this one twice

This is the hazard that makes this refactor different from a normal re-skin.

`#e4f222` measures **16.2:1** against `#08090a` and **1.23:1** against white. On the dark
canvas it is a flashlight. On a white card it is invisible — not "low contrast", *invisible*.

In shadcn, `--primary` does double duty: it is the **fill** of the primary button (with
`--primary-foreground` as its text) *and* the colour behind `text-primary`, `border-primary`,
`ring-primary`. Those two jobs need different values here:

- As a **fill** with `#08090a` text on top, lime is perfect — 16.2:1, same as on dark.
- As **ink** (text, icon, 1px border, focus ring) on a light surface, lime fails completely.

This codebase has **48 `text-primary` and 12 `border-primary` usages across 37 files.**
Setting `--primary` to lime without addressing them makes 60 pieces of UI vanish in light
mode — brand marks, active nav states, accent headings, focused inputs.

**The fix** is a second token, `--accent-ink`, that resolves per theme:

| Token | Dark | Light | Used for |
|---|---|---|---|
| `--primary` | `#e4f222` | `#e4f222` | Filled CTA background **only** |
| `--primary-foreground` | `#08090a` | `#08090a` | Text on that fill |
| `--accent-ink` | `#e4f222` | `#727a00` (Lime Shade) | Accent as text, icon, border, ring |

`#727a00` is the same hue (64°) at 4.7:1 on white — it passes AA where the bright lime
cannot. Phase 3 migrates all 60 call sites to `text-accent-ink` / `border-accent-ink`.

`bg-primary` (36 files) stays as-is — that is the fill role, and it is correct.

### 4.2 Font weights are remapped, not rewritten

The spec caps at weight 590 and says the system "deliberately avoids heavy display weights".
The app currently uses `font-bold` 32×, `font-extrabold` 1×, `font-semibold` 61×.

Rather than editing ~94 call sites, **redefine what the utilities mean** (Phase 4.1):

| Utility | Tailwind default | Becomes |
|---|---|---|
| `font-medium` | 500 | **510** |
| `font-semibold` | 600 | **590** |
| `font-bold` | 700 | **590** |
| `font-extrabold` | 800 | **590** |

Four lines, ~190 call sites fixed, zero file edits.

> **Known side effect:** `font-bold` and `font-semibold` both become 590, so they stop being
> visually distinct. That is correct per the spec, but any hierarchy that relied on
> bold-vs-semibold will flatten. Phase 8 includes a pass to restore that hierarchy through
> **size**, not weight. Do not "fix" it by reintroducing 700.

### 4.3 One line gets the radii right

Spec radii: cards 12px, buttons 6px, inputs 6px, badges 4px, small 2px, pills 9999px.

The repo derives its scale from `--radius` (currently `0.75rem`):
`sm = radius−4`, `md = radius−2`, `lg = radius`, `xl = radius+4`.

Set **`--radius: 0.5rem`** and the scale becomes `sm=4px, md=6px, lg=8px, xl=12px`, which
lands exactly on the spec:

- `Card.vue` already uses `rounded-xl` → 12px ✓ **no edit needed**
- Button base already uses `rounded-md` → 6px ✓ **no edit needed**
- `Input.vue` already uses `rounded-md` → 6px ✓ **no edit needed**
- Badge uses `rounded-full` → must become `rounded-sm` (4px) — one edit

So the radius work is one CSS line plus one badge class. Do not add custom `--radius-button`
style tokens; the derived scale already fits.

### 4.4 Light stays `:root`, dark stays `.dark` — only the *default* flips

The spec is dark-primary, which suggests making `:root` dark. **Don't.** There are 91 `dark:`
utilities in the codebase, all written assuming light-is-default; inverting `:root` would
silently disable every one of them.

Instead: keep the mechanism exactly as-is (`:root` = light values, `.dark` = dark values),
populate `:root` from DESIGN.md's **Light Theme** section and `.dark` from the dark spec,
then flip the *landing* default to dark in `ThemeToggle.vue` (Phase 1.4). Users land in dark,
both themes are fully specified, and all 91 `dark:` utilities keep working as written.

### 4.5 Berkeley Mono is out of scope

The spec pairs Inter with Berkeley Mono for issue IDs and keyboard shortcuts. This app has no
such UI, and Berkeley Mono is a paid font. Load Inter only. If a monospace need appears
later, the substitute is JetBrains Mono.

### 4.6 Destructive stays red — but use the spec's red

`DESIGN.md` has no dedicated error colour and reserves Coral Red `#eb5757` as a "supporting
accent, not a status color". We need *some* red for destructive actions, and inventing a new
one would add a colour to a system that is deliberately narrow. Use Coral Red for
`--destructive`, accepting that it bends the spec's "supporting accent" note — it is the
closest thing the palette has, and a destructive action is the one place a red is
non-negotiable. Use the darkened twin `#c23434` in light mode so it passes AA as text.

---

## 5. Phase 1 — Replace the design tokens

**File: `resources/css/app.css`.** This one file is the bulk of the visual change.

### 5.1 Add the palette and new tokens to `@theme inline`

Keep every existing `--color-*` line — pages depend on them. You are **adding**, not
replacing. Add the raw palette:

```css
    /* DESIGN.md palette — prefer semantic tokens; reach for these only for
       the specific accent roles the spec names. */
    --color-void: hsl(210 11% 3.5%);
    --color-carbon: hsl(210 6% 6.3%);
    --color-obsidian: hsl(210 4.3% 9%);
    --color-graphite: hsl(223 9% 15%);
    --color-smoke: hsl(214 6% 23%);
    --color-ash: hsl(218 5.3% 41%);
    --color-fog: hsl(219 6.4% 57%);
    --color-mist: hsl(218 20.5% 85%);
    --color-bone: hsl(240 2% 90%);
    --color-paper: hsl(0 0% 100%);
    --color-acid-lime: hsl(64 89% 54%);
    --color-lime-edge: hsl(64 100% 42%);
    --color-lime-shade: hsl(64 100% 24%);
    --color-pulse-green: hsl(134 62% 40%);
    --color-coral-red: hsl(0 79% 63%);
    --color-signal-teal: hsl(186 98% 40%);
    --color-iris-violet: hsl(239 84% 67%);
    --color-lavender: hsl(258 90% 66%);
```

Add the accent-ink token from decision 4.1 — this is the important one:

```css
    --color-accent-ink: hsl(var(--accent-ink));
```

Add the weight remap from decision 4.2:

```css
    --font-weight-medium: 510;
    --font-weight-semibold: 590;
    --font-weight-bold: 590;
    --font-weight-extrabold: 590;
```

Add the type scale (tracking values come from DESIGN.md's Type Scale table):

```css
    --text-caption: 13px;
    --text-caption--line-height: 1.2;

    --text-body-sm: 15px;
    --text-body-sm--line-height: 1.6;
    --text-body-sm--letter-spacing: -0.011em;

    --text-body-lg: 20px;
    --text-body-lg--line-height: 1.33;
    --text-body-lg--letter-spacing: -0.012em;

    --text-subheading: 24px;
    --text-subheading--line-height: 1.33;
    --text-subheading--letter-spacing: -0.012em;

    --text-heading-sm: 32px;
    --text-heading-sm--line-height: 1.13;
    --text-heading-sm--letter-spacing: -0.022em;

    --text-heading: 48px;
    --text-heading--line-height: 1;
    --text-heading--letter-spacing: -0.022em;

    --text-heading-lg: 64px;
    --text-heading-lg--line-height: 1;
    --text-heading-lg--letter-spacing: -0.022em;

    --text-display: 72px;
    --text-display--line-height: 1;
    --text-display--letter-spacing: -0.022em;
```

Add the shadows. Note there is no big drop-shadow here — elevation is borders:

```css
    --shadow-subtle: inset 0 0 0 1px rgb(35 37 42);
    --shadow-sm: 0 2px 4px 0 rgb(0 0 0 / 0.4);
    --shadow-xl: 0 4px 32px 0 rgb(8 9 10 / 0.6);
```

### 5.2 Change `--radius` and replace the `:root` values (light theme)

Replace the **entire** `:root` block. These are DESIGN.md's Light Theme values, already
converted — verified to round-trip back to the correct hex.

```css
:root {
    /* Canvas + text — Light Theme */
    --background: 210 14% 97%;        /* Daylight   #f7f8f9 */
    --foreground: 210 11% 3.5%;       /* Void       #08090a */

    --card: 0 0% 100%;                /* Paper      #ffffff */
    --card-foreground: 223 9% 15%;    /* Graphite   #23252a — body ink */

    --popover: 0 0% 100%;
    --popover-foreground: 223 9% 15%;

    /* Filled CTA only — see decision 4.1 */
    --primary: 64 89% 54%;            /* Acid Lime  #e4f222 */
    --primary-foreground: 210 11% 3.5%; /* Void — 16.2:1 on lime */

    /* Accent as ink. NOT the same as --primary in light mode. */
    --accent-ink: 64 100% 24%;        /* Lime Shade #727a00 — 4.7:1 on white */

    /* Quiet surfaces */
    --secondary: 220 12% 95%;         /* Powder     #f1f2f4 */
    --secondary-foreground: 223 9% 15%;

    --muted: 220 12% 95%;             /* Powder */
    --muted-foreground: 218 5.3% 41%; /* Ash        #62666d — 5.8:1 */

    --accent: 220 12% 95%;            /* Powder */
    --accent-foreground: 223 9% 15%;

    /* Destructive — see decision 4.6 */
    --destructive: 0 58% 48%;         /* #c23434 — darkened twin, AA as text */
    --destructive-foreground: 0 0% 100%;

    /* Structure */
    --border: 225 9.5% 92%;           /* Chalk      #e8e9ec */
    --input: 225 9.5% 92%;            /* Chalk */
    --ring: 64 100% 24%;              /* Lime Shade — a lime ring is invisible here */

    --radius: 0.5rem;                 /* see decision 4.3 — do not change */
}
```

> `--ring` is Lime Shade, not lime. A `#e4f222` focus ring on a white input is 1.23:1 — it
> would look like the field simply has no focus state, which is an accessibility failure, not
> a style choice.

### 5.3 Replace the `.dark` block

```css
.dark {
    --background: 210 11% 3.5%;       /* Void       #08090a */
    --foreground: 0 0% 100%;          /* Paper      #ffffff */

    --card: 210 6% 6.3%;              /* Carbon     #0f1011 */
    --card-foreground: 218 20.5% 85%; /* Mist       #d0d6e0 — body ink */

    --popover: 210 4.3% 9%;           /* Obsidian   #161718 */
    --popover-foreground: 218 20.5% 85%;

    --primary: 64 89% 54%;            /* Acid Lime — unchanged across themes */
    --primary-foreground: 210 11% 3.5%;

    --accent-ink: 64 89% 54%;         /* On void, lime IS the ink — 16.2:1 */

    --secondary: 223 9% 15%;          /* Graphite   #23252a */
    --secondary-foreground: 218 20.5% 85%;

    --muted: 223 9% 15%;              /* Graphite */
    --muted-foreground: 219 6.4% 57%; /* Fog        #8a8f98 */

    --accent: 223 9% 15%;
    --accent-foreground: 218 20.5% 85%;

    --destructive: 0 79% 63%;         /* Coral Red  #eb5757 */
    --destructive-foreground: 210 11% 3.5%;

    --border: 223 9% 15%;             /* Graphite — the hairline */
    --input: 223 9% 15%;
    --ring: 64 89% 54%;               /* Acid Lime */
}
```

### 5.4 Flip the default theme to dark

**File: `resources/js/Components/ThemeToggle.vue`**

`useColorMode` currently defaults to `auto` (follows the OS). The spec is dark-primary, so a
first-time visitor should land in dark regardless of OS setting:

```js
const mode = useColorMode({
    emitAuto: true,
    initialValue: "dark",
});
```

This only affects users with no stored preference; anyone who has toggled keeps their choice.

### 5.5 Checkpoint

`npm run build` must pass. Then `npm run dev` and load any page — it should be dark, with
hairline borders and near-black cards. **Do not continue until this works.**

---

## 6. Phase 2 — Fonts

There is a pre-existing bug: `app.css` downloads **Noto Sans** but sets `--font-sans` to
**Figtree**, while `app.blade.php` loads Figtree from a third host. One font is fetched and
never used, and two sources are in play.

### 6.1 `resources/css/app.css`

Replace the Google Fonts import on line 2 with Inter, loading the weights the spec uses
(300/400/500/600 — which now render as 300/400/510/590 after the Phase 1 remap):

```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');
```

Replace the `--font-sans` line inside `@theme inline`:

```css
    --font-sans: "Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
```

Add the OpenType features to the `body` rule near the bottom of the file — the spec calls
these out as defining the typographic identity:

```css
body {
    /* ...existing declarations... */
    font-feature-settings: "cv01" on, "ss03" on, "zero" on;
}
```

Leave `--font-heading: var(--font-sans);` — the spec uses one typeface everywhere.

### 6.2 `resources/views/app.blade.php`

Delete the two Figtree lines (the `bunny.net` preconnect and stylesheet link) from `<head>`.

### 6.3 Checkpoint

Load a page, confirm in DevTools that body text computes to Inter. If it still says Figtree,
you missed one of the two files.

---

## 7. Phase 3 — The accent-ink migration

**This is the phase that prevents 60 pieces of UI from disappearing.** Re-read decision 4.1
if it is not fresh.

Find every usage:

```bash
grep -rnP '\b(text|border|ring|fill|stroke)-primary(?!-)\b' resources/js --include=*.vue
```

You should see ~60 hits across ~37 files. For each:

| Current | Becomes |
| --- | --- |
| `text-primary` | `text-accent-ink` |
| `border-primary` | `border-accent-ink` |
| `ring-primary` | `ring-accent-ink` |
| `fill-primary` / `stroke-primary` | `fill-accent-ink` / `stroke-accent-ink` |

**Do not touch `bg-primary`.** That is the fill role and it is correct as-is.

**Do not touch `text-primary-foreground`.** Despite the name it is the text *on* the primary
fill, not the accent. Rewriting it to `text-accent-ink-foreground` breaks the class entirely,
and rewriting it to `text-accent-ink` turns every lime button's label into dark olive.

This is easy to get wrong. A word-boundary grep does **not** protect you — `\b` matches
between `y` and `-`, so `grep -E '\btext-primary\b'` happily matches inside
`text-primary-foreground`:

```bash
echo 'text-primary-foreground' | grep -oE '\btext-primary\b'   # prints: text-primary
echo 'text-primary-foreground' | grep -oP '\btext-primary(?!-)\b'  # prints nothing ✓
```

Use the `-P` lookahead form given above, and if your editor does the replace, confirm the
match count matches the grep count before accepting it.

### 7.1 Two cases that need judgement

Most hits are mechanical. Two are not:

**Brand marks on a lime fill.** `Components/Layout/SidebarBrand.vue` and `Layouts/AuthLayout.vue`
render the logo as `bg-primary text-primary-foreground`. That pairing is already correct —
lime fill, near-black glyph, 16.2:1. **Leave these alone.** They are the one place the raw
lime belongs.

**Accent text inside a heading.** `AuthLayout.vue` has
`<span class="text-primary">with confidence.</span>`. This becomes `text-accent-ink`, which
in light mode renders as a dark olive. Verify it still reads as an accent against the
surrounding `text-foreground` and does not just look like slightly-off body text. If it does
not separate, that phrase is a candidate for dropping the accent entirely rather than
forcing it — a muted accent that reads as a mistake is worse than no accent.

### 7.2 Checkpoint

Build, then load a page in **light mode specifically** and confirm nothing has vanished.
Light mode is where this phase's bugs show up; dark mode will look fine either way.

---

## 8. Phase 4 — Verify the weight and radius remaps

Phases 1 and 4.2/4.3 already did the work via token redefinition. This phase is verification
plus the two edits the tokens cannot cover.

### 8.1 Badge radius

**File: `resources/js/components/ui/badge/index.js`** — in the base `cva()` string, change
`rounded-full` → `rounded-sm` (4px per the spec's badge radius).

### 8.2 Confirm the weight remap landed

In DevTools, inspect any `font-bold` element. Computed `font-weight` must be **590**, not 700.
If it still says 700, the `--font-weight-*` lines are in the wrong block — they belong in
`@theme inline`, not `:root`.

### 8.3 Confirm the radius remap landed

Inspect a `Card` (should compute to 12px), a `Button` (6px), an `Input` (6px), a `Badge` (4px).
If a button is still 10px, `--radius` is still `0.75rem`.

---

## 9. Phase 5 — Reshape the shared UI primitives

Thanks to decisions 4.2 and 4.3 these are small. Only what actually moves is listed.

### 9.1 Button — `resources/js/components/ui/button/index.js`

The base string's `rounded-md` is already 6px — **leave it**. The `variants.size` entries
that hardcode `rounded-[min(var(--radius-md),8px)]` and `rounded-[min(var(--radius-md),10px)]`
now fight the scale; replace each with plain `rounded-md`. Leave the
`in-data-[slot=button-group]:rounded-md` parts alone.

Per the spec's Primary Action Button, the `default` variant keeps `bg-primary
text-primary-foreground`. Add the inset elevation the spec gives the CTA — it is the one
chrome element in the system with a real shadow:

```
default: "bg-primary text-primary-foreground hover:bg-primary/80 shadow-sm",
```

(Only `shadow-sm` is added — leave the existing `hover:bg-primary/80` alone.)

For `outline` (the spec's Ghost/Outline Button), replace `shadow-xs` with nothing — the spec
says transparent background, 1px border, no shadow. Keep `border-border`.

**Do not remove `max-sm:min-h-11` / `max-sm:min-w-11`.** Those are mobile touch targets from
a previous issue; removing them is a regression.

### 9.2 Card — `resources/js/components/ui/card/Card.vue`

The spec wants a hairline edge and *no* outer shadow. Today the card uses a `ring` plus
`shadow-xs`. In the `cn(...)` string:

- Replace `ring-foreground/10` and `ring-1` with `border border-border`.
- Replace `shadow-xs` with `shadow-none`.
- Leave `rounded-xl` — it is 12px after Phase 1.

> The spec describes this edge as an inset box-shadow. A 1px border is visually identical
> here and picks up `--border` automatically in both themes, so use the border.

### 9.3 Input — `resources/js/components/ui/input/Input.vue`

- Leave `rounded-md` — 6px is correct.
- Replace `shadow-xs` with `shadow-none` (inputs are flat).
- Leave the `h-9 max-sm:h-11` sizing.

Apply the same `shadow-xs` → `shadow-none` change to:

- `resources/js/components/ui/textarea/Textarea.vue`
- `resources/js/components/ui/select/SelectTrigger.vue`

### 9.4 Badge variant colours — `resources/js/components/ui/badge/index.js`

Three variants hardcode palette colours. Map them to the spec's chromatic accents, using the
**darkened twins** in light mode (DESIGN.md → Light Theme → Chromatic accents):

- `success`: `bg-emerald-500 text-white` → `bg-pulse-green/15 text-pulse-green dark:bg-pulse-green/20`
- `warning`: `bg-amber-100 text-amber-700` → `bg-signal-teal/15 text-signal-teal dark:bg-signal-teal/20`
- `info`: `bg-blue-100 text-blue-700` → `bg-iris-violet/15 text-iris-violet dark:bg-iris-violet/20`
- `pending`: `bg-violet-100 text-violet-700` → `bg-lavender/15 text-lavender dark:bg-lavender/20`

> The spec calls these "supporting accents, not status colors", but badges are exactly the
> "tag/badge fills" role it assigns them, so this is in-bounds. What is *not* in bounds is
> using acid lime for any of them — there is one lime element per view and it is the CTA.

### 9.5 Table — `resources/js/components/ui/table/Table.vue`

Gridlines need no work: `TableRow.vue` uses a bare `border-b`, which picks up the hairline
from the global `* { border-color: hsl(var(--border)); }` rule.

The container does. It currently has no border or radius:

```html
<div data-slot="table-container" class="relative w-full overflow-x-auto">
```

Becomes:

```html
<div data-slot="table-container" class="relative w-full overflow-x-auto rounded-xl border border-border">
```

`rounded-xl` = 12px, the spec's card radius. (The old spec's 24px table radius does not exist
in this system — 12px is the maximum panel radius, per the "no radii 16px+" rule.)

Keep `overflow-x-auto` — it is the mobile horizontal-scroll container, and it clips the
corners so the radius reads.

### 9.6 Checkpoint

`npm run build`, then click through Dashboard, Documents index, Members, Settings in both
themes.

---

## 10. Phase 6 — Remove hardcoded colours

~30 files bypass the tokens. **Do not do these in one commit** — work the batches below.

### 10.1 The translation table

| Hardcoded class | Replace with |
| --- | --- |
| `text-gray-900`, `text-gray-800`, `text-slate-900` | `text-foreground` |
| `text-gray-700`, `text-gray-600` | `text-card-foreground` |
| `text-gray-500`, `text-gray-400` | `text-muted-foreground` |
| `bg-gray-100`, `bg-gray-50`, `bg-slate-100` | `bg-muted` |
| `bg-gray-900`, `bg-gray-800` | `bg-foreground` |
| `border-gray-300`, `border-gray-200` | `border-border` |
| `text-emerald-*`, `text-green-*` | `text-pulse-green` |
| `bg-emerald-50`, `bg-green-100` | `bg-pulse-green/15` |
| `text-amber-*`, `text-yellow-*` | `text-signal-teal` |
| `text-blue-*`, `ring-indigo-500`, `border-indigo-*` | `text-iris-violet` / `ring-ring` / `border-border` |
| `text-red-*`, `bg-red-*` | `text-destructive` / `bg-destructive/10` |
| `text-rose-500`, `text-pink-*` | `text-lavender` |
| `text-orange-500` | `text-signal-teal` |

**Prefer the semantic token over the palette colour.** `text-foreground` over `text-paper`,
`bg-muted` over `bg-graphite` — semantic tokens adapt across themes, raw palette colours do
not. Reach for named palette colours only where the design calls for that specific accent.

> Yellow and orange have no equivalent in this palette. The spec's chromatic set is green /
> red / teal / violet / lavender — amber simply does not exist. Map warm accents to Signal
> Teal rather than inventing a colour, and if that reads wrong for the content, drop the
> colour and use `text-muted-foreground`.

### 10.2 Files, in batches

```bash
grep -rn --include=*.vue -E '\b(bg|text|border|from|to|via|ring)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-[0-9]{2,3}' resources/js
```

**Batch A — layouts (highest visibility, do first):** `Layouts/AuthLayout.vue`,
`Layouts/GuestLayout.vue`

**Batch B — dashboard:** `Components/dashboard/DashboardStats.vue`,
`Components/dashboard/RecentDocuments.vue`, `Components/dashboard/StatusChart.vue`

**Batch C — document + signing:** `Components/documents/prepare/PrepareSidebar.vue`,
`Components/documents/prepare/SignatureField.vue`,
`Components/documents/signing/SigningField.vue`, `Components/feedback/FeedbackDialog.vue`,
`Pages/Signing/Completed.vue`

**Batch D — landing:** `Pages/Landing.vue`, `Components/landing/FeatureTabs.vue`,
`Components/landing/Pricing.vue`, `Components/landing/Testimonials.vue`

**Batch E — auth + profile:** `Pages/Auth/Login.vue`, `Pages/Auth/ForgotPassword.vue`,
`Pages/Auth/VerifyEmail.vue`, `Pages/Auth/ConfirmPassword.vue`,
`Pages/Profile/Partials/UpdateProfileInformationForm.vue`

### 10.3 Special case: `StatusChart.vue` uses hex, not classes

`Components/dashboard/StatusChart.vue` passes raw hex into the Unovis donut
(`#eab308`, `#3b82f6`, `#22c55e`) — the grep above will not catch these. Replace with the
spec's chromatics: `#02b8cc` (draft), `#6366f1` (sent), `#27a644` (completed). Chart fills are
one of the two places DESIGN.md permits decorative colour, so these stay as literal hex.

### 10.4 Special case: `SigningField.vue` sits on a white PDF

`Components/documents/signing/SigningField.vue` draws signature fields **on top of a rendered
PDF page**. The PDF is white in both themes. These fields cannot follow `--background` or
they will vanish in dark mode.

Keep them as fixed, non-token colours: `border-graphite`, `bg-mist`, with the active-field
highlight as `border-lime-edge` (not `border-acid-lime` — `#c9d600` has an edge against white,
`#e4f222` does not). Do **not** swap them for `bg-muted` / `bg-background`.

This is the single most likely thing to break in this refactor. Test it in dark mode
specifically.

---

## 11. Phase 7 — Delete the dead Breeze components

The repo still carries Laravel Breeze starter components. Most are unreferenced and several
are on the hardcoded-colour list, so deleting them removes work from Phase 6.

**Verified zero references — delete:**

- `resources/js/Components/SecondaryButton.vue`
- `resources/js/Components/DangerButton.vue`
- `resources/js/Components/Modal.vue`
- `resources/js/Layouts/AuthenticatedLayout.vue`
- `resources/js/Components/NavLink.vue` (only used by AuthenticatedLayout)
- `resources/js/Components/DropdownLink.vue` (only used by AuthenticatedLayout)
- `resources/js/Components/ResponsiveNavLink.vue` (only used by AuthenticatedLayout)
- `resources/js/Pages/Welcome.vue` (the route renders `Landing` — see `routes/web.php:35`)
- `resources/views/welcome.blade.php` (contains an inlined copy of Tailwind **v3**)

**Still referenced — migrate, don't delete:**

`Pages/Auth/ConfirmPassword.vue` is the last page using `PrimaryButton`, `TextInput`,
`InputLabel`, `InputError`, and `GuestLayout`. Rewrite it with the shadcn primitives
(`Button`, `Input`, `Label`) and `AuthLayout`, matching `Pages/Auth/Login.vue`. Then delete
those five files too.

`Components/Checkbox.vue` is used in 4 places — **keep it**, just fix its colours.

> Before deleting anything, re-run the check yourself:
> `grep -rn "ComponentName" resources/js --include=*.vue --include=*.js`
> If it returns anything other than the file itself, do not delete it.

---

## 12. Phase 8 — Density, hierarchy and the landing page

Only after Phases 1–7 are committed.

### 12.1 Restore the hierarchy the weight remap flattened

Decision 4.2 collapsed `font-bold` and `font-semibold` to the same 590. Walk the pages and
check that headings still read as headings. Where two levels now look identical, separate
them by **size** (`text-subheading` vs `text-body-lg`) or by colour
(`text-foreground` vs `text-muted-foreground`) — never by reintroducing weight 700.

### 12.2 Spacing ladder

The spec's rhythm is 8 / 12 / 24 / 96 and the density is **compact** (the old spec was
comfortable, so most spacing in the app is currently too loose):

- Section gaps → 96px (`py-24`)
- Card padding → 24px (`p-6`)
- Element gaps → 8px (`gap-2`)
- Page max-width → 1200px. The app uses `max-w-7xl` (1280px); switch page shells to
  `max-w-[1200px]`.

### 12.3 Landing page (`Pages/Landing.vue` + `Components/landing/*`)

This is the page the spec describes most directly:

- Hero headline → `text-heading-lg` (64px) at weight 510, tracking -0.022em.
- **Left-aligned**, not centred. The spec's hero is "a left-aligned oversized headline paired
  with a right-aligned link CTA". The current hero is already a 2-column grid — keep it.
- One acid-lime CTA per view. The secondary action is the outline/ghost variant. If the page
  currently has two filled buttons side by side, demote one.
- Section rhythm alternates text-left/image-right 2-column with full-width showcase bands,
  separated by 96px.
- No 3-column card grids — the spec explicitly rules them out. The Pricing section's
  `lg:grid-cols-3` is the exception worth keeping (pricing tiers are inherently a 3-up), but
  do not add others.

### 12.4 Gradients

The spec permits gradients **only** as the hero atmospheric floor or chart fills — never on
buttons, cards, or text. `AuthLayout.vue` has
`bg-gradient-to-br from-background via-background to-muted/40` on its page wrapper. That is a
full-bleed section so it is permitted, but retune it toward the spec's Hero Gradient Floor
(dark-to-light wash) rather than the current muted drift. Remove any gradient you find on a
button or card.

---

## 13. Verification

`npm run build` must pass with no errors.

Then `npm run dev` and walk every page in **both themes** (toggle is in the app header). For
each, confirm: no unreadable text, no invisible borders, no element still in the old theme.

- [ ] Landing (`/`)
- [ ] Login, Register, Forgot Password, Confirm Password
- [ ] Dashboard
- [ ] Documents index / Show / Prepare
- [ ] Templates index / Show / Prepare
- [ ] Members, Settings → Organization, Billing, Profile
- [ ] Signing page + signature dialog — **check fields are visible over the PDF in dark mode**
- [ ] Invitation accept page
- [ ] Error page (403)

Then confirm:

- [ ] A new incognito window lands in **dark** mode
- [ ] **In light mode**, no accent element has disappeared (Phase 3 regression check)
- [ ] Acid lime appears on filled CTAs only — never as text, border, or focus ring on a light
      surface
- [ ] No computed `font-weight` above 590 anywhere
- [ ] Radii are 4 / 6 / 12px on badges / buttons+inputs / cards
- [ ] Focus rings are visible on inputs in **both** themes
- [ ] The hardcoded-colour grep from 10.2 returns nothing in app code
- [ ] Mobile (~375px): no horizontal scroll, touch targets still ≥44px

---

## 14. Guardrails

- **Do not change behaviour.** No props, emits, routes, or form logic. If a visual fix seems
  to need a logic change, stop and ask.
- **Do not use `#e4f222` as text, icon fill, border, or focus ring on a light surface.**
  1.23:1. That is what `--accent-ink` is for.
- **Do not use more than one acid-lime element per view.** It is the only chromatic UI colour
  in the system.
- **Do not reintroduce weight 700**, including to fix hierarchy the remap flattened.
- **Do not use radii ≥16px** on cards or panels. 12px is the ceiling.
- **Do not add drop shadows** to separate cards from the canvas — use hairline borders. The
  only real shadow in the system is on the CTA button.
- **Do not put gradients on buttons, cards, or text.**
- **Do not invert `:root` to dark** — it silently disables 91 `dark:` utilities. See 4.4.
- **Do not remove `max-sm:h-11` / `max-sm:min-h-11`** from the ui primitives. Mobile touch
  targets; removing them is a regression.
- **Do not hand-convert hex to HSL.** Section 15 has every value pre-computed.
- Commit per phase. One giant commit makes a visual regression impossible to bisect.

---

## 15. Reference: hex → HSL conversions

Computed and verified to round-trip. Use these; do not recalculate.

### Dark palette

| Name | Hex | HSL triple |
| --- | --- | --- |
| Void | `#08090a` | `210 11% 3.5%` |
| Carbon | `#0f1011` | `210 6% 6.3%` |
| Obsidian | `#161718` | `210 4.3% 9%` |
| Graphite | `#23252a` | `223 9% 15%` |
| Smoke | `#383b3f` | `214 6% 23%` |
| Ash | `#62666d` | `218 5.3% 41%` |
| Fog | `#8a8f98` | `219 6.4% 57%` |
| Mist | `#d0d6e0` | `218 20.5% 85%` |
| Bone | `#e5e5e6` | `240 2% 90%` |
| Paper | `#ffffff` | `0 0% 100%` |

### Accent

| Name | Hex | HSL triple |
| --- | --- | --- |
| Acid Lime | `#e4f222` | `64 89% 54%` |
| Lime Edge | `#c9d600` | `64 100% 42%` |
| Lime Shade | `#727a00` | `64 100% 24%` |

### Chromatics (dark / light-text twin)

| Name | Dark hex | Dark HSL | Light hex | Light HSL |
| --- | --- | --- | --- | --- |
| Pulse Green | `#27a644` | `134 62% 40%` | `#1a7a30` | `134 65% 29%` |
| Coral Red | `#eb5757` | `0 79% 63%` | `#c23434` | `0 58% 48%` |
| Signal Teal | `#02b8cc` | `186 98% 40%` | `#027d8c` | `186 97% 28%` |
| Iris Violet | `#6366f1` | `239 84% 67%` | `#4f52d9` | `239 64% 58%` |
| Lavender | `#8b5cf6` | `258 90% 66%` | `#7340e6` | `258 77% 58%` |

### Light-theme surfaces

| Name | Hex | HSL triple |
| --- | --- | --- |
| Daylight | `#f7f8f9` | `210 14% 97%` |
| Powder | `#f1f2f4` | `220 12% 95%` |
| Chalk | `#e8e9ec` | `225 9.5% 92%` |
| Quartz | `#d3d5da` | `223 8.6% 84%` |

---

## 16. Commit and PR

Commit per phase:

```
refactor(ui): swap theme tokens to the Linear palette, dark-first
refactor(ui): load Inter with cv01/ss03/zero, drop unused Figtree/Noto
refactor(ui): split accent into fill and ink so lime survives light mode
refactor(ui): reshape button, card, input, badge and table to spec
refactor(ui): replace hardcoded palette colours with theme tokens
chore: remove unused Laravel Breeze starter components
refactor(ui): apply the compact spacing ladder and landing layout
```

Open one PR titled **"refactor: align the app UI with DESIGN.md (Linear)"**. In the
description, include before/after screenshots of Dashboard, Landing, and Signing **in both
themes** — this is a visual change, so screenshots are how it gets reviewed. Call out the
accent split (decision 4.1) explicitly; it is the part a reviewer most needs to understand.
