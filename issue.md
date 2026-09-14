# Documents/Prepare: "Use Template" action with free (unassigned) signature fields

## Goal

On the **Prepare Document** page (`/documents/{id}/prepare`) add a **Use Template** button.
Clicking it opens a dialog listing the organization's templates. The user picks one and clicks
**Use**. Then:

1. The app checks that the document has enough pages for the template's fields.
2. **Not enough pages** → close the template dialog and show an error dialog with exactly:
   > template signature fields is exceeding the current document pages, please use uploaded document as base for the new template
3. **Enough pages** → delete every signature field the document already has (server-side), then
   show the template's fields on the PDF as **free fields**.

A **free field**:

- exists **only in the browser**. Nothing is written to `document_signature_fields` yet. Its
  page / x / y / width / height come straight from the template's `template_signature_fields`.
- is **locked**: it cannot be moved, resized, or deleted.
- shows the label **"Unassigned"** and looks different (dashed border) so the user knows it
  still needs an owner.
- when **clicked**, opens a **signer picker dialog** listing the document's signers. Picking a
  signer creates the real `document_signature_fields` row (POST to the existing store route)
  with that signer, and the free field is replaced by the normal, editable field.

Free fields are lost on page reload (they were never saved). That is expected. The sidebar shows
how many are still unassigned and offers a **Discard unassigned** action, and **Finish
Preparing** refuses to run while any remain.

## Page count

The `documents` table has no page count and the server never needs it: the check is done in
the browser. Count the pages once when the PDF is loaded on mount (`pdfDoc.numPages`) and keep
it in a **module-level variable** `documentPageCount` in `Prepare.vue`. Use that variable for
the check — not a prop, not a request.

"Exceeding" means: the highest `page` among the template's fields is greater than
`documentPageCount`. A 3-page document can use a template with fields on pages 1–3, or 1–2, or
just 1. It cannot use one with a field on page 4.

## What already exists (read these first)

| Thing | Where |
|---|---|
| Prepare page: `pdfDoc`, `editor` state, `signatureFields` ref, `pageFields()`, `placeField`, `deleteField`, `finishPreparing` | `resources/js/Pages/Documents/Prepare.vue` |
| Sidebar (signer selector, **Add Signature Field** button, instructions) | `resources/js/Components/documents/prepare/PrepareSidebar.vue` |
| Canvas wrapper that renders fields for the current page | `resources/js/Components/documents/prepare/PdfCanvas.vue` |
| One field box — already has an `editable` prop that hides delete/resize and blocks drag | `resources/js/Components/documents/prepare/SignatureField.vue` |
| Feedback helpers (`showError`, `showLoading`, `hideLoading`) | `resources/js/Composables/useFeedback.js` (already used in Prepare.vue) |
| Document field CRUD (`store`, `update`, `destroy`) | `app/Http/Controllers/DocumentSignatureFieldController.php` |
| Routes for the above | `routes/web.php` ~lines 180–192 |
| Template + fields models | `app/Models/Template.php`, `app/Models/TemplateSignatureField.php` |
| Org → templates relation | `app/Models/Organization.php` `templates()` |
| Dialog UI primitives (working example: `Components/billing/TopUpDialog.vue`) | `resources/js/components/ui/dialog` |
| Prepare page props | `DocumentController::prepare()` (`app/Http/Controllers/DocumentController.php` ~line 425) |

Both template fields and document fields store `x, y, width, height` as **fractions of the page
(0–1)**, so values copy over 1:1 — no conversion.

No database migration is needed: free fields never touch the database, so `signer_id` stays
`NOT NULL`.

---

## Step 1 — Backend: send templates (with their fields) to the Prepare page

`app/Http/Controllers/DocumentController.php`, method `prepare()`. Change `Inertia::render` to:

```php
return Inertia::render('Documents/Prepare', [
    'document' => $document->load([
        'signers',
        'signatureFields.signer',
    ]),

    'templates' => $request->user()->organization
        ->templates()
        ->with('signatureFields:id,template_id,page,x,y,width,height')
        ->latest()
        ->get(['id', 'name', 'created_at']),
]);
```

