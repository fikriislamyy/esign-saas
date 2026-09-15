# Polish: richer invitation signup page, premium mobile-first signing page

## Goal

Two public-facing pages are visually far behind the rest of the app. Bring them up to the
standard already set by `Auth/Register.vue` and `Signing/Otp.vue`.

**Part A — Invitation accept page** (`/invitations/{token}`, `resources/js/Pages/Invitations/Accept.vue`)

Today: a bare bordered box with three unlabeled inputs, no error messages, no branding, no
password toggle. After: it uses the same `AuthLayout` split-screen as Login/Register, a `Card`
with labels, icons, inline validation errors, show/hide password, an invitation summary
(organization, role, locked email), and a proper submit button with a spinner.

**Part B — Signing page** (`/sign/{token}`, `resources/js/Pages/Signing/Show.vue` + components in
`resources/js/Components/documents/signing/`)

Today: works, but on a phone the "Finish Signing" button is above the PDF (you sign, then scroll
back up), the toolbar wraps into two rows, the PDF renders narrower than it could, the
signature dialog is small, and nothing reassures the signer that this is a secure, official
step. After: a branded top bar, compact header, sticky page/field navigation, a **sticky bottom
action bar** with the finish button, the current field highlighted with a pulse, a larger
finger-friendly signature pad, and a trust footer.

**No backend changes.** All props the pages need are already passed
(`InvitationAcceptController::show()` sends `email`, `role`, `organization`, `token`;
`SigningController::show()` sends `signer` and `document`).

## Reference files (read these first)

| File | Why it matters |
|---|---|
| `resources/js/Pages/Auth/Register.vue` | The style to copy for Part A: `AuthLayout` + `Card` + `Label` + icon inside input + eye toggle + `form.errors.*` messages. |
| `resources/js/Layouts/AuthLayout.vue` | Split-screen layout with brand on the left (desktop only) and a `<slot />` on the right. |
| `resources/js/Pages/Signing/Otp.vue` | Existing signing-flow page with the visual tone we want (centered card, icon badge, `bg-muted/30`). |
| `resources/js/Pages/Signing/Show.vue` | The page to restructure in Part B. Logic (PDF rendering, field navigation, submit) **stays as is**; only the template and one line in `renderPage()` change. |
| `resources/js/Components/documents/signing/*.vue` | Six small components; five get small edits. |

---

# Part A — Invitation accept page

### Step A1 — Rewrite `Invitations/Accept.vue`

Replace the whole file with the version below. It keeps the same `useForm` fields and the same
`form.post(route("invitations.complete", token))`, so the backend is untouched.

