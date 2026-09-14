# Add Templates: reusable documents with pre-placed signature fields (no signers)

## Goal

Add a **Templates** menu that works like **Documents**, minus everything about signers and
sending. A template is a PDF plus a set of signature-field positions. Later (not in this
issue) the Documents flow will let a user pick a template to start from.

**In scope (this issue)**

- Upload a template PDF → it appears in a table
- Open a template → see its info + a preview button
- "Prepare" a template → place / drag / resize / delete signature fields on the PDF
  (exactly the existing Prepare editor, but without choosing a signer first)
- Sidebar menu item **Templates**

**Out of scope (do not build)**

- Using a template when creating a document
- Signers, sending, OTP, status, deleting templates

---

## How Documents works today (you are copying this)

Read these files first — every new file in this issue is a trimmed copy of one of them.

| Layer | Documents (existing) | Templates (you create) |
|---|---|---|
| Migration | `database/migrations/2026_06_10_134106_create_documents_table.php` | `xxxx_create_templates_table.php` |
| Migration | `database/migrations/2026_06_12_123059_create_document_signature_fields_table.php` | `xxxx_create_template_signature_fields_table.php` |
| Model | `app/Models/Document.php` | `app/Models/Template.php` |
| Model | `app/Models/DocumentSignatureField.php` | `app/Models/TemplateSignatureField.php` |
| Controller | `app/Http/Controllers/DocumentController.php` (`index`, `store`, `show`, `prepare`, `preview`) | `app/Http/Controllers/TemplateController.php` |
| Controller | `app/Http/Controllers/DocumentSignatureFieldController.php` | `app/Http/Controllers/TemplateSignatureFieldController.php` |
| Routes | `routes/web.php` — the `documents.*` block | new `templates.*` block |
| Sidebar | `resources/js/Components/Layout/navigation.js` | add one item |
| Page | `resources/js/Pages/Documents/Index.vue` | `resources/js/Pages/Templates/Index.vue` |
| Page | `resources/js/Pages/Documents/Show.vue` | `resources/js/Pages/Templates/Show.vue` |
| Page | `resources/js/Pages/Documents/Prepare.vue` | `resources/js/Pages/Templates/Prepare.vue` |
| Component | `resources/js/Components/documents/columns.js` | `resources/js/Components/templates/columns.js` |
| Component | `resources/js/Components/documents/DocumentTable.vue` | `resources/js/Components/templates/TemplateTable.vue` |
| Component | `resources/js/Components/documents/DocumentInfo.vue` | `resources/js/Components/templates/TemplateInfo.vue` |
| Component | `resources/js/Components/documents/prepare/PrepareSidebar.vue` | `resources/js/Components/templates/TemplatePrepareSidebar.vue` |

**Reused as-is (do not copy):** `UploadCard.vue`, `PdfCanvas.vue`, `PrepareToolbar.vue`,
`ZoomControls.vue`, `PageNavigator.vue`, `SignatureField.vue` (one tiny edit, see Step 12),
`PageHeader`, `PageSection`, `DataTable`, `useFeedback`.

Conventions in this codebase you must follow:

- All primary keys are **UUIDs**: `$table->uuid('id')->primary()` in migrations and
  `use HasUuids; public $incrementing = false; protected $keyType = 'string';` in models.
- Foreign keys to UUID tables use `foreignUuid(...)`.
- Every controller action checks ownership:
  `abort_unless($model->organization_id === $request->user()->organization_id, 403);`
- Files are stored on the disk named by `env('DOCUMENTS_DISK', 'documents')`.
- Field coordinates are **fractions of the page (0–1)**, not pixels. Default new-field size
  is `width: 0.25, height: 0.06`.
- Run `php artisan` **inside the container**: `docker compose exec app php artisan ...`

---

## Step-by-step

### Step 0 — Run the app

```bash
docker compose up -d
npm run dev
```

Open http://localhost:8000, log in, and click **Documents** so you can compare as you go.

### Step 1 — `templates` migration