Check: open `/documents/{id}/prepare` and inspect `$page.props.templates` in Vue devtools —
each template has `signature_fields: [{ id, page, x, y, width, height }, ...]`.

## Step 2 — Backend: bulk-clear endpoint

Applying a template must delete the document's existing fields in one go.

### 2a. Route

`routes/web.php`, next to the other `documents.signature-fields.*` routes (~line 180):

```php
Route::delete(
    '/documents/{document}/signature-fields',
    [DocumentSignatureFieldController::class, 'destroyAll']
)->name('documents.signature-fields.destroy-all');
```

### 2b. Controller method

`app/Http/Controllers/DocumentSignatureFieldController.php`, add:

```php
public function destroyAll(Request $request, Document $document)
{
    abort_unless(
        $document->organization_id === $request->user()->organization_id,
        403
    );

    $document->signatureFields()->delete();

    return response()->json([
        'success' => true,
    ]);
}
```

## Step 3 — Backend: let `store` accept width and height

Today `store()` hard-codes `width => 0.25, height => 0.06` and ignores what the client sends.
A free field must keep the template's exact size, so `store` needs to accept them (optional, so
the existing click-to-place flow keeps working).

In `DocumentSignatureFieldController::store`, extend the validation:

```php
$validated = $request->validate([
    'signer_id' => ['required', 'exists:document_signers,id'],
    'page' => ['required', 'integer', 'min:1'],
    'x' => ['required', 'numeric', 'min:0'],
    'y' => ['required', 'numeric', 'min:0'],
    'width' => ['nullable', 'numeric', 'min:0'],
    'height' => ['nullable', 'numeric', 'min:0'],
]);
```

and in the `create([...])` call replace the two hard-coded lines with:

```php
'width' => $validated['width'] ?? 0.25,
'height' => $validated['height'] ?? 0.06,
```

Also add an ownership check so a signer from another document can't be used — insert this right
after the `validate()` call:

```php
abort_unless(
    $document->signers()->whereKey($validated['signer_id'])->exists(),
    422,
    'The selected signer does not belong to this document.'
);
```

Check: the existing **Add Signature Field** click-to-place still works (it already sends
width/height, so placed fields will now be 180×60 px at the zoom used — that is fine).

## Step 4 — Frontend: template picker dialog

Create `resources/js/Components/documents/prepare/UseTemplateDialog.vue`:

```vue
<script setup>
import { computed, ref, watch } from "vue";
import { LayoutTemplate, Check } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import { Button } from "@/components/ui/button";

const props = defineProps({
    open: { type: Boolean, default: false },
    templates: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:open", "use"]);

const selectedId = ref(null);

const selectedTemplate = computed(
    () => props.templates.find((t) => t.id === selectedId.value) ?? null,
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) selectedId.value = null;
    },
);

function maxPage(template) {
    const pages = (template.signature_fields ?? []).map((f) => Number(f.page));

    return pages.length ? Math.max(...pages) : 0;
}

function use() {
    if (selectedTemplate.value) emit("use", selectedTemplate.value);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Use Template</DialogTitle>

                <DialogDescription>
                    Pick a template. Its fields replace every field currently on
                    this document. You assign a signer to each field afterwards.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="templates.length === 0"
                class="rounded-xl border bg-muted/30 p-6 text-center text-sm text-muted-foreground"
            >
                No templates yet. Upload one from the Templates page first.
            </div>

            <div v-else class="max-h-[50vh] space-y-2 overflow-y-auto">
                <button
                    v-for="template in templates"
                    :key="template.id"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors hover:bg-muted/60"
                    :class="template.id === selectedId ? 'border-primary bg-primary/5' : 'border-border'"
                    @click="selectedId = template.id"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <LayoutTemplate class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ template.name }}</p>

                        <p class="text-xs text-muted-foreground">
                            {{ template.signature_fields.length }}
                            {{ template.signature_fields.length === 1 ? "field" : "fields" }}
                            <template v-if="maxPage(template)">
                                · up to page {{ maxPage(template) }}
                            </template>
                        </p>
                    </div>

                    <Check v-if="template.id === selectedId" class="h-4 w-4 shrink-0 text-primary" />
                </button>
            </div>

            <DialogFooter class="grid gap-2 sm:flex sm:justify-end">
                <Button variant="outline" :disabled="processing" @click="emit('update:open', false)">
                    Cancel
                </Button>

                <Button :disabled="!selectedTemplate || processing" @click="use">
                    {{ processing ? "Applying..." : "Use" }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
```

