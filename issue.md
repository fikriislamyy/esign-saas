# Document uploads: PDF only, 5 MB maximum

## 1. What we are building, in one paragraph

There are two places a user uploads a document: the **Documents** page (`POST /documents`) and the
**Templates** page (`POST /templates`). Today Documents accepts PDF, DOC and DOCX up to 10 MB, and
Templates accepts PDF up to 10 MB. After this change both accept **only PDF, at most 5 MB**, the
server rejects anything else with a clear message, and the upload box tells the user the rule
before they pick a file.

The organisation **logo** upload on the Settings page is an image, not a document. It is out of
scope. Do not touch `OrganizationSettingsController`.

## 2. Read this first: a 5 MB limit does not exist yet, at any layer

Before you change a single validation rule, understand what the server does with a large file
today. Run this:

```bash
docker exec esign-app php -r 'echo ini_get("upload_max_filesize"), " / ", ini_get("post_max_size"), "\n";'
```

You will see `2M / 8M`. That is PHP's own ceiling, and it sits **in front of** Laravel. A 3 MB PDF
never reaches `DocumentController::store()`. PHP discards it, hands Laravel an empty upload, and
Laravel's `file` rule fails with the generic message *"The file failed to upload."* The `max:10240`
rule that is in the code right now has never once fired, because nothing bigger than 2 MB has ever
got past PHP.

So the task has a hidden first step: **raise PHP's limit above 5 MB**, so that Laravel's rule,
with its friendly message, is the thing that says no. If you skip this, you will write
`max:5120`, test with a 3 MB file, see "The file failed to upload", and spend an afternoon
debugging validation that is not the problem.

Nginx is already fine: both `docker/nginx/default.conf` and `docker/render/nginx.conf` set
`client_max_body_size 100M`. Leave them alone.

## 3. Things you must not do

- **Do not** rely on the browser. The `accept=".pdf"` attribute on the file input and the
  client-side size check are conveniences. Anyone can POST a 50 MB `.exe` with `curl`. The server
  rule is the only real gate.
- **Do not** check only the file extension on the server. A file called `virus.pdf` whose bytes are
  a Windows executable must be rejected. Section 5 explains the two rules that together handle this.
- **Do not** write `max:5` or `max:5000000`. Laravel's `max` on a file is in **kilobytes**.
  5 MB is `max:5120`. This is the single most common mistake with this rule.
- **Do not** add a migration or delete existing rows. Documents that were uploaded as DOCX before
  this change stay in the database. This change is about what is accepted from now on.
- **Do not** put the limit in `.env`. It is a product rule, not a deployment setting. Hard-code
  `5120` in the two controllers.
- **Do not** touch the logo upload.

## 4. Where everything is

