# Bug: PDF editor load is hijacked by Internet Download Manager (IDM)

## Symptom

Open any page that renders a PDF in the browser (Documents → Prepare, Templates → Prepare,
or the `/sign/{token}` signing page). If the user has the **Internet Download Manager**
browser extension installed, a "Download File Info" dialog pops up the moment the page
mounts, offering to save `preview.pdf`. The canvas behind it stays blank until the user
cancels the dialog, and on some IDM versions the PDF never renders at all.

## Root cause

All three pages load the PDF like this:

```js
pdfDoc = await pdfjsLib.getDocument({ url: route("documents.preview", id) }).promise;
```

pdf.js then fetches that URL itself. The `preview` endpoint answers with
`Content-Type: application/pdf` and `Content-Disposition: inline; filename="….pdf"`.
IDM's extension watches every network response in the tab and takes over any whose
content type or filename looks like a downloadable file — PDFs are on its list. So IDM
grabs the response before pdf.js ever sees it.

**The fix is to stop sending the bytes as a PDF response.** Instead, add a small JSON
endpoint that returns the file as a base64 string. IDM ignores `application/json`. The
browser decodes the base64 into bytes and hands them to pdf.js via `getDocument({ data })`
instead of `getDocument({ url })`. Rendering, zoom, fields — nothing else changes.

The existing `preview` and `download` routes stay as they are. They back the
"Preview" / "Download" buttons that open the PDF in a new tab, where a real PDF response is
the correct behaviour.

## Files you will touch

| What | File |
|---|---|
| New JSON endpoint for documents | `app/Http/Controllers/DocumentController.php` |
| New JSON endpoint for templates | `app/Http/Controllers/TemplateController.php` |
| New JSON endpoint for signing | `app/Http/Controllers/SigningController.php` |
| Three new routes | `routes/web.php` |
| New shared loader (create) | `resources/js/Composables/usePdfLoader.js` |
| Swap the loader in | `resources/js/Pages/Documents/Prepare.vue` |
| Swap the loader in | `resources/js/Pages/Templates/Prepare.vue` |
| Swap the loader in | `resources/js/Pages/Signing/Show.vue` |

Conventions to keep:

- Ownership check on every document/template action:
  `abort_unless($model->organization_id === $request->user()->organization_id, 403);`
- Files live on the disk named by `env('DOCUMENTS_DISK', 'documents')`.
- Run artisan inside the container: `docker compose exec app php artisan …`

---

## Step-by-step

### Step 0 — Reproduce first

1. Install the IDM browser extension (or ask someone who has it) and open
   `http://localhost:8000/documents/<id>/prepare`.
2. Confirm the "Download File Info" dialog appears on load.
3. Open DevTools → Network, reload, click the `preview` request: note
   `Content-Type: application/pdf`. That is the thing we are changing.

### Step 1 — Document JSON endpoint

In `app/Http/Controllers/DocumentController.php`, add this method right **after** the
existing `preview()` method. It picks the same file `preview()` picks (signed copy if it
exists, otherwise the original) but returns JSON:

```php
public function pdf(Request $request, Document $document)
{
    abort_unless(
        $document->organization_id === $request->user()->organization_id,
        403
    );

    $filePath = $document->signed_path ?: $document->file_path;

    abort_unless($filePath, 404);

    $contents = Storage::disk(env('DOCUMENTS_DISK', 'documents'))->get($filePath);

    return response()->json([
        'data' => base64_encode($contents),
    ]);
}
```

`Storage` is already imported at the top of this controller.

### Step 2 — Template JSON endpoint

In `app/Http/Controllers/TemplateController.php`, add after `preview()`:

```php
public function pdf(Request $request, Template $template)
{
    abort_unless(
        $template->organization_id === $request->user()->organization_id,
        403
    );

    $contents = Storage::disk(env('DOCUMENTS_DISK', 'documents'))->get($template->file_path);

    return response()->json([
        'data' => base64_encode($contents),
    ]);
}
```

### Step 3 — Signing JSON endpoint

The signing page is opened by a token, not by a logged-in document owner, so it gets its
own endpoint keyed by the token. In `app/Http/Controllers/SigningController.php`, add a
method next to `show()`:

```php
public function pdf(string $token)
{
    $signer = DocumentSigner::with('document')
        ->where('token', $token)
        ->firstOrFail();

    $document = $signer->document;

    $filePath = $document->signed_path ?: $document->file_path;

    abort_unless($filePath, 404);

    $contents = Storage::disk(env('DOCUMENTS_DISK', 'documents'))->get($filePath);

    return response()->json([
        'data' => base64_encode($contents),
    ]);
}
```

Check the top of the file already has `use App\Models\DocumentSigner;` and
`use Illuminate\Support\Facades\Storage;` — both are used elsewhere in this controller, so
they should be there. Add them if not.

### Step 4 — Routes

In `routes/web.php`:

1. Next to the other public `/sign/{token}/…` routes (outside the auth group), add:

```php
Route::get(
    '/sign/{token}/pdf',
    [SigningController::class, 'pdf']
)->name('signing.pdf');
```

2. Inside the `Route::middleware('auth', 'verified')` group, right after
   `documents.preview`, add:

```php
Route::get(
    '/documents/{document}/pdf',
    [DocumentController::class, 'pdf']
)->name('documents.pdf');
```

3. Right after `templates.preview`, add:

```php
Route::get('/templates/{template}/pdf', [TemplateController::class, 'pdf'])
    ->name('templates.pdf');
```

Verify:

```bash
docker compose exec app php artisan route:list --name=pdf
```