## Step 5 — Frontend: signer picker dialog (opened by clicking a free field)

Create `resources/js/Components/documents/prepare/AssignSignerDialog.vue`:

```vue
<script setup>
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

defineProps({
    open: { type: Boolean, default: false },
    signers: { type: Array, default: () => [] },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["update:open", "assign"]);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Assign Signer</DialogTitle>

                <DialogDescription>
                    Choose who must sign in this field.
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[50vh] space-y-2 overflow-y-auto">
                <button
                    v-for="signer in signers"
                    :key="signer.id"
                    type="button"
                    :disabled="processing"
                    class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-colors hover:bg-muted/60 disabled:opacity-50"
                    @click="emit('assign', signer)"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <UserRound class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ signer.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ signer.email }}</p>
                    </div>

                    <Check class="h-4 w-4 shrink-0 text-muted-foreground" />
                </button>
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

Clicking a signer row assigns immediately (no separate confirm button) — one tap on mobile.

## Step 6 — Frontend: make a field box clickable when it is free

`resources/js/Components/documents/prepare/SignatureField.vue`.

Add `"assign"` to the emits:

```js
const emit = defineEmits(["dragStart", "resizeStart", "delete", "assign"]);
```

Add a click handler in `<script setup>`:

```js
function handleClick(event) {
    // Free (template) fields open the signer picker. Normal fields do nothing on click.
    if (props.field.free) {
        event.stopPropagation();

        emit("assign", props.field);
    }
}
```

Change the root `<div>` in the template — replace its opening tag with:

```vue
<div
    class="absolute select-none rounded-lg border-2 bg-background/90 shadow-sm backdrop-blur-sm transition-shadow hover:shadow-md dark:bg-slate-900/90"
    :class="{
        'cursor-move border-primary': editable && !editor.isResizing,
        'border-primary': !field.free && !editable,
        'cursor-pointer border-dashed border-amber-500 bg-amber-50/90 dark:bg-amber-950/60':
            field.free,
    }"
    :style="{
        ...style,
        touchAction: 'none',
    }"
    @pointerdown.stop="handleDragStart"
    @click.stop="handleClick"
>
```

(`border-primary` moved from the static class into `:class` so free fields can be amber and
dashed instead.)

Change the label so free fields say "Unassigned":

```vue
<span class="truncate text-xs font-semibold text-foreground">
    {{ field.free ? "Unassigned" : (field.signer?.name ?? "Signature") }}
</span>
```

The delete button and resize handle already have `v-if="editable"` and `handleDragStart`
already returns early when `!editable` — Step 7 passes `:editable="!field.free"`, so free fields
are locked with no further changes here.

**Why `@click.stop`:** the parent `.pdf-page` div in `PdfCanvas.vue` has `@click` that places a
new field while in placement mode. Without `.stop`, clicking a free field would also drop a new
field underneath it.

## Step 7 — Frontend: pass `editable` and forward `assign` through `PdfCanvas.vue`

`resources/js/Components/documents/prepare/PdfCanvas.vue`.

Add `"assignField"` to emits:

```js
const emit = defineEmits(["placeField", "dragStart", "resizeStart", "deleteField", "assignField"]);
```

Update the `<SignatureField ... />` usage:

```vue
<SignatureField
    v-for="field in fields"
    :key="field.id"
    :field="field"
    :editable="!field.free"
    :canvas-width="canvasWidth"
    :canvas-height="canvasHeight"
    :editor="editor"
    @drag-start="handleDragStart"
    @resize-start="handleResizeStart"
    @delete="handleDelete"
    @assign="emit('assignField', $event)"