```bash
docker compose exec app php artisan make:migration create_templates_table
```

```php
public function up(): void
{
    Schema::create('templates', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->foreignUuid('organization_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->string('name');
        $table->string('file_path');
        $table->unsignedBigInteger('file_size');
        $table->string('mime_type');

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('templates');
}
```

(The spec says `file_size long` — `unsignedBigInteger` is the Laravel equivalent and matches
the `documents` table.)

### Step 2 — `template_signature_fields` migration

```bash
docker compose exec app php artisan make:migration create_template_signature_fields_table
```

```php
public function up(): void
{
    Schema::create('template_signature_fields', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->foreignUuid('template_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->integer('page');

        $table->double('x');
        $table->double('y');
        $table->double('width');
        $table->double('height');

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('template_signature_fields');
}
```

Run both:

```bash
docker compose exec app php artisan migrate
```

### Step 3 — Models

`app/Models/Template.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'file_path',
        'file_size',
        'mime_type',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function signatureFields(): HasMany
    {
        return $this->hasMany(TemplateSignatureField::class);
    }
}
```

`app/Models/TemplateSignatureField.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateSignatureField extends Model
{
    use HasUuids;

    protected $fillable = [
        'template_id',
        'page',
        'x',
        'y',
        'width',
        'height',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
```

In `app/Models/Organization.php`, next to the existing `documents()` method, add:

```php
public function templates()
{
    return $this->hasMany(Template::class);
}
```

### Step 4 — `TemplateController`

Create `app/Http/Controllers/TemplateController.php`. It is `DocumentController` with the
signer/send/status parts removed.

```php
<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = $request->user()->organization
            ->templates()
            ->withCount('signatureFields')
            ->latest()
            ->get()
            ->map(function ($template) {
                $template->created_at_human = $template->created_at->diffForHumans();

                return $template;
            });

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('file');

        $path = $file->store('templates', env('DOCUMENTS_DISK', 'documents'));

        Template::create([
            'organization_id' => $request->user()->organization_id,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back();
    }

    public function show(Request $request, Template $template): Response
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        $template->loadCount('signatureFields');

        $template->created_at_human = $template->created_at->diffForHumans();

        return Inertia::render('Templates/Show', [
            'template' => $template,
        ]);
    }

    public function prepare(Request $request, Template $template): Response
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        return Inertia::render('Templates/Prepare', [
            'template' => $template->load('signatureFields'),
        ]);
    }

    public function preview(Request $request, Template $template)
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        return Storage::disk(env('DOCUMENTS_DISK', 'documents'))->response(
            $template->file_path,
            $template->name . '.pdf',
            ['Content-Disposition' => 'inline']
        );
    }
}
```

Note: templates accept **PDF only** (`mimes:pdf`) because the Prepare editor renders with
pdf.js and can't open Word files.

### Step 5 — `TemplateSignatureFieldController`

Create `app/Http/Controllers/TemplateSignatureFieldController.php`. Copy of
`DocumentSignatureFieldController` with `signer_id` removed and the ownership check added to
`update`/`destroy` (the document version relies on other checks; templates should be safe on
their own).

```php
<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateSignatureField;
use Illuminate\Http\Request;

class TemplateSignatureFieldController extends Controller
{
    public function store(Request $request, Template $template)
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'x' => ['required', 'numeric', 'min:0'],
            'y' => ['required', 'numeric', 'min:0'],
        ]);

        $field = TemplateSignatureField::create([
            'template_id' => $template->id,
            'page' => $validated['page'],
            'x' => $validated['x'],
            'y' => $validated['y'],
            'width' => 0.25,
            'height' => 0.06,
        ]);

        return response()->json(['success' => true, 'field' => $field]);
    }

    public function update(Request $request, TemplateSignatureField $signatureField)
    {
        abort_unless(
            $signatureField->template->organization_id === $request->user()->organization_id,
            403
        );

        $signatureField->update($request->only('x', 'y', 'width', 'height'));

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, TemplateSignatureField $signatureField)
    {
        abort_unless(
            $signatureField->template->organization_id === $request->user()->organization_id,
            403
        );

        $signatureField->delete();

        return response()->json(['success' => true]);
    }
}
```

