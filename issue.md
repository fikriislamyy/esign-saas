# UI polish pass: press feedback, transitions, icons and surfaces

## 1. What we are building

A polish pass across the existing UI. No new screens, no new features, no redesign. Six focused
changes that make the interface feel deliberate instead of default.

The work is driven by the `better-ui` skill in `.claude/skills/better-ui/`. **Read this document
first, not the skill.** The skill is written for React projects with a motion library. This project
is Vue 3 with no motion library, and it has its own design system in `DESIGN.md` that overrides the
skill in three specific places. Section 3 lists them. Applying the skill mechanically will break
the product's visual identity.

**Definition of done:**

- Every button scales to `0.96` on press instead of nudging down 1px.
- Zero occurrences of `transition-all` in `resources/js/`.
- The theme toggle cross-fades its icon and the page does not smear when the theme flips.
- One icon library, not two.
- All five `<img>` tags carry the prescribed outline.
- `npm run build` passes.
- Nothing in `DESIGN.md` is contradicted.

**Effort:** roughly one day. Phases 1–3 are the ones users will actually feel; 4–6 are cleanup.

---

## 2. Before you write any code

### The stack is not what the skill assumes

| Skill assumes | This project actually is | What that means for you |
| --- | --- | --- |
| React / JSX | **Vue 3** (`<script setup>`) | Every code sample must be translated. `className` → `class`, `useEffect` → `onMounted`, `cn()` exists here as `cn` from `@/lib/utils` |
| `motion` or `framer-motion` | **Neither is installed** | Use the CSS recipes only. **Never install a motion library for this work** — the skill says so explicitly, and a dependency for icon cross-fades is not justified |
| Tailwind v3 | **Tailwind v4** (`@tailwindcss/vite`) | Config is CSS-first in `resources/css/app.css` via `@theme`. There is no `tailwind.config.js` to edit |

Check for yourself before starting:

```bash
grep -E '"motion"|"framer-motion"' package.json    # must return nothing
```

If that returns nothing, you are on the CSS path for everything in this document.

### Where things live

| Thing | Path |
| --- | --- |
| Button variants (the highest-leverage file) | `resources/js/components/ui/button/index.js` |
| Theme toggle | `resources/js/Components/ThemeToggle.vue` |
| Enter animations | `resources/js/Components/animations/FadeIn.vue` |
| Design tokens | `resources/css/app.css` |
| **The design system you must not contradict** | `DESIGN.md` |
| The skill and its recipes | `.claude/skills/better-ui/` |

Note the two `Components` directories: `resources/js/Components/` (app components, capital C) and
`resources/js/components/ui/` (shadcn-vue primitives, lowercase). This is existing convention. Do
not try to merge them.

### Build after every phase

```bash
npm run build
```

---

## 3. Decisions already made (do not re-litigate)

### 3.1 DO NOT convert card borders to shadows

The skill says "Shadows for elevation, borders for structure" and gives a `--shadow-border` recipe.

**`DESIGN.md` says the exact opposite, deliberately:**