/>
```

## Step 8 — Frontend: Prepare page wiring

All edits in `resources/js/Pages/Documents/Prepare.vue`.

### 8a. Props

```js
const props = defineProps({
    document: { type: Object, required: true },
    templates: { type: Array, default: () => [] },
});
```

### 8b. Imports

Next to the other component imports:

```js
import UseTemplateDialog from "@/Components/documents/prepare/UseTemplateDialog.vue";
import AssignSignerDialog from "@/Components/documents/prepare/AssignSignerDialog.vue";
```

### 8c. Global page count

Directly under `let pdfDoc = null;` (~line 62):

```js
let documentPageCount = 0;
```

In `onMounted`, right after `editor.totalPages = pdfDoc.numPages;` (~line 597):

```js
documentPageCount = pdfDoc.numPages;
```

(`editor.totalPages` stays — the toolbar uses it. `documentPageCount` is the variable the
template check reads.)

### 8d. Free fields state + canvas merge

After the `signatureFields` ref (~line 122):

```js
const freeFields = ref([]);
```

Replace `pageFields()` (~line 142) with:

```js
function pageFields() {
    const page = Number(editor.currentPage);

    return [
        ...signatureFields.value.filter((field) => Number(field.page) === page),
        ...freeFields.value.filter((field) => Number(field.page) === page),
    ];
}
```

### 8e. Use-template flow

Add after `deleteField` (~line 323):

```js
/*
|--------------------------------------------------------------------------
| Use Template
|--------------------------------------------------------------------------
*/

const templateDialogOpen = ref(false);

const applyingTemplate = ref(false);

const TEMPLATE_PAGES_ERROR =
    "template signature fields is exceeding the current document pages, please use uploaded document as base for the new template";

async function applyTemplate(template) {
    if (applyingTemplate.value) {
        return;
    }

    const templateFields = template.signature_fields ?? [];

    const maxPage = templateFields.length
        ? Math.max(...templateFields.map((field) => Number(field.page)))
        : 0;

    if (maxPage > documentPageCount) {
        templateDialogOpen.value = false;

        showError(TEMPLATE_PAGES_ERROR, "Template Not Applicable");

        return;
    }

    applyingTemplate.value = true;

    showLoading("Applying template...");

    try {
        await axios.delete(
            route("documents.signature-fields.destroy-all", props.document.id),
        );

        signatureFields.value = [];

        freeFields.value = templateFields.map((field) => ({
            id: `free-${field.id}`,
            free: true,
            signer: null,
            page: Number(field.page),
            x: Number(field.x),
            y: Number(field.y),
            width: Number(field.width),
            height: Number(field.height),
        }));

        editor.placingSignature = false;

        templateDialogOpen.value = false;
    } catch (error) {
        console.error(error);

        templateDialogOpen.value = false;

        showError(
            error.response?.data?.message ??
                "The template could not be applied. Please try again.",
            "Template Not Applied",
        );
    } finally {
        hideLoading();

        applyingTemplate.value = false;
    }
}

function discardFreeFields() {
    freeFields.value = [];
}

/*
|--------------------------------------------------------------------------
| Assign Free Field
|--------------------------------------------------------------------------
*/

const assignDialogOpen = ref(false);

const assigningField = ref(null);

const assigning = ref(false);

function openAssignDialog(field) {
    assigningField.value = field;

    assignDialogOpen.value = true;
}