### Step 6 — Routes

In `routes/web.php`:

1. Add imports at the top with the other controllers:

```php
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TemplateSignatureFieldController;
```

2. Inside the `Route::middleware('auth', 'verified')->group(...)`, right after the last
   `documents.*` route, add:

```php
Route::get('/templates', [TemplateController::class, 'index'])
    ->name('templates.index');

Route::post('/templates', [TemplateController::class, 'store'])
    ->name('templates.store');

Route::get('/templates/{template}', [TemplateController::class, 'show'])
    ->name('templates.show');

Route::get('/templates/{template}/prepare', [TemplateController::class, 'prepare'])
    ->name('templates.prepare');

Route::get('/templates/{template}/preview', [TemplateController::class, 'preview'])
    ->name('templates.preview');

Route::post('/templates/{template}/signature-fields', [TemplateSignatureFieldController::class, 'store'])
    ->name('templates.signature-fields.store');

Route::patch('/template-signature-fields/{signatureField}', [TemplateSignatureFieldController::class, 'update'])
    ->name('templates.signature-fields.update');

Route::delete('/template-signature-fields/{signatureField}', [TemplateSignatureFieldController::class, 'destroy'])
    ->name('templates.signature-fields.destroy');
```

Check they registered:

```bash
docker compose exec app php artisan route:list --name=templates
```

You should see 8 routes.

### Step 7 — Sidebar item

In `resources/js/Components/Layout/navigation.js`:

- Add `LayoutTemplate` to the `lucide-vue-next` import.
- Add this item **right after** the Documents item:

```js
{
    title: "Templates",
    icon: LayoutTemplate,
    route: "templates.index",
},
```

### Step 8 — `Templates/Index.vue`

Copy `resources/js/Pages/Documents/Index.vue` → `resources/js/Pages/Templates/Index.vue` and
change:

| Find | Replace with |
|---|---|
| `documents: Array` | `templates: Array` |
| `route("documents.store")` | `route("templates.store")` |
| `import DocumentTable from "@/Components/documents/DocumentTable.vue"` | `import TemplateTable from "@/Components/templates/TemplateTable.vue"` |
| `<DocumentTable :documents="documents" />` | `<TemplateTable :templates="templates" />` |
| `<Head title="Documents" />` | `<Head title="Templates" />` |
| `FileText` icon | `LayoutTemplate` |
| Header title/description | `Templates` / `Reusable documents with pre-placed signature fields.` |
| Upload section title/description | `Upload Template` / `Upload a PDF to use as a template.` |
| Table section title/description | `All Templates` / `Browse and manage your organization's templates.` |
| Feedback strings | say "template" instead of "document" |

`UploadCard` is reused unchanged (its button says "Upload Document" — acceptable for now).

### Step 9 — `TemplateTable.vue` + `columns.js`

Create `resources/js/Components/templates/TemplateTable.vue` — copy of `DocumentTable.vue`:

- prop `templates` instead of `documents`
- `<ResourceCount :count="templates.length" singular="Template" />`
- `search-placeholder="Search templates..."`
- `import { columns } from "./columns";` (the new file below)

Create `resources/js/Components/templates/columns.js` — copy of
`resources/js/Components/documents/columns.js` with:

- **Delete the whole `status` column** and the `badgeVariant()` helper (templates have no status).
- Every `route("documents.show", ...)` → `route("templates.show", ...)`.
- Column label `"Document"` → `"Template"`.
- Add a new column after `name`:

```js
{
    accessorKey: "signature_fields_count",
    meta: { label: "Fields" },
    header: ({ column }) =>
        h(DataTableColumnHeader, { column, title: "Fields" }),
    cell: ({ row }) => row.original.signature_fields_count ?? 0,
},
```