```vue
<script setup>
import { ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";

import {
    Building2,
    User,
    Mail,
    Lock,
    Eye,
    EyeOff,
    Loader2,
    ShieldCheck,
} from "lucide-vue-next";

import AuthLayout from "@/Layouts/AuthLayout.vue";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";

const props = defineProps({
    invitation: {
        type: Object,
        required: true,
    },
});

const showPassword = ref(false);
const showConfirmPassword = ref(false);

const form = useForm({
    name: "",
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("invitations.complete", props.invitation.token), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <Head title="Accept Invitation" />

    <AuthLayout>
        <Card
            class="w-full max-w-md rounded-2xl border bg-background/95 shadow-xl backdrop-blur"
        >
            <CardHeader class="pb-4 text-center">
                <div
                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10"
                >
                    <Building2 class="h-7 w-7 text-primary" />
                </div>

                <CardTitle class="text-3xl font-bold">
                    You're invited
                </CardTitle>

                <CardDescription class="text-base">
                    Join
                    <span class="font-semibold text-foreground">
                        {{ invitation.organization }}
                    </span>
                    on EZSign.
                </CardDescription>
            </CardHeader>

            <CardContent class="pb-8 pt-2">
                <!-- Invitation summary -->

                <div class="mb-6 space-y-3 rounded-xl border bg-muted/30 p-4">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-muted-foreground">Organization</span>

                        <span class="truncate font-medium">
                            {{ invitation.organization }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-muted-foreground">Your role</span>

                        <Badge variant="secondary" class="capitalize">
                            {{ invitation.role }}
                        </Badge>
                    </div>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <!-- Email (locked) -->

                    <div class="space-y-2">
                        <Label for="email">Email Address</Label>

                        <div class="relative">
                            <Mail
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="email"
                                :model-value="invitation.email"
                                type="email"
                                disabled
                                class="pl-10"
                            />
                        </div>

                        <p class="text-xs text-muted-foreground">
                            This invitation is tied to this email address.
                        </p>
                    </div>

                    <!-- Name -->

                    <div class="space-y-2">
                        <Label for="name">Full Name</Label>

                        <div class="relative">
                            <User
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="John Doe"
                                autocomplete="name"
                                autofocus
                                class="pl-10"
                            />
                        </div>

                        <p v-if="form.errors.name" class="text-sm text-destructive">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Password -->

                    <div class="space-y-2">
                        <Label for="password">Password</Label>

                        <div class="relative">
                            <Lock
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                class="pl-10 pr-10"
                            />

                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="showPassword = !showPassword"
                            >
                                <Eye v-if="!showPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>

                        <p v-if="form.errors.password" class="text-sm text-destructive">
                            {{ form.errors.password }}
                        </p>

                        <p v-else class="text-xs text-muted-foreground">
                            Minimum 8 characters.
                        </p>
                    </div>

                    <!-- Confirm password -->

                    <div class="space-y-2">
                        <Label for="password_confirmation">Confirm Password</Label>

                        <div class="relative">
                            <Lock
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                class="pl-10 pr-10"
                            />

                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                @click="showConfirmPassword = !showConfirmPassword"
                            >
                                <Eye v-if="!showConfirmPassword" class="h-4 w-4" />
                                <EyeOff v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        class="w-full"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="mr-2 h-4 w-4 animate-spin"
                        />

                        {{ form.processing ? "Joining..." : "Join Organization" }}
                    </Button>

                    <p
                        class="flex items-center justify-center gap-2 text-center text-xs text-muted-foreground"
                    >
                        <ShieldCheck class="h-3.5 w-3.5" />
                        Your account is protected with bank-level security.
                    </p>
                </form>
            </CardContent>
        </Card>
    </AuthLayout>
</template>
```

Notes for the implementer:

- `invitation.role` is a plain string like `admin` / `member`; `capitalize` handles the display.
- The email input is `disabled` and bound with `:model-value` (one-way) because the backend
  always uses the invitation's email — the user cannot change it.
- Laravel's `confirmed` rule puts the mismatch error on `password`, not on
  `password_confirmation`, which is why only the password block shows `form.errors.password`.

---

# Part B — Signing page

Work through B1–B7 in order. B1–B4 are the biggest visual wins.

### Step B1 — `Signing/Show.vue`: restructure the template

Only the `<template>` changes (lines 478–586). Every `ref`/function in `<script setup>` is reused
as is. Replace the `<main>…</main>` block with:

```vue
<main class="flex min-h-screen flex-col bg-muted/30">
    <!-- Brand bar -->

    <div class="border-b bg-background/80 backdrop-blur">
        <div
            class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground"
                >
                    E
                </div>

                <span class="text-sm font-semibold">EZSign</span>
            </div>

            <div
                class="flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <Lock class="h-3.5 w-3.5" />
                Secure signing session
            </div>
        </div>
    </div>

    <!-- Content -->

    <div
        class="mx-auto w-full max-w-7xl flex-1 space-y-4 px-4 py-4 sm:space-y-6 sm:px-6 sm:py-8"
    >
        <SigningHeader
            :document="document"
            :signer="signer"
            :signed-count="signedCount"
            :total-fields="myFields.length"
        />

        <SigningProgress :signed="signedCount" :total="myFields.length" />

        <!-- Workspace -->

        <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
            <SigningToolbar
                :current-page="currentPage"
                :total-pages="totalPages"
                :current-field-index="currentFieldIndex"
                :total-fields="sortedFields.length"
                @previous-page="previousPage"
                @next-page="nextPage"
                @previous-signature="previousSignature"
                @next-signature="nextSignature"
            />

            <SigningCanvas
                :fields="pageFields"
                :active-field-id="sortedFields[currentFieldIndex]?.id ?? null"
                :canvas-width="canvasWidth"
                :canvas-height="canvasHeight"
                @sign="openSignatureDialog"
                @resize="handleWorkspaceResize"
            >
                <template #canvas>
                    <canvas ref="pdfCanvas" class="block bg-white shadow-sm" />
                </template>
            </SigningCanvas>
        </div>

        <!-- Trust footer -->

        <p
            class="flex items-center justify-center gap-2 text-center text-xs text-muted-foreground"
        >
            <ShieldCheck class="h-3.5 w-3.5" />
            Signed documents are sealed with a digital certificate.
        </p>
    </div>

    <!-- Sticky action bar -->

    <div
        class="sticky bottom-0 z-20 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80"
    >
        <div
            class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6"
        >
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">
                    {{
                        allFieldsSigned
                            ? "Ready to submit"
                            : `${signedCount} of ${myFields.length} signed`
                    }}
                </p>

                <p class="hidden truncate text-xs text-muted-foreground sm:block">
                    {{
                        allFieldsSigned
                            ? "Review your signatures, then submit."
                            : "Tap each highlighted field to sign."
                    }}
                </p>
            </div>

            <Button
                size="lg"
                class="shrink-0"
                :disabled="!allFieldsSigned || signing"
                @click="finishSigning"
            >
                <Loader2 v-if="signing" class="mr-2 h-4 w-4 animate-spin" />
                <CheckCircle2 v-else class="mr-2 h-4 w-4" />
                {{ signing ? "Submitting..." : "Finish Signing" }}
            </Button>
        </div>
    </div>
</main>
```

Then update the lucide import on line 16:

```js
import { CheckCircle2, Loader2, Lock, ShieldCheck } from "lucide-vue-next";
```

What moved and why:

- The old "Finish" block (lines 512–548) that sat *between* the toolbar and the canvas is gone.
  It is now the sticky bottom bar, so on a phone the button is always one thumb away.
- `pb-[max(0.75rem,env(safe-area-inset-bottom))]` keeps the bar above the iPhone home indicator.
- `:active-field-id` is a new prop, wired in B4.

### Step B2 — `Signing/Show.vue`: let the PDF use the full width on phones

In `renderPage()` (line 204), the available width subtracts a hard-coded `48`, but the canvas
padding on phones is only 24px (`p-3` in `SigningCanvas.vue`). A variable for this already
exists on line 90 (`workspacePadding`) and is never used. Use it:

```js
// before (line 204–206)
const availableWidth = workspaceWidth.value
    ? workspaceWidth.value - 48
    : Math.min(window.innerWidth - 48, 1000);
```

```js
// after
const availableWidth = workspaceWidth.value
    ? workspaceWidth.value - workspacePadding
    : Math.min(window.innerWidth - workspacePadding, 1000);
```

### Step B3 — `SigningToolbar.vue`: one row, sticky, thumb-sized

Replace the root `<div>` classes so both navigation groups sit on one row on every screen size
and the toolbar stays visible while scrolling the PDF:

```vue
<div
    class="sticky top-0 z-10 flex items-center justify-between gap-2 border-b bg-card/95 p-2 backdrop-blur sm:p-3"
>
```

Inside, keep the two groups exactly as they are, but:

- On every `size="icon"` button add `class="h-10 w-10"` (thumb-sized tap targets).
- Shrink the page label: `min-w-[90px]` → `min-w-[72px]`, and change the text
  `Page {{ currentPage }} / {{ totalPages }}` to
  `<span class="hidden sm:inline">Page&nbsp;</span>{{ currentPage }} / {{ totalPages }}`
  so desktop still reads "Page 1 / 3" and phones read "1 / 3".
- Shrink the field label: `min-w-[120px]` → `min-w-[84px]`.

### Step B4 — `SigningCanvas.vue` + `SigningField.vue`: highlight the current field

**`SigningCanvas.vue`** — add a prop and pass it down:

```js
// add to defineProps
activeFieldId: {
    type: [String, Number, null],
    default: null,
},
```

```vue
<SigningField
    v-for="field in fields"
    :key="field.id"
    :field="field"
    :active="field.id === activeFieldId"
    :canvas-width="canvasWidth"
    :canvas-height="canvasHeight"
    @sign="(field) => emit('sign', field)"
/>
```

Also change `min-h-[65vh]` → `min-h-[60vh] sm:min-h-[65vh]` so the phone layout leaves room
for the sticky bar.

**`SigningField.vue`** — add the prop and the visual states:

```js
// add to defineProps
active: {
    type: Boolean,
    default: false,
},
```

Replace the `:class` on the `<button>`:

```vue
:class="{
    'border-slate-400 bg-slate-50': field.signature,
    'border-primary bg-primary/10 ring-4 ring-primary/20 animate-pulse': active && !field.signature,
}"
```

and change the unsigned label so it reads "Tap to sign" on touch devices:

```vue
<span class="sm:hidden">Tap to sign</span>
<span class="hidden sm:inline">Click to Sign</span>
```

### Step B5 — `SigningHeader.vue`: compact on mobile, show the signer email

Replace the template with:

```vue
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-start gap-3 sm:gap-4">
        <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border bg-muted/40 sm:h-12 sm:w-12"
        >
            <FileSignature class="h-5 w-5 text-primary sm:h-6 sm:w-6" />
        </div>

        <div class="min-w-0">
            <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">
                {{ document.name }}
            </h1>

            <p class="mt-0.5 truncate text-sm text-muted-foreground">
                For
                <span class="font-medium text-foreground">{{ signer.name }}</span>
                · {{ signer.email }}
            </p>
        </div>
    </div>

    <Badge variant="outline" class="w-fit gap-2 px-3 py-1.5">
        <CheckCircle2 class="h-4 w-4" />
        {{ signedCount }} of {{ totalFields }} completed
    </Badge>
</div>
```

(The old `border-b pb-6` is dropped — the brand bar from B1 now separates the header from the
top of the page.)

### Step B6 — `SigningProgress.vue`: clearer label

Change the two `<span>`s inside the first row:

```vue
<span class="font-medium">
    {{ signed === total && total > 0 ? "All fields signed" : "Signing progress" }}
</span>

<span class="text-muted-foreground">{{ signed }} / {{ total }} · {{ progress }}%</span>
```

### Step B7 — `SignatureDialog.vue`: a bigger, finger-friendly pad

1. Dialog width and canvas height — replace line 92 and lines 105–107:

```vue
<DialogContent class="w-[calc(100%-1.5rem)] max-w-xl rounded-2xl p-4 sm:p-6">
```

```vue
<div class="relative overflow-hidden rounded-xl border bg-white">
    <canvas
        ref="canvas"
        class="h-[38vh] max-h-[300px] min-h-[200px] w-full touch-none"
    />

    <!-- baseline -->
    <div
        class="pointer-events-none absolute inset-x-6 bottom-10 border-t border-dashed border-slate-300"
    />

    <p
        class="pointer-events-none absolute bottom-3 left-0 right-0 text-center text-xs text-slate-400"
    >
        Sign above the line
    </p>
</div>
```

2. Description text (line 101): `Draw your signature in the area below.` →
   `Use your finger or mouse to draw your signature.`

3. Re-fit the pad when the phone rotates. Update the Vue import on line 2 and replace the
   `onBeforeUnmount` block (lines 85–87):

```js
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from "vue";

onMounted(() => {
    window.addEventListener("resize", resizeCanvas);
});

onBeforeUnmount(() => {
    window.removeEventListener("resize", resizeCanvas);
    signaturePad?.off();
});
```

4. Footer buttons fill the width on phones — replace line 109 and add `class` to both buttons:

```vue
<DialogFooter class="flex-row gap-2">
    <Button variant="outline" class="flex-1 sm:flex-none" @click="clear">
        <Eraser class="mr-2 h-4 w-4" />
        Clear
    </Button>

    <Button class="flex-1 sm:flex-none" @click="save">
        <Save class="mr-2 h-4 w-4" />
        Apply Signature
    </Button>
</DialogFooter>
```