async function assignFreeField(signer) {
    const field = assigningField.value;

    if (!field || assigning.value) {
        return;
    }

    assigning.value = true;

    showLoading("Assigning signer...");

    try {
        const response = await axios.post(
            route("documents.signature-fields.store", props.document.id),
            {
                signer_id: signer.id,
                page: field.page,
                x: field.x,
                y: field.y,
                width: field.width,
                height: field.height,
            },
        );

        signatureFields.value.push({
            ...response.data.field,
            x: Number(response.data.field.x),
            y: Number(response.data.field.y),
            width: Number(response.data.field.width),
            height: Number(response.data.field.height),
        });

        freeFields.value = freeFields.value.filter((f) => f.id !== field.id);

        assignDialogOpen.value = false;

        assigningField.value = null;
    } catch (error) {
        console.error(error);

        showError(
            error.response?.data?.message ??
                "The signer could not be assigned. Please try again.",
            "Assignment Failed",
        );
    } finally {
        hideLoading();

        assigning.value = false;
    }
}
```

### 8f. Block Finish while free fields remain

At the top of `finishPreparing` (~line 209), after the `if (loading.value) return;` guard:

```js
if (freeFields.value.length > 0) {
    showError(
        `${freeFields.value.length} template field(s) still have no signer. Click each unassigned field to assign a signer, or discard them from the sidebar.`,
        "Unassigned Fields",
    );

    return;
}
```

### 8g. Template markup

Sidebar (~line 664) — pass the new data and events:

```vue
<PrepareSidebar
    :document="document"
    :editor="editor"
    :signature-fields="signatureFields"
    :free-fields="freeFields"
    @start-placement="editor.placingSignature = true"
    @use-template="templateDialogOpen = true"
    @discard-free-fields="discardFreeFields"
/>
```

Canvas (~line 687) — add the assign event:

```vue
<PdfCanvas
    ...existing props/events...
    @assign-field="openAssignDialog"
>
```

Dialogs — next to the existing `<FeedbackDialog>` at the bottom:

```vue
<UseTemplateDialog
    v-model:open="templateDialogOpen"
    :templates="templates"
    :processing="applyingTemplate"
    @use="applyTemplate"
/>

<AssignSignerDialog
    v-model:open="assignDialogOpen"
    :signers="document.signers"
    :processing="assigning"
    @assign="assignFreeField"
/>
```

### 8h. Do not show a success dialog

`handleFeedbackClose` redirects to the document page when the feedback type is `success`. Never
call `showSuccess` in `applyTemplate` or `assignFreeField` — the fields changing on the canvas
is the confirmation.

## Step 9 — Frontend: sidebar button + unassigned notice

`resources/js/Components/documents/prepare/PrepareSidebar.vue`.

Icons (line 2):

```js
import { FileSignature, MousePointerClick, Info, LayoutTemplate, AlertTriangle } from "lucide-vue-next";
```

Props — add:

```js
freeFields: {
    type: Array,
    default: () => [],
},
```

Emits:

```js
const emit = defineEmits(["start-placement", "use-template", "discard-free-fields"]);
```

Summary block — show the unassigned count under the field count:

```vue
<p class="mt-2 text-sm text-muted-foreground">
    {{ signatureFields.length }}
    {{ signatureFields.length === 1 ? "Field" : "Fields" }}
    <span v-if="freeFields.length" class="text-amber-600 dark:text-amber-400">
        · {{ freeFields.length }} unassigned
    </span>
</p>
```

Tools block — under **Add Signature Field** add the new button. **It does not need a signer
selected**, because ownership is chosen per field afterwards:

```vue
<Button
    variant="outline"
    class="w-full"
    @click="emit('use-template')"
>
    <LayoutTemplate class="mr-2 h-4 w-4" />

    Use Template
</Button>
```

Unassigned notice — add after the **Placement Mode** transition block:

```vue
<div
    v-if="freeFields.length"
    class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4"
>
    <div class="flex gap-3">
        <AlertTriangle class="mt-0.5 h-4 w-4 text-amber-600 dark:text-amber-400" />

        <div class="space-y-2">
            <p class="text-sm font-medium">
                {{ freeFields.length }} unassigned
                {{ freeFields.length === 1 ? "field" : "fields" }}
            </p>

            <p class="text-sm text-muted-foreground">
                Click each dashed field on the PDF to choose its signer.
                Unassigned fields are not saved.
            </p>

            <Button variant="ghost" size="sm" @click="emit('discard-free-fields')">
                Discard unassigned
            </Button>
        </div>
    </div>