| Layer | File | What it says today |
| --- | --- | --- |
| PHP ceiling | not in the repo — PHP's built-in default | `upload_max_filesize=2M`, `post_max_size=8M` |
| Documents rule | [app/Http/Controllers/DocumentController.php:54-59](app/Http/Controllers/DocumentController.php#L54-L59) | `mimes:pdf,doc,docx`, `max:10240` |
| Templates rule | [app/Http/Controllers/TemplateController.php:34](app/Http/Controllers/TemplateController.php#L34) | `mimes:pdf`, `max:10240` |
| Drop zone UI | [resources/js/Components/FileDropzone.vue](resources/js/Components/FileDropzone.vue) | "Drag & drop PDF or DOCX files", "Maximum size: 10 MB", `accept=".pdf,.doc,.docx"` |
| Wrapper that shows errors | [resources/js/Components/documents/UploadCard.vue](resources/js/Components/documents/UploadCard.vue) | Renders `form.errors.file` in a red box |
| Pages using the wrapper | `Pages/Documents/Index.vue`, `Pages/Templates/Index.vue` | Both post `form.file` and pass errors to `showError()` |

`FileDropzone` is used by `UploadCard`, and `UploadCard` is used by both pages. So one change to
`FileDropzone` fixes the text on both pages. You do not need to touch the two `Index.vue` files.

## 5. Phase 1 — raise the PHP ceiling (Docker)

### 5.1 New file: `docker/php/uploads.ini`

```ini
upload_max_filesize = 6M
post_max_size = 10M
```

Why 6 and not 5: `upload_max_filesize` measures the raw multipart body, which is a little bigger
than the file itself (boundaries, headers, the CSRF token). If PHP's limit were exactly 5M, a
4.99 MB PDF could still be cut off by PHP and the user would get the generic message instead of
ours. Setting PHP slightly above the product limit means Laravel's `max:5120` is always the rule
that fires for files in the 5–6 MB range. Files above 6 MB still hit PHP's wall and get *"The file
failed to upload."* That is acceptable; the drop zone will have told them 5 MB already.

`post_max_size` must be larger than `upload_max_filesize`. If it is not, PHP silently drops the
entire POST body, including the CSRF token, and Laravel returns a 419 page. That is a confusing
failure and this is the only place it can come from.

### 5.2 Local image: `docker/php/Dockerfile`

Add after the `COPY --from=composer` line:

```dockerfile
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
```

`/usr/local/etc/php/conf.d/` is the directory the official PHP image scans for extra `.ini`
files. You can see the list it currently loads with `docker exec esign-app php --ini`.

### 5.3 Production image: root `Dockerfile`

This is the Render build. The third stage starts at `FROM php:8.3-fpm-bookworm` (line 61). Add
the same `COPY` line next to the nginx one near the bottom of that stage:

```dockerfile
# PHP upload limits
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Nginx configuration
COPY docker/render/nginx.conf /etc/nginx/conf.d/default.conf
```

Both Dockerfiles, not one. If you only do the local one, production still has a 2 MB wall and
nobody notices until a customer complains.

### 5.4 Rebuild and verify

```bash
docker compose build app && docker compose up -d app
docker exec esign-app php -r 'echo ini_get("upload_max_filesize"), " / ", ini_get("post_max_size"), "\n";'
```

You must see `6M / 10M`. If you still see `2M`, the container is running the old image; check
`docker compose ps` and that the build actually ran. **Do not continue to Phase 2 until this
prints 6M.** Everything after this depends on it.

The `-r` check runs PHP CLI, not PHP-FPM. They read the same `conf.d` directory in this image, so
CLI is a fair proxy. If you want to be certain, also restart FPM — `docker compose up -d app` does
that — and upload a 3 MB PDF through the browser once Phase 2 is done.

## 6. Phase 2 — the server rules

### 6.1 The two rules that together mean "a real PDF"

```php
'file' => ['required', 'file', 'extensions:pdf', 'mimes:pdf', 'max:5120'],
```

- `extensions:pdf` looks at the **filename** the browser sent. `report.txt` fails here even if
  its bytes are a PDF. We want this because `DocumentController` derives the document's display
  name from the filename, and downstream code assumes `.pdf`.
- `mimes:pdf` looks at the **bytes**, not the name. Laravel sniffs the file's magic number; a
  Windows executable renamed to `.pdf` fails here. This is the security rule.
- `max:5120` is 5 MB in kilobytes.

You need both `extensions` and `mimes`. Each catches something the other lets through.
(`extensions` was added in Laravel 10.15; this project is on 10.50, so it is available.)

### 6.2 Documents: `app/Http/Controllers/DocumentController.php`

Replace the rule array and add a messages array. The whole `validate()` call becomes:

```php
$request->validate([
    'file' => ['required', 'file', 'extensions:pdf', 'mimes:pdf', 'max:5120'],
], [
    'file.extensions' => 'Only PDF files can be uploaded.',
    'file.mimes' => 'Only PDF files can be uploaded.',
    'file.max' => 'The file must be 5 MB or smaller.',
]);
```

Nothing below the `validate()` call changes. The plan-limit checks, the `store()`, the
`Document::create()` all stay exactly as they are.

The two "Only PDF" messages are deliberately identical. The user does not care which rule caught
it; they care what to do. Tell them what is allowed, not what went wrong.

### 6.3 Templates: `app/Http/Controllers/TemplateController.php`

Same shape:

```php
$request->validate([
    'file' => ['required', 'file', 'extensions:pdf', 'mimes:pdf', 'max:5120'],
], [
    'file.extensions' => 'Only PDF files can be uploaded.',
    'file.mimes' => 'Only PDF files can be uploaded.',
    'file.max' => 'The file must be 5 MB or smaller.',
]);
```

Yes, the same five lines twice. Two controllers, two copies. Do not create a shared rule class
or a config key for this; the duplication is nine lines and anyone reading either controller sees
the whole rule without opening another file.

### 6.4 Check it from the terminal before touching Vue

Log in through the browser, then in DevTools → Application → Cookies copy the `XSRF-TOKEN` and
the session cookie. Or, simpler, skip curl and go straight to Phase 4's tests, which is what they
are for. Section 8 has the curl commands if you want them.

## 7. Phase 3 — the drop zone

### 7.1 `resources/js/Components/FileDropzone.vue`

Three text changes and one attribute in the template:

```vue
<h3 class="font-medium">Drop document here</h3>

<p class="text-sm text-muted-foreground mt-1">
    Drag & drop a PDF file
</p>

<p class="text-xs text-muted-foreground mt-2">
    Maximum size: 5 MB
</p>

<input
    type="file"
    class="hidden"
    accept="application/pdf,.pdf"
    @change="onInputChange"
/>
```

`accept` takes both the MIME type and the extension because browsers differ in which one they
honour in the file picker. This only filters the picker; drag-and-drop ignores it entirely,
which is why the next part exists.

### 7.2 Client-side pre-check, so the user does not wait for a round trip

In the same file, change `selectFile` so an obviously wrong file is rejected before it is even
attached to the form:

```js
const emit = defineEmits(["select", "error"]);

const MAX_BYTES = 5 * 1024 * 1024;

const selectFile = (selectedFile) => {
    if (!selectedFile) return;

    if (!selectedFile.name.toLowerCase().endsWith(".pdf")) {
        emit("error", "Only PDF files can be uploaded.");
        return;
    }

    if (selectedFile.size > MAX_BYTES) {
        emit("error", "The file must be 5 MB or smaller.");
        return;
    }

    file.value = selectedFile;
    emit("select", selectedFile);
};
```

Two notes:

- Check the **name**, not `selectedFile.type`. Browsers set `type` from the OS's extension
  registry, and on some machines a `.pdf` reports an empty string. The name is reliable; the bytes
  are the server's job.
- The messages are word-for-word the same as the server's. If the server ever disagrees with the
  client, the user sees the server's version, and it reads the same. Keep them in sync.

### 7.3 `resources/js/Components/documents/UploadCard.vue`

`UploadCard` already renders `form.errors.file` in a red box. Wire the new event into it:

```vue
<FileDropzone @select="selectFile" @error="showError" />
```

and in the script:

```js
function selectFile(file) {
    props.form.clearErrors("file");
    props.form.file = file;
}

function showError(message) {
    props.form.file = null;
    props.form.setError("file", message);
}
```

`setError` and `clearErrors` are built into Inertia's `useForm`; they are the same mechanism the
server's validation errors arrive through. So a client-side rejection and a server-side rejection
render in the same red box, in the same place, in the same words. The user cannot tell which one
caught it, and should not have to.

`clearErrors("file")` on a fresh selection matters: without it, a user who drops a 6 MB file, sees
the error, then drops a valid 2 MB file, still sees the stale error until they submit.

### 7.4 Build and look

```bash
npm run build
```

Open `/documents`. The box says "Drag & drop a PDF file" and "Maximum size: 5 MB". Drag a `.docx`
onto it: red box, "Only PDF files can be uploaded.", nothing attached, Upload button stays
disabled. Drag a real PDF: the file card appears, red box gone.

Repeat on `/templates`. Same component, so same result, but look anyway.

## 8. Phase 4 — tests

There are no upload tests today. Add two files. They are nearly identical; the second is left as
a copy-and-adjust exercise once the first passes.

### 8.1 The fake-file trap, explained before you hit it

`UploadedFile::fake()->create('a.pdf', 5120)` makes a 5 MB file **full of null bytes** and tells
Laravel its MIME type is whatever you pass as the third argument. But `mimes:pdf` does not trust
that argument; it sniffs the bytes. Null bytes sniff as `application/octet-stream`, so the rule
fails, and your "5 MB PDF should be accepted" test fails for the wrong reason.

Use `createWithContent()` and start the content with a real PDF header. PHP's `finfo` recognises
the `%PDF-` magic number and reports `application/pdf`; the rest of the file can be padding.

```php
private function pdf(string $name, int $bytes): UploadedFile
{
    $header = "%PDF-1.4\n";

    return UploadedFile::fake()->createWithContent(
        $name,
        $header.str_repeat('a', $bytes - strlen($header))
    );
}
```

### 8.2 A user with an organisation

`User::factory()` does not create an organisation, and `DocumentController::store()` needs one
(for the plan checks and the `organization_id` column). Build it by hand in `setUp()`. The
`Organization` model generates its own UUID and slug on create, and `PlanService` creates a free
subscription on first use, so this is enough:

```php
$this->organization = Organization::create(['name' => 'Acme']);

$this->user = User::factory()->create([
    'organization_id' => $this->organization->id,
    'role' => 'owner',
]);
```

The free plan allows 3 documents per week and 100 MB of storage, so none of the tests below
trip a plan limit. If you ever see *"Your plan allows 3 documents per week"* in a test, that is
why.

### 8.3 New file: `tests/Feature/DocumentUploadTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $organization = Organization::create(['name' => 'Acme']);

        $this->user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => 'owner',
        ]);
    }

    private function pdf(string $name, int $bytes): UploadedFile
    {
        $header = "%PDF-1.4\n";

        return UploadedFile::fake()->createWithContent(
            $name,
            $header.str_repeat('a', $bytes - strlen($header))
        );
    }

    public function test_a_pdf_of_exactly_5mb_is_accepted(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => $this->pdf('contract.pdf', 5 * 1024 * 1024),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documents', ['name' => 'contract']);
    }

    public function test_a_pdf_one_byte_over_5mb_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => $this->pdf('contract.pdf', 5 * 1024 * 1024 + 1),
        ]);

        $response->assertSessionHasErrors(['file' => 'The file must be 5 MB or smaller.']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_docx_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => UploadedFile::fake()->create('contract.docx', 100),
        ]);

        $response->assertSessionHasErrors(['file' => 'Only PDF files can be uploaded.']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_non_pdf_renamed_to_pdf_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('contract.pdf', str_repeat("\0", 1024));

        $response = $this->actingAs($this->user)->post('/documents', ['file' => $file]);

        $response->assertSessionHasErrors(['file' => 'Only PDF files can be uploaded.']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_real_pdf_with_the_wrong_extension_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post('/documents', [
            'file' => $this->pdf('contract.txt', 1024),
        ]);

        $response->assertSessionHasErrors(['file' => 'Only PDF files can be uploaded.']);
        $this->assertDatabaseCount('documents', 0);
    }
}
```

The last two tests are the ones that justify having both `extensions` and `mimes`. Comment out
`extensions:pdf` in the controller and the `.txt` test fails. Comment out `mimes:pdf` and the
renamed-null-bytes test fails. Each rule has a test that only it can pass.

`Storage::fake('documents')` swaps the S3 disk for a temp directory for the duration of the test,
so the accepted upload does not try to reach AWS.

### 8.4 New file: `tests/Feature/TemplateUploadTest.php`

Copy the file above. Change the class name, change `/documents` to `/templates`, change the
`documents` table to `templates` in the two database assertions. Everything else is the same.

### 8.5 Run them

```bash
docker exec esign-app php artisan test tests/Feature/DocumentUploadTest.php tests/Feature/TemplateUploadTest.php
```

Ten tests, all green. Then run the whole suite to be sure nothing else moved:

```bash
docker exec esign-app php artisan test
```

Two registration tests in `tests/Feature/Auth` fail before you start and will still fail after.
They are unrelated to uploads (they post a registration form that predates the phone-number
fields). Do not try to fix them in this change; just confirm the count of failures did not go up.

### 8.6 curl, if you want to see it from outside

With the app running and a logged-in browser session, DevTools → Application → Cookies. Copy the
values of `XSRF-TOKEN` and `laravel_session`. Then:

```bash
head -c 6000000 /dev/urandom > /tmp/big.pdf
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/documents \
  -H "X-XSRF-TOKEN: <decoded XSRF value>" \
  -b "laravel_session=<value>; XSRF-TOKEN=<value>" \
  -F "file=@/tmp/big.pdf"
