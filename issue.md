# Prepare: pick signers from the member list when adding a field; signer manager becomes edit-only

## Goal

Today signers are added on the **document page** (`/documents/{id}`) through the **Add Signer**
dialog, and then on the **Prepare page** (`/documents/{id}/prepare`) you must first pick one of
those signers in a dropdown before **Add Signature Field** is enabled. Two screens, two steps.

After this change:

- **Prepare page:** clicking **Add Signature Field** opens a modal listing the organization's
  members. Pick one, click on the PDF, and the field is created for that member. If the member is
  not yet a signer of the document, the signer row is created automatically at that moment.
- **Document page:** the signer section becomes an **editor only** — reorder and remove existing
  signers. The **Add Signer** button is gone.
- **Use Template** free fields: clicking a dashed "Unassigned" field opens the same member modal.

One rule that shapes the whole design: **a signer row is created only when a field is actually
placed**, never when a member is merely picked. Otherwise cancelling a placement would leave a
signer with zero fields, and the document page refuses to send documents with such signers
(`canSendForSignature` in `DocumentController::show`).

## How it works today (read this first)

| Piece | File | What it does |
|---|---|---|
| Add Signer dialog | `resources/js/Components/documents/signers/AddSignerDialog.vue` | Member search + "Sequential Signing" checkbox (only when the document has no signers). Posts `member_id`, `signing_order` to `documents.signers.store` as an Inertia form. |
| Signer section | `resources/js/Components/documents/signers/SignersSection.vue` | Header + `AddSignerDialog` + `SignersManager`. |
| Signer manager | `resources/js/Components/documents/signers/SignersManager.vue` | Lists signers, **Edit Order** (drag & drop → `documents.signers.reorder`), remove (→ `documents.signers.destroy`). **Keep as is.** |
| Signer store | `app/Http/Controllers/DocumentSignerController.php` `store()` | Validates `member_id`, rejects duplicates with `withErrors`, computes `signing_order` (first signer decides parallel vs sequential), creates the row, `return back()`. |
| Show props | `app/Http/Controllers/DocumentController.php` `show()` ~line 131 | Passes `members` (org users `id, name, email`) to `Documents/Show.vue`. |
| Prepare props | `DocumentController::prepare()` ~line 425 | Passes `document` (with `signers`, `signatureFields.signer`) and `templates`. **No members.** |
| Signer dropdown | `resources/js/Components/documents/prepare/SignerSelector.vue` | Sets `editor.selectedSigner`. Used by `PrepareSidebar.vue`. |
| Sidebar | `resources/js/Components/documents/prepare/PrepareSidebar.vue` | **Add Signature Field** button is `:disabled="!editor.selectedSigner"`; emits `start-placement`. |
| Prepare page | `resources/js/Pages/Documents/Prepare.vue` | `editor.selectedSigner`, `editor.placingSignature`; `placeField()` (~line 502) posts `signer_id: editor.selectedSigner.id` to `documents.signature-fields.store` via axios; `assignFreeField(signer)` (~line 444) does the same for template free fields. |
| Assign dialog | `resources/js/Components/documents/prepare/AssignSignerDialog.vue` | Lists **document signers** for free fields. Will be replaced by the member picker. |

The Prepare page talks to the backend with **axios** and expects JSON; the Add Signer dialog used
Inertia forms and expects a redirect. That is why `store()` has to change its response (step 2).

## Steps

### Step 1 — Backend: give the Prepare page the member list

`app/Http/Controllers/DocumentController.php`, method `prepare()`. Add a `members` prop using
the exact same query `show()` already uses:

```php
return Inertia::render(
    'Documents/Prepare',
    [
        'document' => $document->load([
            'signers',
            'signatureFields.signer',
        ]),

        'members' => $request->user()->organization
            ->users()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get(),

        'templates' => // ... unchanged
    ]
);
```

In the same file, method `show()`: **delete** the `'members' => ...` entry (~lines 131–141). The
document page no longer needs it.

### Step 2 — Backend: make `DocumentSignerController::store()` return JSON and be idempotent

Replace the duplicate check and the final `return back();`:

```php
// before
if ($alreadyExists) {
    return back()->withErrors([
        'member_id' => 'This member is already a signer.',
    ]);
}
```

```php
// after — an existing signer is simply returned, no error
$existing = $document->signers()->where('email', $member->email)->first();

if ($existing) {
    return response()->json(['signer' => $existing]);
}
```