Remove now-unused imports (`Badge`, `badgeVariants`, `Pencil`).

### Step 10 — `Templates/Show.vue` + `TemplateInfo.vue`

Create `resources/js/Components/templates/TemplateInfo.vue` — copy of `DocumentInfo.vue`:

- prop `template` instead of `document`
- **Remove** the Status block, the Signing Progress block, the `signedCount` computed, and
  `statusVariant`/`statusLabel` helpers and the `Badge` import.
- **Remove** the "Uploaded By" block (templates have no `owner_id`).
- **Add** a block:

```vue
<div class="space-y-2">
    <dt class="text-sm text-muted-foreground">Signature Fields</dt>
    <dd class="font-medium">{{ template.signature_fields_count ?? 0 }}</dd>
</div>
```

Keep File Type, File Size, Uploaded At.

Create `resources/js/Pages/Templates/Show.vue` — copy of `Documents/Show.vue`, then:

- Props: only `template: Object`.
- Delete: `sendForm`, `sendForSignature()`, `sendDocument()`, `downloadDocument()`, the
  confirmation `FeedbackDialog`, all `SignersSection` / `DocumentPreparation` /
  `DocumentActions` imports and usage, the "Preparation Status" section, the "Signers" section.
- Keep `previewDocument()` but point it at `route("templates.preview", props.template.id)`.
- Header: `:title="template.name + ' - Template Details'"`,
  `description="Manage template information and signature field layout."`,
  icon `LayoutTemplate`.
- Header actions slot — put the two buttons inline (no separate component needed):

```vue
<template #actions>
    <div class="flex flex-wrap justify-end gap-2">
        <Button variant="outline" @click="previewDocument">
            <Eye class="mr-2 h-4 w-4" />
            Preview
        </Button>

        <Button as-child>
            <Link :href="route('templates.prepare', template.id)">
                <FilePenLine class="mr-2 h-4 w-4" />
                Prepare Template
            </Link>
        </Button>
    </div>
</template>
```

- Body: one `PageSection` titled "Template Information" containing
  `<TemplateInfo :template="template" />`.

### Step 11 — `TemplatePrepareSidebar.vue`

Create `resources/js/Components/templates/TemplatePrepareSidebar.vue` — copy of
`resources/js/Components/documents/prepare/PrepareSidebar.vue`, then:

- prop `template` instead of `document` (and `{{ template.name }}` in the summary box).
- **Delete** the `SignerSelector` import and the `<SignerSelector ... />` line.
- The "Add Signature Field" button: remove `:disabled="!editor.selectedSigner"`.
- Title/description: `Prepare Template` / `Place signature fields on the PDF.`
- Instructions: delete "1. Select a signer." and renumber the rest (1–3).

### Step 12 — `Templates/Prepare.vue` (the big one)

Copy `resources/js/Pages/Documents/Prepare.vue` → `resources/js/Pages/Templates/Prepare.vue`.
The editor logic (rendering, drag, resize, zoom, pages) stays byte-for-byte. Make **only**
these edits:

1. **Prop rename.** `document: { type: Object, required: true }` →
   `template: { type: Object, required: true }`. Then find-and-replace in this file:
   - `props.document` → `props.template` (every occurrence)
   - in the `<template>` section: `:document="document"` → `:template="template"`,
     `document.id` → `template.id`, `document.name` → `template.name`
   - ⚠️ Do **not** touch `document.addEventListener` / `window.` — those refer to the
     browser DOM, not the prop. Only rename where it means the Inertia prop.

2. **Routes.** Replace every `route("documents.…")` with the `templates` equivalent:
   - `documents.preview` → `templates.preview`
   - `documents.show` → `templates.show`
   - `documents.signature-fields.store` → `templates.signature-fields.store`
   - `documents.signature-fields.update` → `templates.signature-fields.update`
   - `documents.signature-fields.destroy` → `templates.signature-fields.destroy`

3. **Initial fields.** `props.document.signature_fields` → `props.template.signature_fields`
   (this happens automatically from the rename in 1, just confirm it).