```

A 302 back to `/documents` with the error in the session is what you want. Honestly, the tests in
8.3 tell you the same thing with less copying of cookies.

## 9. Verification checklist

Tick each only if you saw it happen.

**PHP ceiling:**

- [ ] `docker exec esign-app php -r 'echo ini_get("upload_max_filesize");'` prints `6M`
- [ ] `docker/php/uploads.ini` is `COPY`'d in **both** `docker/php/Dockerfile` and the root `Dockerfile`

**Server:**

- [ ] `php artisan test tests/Feature/DocumentUploadTest.php` — 5 pass
- [ ] `php artisan test tests/Feature/TemplateUploadTest.php` — 5 pass
- [ ] Full suite: no new failures beyond the two pre-existing registration ones

**Browser, `/documents`:**

- [ ] Box reads "Drag & drop a PDF file" and "Maximum size: 5 MB"
- [ ] Clicking the box opens a picker that filters to PDF
- [ ] Drag a `.docx` → red "Only PDF files can be uploaded.", nothing attached, button disabled
- [ ] Drag a 6 MB PDF → red "The file must be 5 MB or smaller.", nothing attached
- [ ] After either error, drag a valid 1 MB PDF → red box disappears, file card appears
- [ ] Upload the valid PDF → success toast, document listed
- [ ] Upload a 3 MB PDF → **succeeds**. (This is the Phase 1 check. Before this change it failed.)
- [ ] Temporarily set `MAX_BYTES` to something huge in `FileDropzone.vue`, rebuild, drag a 6 MB PDF,
      click Upload → the **server's** red message appears. Put `MAX_BYTES` back. This proves the
      server rule works when the client check is bypassed.

**Browser, `/templates`:**

- [ ] Same first five checks. It is the same component, but look.

**Untouched:**

- [ ] Settings → Organisation → logo upload still accepts a PNG

## 10. Common ways this goes wrong

| Symptom | Cause | Fix |
| --- | --- | --- |
| Every file over 2 MB: "The file failed to upload." | Phase 1 skipped, or container not rebuilt | 5.4 |
| Large upload returns a 419 "Page Expired" | `post_max_size` ≤ `upload_max_filesize` | 5.1 |
| `max:5` rejects everything | Kilobytes, not megabytes | `max:5120` |
| "5 MB PDF accepted" test fails on `mimes` | `UploadedFile::fake()->create()` makes null bytes | 8.1, use `createWithContent` with `%PDF-` |
| Test: "Your plan allows 3 documents per week" | User has no organisation, or too many docs created in one test | 8.2 |
| Test tries to reach S3 | `Storage::fake('documents')` missing from `setUp()` | 8.3 |
| Drop zone still says DOCX on `/templates` | Edited a page instead of `FileDropzone.vue` | 7.1 |
| Stale error stays after picking a valid file | `clearErrors("file")` missing in `selectFile` | 7.3 |
| `.pdf` from a Mac rejected by client check | Checked `file.type` instead of `file.name` | 7.2 |
| Works locally, 2 MB wall in production | `COPY uploads.ini` only in the local Dockerfile | 5.3 |

## 11. Definition of done

- Both `POST /documents` and `POST /templates` reject non-PDF files and files over 5 MB with
  the messages in 6.2, and accept a 5 MB PDF.
- PHP's `upload_max_filesize` is 6M in both the local and the production image.
- The drop zone says PDF / 5 MB and rejects wrong files before submit, using the same words as
  the server.
- Ten new tests pass; the full suite has no new failures.
- No migration, no new dependency, no change to the logo upload.