</div>
```

Instructions block — add a line:

```vue
<p>Or click <strong>Use Template</strong>, then click each field to assign a signer.</p>
```

---

## Step 10 — Test

Setup: `docker compose up -d && npm run dev`. In one org create:

- Template **A**: 2 fields on page 1.
- Template **B**: at least one field on page 3 (upload a 3+ page PDF as the template, place a
  field on page 3 in `/templates/{id}/prepare`).
- Document **D1**: 1-page PDF with two signers.
- Document **D2**: 3-page PDF with one signer.

On `/documents/{id}/prepare`:

| # | Steps | Expect |
|---|---|---|
| 1 | Open D1, no signer selected, click **Use Template** | Dialog opens (button works without a signer) |
| 2 | Dialog lists A and B | Field counts and "up to page N" shown; **Use** disabled until a row is picked |
| 3 | Pick B, **Use** | Template dialog closes, error dialog with the exact "exceeding" message, canvas unchanged |
| 4 | Pick A, **Use** | Two dashed amber "Unassigned" boxes on page 1; sidebar shows "2 unassigned" notice |
| 5 | Try to drag, resize, or find a delete button on a free field | Nothing moves/resizes; no X button |
| 6 | Reload the page | Free fields are gone (never saved); no rows in `document_signature_fields` |
| 7 | Use A again, click one free field | Signer picker opens listing both signers |
| 8 | Pick a signer | Loading, dialog closes, that box becomes a normal solid field with the signer's name, same position and size; sidebar shows "1 unassigned" |
| 9 | Drag / resize / delete the assigned field | Works like a normally placed field |
| 10 | Click **Finish Preparing** with 1 free field left | Error dialog "Unassigned Fields", no request sent |
| 11 | **Discard unassigned** | Free field disappears; Finish now works |
| 12 | Manually place 2 fields, then use A again | The 2 manual fields are deleted (server + canvas), A's free fields appear |
| 13 | Open D2, use B, go to page 3 | Free field visible on page 3; assign it; reload → still there |
| 14 | Placement mode on, click a free field | Signer picker opens, **no** new field placed underneath |
| 15 | Org with zero templates | Dialog shows "No templates yet" |
| 16 | 375px width | Both dialogs fit; footer buttons full-width |

Finally:

```bash
npm run build
```

---

## Gotchas

- Free fields need a **unique `id`** for `v-for` keys and for removal after assignment — that
  is why they use `free-${templateFieldId}`. Never send that id to the server.
- Keep the `free: true` flag on the object; `SignatureField.vue`, `PdfCanvas.vue` and
  `pageFields()` all rely on it.
- `store()` must accept `width`/`height` (Step 3) or every assigned field snaps to the default
  0.25 × 0.06 size and the template layout is lost.
- `@click.stop` on the field root (Step 6) is required; see the note there.
- Do not call `showSuccess` anywhere in the new code (Step 8h).
- Do not add `editable="false"` tests based on `signer` being null — normal fields always have a
  signer; use the `free` flag.
- Nothing about signing (`SigningController`) changes: only real, signer-owned rows exist in the
  database.

## Definition of done

- [ ] `DocumentController::prepare` passes `templates` with their `signature_fields`
- [ ] `DELETE /documents/{document}/signature-fields` clears all fields (org-checked)
- [ ] `store` accepts optional `width`/`height` and checks the signer belongs to the document
- [ ] `documentPageCount` set once on mount and used for the page check; no page data sent to the server
- [ ] `UseTemplateDialog.vue` and `AssignSignerDialog.vue` created
- [ ] Free fields: locked, dashed/amber, labeled "Unassigned", click opens signer picker
- [ ] Assigning creates the row via `documents.signature-fields.store` with the template's exact geometry and swaps the free field for the real one
- [ ] Sidebar: **Use Template** button (no signer required), unassigned count + notice + **Discard unassigned**
- [ ] **Finish Preparing** blocked while free fields remain
- [ ] All 16 test rows pass, `npm run build` passes
- [ ] One commit on branch `feature/prepare-use-template`:

```
feat: apply template layouts as free signature fields on the prepare page

Adds a Use Template action that shows a template's fields as locked,
unassigned fields on the document. Clicking a free field opens a signer
picker; only then is the document signature field created. Existing
fields are cleared when a template is applied, and the page-count check
runs in the browser against the count taken when the PDF is loaded.
```