4. **`placeField()`** — delete the block
   `if (!editor.selectedSigner) { return; }` and delete the line
   `signer_id: editor.selectedSigner.id,` from the axios payload.

5. **`finishPreparing()`** — there is no server "finish" step for templates. Replace the
   whole function body with:

```js
function finishPreparing() {
    router.visit(route("templates.show", props.template.id));
}
```

   and delete `handleFeedbackClose()`; change the `FeedbackDialog` at the bottom to
   `@close="closeFeedback"`.

6. **Sidebar.** Replace the import
   `import PrepareSidebar from "@/Components/documents/prepare/PrepareSidebar.vue";` with
   `import TemplatePrepareSidebar from "@/Components/templates/TemplatePrepareSidebar.vue";`
   and the tag `<PrepareSidebar ... />` with `<TemplatePrepareSidebar :template="template" … />`
   (same other props/events).

7. **Text.** `<Head :title="\`Prepare - ${template.name}\`" />`, header
   `title="Prepare Template"`, sidebar `PageSection` description
   `"Place signature fields."`, button label `"Done"` instead of "Finish Preparing".

8. Remove the unused import `import { hide } from "@unovis/ts/components/free-brush/style";`
   (it's dead code copied from the original).

**One shared-component edit** — `resources/js/Components/documents/prepare/SignatureField.vue`
line ~94 shows `{{ field.signer?.name }}`. Template fields have no signer, so the box would be
blank. Change it to:

```vue
{{ field.signer?.name ?? "Signature" }}
```

This is safe for documents (they always have a signer).

### Step 13 — Build and smoke-test

```bash
npm run build
```

Then in the browser:

1. Sidebar shows **Templates** between Documents and Members.
2. `/templates` → upload a PDF → it appears in the table with `Fields = 0`.
3. Click the name → Show page with info and two buttons. **Preview** opens the PDF in a new tab.
4. **Prepare Template** → the editor loads the PDF. **Add Signature Field** is enabled
   immediately (no signer step). Click on the page → a box labelled "Signature" appears.
5. Drag it, resize it, delete it, add two more, change page, add one there.
6. Click **Done** → back on Show; `Signature Fields` shows the right count. Reload
   `/templates` — the Fields column matches.
7. Re-open Prepare → the fields are where you left them.
8. Log in as a user from **another organization** and open the first org's template URL
   directly → you get a 403.

---

## Rules / gotchas

- UUID primary keys and `foreignUuid` everywhere — copy the `documents` migrations, not the
  Laravel default stubs.
- Keep field coordinates as 0–1 fractions; don't "fix" them to pixels.
- Don't add signer, status, or send logic anywhere in templates.
- Don't modify the existing Documents pages/components except the one-line fallback in
  `SignatureField.vue`.
- `route()` in Vue is the Ziggy helper — route names must exactly match `routes/web.php`.
- Run `php artisan` inside the container (`docker compose exec app …`).
- If the sidebar item does not appear, check the icon import in `navigation.js`.
- If the Prepare page shows a blank canvas, open the browser console — a wrong `route()` name
  in `templates.preview` is the usual cause.

## Definition of done

- [ ] `templates` and `template_signature_fields` tables exist with the exact columns in the spec
- [ ] `Template` / `TemplateSignatureField` models with relations; `Organization::templates()`
- [ ] 8 `templates.*` routes registered and organisation-scoped (403 for other orgs)
- [ ] Sidebar shows **Templates**
- [ ] Index: upload PDF, table with name / fields / size / uploaded
- [ ] Show: info + Preview + Prepare Template buttons, no signer UI
- [ ] Prepare: add/drag/resize/delete fields without selecting a signer; persists across reloads
- [ ] `npm run build` passes; no console errors on the three pages
- [ ] One commit on branch `feature/templates`:

```
feat: add document templates with reusable signature field layouts

Templates are organisation-scoped PDFs with pre-placed signature fields
and no signers; the Prepare editor is reused without the signer step.
```