(Delete the `$alreadyExists = ...` block above it; `$existing` replaces it.)

```php
// before
DocumentSigner::create([ ... ]);

return back();
```

```php
// after
$signer = DocumentSigner::create([
    'document_id' => $document->id,
    'name' => $member->name,
    'email' => $member->email,
    'signing_order' => $signingOrder,
]);

return response()->json(['signer' => $signer], 201);
```

Leave the `signing_order` computation between them untouched. Nothing else calls this endpoint
after step 3, so the Inertia-style response is no longer needed.

### Step 3 — Document page: signer section becomes edit-only

**3a.** Delete `resources/js/Components/documents/signers/AddSignerDialog.vue`.

**3b.** `resources/js/Components/documents/signers/SignersSection.vue`:

- Remove the `AddSignerDialog` import and the `members` prop.
- Remove the whole `<div class="shrink-0"> <AddSignerDialog .../> </div>` block.
- Change the description text to: `Signers are added on the Prepare page when you place
  signature fields.`

**3c.** `resources/js/Components/documents/signers/SignersManager.vue`, empty state (~line 235):

```vue
<div
    v-if="!document.signers.length"
    class="rounded-xl border border-dashed py-10 text-center text-muted-foreground"
>
    No signers yet.
    <Link
        v-if="document.status === 'draft'"
        :href="route('documents.prepare', document.id)"
        class="text-primary underline"
    >
        Prepare the document
    </Link>
    to place signature fields and add signers.
</div>
```

Add `Link` to the existing `@inertiajs/vue3` import at the top of that file.

**3d.** `resources/js/Pages/Documents/Show.vue`: remove `members: Array` from `defineProps` and
remove `:members="members"` from `<SignersSection ...>`.

### Step 4 — Prepare page: new `MemberPickerDialog.vue`

Create `resources/js/Components/documents/prepare/MemberPickerDialog.vue`. It is
`AddSignerDialog` minus the form: it only **emits the chosen member**.

```vue
<script setup>
import { computed, ref, watch } from "vue";
import { Check, UserRound } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";

const props = defineProps({
    open: { type: Boolean, default: false },
    members: { type: Array, default: () => [] },
    signers: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:open", "select"]);

const search = ref("");
const sequential = ref(false);

watch(
    () => props.open,
    (open) => {
        if (open) {
            search.value = "";
        }
    },
);

const hasSigners = computed(() => props.signers.length > 0);

const workflowIsSequential = computed(() =>
    hasSigners.value ? props.signers[0].signing_order > 0 : sequential.value,
);

const signerEmails = computed(() => new Set(props.signers.map((s) => s.email)));

const filteredMembers = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.members;
    }

    return props.members.filter(
        (member) =>
            member.name.toLowerCase().includes(term) ||
            member.email.toLowerCase().includes(term),
    );
});

function choose(member) {
    emit("select", member, workflowIsSequential.value);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Choose Signer</DialogTitle>

                <DialogDescription>
                    Pick the member who must sign this field.
                </DialogDescription>
            </DialogHeader>

            <!-- Workflow: only editable before the first signer exists -->

            <div v-if="!hasSigners" class="flex items-center gap-3 rounded-xl border p-3">
                <Checkbox v-model="sequential" />

                <div>
                    <p class="text-sm font-medium">Sequential Signing</p>
                    <p class="text-xs text-muted-foreground">Signers must sign in order.</p>
                </div>
            </div>

            <p v-else class="rounded-xl bg-muted/50 p-3 text-xs text-muted-foreground">
                Workflow: {{ workflowIsSequential ? "Sequential" : "Parallel" }} signing (locked
                because signers already exist).
            </p>

            <div class="space-y-2">
                <Label>Search Member</Label>
                <Input v-model="search" placeholder="Search by name or email..." />
            </div>

            <div class="max-h-[40vh] space-y-2 overflow-y-auto">
                <button
                    v-for="member in filteredMembers"
                    :key="member.id"
                    type="button"
                    :disabled="processing"
                    class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors hover:bg-muted/60 disabled:opacity-50"
                    @click="choose(member)"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <UserRound class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ member.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ member.email }}</p>
                    </div>

                    <span
                        v-if="signerEmails.has(member.email)"
                        class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                    >
                        Signer
                    </span>

                    <Check v-else class="h-4 w-4 shrink-0 text-muted-foreground" />
                </button>

                <p v-if="!filteredMembers.length" class="p-4 text-sm text-muted-foreground">
                    No members found.
                </p>
            </div>

            <DialogFooter class="grid gap-2 sm:flex sm:justify-end">
                <Button variant="outline" :disabled="processing" @click="emit('update:open', false)">
                    Cancel
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
```