### Step B8 — Build

```bash
npm run build
```

---

## Test

### Part A

1. Send an invitation from the Members page, open the link from the email (or copy the token
   from the `invitations` table and open `/invitations/{token}`).
2. Desktop: the page shows the EZSign brand panel on the left and the card on the right, like
   `/register`. Phone (< 1024px): only the card, full width, no horizontal scroll.
3. Card shows organization name, role badge, and the invitation email greyed out (cannot edit).
4. Submit empty → "The name field is required." appears under Name; password error appears under
   Password. Enter mismatched passwords → error under Password.
5. Eye icons toggle both password fields independently.
6. Valid submit → button shows spinner + "Joining...", then you land in the app logged in.

### Part B — test on a real phone or DevTools device mode (iPhone 12/13, 390px)

1. Brand bar at the top with "Secure signing session"; header title truncates instead of
   wrapping into three lines; signer email visible.
2. Toolbar is a single row: page arrows on the left, field arrows on the right. It stays pinned
   at the top of the workspace when you scroll the PDF.
3. PDF fills the phone width minus 24px total (noticeably wider than before B2).
4. The current field pulses with a primary-colored ring; label says "Tap to sign" on phone and
   "Click to Sign" on desktop.
5. "Finish Signing" bar is stuck to the bottom of the screen at all times and is disabled until
   every field is signed. It does not overlap the home indicator on iPhone.
6. Tap a field → dialog nearly full-width, pad at least 200px tall with a dashed baseline. Draw
   with a finger — the page does not scroll while drawing. Rotate the phone → pad resizes
   without breaking (it clears; that is expected).
7. Apply → the field shows the signature and the view jumps to the next unsigned field
   (existing behaviour). After the last one, the bottom bar reads "Ready to submit".
8. Desktop (≥ 1024px): everything above still looks right; the bottom bar is a slim strip
   with helper text visible.
9. Regression: `/sign/{token}` for a signer whose OTP is not verified still shows the OTP page
   (untouched). Completed page (untouched) still renders after submit.

## Gotchas

- `AuthLayout` already wraps the slot in a `FadeIn`; do **not** add another one inside
  `Accept.vue`.
- In `Show.vue` the sticky bar must be a **sibling** of the scrolling content inside the
  flex-column `<main>`, not inside the workspace card — `overflow-hidden` on the card would
  break `sticky`.
- `sortedFields[currentFieldIndex]?.id` — the optional chaining matters when a signer has no
  fields; without it the page throws on load.
- The pulse animation uses Tailwind's built-in `animate-pulse`; no config change needed.
- `touch-none` on the signature canvas is already present — keep it; it is what stops the page
  from scrolling while the user draws.
- Do not change anything in `SigningController.php` or `InvitationAcceptController.php`.

## Definition of done

- [ ] `Invitations/Accept.vue` uses `AuthLayout` + `Card`, shows role badge and locked email, inline errors, eye toggles, spinner button
- [ ] `Signing/Show.vue`: brand bar, sticky bottom action bar, trust footer, old inline finish block removed, `workspacePadding` used in `renderPage()`
- [ ] `SigningToolbar.vue` single row + sticky + `h-10 w-10` buttons
- [ ] `SigningCanvas.vue` / `SigningField.vue`: `activeFieldId` → `active` prop, pulse ring, "Tap to sign" on mobile
- [ ] `SigningHeader.vue`, `SigningProgress.vue`, `SignatureDialog.vue` updated as above
- [ ] `npm run build` passes; Part A tests 1–6 and Part B tests 1–9 pass
- [ ] Branch `feature/invitation-and-signing-polish`, PR to `main`. Suggested commit message:

```
feat: polish the invitation signup and signing pages for mobile

The invitation page was a bare form; it now uses the auth layout with
labels, validation errors, and an invitation summary. The signing page
gets a brand bar, a sticky bottom action bar, a single-row sticky
toolbar, a highlighted current field, and a larger finger-friendly
signature pad.
```
