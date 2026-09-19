# Templates: delete action and a 5-template limit

## 1. What we are building, in one paragraph

Two related things. First, a user can **delete a template**: the row in `templates` goes, every
row in `template_signature_fields` that belongs to it goes, and the PDF file in storage goes.
Second, **every organisation can hold at most 5 templates**, on every plan, free or paid. When an
organisation already has 5, the upload box on the Templates page is replaced by a message saying
to delete one first, and the server rejects the upload even if someone bypasses the page.

The two halves depend on each other. A hard limit with no way to delete is a dead end; a delete
button with no limit is just a feature. Build the delete first (Phases 1–3), then the limit
(Phases 4–6), so that at no point is a user stuck.

## 2. Things you must not do

- **Do not** delete `template_signature_fields` rows by hand in the controller with a loop or a
  `->signatureFields()->delete()` call. The database already does it. Section 3 explains; the
  test in 8.2 proves it. Extra deletion code is not wrong, it is noise that suggests the author
  did not know about the cascade.
- **Do not** put the limit only in the Vue page. Hiding the upload box is a courtesy. The server
  check in Phase 5 is the rule.
- **Do not** make the limit vary by plan, and **do not** read it from `.env`. The task says 5 for
  everyone. It goes in `config/plans.php` under each plan's `limits`, with the same value three
  times, because that is where every other limit lives and where `PlanService` reads them.
- **Do not** put the Delete button in the table row. It goes on the template's own page, behind a
  confirmation dialog that names the template. Section 6.1 explains the reasoning; you may
  disagree, but do it there first.
- **Do not** use `window.confirm()`. `PendingInvitations.vue` does, and it is the one place in
  the app that does. There is a proper `AlertDialog` component in `components/ui/alert-dialog`
  that nothing uses yet. This feature is where it gets used.
- **Do not** soft-delete. `Template` has no `SoftDeletes` trait and no `deleted_at` column. A
  deleted template is gone.

## 3. How the pieces already fit together

Read these before writing anything.