Then delete `SignerSelector.vue` and `AssignSignerDialog.vue` in the same folder — both are
replaced by this dialog.

### Step 5 — `PrepareSidebar.vue`: no dropdown, button always available

- Remove the `SignerSelector` import and the `<SignerSelector .../>` element.
- **Add Signature Field** button: change `:disabled="!editor.selectedSigner"` to
  `:disabled="editor.placingSignature"`.
- In the **Placement Mode** box, change the text to show who the field is for:

```vue
<p class="text-sm text-muted-foreground">
    Click anywhere on the PDF to place a field for
    <strong>{{ editor.selectedMember?.name }}</strong>.
</p>
```

- Instructions block: replace the four numbered lines with:

```vue
<p>1. Click <strong>Add Signature Field</strong> and choose a member.</p>
<p>2. Click on the PDF to place the field.</p>
<p>3. Drag and resize as needed.</p>
<p>Or click <strong>Use Template</strong>, then click each field to choose its signer.</p>
```

### Step 6 — `Prepare.vue`: wire the picker and create signers on placement

All in `resources/js/Pages/Documents/Prepare.vue`.

**6a. Props and imports.** Add `members: { type: Array, default: () => [] }` to `defineProps`.
Replace the `AssignSignerDialog` import with:

```js
import MemberPickerDialog from "@/Components/documents/prepare/MemberPickerDialog.vue";
```

**6b. Local signer list.** The `document.signers` prop is frozen for the life of the page, but we
will create signers with axios, so keep a local copy. Add near the `signatureFields` ref:

```js
const signers = ref([...props.document.signers]);
```

**6c. Editor state.** In the `reactive({...})` editor object, replace `selectedSigner: null,` with:

```js
selectedMember: null,
sequential: false,
```

**6d. Picker state.** Next to the existing `assignDialogOpen` / `assigningField` refs add:

```js
const memberDialogOpen = ref(false);
const memberPickerPurpose = ref("place"); // "place" | "assign"
```

**6e. Open the picker.** Add these two functions and change the sidebar binding:

```js
function openMemberPickerForPlacement() {
    memberPickerPurpose.value = "place";
    memberDialogOpen.value = true;
}

function openAssignDialog(field) {
    assigningField.value = field;
    memberPickerPurpose.value = "assign";
    memberDialogOpen.value = true;
}
```

(`openAssignDialog` already exists — replace its body; it used to set `assignDialogOpen`.)

In the template, change `@start-placement="editor.placingSignature = true"` on
`<PrepareSidebar>` to `@start-placement="openMemberPickerForPlacement"`.

**6f. Handle the choice.** Add:

```js
function handleMemberSelected(member, sequential) {
    editor.selectedMember = member;
    editor.sequential = sequential;
    memberDialogOpen.value = false;

    if (memberPickerPurpose.value === "assign") {
        assignFreeField(member);
        return;
    }

    editor.placingSignature = true;
}
```

**6g. Create-or-reuse the signer.** Add:

```js
async function ensureSigner(member) {
    const existing = signers.value.find((signer) => signer.email === member.email);

    if (existing) {
        return existing;
    }

    const response = await axios.post(route("documents.signers.store", props.document.id), {
        member_id: member.id,
        signing_order: editor.sequential ? 1 : 0,
    });

    signers.value.push(response.data.signer);

    return response.data.signer;
}
```

**6h. `placeField()`.** Change the guard and the request:

```js
// before
if (!editor.selectedSigner) {
    return;
}
```

```js
// after
if (!editor.selectedMember) {
    return;
}
```

and inside the `try`, **before** the `axios.post(... signature-fields.store ...)` call:

```js
const signer = await ensureSigner(editor.selectedMember);
```

then in that post body change `signer_id: editor.selectedSigner.id,` to `signer_id: signer.id,`.
The rest of the function stays: the backend returns the field with its `signer` relation loaded,
so the label shows the name immediately.