You should see exactly three routes: `documents.pdf`, `templates.pdf`, `signing.pdf`.

Quick manual check before touching the frontend — log in, then open
`http://localhost:8000/documents/<id>/pdf` in a tab. You should see
`{"data":"JVBERi0xLj…"}` (every PDF's base64 starts with `JVBERi`). IDM will **not** pop
up on this URL.

### Step 5 — Shared loader composable

Create `resources/js/Composables/usePdfLoader.js`:

```js
import axios from "axios";
import * as pdfjsLib from "pdfjs-dist";

function base64ToBytes(base64) {
    const binary = atob(base64);

    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}

// Fetches the PDF as JSON/base64 so download managers don't intercept it,
// then hands raw bytes to pdf.js instead of a URL.
export async function loadPdf(url) {
    const response = await axios.get(url);

    const bytes = base64ToBytes(response.data.data);

    return pdfjsLib.getDocument({ data: bytes }).promise;
}
```

`loadPdf()` returns the same `pdfDoc` object the pages already use
(`pdfDoc.numPages`, `pdfDoc.getPage(n)`), so nothing downstream changes.

### Step 6 — Swap the loader into `Documents/Prepare.vue`

1. Add the import next to the other composable import:

```js
import { loadPdf } from "@/Composables/usePdfLoader";
```

2. In `onMounted`, find this block (around line 580):

```js
const pdfUrl = route("documents.preview", props.document.id);

console.log("Loading PDF:", pdfUrl);

pdfDoc = await pdfjsLib.getDocument({
    url: pdfUrl,
}).promise;
```

Replace it with:

```js
pdfDoc = await loadPdf(route("documents.pdf", props.document.id));
```

Leave the `pdfjsLib` import and the `GlobalWorkerOptions.workerSrc` lines at the top of
the file alone — `renderPage()` still needs pdf.js and the worker must be configured
before the first `getDocument` call.

### Step 7 — Swap the loader into `Templates/Prepare.vue`

Same two edits. The block to replace is around line 538 and uses
`route("templates.preview", props.template.id)`. Replace it with:

```js
pdfDoc = await loadPdf(route("templates.pdf", props.template.id));
```

### Step 8 — Swap the loader into `Signing/Show.vue`

Same two edits. The block is at the top of `onMounted` (around line 442):

```js
const pdfUrl = route("documents.preview", props.document.id);
showLoading("Loading document...");
try {
    pdfDoc = await pdfjsLib.getDocument({
        url: pdfUrl,
    }).promise;
```

becomes:

```js
showLoading("Loading document...");
try {
    pdfDoc = await loadPdf(route("signing.pdf", props.signer.token));
```

Check what the signing page's props are called — open the `defineProps` at the top of
`Signing/Show.vue` and use whichever prop carries the signer token (it is the same token
that is in the page URL `/sign/{token}`). If no prop has it, read it from the URL:

```js
const token = window.location.pathname.split("/")[2];
pdfDoc = await loadPdf(route("signing.pdf", token));
```

### Step 9 — Build and verify

```bash
npm run build
```

Then, **with the IDM extension enabled**:

1. `/documents/<id>/prepare` → no IDM dialog, PDF renders, fields load, drag/resize/delete
   still save.
2. `/templates/<id>/prepare` → same.
3. Open a signing link `/sign/<token>` → no IDM dialog, PDF renders, you can sign.
4. DevTools → Network on any of those pages: the request that loads the PDF is now
   `…/pdf` with `Content-Type: application/json`. There is no request to `…/preview`.
5. Document → Show → **Preview Document** still opens the PDF in a new tab (IDM may
   catch that one — expected, it's a real file view).
6. Log in as a user from another organization and hit `/documents/<id>/pdf` directly
   → 403. Hit `/sign/not-a-real-token/pdf` → 404.

---

## Gotchas

- **Don't change `preview()` or `download()`.** They are for the new-tab buttons. Only the
  in-page editors move to the JSON endpoint.
- **Don't try `Content-Disposition: attachment`, `application/octet-stream`, or renaming
  the URL.** IDM catches all of those too. The only reliable escape is a content type IDM
  doesn't hook — JSON.
- `atob` + a `for` loop is fine for 10 MB PDFs (the upload cap). Don't reach for a
  library.
- The three `pdf()` methods look nearly identical. That's OK — three explicit methods are
  easier to read and secure (each has its own ownership check) than a shared helper.
- If the canvas is blank after the change, open the console: a wrong `route()` name in the
  `loadPdf(...)` call is the usual cause.

## Definition of done

- [ ] `documents.pdf`, `templates.pdf`, `signing.pdf` routes return `{"data": "<base64>"}`
- [ ] `documents.pdf` / `templates.pdf` return 403 for another organisation's user
- [ ] `signing.pdf` returns 404 for an unknown token
- [ ] `usePdfLoader.js` exists and all three pages call `loadPdf(...)` instead of
      `pdfjsLib.getDocument({ url })`
- [ ] No `…/preview` request appears in the Network tab on the three editor pages
- [ ] IDM no longer pops up on page load; PDF renders and existing interactions work
- [ ] "Preview" / "Download" buttons unchanged
- [ ] `npm run build` passes
- [ ] One commit on branch `fix/pdf-loader-idm`:

```
fix: load editor PDFs as base64 JSON so download managers don't intercept

pdf.js fetched the preview URL directly, which answers with
application/pdf and gets hijacked by IDM-style extensions. The editors
now fetch a JSON payload and pass raw bytes to pdf.js instead.
```