| You need to | Look at | What you will see |
| --- | --- | --- |
| Check a template belongs to the caller | [TemplateController.php:56-59](app/Http/Controllers/TemplateController.php#L56-L59) | `abort_unless($template->organization_id === $request->user()->organization_id, 403)` — every method does this |
| Talk to the file store | [TemplateController.php:87](app/Http/Controllers/TemplateController.php#L87) | `Storage::disk(env('DOCUMENTS_DISK', 'documents'))` — same disk, same env key, every time |
| See how a plan limit is defined | [config/plans.php](config/plans.php) | `'limits' => ['documents' => [...], 'members' => 3, 'storage_bytes' => ...]` per plan |
| See how a plan limit is checked | [PlanService.php](app/Services/PlanService.php) `canAddMember()` | `$limit === null \|\| used < $limit` — null means unlimited |
| See how a limit blocks an upload | [DocumentController.php:66-73](app/Http/Controllers/DocumentController.php#L66-L73) | `if (! $planService->canUploadDocument(...)) return back()->withErrors(['file' => '...'])` |
| See how usage reaches the Plan page | `PlanService::usage()` → `SubscriptionController::index()` → `Pages/Plan/Index.vue` `quotas` | An array per quota with `used`, `limit`, `formatter`, `caption` |
| See a destructive action on a page header | [Pages/Templates/Show.vue](resources/js/Pages/Templates/Show.vue) `#actions` slot | Preview and Prepare buttons; Delete goes beside them |

**The cascade.** Open
[the `template_signature_fields` migration](database/migrations/2026_09_14_162857_create_template_signature_fields_table.php)
and find:

```php
$table->foreignUuid('template_id')
    ->constrained()
    ->cascadeOnDelete();
```

`cascadeOnDelete()` is a PostgreSQL foreign-key rule. When a `templates` row is deleted, Postgres
itself deletes every `template_signature_fields` row with that `template_id`, in the same
statement, before Laravel gets control back. So `$template->delete()` **is** the deletion of the
fields. You do not write a second line. The test in 8.2 asserts the fields are gone and will
pass with the one-line delete.

Nothing else references `templates`. `documents` has no `template_id` column (checked: the only
foreign key to `templates` in any migration is the one above). So deleting a template cannot
orphan a document.

## 4. Phase 1 — the route and the controller method

### 4.1 Route: `routes/web.php`

Inside the `auth, verified` group, after the `templates.pdf` route (line 236):

```php
Route::delete('/templates/{template}', [TemplateController::class, 'destroy'])
    ->name('templates.destroy');
```

`Route::delete`, not `post`. Inertia's `router.delete()` sends a real `DELETE` request, and the
verb is what makes the URL `/templates/{id}` mean "remove" rather than "show".

### 4.2 Controller: `app/Http/Controllers/TemplateController.php`

Add after `pdf()`:

```php
public function destroy(Request $request, Template $template)
{
    abort_unless(
        $template->organization_id === $request->user()->organization_id,
        403
    );

    $path = $template->file_path;

    $template->delete();

    if (! Storage::disk(env('DOCUMENTS_DISK', 'documents'))->delete($path)) {
        Log::warning('Template file was not removed from storage', [
            'template_id' => $template->id,
            'path' => $path,
        ]);
    }

    return redirect()
        ->route('templates.index')
        ->with('status', 'Template deleted.');
}
```

Add `use Illuminate\Support\Facades\Log;` to the imports at the top.

Why this order and shape:

- **Ownership check first**, copied from every other method in the file. Without it, anyone who
  guesses a UUID can delete another organisation's template.
- **Save `$path` before `delete()`.** After `$template->delete()` the model is still in memory
  and `$template->file_path` would still work, but reading a property off a deleted model is the
  kind of thing that looks like a bug to the next reader. Copy it out first.
- **Database row first, file second.** If the file deletion fails (S3 is down, credentials
  expired), you are left with a file in storage that nothing points to. That costs a few
  kilobytes and is invisible to the user. The other order — file first, then row — fails the
  other way: a row that points at a file that no longer exists, so the template still appears in
  the list, and opening it 404s. The orphan file is the lesser harm.
- **`Storage::delete()` returns `false` rather than throwing** when it cannot remove the file.
  Without the `if`, that failure is silent. The log line means an orphaned file is at least
  findable later.
- **Redirect to the index**, because the page the user was on (the template's own page) no
  longer exists. `with('status', ...)` is the same flash the login page uses.

### 4.3 Quick check

```bash
docker exec esign-app php artisan route:list --name=templates.destroy
```

One line, `DELETE templates/{template}`. If it says `GET` or is missing, re-read 4.1.

## 5. Phase 2 — the Delete button

### 5.1 Why the Show page and not the table

The table on `/templates` has one action per row, an eye icon that opens the template. Putting a
trash icon next to it means a destructive action one pixel from a harmless one, on a row the user
may be scanning past. The Show page already has a header with Preview and Prepare; it is where
the user has confirmed which template they are looking at. Delete belongs there, behind a dialog
that says the template's name and how many signature fields go with it. One place, one
confirmation, no accidental clicks in a list.

### 5.2 `resources/js/Pages/Templates/Show.vue`

**Imports.** Add to the existing ones:

```js
import { router } from "@inertiajs/vue3";
import { Trash2 } from "lucide-vue-next";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
```

`Head` and `Link` are already imported from `@inertiajs/vue3`; add `router` to that same line
rather than a second import. `LayoutTemplate, Eye, FilePenLine` are already imported from
`lucide-vue-next`; add `Trash2` to that line.

**Script.** After `previewTemplate()`:

```js
const confirmingDelete = ref(false);

function deleteTemplate() {
    confirmingDelete.value = false;

    router.delete(route("templates.destroy", props.template.id), {
        onStart: () => showLoading("Deleting template..."),
        onFinish: () => hideLoading(),
    });
}
```

Add `import { ref } from "vue";` at the top. `showLoading` and `hideLoading` are already
destructured from `useFeedback()` in this file.

The server redirects to `/templates` on success, so there is no `onSuccess` here; Inertia follows
the redirect and the Templates page renders. If the server returns 403, Inertia shows its error
modal, which is correct for a case that should never happen from the UI.

**Template.** In the `#actions` slot, after the Prepare button:

```vue
<Button variant="destructive" @click="confirmingDelete = true">
    <Trash2 class="mr-2 h-4 w-4" />
    Delete
</Button>
```

And after the closing `</PageHeader>`, still inside the `<FadeIn>`:

```vue
<AlertDialog v-model:open="confirmingDelete">
    <AlertDialogContent>
        <AlertDialogHeader>
            <AlertDialogTitle>Delete this template?</AlertDialogTitle>
            <AlertDialogDescription>
                "{{ template.name }}" and its
                {{ template.signature_fields_count }}
                signature field{{ template.signature_fields_count === 1 ? "" : "s" }}
                will be permanently removed. This cannot be undone.
            </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction @click="deleteTemplate">
                Delete template
            </AlertDialogAction>
        </AlertDialogFooter>
    </AlertDialogContent>
</AlertDialog>
```

`template.signature_fields_count` is already there: `TemplateController::show()` calls
`loadCount('signatureFields')` before rendering. The dialog tells the user exactly what is about
to go, by name and by count. "Are you sure?" with no specifics is not a confirmation, it is a
speed bump.

### 5.3 Build and try it

```bash
npm run build
```

Open any template, click Delete. Dialog appears with the right name and count. Cancel closes it.
Delete again, confirm: loading overlay, then you land on `/templates` and the template is gone
from the list.

## 6. Phase 3 — tests for delete

No factory exists for `Template`. Create rows directly with `Template::create()`; the model fills
its own UUID. The organisation and user setup is the same as `DocumentUploadTest.php`, which you
can open for reference.

### 6.1 New file: `tests/Feature/TemplateDeleteTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Template;
use App\Models\TemplateSignatureField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $this->organization = Organization::create(['name' => 'Acme']);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'owner',
        ]);
    }

    private function templateFor(Organization $organization, string $name = 'nda'): Template
    {
        $path = "templates/{$name}.pdf";

        Storage::disk('documents')->put($path, '%PDF-1.4 fake');

        return Template::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'file_path' => $path,
            'file_size' => 14,
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_deleting_a_template_removes_the_row_its_fields_and_its_file(): void
    {
        $template = $this->templateFor($this->organization);

        TemplateSignatureField::create([
            'template_id' => $template->id,
            'page' => 1, 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 40,
        ]);
        TemplateSignatureField::create([
            'template_id' => $template->id,
            'page' => 2, 'x' => 10, 'y' => 10, 'width' => 100, 'height' => 40,
        ]);

        $response = $this->actingAs($this->user)->delete("/templates/{$template->id}");

        $response->assertRedirect('/templates');
        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
        $this->assertDatabaseCount('template_signature_fields', 0);
        Storage::disk('documents')->assertMissing('templates/nda.pdf');
    }

    public function test_a_template_from_another_organisation_cannot_be_deleted(): void
    {
        $other = Organization::create(['name' => 'Rival']);
        $template = $this->templateFor($other);

        $response = $this->actingAs($this->user)->delete("/templates/{$template->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('templates', ['id' => $template->id]);
        Storage::disk('documents')->assertExists('templates/nda.pdf');
    }

    public function test_a_guest_cannot_delete_a_template(): void
    {
        $template = $this->templateFor($this->organization);

        $this->delete("/templates/{$template->id}")->assertRedirect('/login');

        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    }
}
```

The first test is the whole feature in one assertion block: row gone, fields gone (there were
two, now there are zero), file gone. It passes with the one-line `$template->delete()` from 4.2,
which is the proof that the cascade does the fields. If you added a manual fields-delete line
"to be safe", remove it, rerun, and watch this test still pass.

### 6.2 Run

```bash
docker exec esign-app php artisan test tests/Feature/TemplateDeleteTest.php
```

Three green.

## 7. Phase 4 — the limit in config and `PlanService`

### 7.1 `config/plans.php`

Add `'templates' => 5,` to the `limits` array of **all three** plans. Free:

```php
'limits' => [
    'documents' => ['limit' => 3, 'period' => 'week'],
    'templates' => 5,
    'members' => 3,
    'storage_bytes' => 100 * 1024 * 1024,        // 100 MB
],
```

Same line in `pro` and in `enterprise`. Yes, the same number three times, and yes, enterprise
gets `5` rather than `null`. The task says every plan. If that changes later, it is a one-line
edit per plan in the one file that holds every other limit, which is exactly why it lives here
and not in a constant somewhere.

### 7.2 `app/Services/PlanService.php`

Add `use App\Models\Template;` to the imports. Then three additions, each shaped like its
`members` neighbour:

```php
public function templatesUsed(Organization $organization): int
{
    return Template::query()
        ->where('organization_id', $organization->id)
        ->count();
}

public function canAddTemplate(Organization $organization): bool
{
    $limit = $this->limits($organization)['templates'];

    return $limit === null
        || $this->templatesUsed($organization) < $limit;
}
```

And in `usage()`, add a `templates` entry between `documents` and `members`:

```php
'templates' => [
    'used' => $this->templatesUsed($organization),
    'limit' => $limits['templates'],
],
```

The `$limit === null` branch is there because every other `can*()` method has it and the Plan
page's `percentOf()` already treats `null` as unlimited. Nothing sets it to `null` today. Keep
the branch anyway; it is the convention, and removing it would make this method the odd one out.

Note that unlike documents, templates have no period. A document quota resets every week or
month; a template quota is "how many exist right now". That is why `templatesUsed()` has no
`created_at` filter and why the config entry is a bare `5`, not `['limit' => 5, 'period' => …]`.

## 8. Phase 5 — enforce the limit on upload

### 8.1 `app/Http/Controllers/TemplateController.php` — `store()`

After `validate()` and before `$file->store(...)`:

```php
$organization = $request->user()->organization;

if (! app(PlanService::class)->canAddTemplate($organization)) {
    return back()->withErrors([
        'file' => 'You have reached the limit of 5 templates. Delete one to upload another.',
    ]);
}
```

Add `use App\Services\PlanService;` to the imports.

The check sits **before** `$file->store()`, not after. If it came after, a rejected upload would
still have written the PDF to S3 before returning the error. `DocumentController::store()` does
it in the same order for the same reason.

The error goes on the `file` key because that is the key `UploadCard.vue` and `Templates/Index.vue`
already read (`errors.file ?? errors.template ?? …`). Reuse the channel; do not invent a new
key the page would have to learn about.

### 8.2 `TemplateController::index()` — tell the page where it stands

The page needs to know the count and the limit so it can hide the upload box. Add one prop:

```php
$planService = app(PlanService::class);
$organization = $request->user()->organization;

return Inertia::render('Templates/Index', [
    'templates' => $templates,
    'templateQuota' => [
        'used' => $planService->templatesUsed($organization),
        'limit' => $planService->limits($organization)['templates'],
    ],
]);
```

Use `templatesUsed()` rather than `$templates->count()`. They are equal today, but if the index
query ever gains a filter (search, pagination), the count the page shows must stay the count the
server enforces.

## 9. Phase 6 — the page at the limit

### 9.1 `resources/js/Pages/Templates/Index.vue`

Add the prop:

```js
const props = defineProps({
    templates: Array,
    templateQuota: Object,
});
```

(It is currently `defineProps({ templates: Array })` with no `const props =`; add the
assignment so you can read it in script.)

Add a computed and the import for it:

```js
import { computed } from "vue";

const atLimit = computed(
    () =>
        props.templateQuota.limit !== null &&
        props.templateQuota.used >= props.templateQuota.limit,
);
```

Then in the template, replace the Upload section's `<UploadCard :form="form" @upload="submit" />`
with:

```vue
<div
    v-if="atLimit"
    class="rounded-xl border border-dashed p-10 text-center"
>
    <LayoutTemplate class="mx-auto mb-3 h-10 w-10 text-muted-foreground" />

    <h3 class="font-medium">Template limit reached</h3>

    <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
        Your organization can store up to
        {{ templateQuota.limit }} templates. Delete one below to upload
        another.
    </p>
</div>

<UploadCard v-else :form="form" @upload="submit" />
```

And change the section's description to show progress:

```vue
<PageSection
    title="Upload Template"
    :description="`${templateQuota.used} of ${templateQuota.limit} templates used.`"
>
```

`LayoutTemplate` is already imported in this file for the page header.

The `v-if="atLimit"` box is styled to match `FileDropzone`'s dashed border so the page keeps its
shape; the drop zone is replaced by an explanation in the same place, not removed leaving a gap.

"Delete one below" is accurate: the templates table is the next section down, each row opens the
template, and the Delete button is on that page. Do not add a delete control to this notice.

### 9.2 `resources/js/Pages/Plan/Index.vue` — show it with the other quotas

The `quotas` computed lists documents, members and storage. Add templates after documents:

```js
{
    key: "templates",
    label: "Templates",
    icon: LayoutTemplate,
    used: props.usage.templates.used,
    limit: props.usage.templates.limit,
    formatter: formatCount,
    caption: "Stored right now, on every plan",
},
```

Add `LayoutTemplate` to the `lucide-vue-next` import. `usage.templates` is already arriving from
`PlanService::usage()` after 7.2; this just renders it. No controller change.

### 9.3 Build and look

```bash
npm run build
```

With fewer than 5 templates: `/templates` shows the drop zone and "N of 5 templates used" under
the Upload heading. `/plan` shows a Templates card next to Documents.

Upload until you have 5. The drop zone is replaced by "Template limit reached". Open a template,
delete it, land back on `/templates`: the drop zone is back and the caption says "4 of 5".

## 10. Phase 7 — tests for the limit

### 10.1 New file: `tests/Feature/TemplateLimitTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $this->organization = Organization::create(['name' => 'Acme']);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role' => 'owner',
        ]);
    }

    private function seedTemplates(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Template::create([
                'organization_id' => $this->organization->id,
                'name' => "template-{$i}",
                'file_path' => "templates/template-{$i}.pdf",
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
            ]);
        }
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\n".str_repeat('a', 1000));
    }

    public function test_the_fifth_template_is_accepted(): void
    {
        $this->seedTemplates(4);

        $this->actingAs($this->user)
            ->post('/templates', ['file' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('templates', 5);
    }

    public function test_the_sixth_template_is_rejected(): void
    {
        $this->seedTemplates(5);

        $response = $this->actingAs($this->user)->post('/templates', ['file' => $this->pdf()]);

        $response->assertSessionHasErrors([
            'file' => 'You have reached the limit of 5 templates. Delete one to upload another.',
        ]);
        $this->assertDatabaseCount('templates', 5);
        Storage::disk('documents')->assertMissing('templates/new.pdf');
    }

    public function test_deleting_one_makes_room_for_another(): void
    {
        $this->seedTemplates(5);
        $victim = Template::where('name', 'template-3')->first();

        $this->actingAs($this->user)->delete("/templates/{$victim->id}");

        $this->actingAs($this->user)
            ->post('/templates', ['file' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('templates', 5);
    }

    public function test_the_limit_is_per_organisation(): void
    {
        $this->seedTemplates(5);

        $other = Organization::create(['name' => 'Rival']);
        $otherUser = User::factory()->create([
            'organization_id' => $other->id,
            'role' => 'owner',
        ]);

        $this->actingAs($otherUser)
            ->post('/templates', ['file' => $this->pdf()])
            ->assertSessionHasNoErrors();
    }

    public function test_the_templates_page_reports_the_quota(): void
    {
        $this->seedTemplates(2);

        $this->actingAs($this->user)
            ->get('/templates')
            ->assertInertia(fn ($page) => $page
                ->where('templateQuota.used', 2)
                ->where('templateQuota.limit', 5)
            );
    }
}
```

The `assertMissing('templates/new.pdf')` in the sixth-template test is the check for 8.1's
ordering. If the limit check were after `$file->store()`, the file would exist even though the
row does not, and this line would fail. Note the stored filename is generated by Laravel, not
`new.pdf`, so strictly this asserts that *no* file with that name was written; the stronger
check is `assertDirectoryEmpty('templates')` if your Laravel version has it, but the count
assertion above already proves no row was created.

### 10.2 Run everything

```bash
docker exec esign-app php artisan test tests/Feature/TemplateDeleteTest.php tests/Feature/TemplateLimitTest.php
docker exec esign-app php artisan test
```

Eight new tests green. The full suite has the same two pre-existing registration failures as
before and no new ones. (`TemplateUploadTest` from the previous change also still passes: its
`setUp` starts with zero templates, so the limit never triggers.)

## 11. Verification checklist

Tick each only if you saw it happen.

**Delete:**

- [ ] `route:list --name=templates.destroy` shows a `DELETE` route
- [ ] Template page has a red Delete button beside Preview and Prepare
- [ ] Clicking it opens a dialog naming the template and its field count
- [ ] Cancel closes the dialog, nothing deleted
- [ ] Confirm: loading overlay, land on `/templates`, template gone from the list
- [ ] In the database: `select count(*) from template_signature_fields where template_id = '<id>'` is 0
- [ ] In S3 (or the local disk if `DOCUMENTS_DISK` is local): the file is gone
- [ ] `TemplateDeleteTest` — 3 pass

**Limit:**

- [ ] `config/plans.php` has `'templates' => 5` under free, pro **and** enterprise
- [ ] `/templates` with fewer than 5: drop zone visible, "N of 5 templates used"
- [ ] `/templates` with exactly 5: drop zone replaced by "Template limit reached"
- [ ] Delete one, return to `/templates`: drop zone back, "4 of 5"
- [ ] `/plan` shows a Templates card with the same numbers
- [ ] With 5 templates, POST a sixth via the test or curl: rejected, no new row, no new file
- [ ] `TemplateLimitTest` — 5 pass
- [ ] Full suite: no new failures

## 12. Common ways this goes wrong

| Symptom | Cause | Fix |
| --- | --- | --- |
| `template_signature_fields` rows survive the delete | You are not on Postgres, or the migration was edited | Check `DB_CONNECTION=pgsql` and that the migration still has `cascadeOnDelete()` |
| Delete works, file still in S3, no log line | Forgot the `if (! Storage::…->delete())` wrapper | 4.2 |
| Delete works, file still in S3, log line present | S3 credentials or permissions | Not a code bug; the log line is doing its job |
| Dialog does not open | `confirmingDelete` not a `ref`, or `v-model:open` missing | 5.2 |
| Dialog shows "undefined signature fields" | Reading `signature_fields_count` on a page that did not `loadCount` | Only `show()` loads it; the dialog lives on the Show page |
| 405 Method Not Allowed on delete | Route registered as `post` or `get` | 4.1 |
| Sixth upload rejected but file appears in S3 | Limit check placed after `$file->store()` | 8.1 |
| Sixth upload accepted | `'templates'` key missing from the plan the org is on | 7.1, all three plans |
| Page always shows the drop zone | `templateQuota` prop not passed from `index()` | 8.2 |
| Page never shows the drop zone | `atLimit` compares `used > limit` instead of `>=` | 9.1 |
| Plan page crashes: cannot read `templates` of undefined | `usage()` not updated | 7.2 |
| `TemplateUploadTest` starts failing | It seeds 0 templates; if it fails, the limit is reading the wrong org or counting globally | `templatesUsed()` must filter by `organization_id` |

## 13. Definition of done

- `DELETE /templates/{id}` removes the row, its signature fields (via cascade) and its file,
  for the owner's organisation only.
- The Show page has a Delete button behind a dialog that names the template and its field count.
- `config/plans.php` gives every plan `'templates' => 5`; `PlanService` exposes
  `templatesUsed()`, `canAddTemplate()` and a `templates` entry in `usage()`.
- `POST /templates` rejects a sixth template before writing anything to storage.
- The Templates page replaces the drop zone with an explanation at the limit, and the Plan page
  shows the quota.
- Eight new tests pass; the full suite has no new failures.
- No migration, no new dependency, no soft-delete, no manual deletion of field rows.