**6i. `assignFreeField()`.** It currently receives a *signer*; it now receives a *member*. Rename
the parameter to `member` and, inside the `try` before the post, add
`const signer = await ensureSigner(member);` then use `signer_id: signer.id`.

**6j. Template.** Replace the `<AssignSignerDialog ...>` element with:

```vue
<MemberPickerDialog
    v-model:open="memberDialogOpen"
    :members="members"
    :signers="signers"
    :processing="assigning"
    @select="handleMemberSelected"
/>
```

Search the file for any remaining `selectedSigner`, `assignDialogOpen`, `AssignSignerDialog`,
`document.signers` — each one must be gone or switched to the new names (`signers` for the
sidebar's `:document` is fine to leave; the sidebar only reads `document.name`).

### Step 7 — Build and format

```bash
npm run build
docker exec esign-app ./vendor/bin/pint app/Http/Controllers/DocumentController.php app/Http/Controllers/DocumentSignerController.php
```

## Test

Use two members in the organization (invite a second one on the Members page if needed).

1. **Document page:** open a draft. No **Add Signer** button. Empty state links to Prepare.
2. **Prepare — first field:** click **Add Signature Field** → member modal opens with the
   *Sequential Signing* checkbox (no signers yet). Tick it, pick member A, click on the PDF.
   Expect: field appears labelled with A's name. Back on the document page: A is listed as a
   signer with **Order 1** and 1 signature field.
3. **Cancel does not create a signer:** click **Add Signature Field**, pick member B, then do
   **not** click the PDF — navigate back to the document page. Expect: B is **not** a signer.
4. **Second field, new member:** pick B, place a field. Document page shows B as **Order 2**.
   Modal now shows the workflow as locked and A/B with a "Signer" badge.
5. **Existing signer reused:** pick A again, place another field. Expect: no duplicate A row,
   A now has 2 fields. Check the network tab: no request to `/signers` was made.
6. **Parallel workflow:** new document, leave the checkbox unticked, add fields for A and B.
   Both show **Parallel**.
7. **Use Template on a fresh document:** apply a template → dashed fields; click one → member
   modal → pick B. Expect: field becomes B's, signer B created. Pick A for another one.
8. **Signer manager still edits:** on the document page, **Edit Order** drag & drop still works
   and saves; remove-signer still works and deletes their fields (as before this change).
9. **Send for Signature** works end-to-end for a document prepared entirely through the new flow.

## Gotchas

- `document.signers` in `Prepare.vue` is a page prop; it does **not** update after axios calls.
  Always read/write the local `signers` ref, otherwise the second field for the same member
  creates a duplicate signer.
- Keep `store()`'s `signing_order` logic; only the *response* and the *duplicate handling*
  change. The frontend still sends `signing_order` as `1`/`0` and the backend only honours it for
  the first signer.
- The dialog emits `select(member, sequential)` — the second argument is the checkbox state,
  which only matters for the first signer. Store it on `editor` because the signer is created
  later, when the field is placed.
- `ensureSigner()` is `async`; every caller must `await` it, inside the existing `try` so the
  existing `showError` handling covers a failed signer creation too.
- Do not delete `SignersManager.vue` — reorder and remove stay exactly as they are.
- After deleting `AddSignerDialog.vue`, `SignerSelector.vue` and `AssignSignerDialog.vue`, run
  `npm run build`; Vite fails loudly if any import of them was missed.

## Definition of done

- [ ] `prepare()` passes `members`; `show()` no longer does
- [ ] `DocumentSignerController::store()` returns `{ signer }` JSON (201 created / 200 existing), no `withErrors`
- [ ] `AddSignerDialog.vue`, `SignerSelector.vue`, `AssignSignerDialog.vue` deleted; `MemberPickerDialog.vue` added
- [ ] Document page: no Add Signer button; reorder/remove unchanged; empty state links to Prepare
- [ ] Prepare: **Add Signature Field** always enabled; opens member picker; signer created only when a field is placed; existing signers reused
- [ ] Template free fields assign through the same picker
- [ ] Tests 1–9 pass; `npm run build` and `pint` pass
- [ ] Branch `feature/prepare-member-picker`, PR to `main`. Suggested commit message:

```
feat: choose signers from the member list while placing fields

Signers were added on the document page and then re-selected on the
prepare page. Now Add Signature Field opens the organization member
list, and the signer row is created when the field is placed, so the
signer manager only needs to reorder and remove.
```