> Use 0.5px hairline borders (#23252a or #383b3f) instead of shadows for surface separation —
> Linear's elevation comes from borders and subtle inner shadows

> Do not use shadows to separate cards from the canvas

This project's visual identity is Linear-inspired, and flat hairline borders are the whole point.
Converting them to layered shadows would make it look like generic Material.

**The design system wins. Skip that rule entirely.** Do not touch `Card.vue`, `DialogContent.vue`,
or any `border` that separates a surface from the canvas. If you find yourself writing
`box-shadow: 0px 0px 0px 1px oklch(...)`, stop — you are undoing a deliberate decision.

### 3.2 DO NOT invent new border radii for concentric math

The skill says `outerRadius = innerRadius + padding`.

`DESIGN.md` caps the radius vocabulary at four values — 12px cards, 6px buttons/inputs, 4px badges,
9999px pills — and says:

> Do not use large radii (16px+) on cards or panels — 12px is the max card radius in this system

Concentric math on a `p-6` card would demand `12 + 24 = 36px`, which is banned. Conveniently the
skill already exempts this case:

> Past `24px` of padding, treat the layers as separate surfaces and choose each radius independently

`Card.vue` and `DialogContent.vue` are both `rounded-xl` (12px) with `p-6` (24px). **They are at
the exemption boundary and are already correct.** No concentric work is needed anywhere in this
codebase. Skip the rule.

### 3.3 DO NOT change the button's icon padding

`buttonVariants` already does optical alignment correctly:

```
default: "... px-2.5 ... has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2"
```

Text-side padding is `px-2.5` = 10px. Icon-side drops to `pr-2` = 8px. That is exactly the skill's
rule (`icon-side = text-side - 2px`). **It is already right. Leave it alone.**

### 3.4 Keep `FadeIn.vue`'s existing easing and stagger

`FadeIn.vue` uses `cubic-bezier(0.16, 1, 0.3, 1)` and pages stagger it by passing `:delay="100"`,
`:delay="200"`. That 100ms step is exactly what the skill prescribes for staged entrances, and the
easing is the project's motion language. The skill says to match the project's motion language
except where it prescribes an exact value — and it only prescribes `cubic-bezier(0.2, 0, 0, 1)` for
*icon cross-fades*, which is Phase 3.

**Do not globally replace the easing curve.** Phase 6 touches one property in this file and nothing
else.

### 3.5 Scope is UI polish only

Hit areas, focus rings, keyboard support, ARIA and `prefers-reduced-motion` belong to
`better-accessibility`. Type scale and wrapping belong to `better-typography`. Grouping and section
spacing belong to `better-layout`. **All three are out of scope here.** Note anything you spot and
move on.

---

## 4. The traps

### Trap 1 — `transition-all` hides in generated components

Twenty-one occurrences across eighteen files, and some are in `components/ui/` (shadcn-vue
primitives, e.g. `progress/Progress.vue`). Those files are vendored into the repo, not regenerated
on install, so editing them is safe and permanent. Do not skip them.

### Trap 2 — `transition-transform` is not a synonym for `transition-[transform]`

In Tailwind, `transition-transform` expands to `transition-property: transform, translate, scale,
rotate`. That is fine when transforms are all you animate. For a mix like scale plus opacity, use
the bracket form: `transition-[scale,opacity]`. Do not write `transition-all` because the bracket
syntax looked unfamiliar.

### Trap 3 — the press scale must be exactly `0.96`

Not `0.95`, not `0.97`. The skill is explicit that anything below `0.95` reads as exaggerated. In
Tailwind that is `active:scale-[0.96]`.

### Trap 4 — icon cross-fade values are exact, and there are four of them

`scale` `0.25` → `1` (**not** `0.5`), `opacity` `0` → `1`, `blur` `4px` → `0px`, easing
`cubic-bezier(0.2, 0, 0, 1)`. Getting three of four right produces a swap that looks almost right
and therefore worse than no animation.

### Trap 5 — theme transition suppression needs a forced reflow, and it is not optional

Reading `document.body.offsetHeight` looks like dead code. It is not. It forces a synchronous style
flush so the new theme colors commit while the "no transitions" override is still in the document.
Delete that line and the whole fix silently stops working. Comment it so the next person does not
remove it.

### Trap 6 — `will-change` on every instance costs memory

`FadeIn.vue` sets `willChange: "opacity, transform, filter"` on every element it wraps, permanently,
including long after the animation finished. Each one is a GPU compositing layer the browser must
hold. The skill says add it when you observe first-frame stutter, not preemptively.

### Trap 7 — there really are two icon packages

`lucide-vue-next` (79 imports, app components) and `@lucide/vue` (18 imports, all in
`components/ui/`). They can land on the same visual surface — a `Select` chevron next to an app
icon inside one card — which is exactly what "one icon library per surface" forbids. It is also a
duplicate dependency in the bundle.

---

## 5. Phase 1 — Button press feedback

**One file. Every button in the product.** Do this first; it is the change users feel most.

`resources/js/components/ui/button/index.js`. In the long base string, find:

```
active:not-aria-[haspopup]:translate-y-px
```

and

```
transition-all
```

Replace the press feedback with a scale, and name the transitioned properties:

- Remove `active:not-aria-[haspopup]:translate-y-px`
- Add `active:not-aria-[haspopup]:not-disabled:scale-[0.96]`
- Replace `transition-all` with `transition-[scale,background-color,color,box-shadow,border-color] duration-150 ease-out`

Keep the `not-aria-[haspopup]` guard — it stops dropdown triggers animating on every open, which is
a high-frequency interaction. Adding `not-disabled` stops disabled buttons reacting to clicks.

### Checkpoint

```bash
npm run build
```

Then in the browser: press and hold any button. It should shrink slightly and spring back on
release. Press, then drag off the button before releasing — it must return smoothly, not snap. That
smooth return is what makes a CSS transition the right tool here.

---

## 6. Phase 2 — Remove every `transition-all`

Find them:

```bash
grep -rn "transition-all" resources/js/ --include="*.vue" --include="*.js"
```

Twenty-one results across eighteen files. For each, replace `transition-all` with the exact properties that element
actually changes. Work out what changes by reading the hover/active/state classes next to it.

Common cases in this codebase:

| File | Changes on state | Replace with |
| --- | --- | --- |
| `Components/common/StatCard.vue` | `hover:-translate-y-1 hover:shadow-lg` | `transition-[translate,box-shadow]` |
| `Components/dashboard/DashboardStats.vue` | `hover:-translate-y-1 hover:shadow-lg` | `transition-[translate,box-shadow]` |
| `Components/dashboard/RecentDocuments.vue` | `hover:bg-muted/40 hover:shadow-sm` | `transition-[background-color,box-shadow]` |
| `components/ui/progress/Progress.vue` | width/transform of the bar | `transition-[transform]` |
| `Components/documents/signing/SigningProgress.vue` | bar width | `transition-[width]` |
| `Components/Layout/SidebarNavItem.vue` | background and color on hover | `transition-[background-color,color]` |
| `Components/FileDropzone.vue` | border and background on drag-over | `transition-[border-color,background-color]` |

For the rest, read the element and decide. **Do not guess** — if an element has
`hover:bg-x hover:text-y`, the answer is `transition-[background-color,color]`, nothing more.

While you are in `SidebarNavItem.vue` and `RecentDocuments.vue`: these are list rows, a
high-frequency interaction. The skill caps those at `150ms`. Both currently sit at `duration-200`
and `duration-300`. Drop them to `duration-150`.

### Checkpoint

```bash
grep -rc "transition-all" resources/js/ --include="*.vue" --include="*.js" | grep -v ":0" || echo "clean"
```

Prints `clean`.

---

## 7. Phase 3 — Theme toggle

Two separate problems in `resources/js/Components/ThemeToggle.vue`.

### Step 1 — cross-fade the icon instead of swapping it

Current code toggles visibility, which the skill forbids for contextual state icons:

```vue
<Sun v-if="mode === 'light'" class="h-5 w-5" />
<Moon v-else class="h-5 w-5" />
```

There is no motion library, so use the CSS cross-fade: keep **both** icons mounted, absolutely
position one over the other, and animate `opacity`, `scale` and `blur`. Vue translation of the
skill's recipe:

```vue
<script setup>
import { computed } from "vue";
import { useColorMode } from "@vueuse/core";
import { Moon, Sun } from "lucide-vue-next";

import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const mode = useColorMode({ emitAuto: true, initialValue: "dark" });

const isLight = computed(() => mode.value === "light");

const shown = "scale-100 opacity-100 blur-0";
const hidden = "scale-[0.25] opacity-0 blur-[4px]";
const base = "transition-[opacity,filter,scale] duration-300 ease-[cubic-bezier(0.2,0,0,1)]";

function toggleTheme() {
    mode.value = isLight.value ? "dark" : "light";
}
</script>

<template>
    <Button variant="ghost" size="icon" @click="toggleTheme">
        <span class="relative flex h-5 w-5 items-center justify-center">
            <!-- Absolute icon overlays; the Moon below defines the layout box. -->
            <Sun :class="cn('absolute inset-0', base, isLight ? shown : hidden)" class="h-5 w-5" />
            <Moon :class="cn(base, isLight ? hidden : shown)" class="h-5 w-5" />
        </span>
    </Button>
</template>
```

Exact values, per Trap 4: scale `0.25`, blur `4px`, easing `cubic-bezier(0.2,0,0,1)`.

### Step 2 — suppress transitions during the flip

After Phase 2 there are transitions on `background-color`, `color` and `border-color` across the
app. A theme flip changes all of them at once and the page smears instead of snapping.

Create `resources/js/lib/theme-transitions.js`:

```js
export function withoutTransitions(apply) {
    const style = document.createElement("style");
    style.append(
        document.createTextNode("*,*::before,*::after{transition:none !important}"),
    );
    document.head.append(style);

    apply();

    // Read for its side effect: forces a synchronous style flush so the new
    // theme commits while the override above still applies. Trap 5 — do not
    // delete this line, the fix silently stops working without it.
    const _flushReflow = document.body.offsetHeight;

    requestAnimationFrame(() => {
        requestAnimationFrame(() => style.remove());
    });
}
```

Then wrap the toggle:

```js
import { withoutTransitions } from "@/lib/theme-transitions";

function toggleTheme() {
    withoutTransitions(() => {
        mode.value = isLight.value ? "dark" : "light";
    });
}
```

### Checkpoint

Toggle the theme repeatedly. Colors must change instantly with no fade, while the sun/moon icon
still cross-fades. Those two things are not in conflict — the override is removed two frames later,
long before you can click again.

**While you are here, note but do not fix:** `ThemeToggle.vue` sets `initialValue: "dark"` while
`resources/js/app.js` sets `initialValue: "system"`. Those disagree. It is a real bug, but it is a
behaviour bug rather than UI polish. Report it; do not fix it in this pull request.

---

## 8. Phase 4 — One icon library

`lucide-vue-next` has 79 imports; `@lucide/vue` has 18, all inside `resources/js/components/ui/`.
Consolidate onto `lucide-vue-next`, the majority.

```bash
grep -rl 'from "@lucide/vue"' resources/js/
```

For each file, change the import path only. The icon component names are identical between the two
packages, so nothing else changes:

```diff
- import { ChevronRight } from "@lucide/vue";
+ import { ChevronRight } from "lucide-vue-next";
```

Then remove the dependency:

```bash
npm uninstall @lucide/vue
npm run build
```

If the build fails on a name that does not exist in `lucide-vue-next`, stop and report it rather
than inventing a substitute icon.

**Do not** also start setting `stroke-width` on every icon. The codebase has exactly one
`stroke-width` today, and auditing several hundred icons against their adjacent text weight is its
own task. Note it as follow-up work.

---

## 9. Phase 5 — Image outlines

Five `<img>` tags, none with an outline:

```bash
grep -rn "<img" resources/js/ --include="*.vue"
```

- `Pages/Landing.vue`
- `Pages/Settings/Organization.vue`
- `Components/landing/FeatureTabs.vue`
- `Components/landing/DashboardPreviewCarousel.vue`
- `Components/documents/signing/SigningField.vue`

Add to each:

```
outline outline-1 -outline-offset-1 outline-black/10 dark:outline-white/10
```

Pure black and pure white are non-negotiable. **Never** `outline-zinc-*`, `outline-slate-*` or any
tinted neutral — a tinted outline picks up the surface colour behind it and reads as dirt on the
image edge. `outline` is used rather than `border` because it never affects layout, and
`-outline-offset-1` draws the ring just inside the corner radius.

One judgement call: `SigningField.vue` renders the signer's captured signature. If it displays as a
transparent PNG directly on the document, an outline would draw a visible box around the signature
itself, which is wrong. **Look at it in the browser before adding the outline there.** Skip that one
if it boxes the signature; add it to the other four regardless.

---

## 10. Phase 6 — `will-change` in `FadeIn.vue`

`resources/js/Components/animations/FadeIn.vue`, in `styleObject`:

```js
willChange: "opacity, transform, filter",
```

This applies to every wrapped element permanently, including after the animation completes. Every
page in the app uses several `FadeIn`s, so this is a standing pile of GPU compositing layers.

Make it conditional so the hint exists only while the element is actually animating:

```js
const styleObject = computed(() => ({
    ...(visible.value ? visibleStyle.value : hiddenStyle.value),

    transition: `
        opacity ${props.duration}ms ${props.easing},
        transform ${props.duration}ms ${props.easing},
        filter ${props.duration}ms ${props.easing}
    `,

    // Only hint the compositor before the element animates in. Holding this
    // after the transition keeps a GPU layer alive for nothing.
    willChange: visible.value ? "auto" : "opacity, transform, filter",
}));
```

Per decision 3.4, change **nothing else in this file.** Not the easing, not the 700ms duration, not
the transition list.

---

## 11. Verification

Run through this before opening a pull request.

**Automated**

1. `npm run build` passes.
2. `grep -rn "transition-all" resources/js/` returns nothing.
3. `grep -rn '@lucide/vue' resources/js/ package.json` returns nothing.
4. `grep -rn "scale-\[0.96\]" resources/js/components/ui/button/index.js` returns one match.

**In the browser** — run the dev server and check each:

5. Press and hold a button: it scales down. Drag off before releasing: it returns smoothly rather
   than snapping.
6. A disabled button does not scale on click.
7. A dropdown trigger does not scale when opened (the `aria-haspopup` guard).
8. Theme toggle: colours snap instantly, no page-wide smear.
9. Theme toggle: the sun/moon icon cross-fades, scaling and blurring rather than popping.
10. Hover a sidebar item and a dashboard row: the transition is quick (150ms), not floaty.
11. Images on the landing page have a faint hairline edge in both light and dark mode.
12. Cards still use hairline **borders**, not shadows. If any card gained a shadow, you violated
    decision 3.1 — revert it.
13. Open DevTools → Animations, set speed to 10%, and replay the theme-toggle icon swap. The icon
    should scale up from very small with blur clearing. If it barely changes size you used `0.5`
    instead of `0.25`.

Report anything you could not check as **Not verified** rather than assuming it passes.

---

## 12. Out of scope

Note these and move on. Do not fix them here.

- Focus rings, hit areas, keyboard navigation, ARIA, `prefers-reduced-motion` — `better-accessibility`.
- Type scale, line length, truncation, tabular numbers — `better-typography`.
- Section spacing, grouping, reading order, breakpoints — `better-layout`.
- Product copy — `better-writing`.
- Auditing icon `stroke-width` against adjacent text weight across the whole app (Phase 4 note).
- The `initialValue` disagreement between `ThemeToggle.vue` and `app.js` (Phase 3 note) — a real
  bug, but a behaviour bug.
- `FadeIn.vue`'s 700ms default duration. Longer than the skill's guidance for entrances, but it is
  the established motion language and changing it is a design decision, not a polish fix.
