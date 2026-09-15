# Feature: embed a real digital signature in signed PDFs

## What happens now

When a document is fully signed and uploaded to a digital-signature verifier (for example
https://docupilot.com/tools/e-sign-validate), the result is:

- **Dropbox Sign PDF:** "Document integrity verified" **plus** a `Signature #1` block
  (Status: Signature valid, Certificate: Trusted, Signed by: Dropbox Sign, Reason: Tamper Proofing).
- **Our PDF:** "Document integrity verified" only. **No signature block at all.**

That is because our signed PDF only contains the signature *pictures* drawn on the pages. A
verifier looks for a cryptographic signature (a PKCS#7 blob inside the PDF, made with a
certificate's private key) and we never add one. Anybody could edit our PDF and nobody could tell.

## Goal

After this change, uploading one of our signed PDFs to a verifier shows a `Signature #1` block with:

| Row | Expected value |
|---|---|
| Status | **Signature valid** |
| Certificate | *Untrusted / Unknown issuer* with the dev cert (see "About Trusted" below) |
| Signed by | the app name (`APP_NAME`, e.g. `EZSign`) |
| Algorithm | SHA256withRSA |
| Reason | `Signed by Alice Smith, Bob Jones` |

The visible signature images must still appear exactly where they do today.

## How it works today (read this first)

The only place the signed PDF is built is `SigningController::finish()`,
`app/Http/Controllers/SigningController.php` ~lines 588–681:

1. Downloads the **original** upload (`$document->file_path`) to a temp file.
2. `new Fpdi()` (that is `setasign\Fpdi\Fpdi`, which extends **FPDF**).
3. Loops over every page: `importPage` → `AddPage` → `useTemplate` → `Image()` for each
   signature field that has a `signature_image` (a base64 PNG drawn on the signing page).
4. `$pdf->Output('F', $temporarySignedPath)` then uploads it to `documents/signed/{id}.pdf`
   and stores that in `$document->signed_path`.

Important: **every** signer's `finish()` rebuilds the PDF from the *original* file and re-stamps
*all* fields signed so far. So the final PDF is always produced in one go. That is exactly what
we want for the digital signature: one cryptographic signature covering the finished document,
same as Dropbox Sign's single "Tamper Proofing" signature.

## Approach

FPDF cannot sign. **TCPDF** can (`$pdf->setSignature(...)`, pure PHP, uses the `openssl`
extension that PHP already has), and the FPDI package we already use ships a TCPDF flavour:
`setasign\Fpdi\Tcpdf\Fpdi`. So the change is:

- swap the PDF class in `finish()` from the FPDF flavour to the TCPDF flavour,
- tell TCPDF which certificate + private key to sign with,
- everything else (page loop, `Image()`, `Output()`) stays the same.

The certificate for local/dev is a self-signed one we generate with `openssl`. That gives
"Signature valid" but "Certificate not trusted". Getting "Trusted" needs a paid certificate
(see below) — that is a purchase, not code, and is **out of scope** for this issue.

## Steps

### Step 1 — Add the GD extension to Docker (needed for transparent PNGs)

The signature images are PNGs with a transparent background (`signaturePad.toDataURL("image/png")`
in `resources/js/Components/documents/signing/SignatureDialog.vue`). FPDF handles those itself;
TCPDF **needs the GD extension** or it throws
`TCPDF requires the Imagick or GD extension to handle PNG images with alpha channel`.

There are three `docker-php-ext-install` blocks. Add GD to all of them:

- `docker/php/Dockerfile` (local dev)
- `Dockerfile` stage 1 (`FROM php:8.3-cli-bookworm AS vendor`, ~line 6)
- `Dockerfile` runtime stage (`FROM php:8.3-fpm-bookworm`, ~line 58)

In each block, add the three `lib*` packages to the `apt-get install` list, and add the
`docker-php-ext-configure gd` line **before** `docker-php-ext-install`, then `gd` to the
install list. Example for `docker/php/Dockerfile` — the other two are the same edit:

```dockerfile
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        bcmath \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
```

Rebuild and check:

```bash
docker compose build app && docker compose up -d
docker exec esign-app php -m | grep -E "^(gd|openssl)$"   # must print both
```

### Step 2 — Install TCPDF

```bash
docker exec esign-app composer require tecnickcom/tcpdf:^6.7
```

Do **not** remove `setasign/fpdf` or `setasign/fpdi`; FPDI is still the page importer.

### Step 3 — Generate a dev signing certificate

Run on the host (WSL) from the project root:

```bash
mkdir -p storage/certs
openssl req -x509 -newkey rsa:2048 -sha256 -days 3650 -nodes \
  -keyout storage/certs/signing.key \
  -out    storage/certs/signing.crt \
  -subj   "/CN=EZSign Development/O=EZSign"
```

`-nodes` means the key has no password (empty string in config below).

Add to `.gitignore` (the key must never be committed):

```
/storage/certs/
```

Add to `.env` (and document in the README's env section if there is one):

```
PDF_SIGN_CERT=/var/www/storage/certs/signing.crt
PDF_SIGN_KEY=/var/www/storage/certs/signing.key
PDF_SIGN_KEY_PASSWORD=
```

Paths are **container** paths (`/var/www` is the bind mount of the project). In production
(Render), upload the two files as Secret Files and point these variables at them.

### Step 4 — Create `config/signing.php`

New file:

```php
<?php

return [
    'certificate' => env('PDF_SIGN_CERT'),
    'private_key' => env('PDF_SIGN_KEY'),
    'password' => env('PDF_SIGN_KEY_PASSWORD', ''),
];
```

### Step 5 — Switch the PDF builder to TCPDF in `SigningController`

All edits are in `app/Http/Controllers/SigningController.php`.

**5a. Change the import** (line 9):

```php
// before
use setasign\Fpdi\Fpdi;

// after
use setasign\Fpdi\Tcpdf\Fpdi;
```

**5b. Replace `$pdf = new Fpdi();`** (~line 588) with TCPDF setup. TCPDF prints a header line,
footer page numbers and adds margins by default, so every one of these lines matters:

```php
$pdf = new Fpdi();

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false, 0);
```

**5c. Register the signature** right after that block (before the page loop):

```php
$signerNames = $document->signers()
    ->whereNotNull('signed_at')
    ->orderBy('signing_order')
    ->pluck('name')
    ->all();

$pdf->setSignature(
    'file://' . config('signing.certificate'),
    'file://' . config('signing.private_key'),
    config('signing.password'),
    '',
    1,
    [
        'Name' => config('app.name'),
        'Location' => '',
        'Reason' => 'Signed by ' . implode(', ', $signerNames),
        'ContactInfo' => config('mail.from.address'),
    ]
);
```

What the arguments mean (`setSignature($cert, $key, $password, $extraCerts, $certType, $info)`):

- `'file://'` prefix is required — TCPDF passes the string straight to `openssl_pkcs7_sign`.
- `$extraCerts` (`''`) is where an intermediate CA chain goes when we buy a real certificate.
- `$certType = 1` = "certification signature, no changes allowed" — the strongest tamper seal.
  Safe for us because the PDF is always rebuilt from the original, never edited in place.
- `$info` is what the verifier prints as Signed by / Reason / etc.

**5d. Nothing else changes.** `AddPage($orientation, [$w, $h])`, `useTemplate()`, `Image($file,
$x, $y, $w, $h)` and `Output('F', $path)` have the same signatures in TCPDF. TCPDF performs the
actual signing inside `Output()`.

### Step 6 — Fail loudly if the certificate is missing

At the top of the `try` block in `finish()` (before downloading the source PDF), add:

```php
foreach (['certificate', 'private_key'] as $key) {
    $path = config("signing.$key");

    if (!$path || !is_readable($path)) {
        throw new \RuntimeException(
            "PDF signing $key is not configured or not readable: " . ($path ?: '(empty)')
        );
    }
}
```

Without this, a missing key produces a cryptic OpenSSL error deep inside TCPDF.

## Test

### Verify the signature is there

```bash
sudo apt-get install -y poppler-utils   # gives the `pdfsig` command (host, one time)
```

1. `docker compose up -d`, `npm run dev`.
2. Upload a PDF, add 2 signers, place a field for each, send, and sign as **both** signers
   (use Mailpit at http://localhost:8025 for the OTP emails).
3. On the document page click **Download** to get the signed PDF, then:
   ```bash
   pdfsig ~/Downloads/<file>.pdf
   ```
   Expected output contains:
   ```
   Signature #1:
     - Signer Certificate Common Name: EZSign Development
     - Signing Time: ...
     - Signature Validation: Signature is Valid.
     - Certificate Validation: Certificate issuer isn't Trusted.
   ```
   "issuer isn't Trusted" is **correct** for a self-signed cert — see "About Trusted".
4. Upload the same file to https://docupilot.com/tools/e-sign-validate. Expect a
   `Signature #1` block with Status **Signature valid** and Reason
   `Signed by <signer 1>, <signer 2>`.

### Verify nothing else broke

5. Open the downloaded PDF in a viewer: both signature drawings are on the right pages, at the
   same positions and sizes as before this change, with no white box around them (that would
   mean GD is missing / transparency lost), no header line at the top and no page number at
   the bottom (that would mean step 5b was skipped).
6. Test a landscape PDF and a multi-page PDF with a field on page 2 — page size and orientation
   must match the original.
7. After signer 1 finishes but before signer 2, open signer 2's signing link. The preview
   (`SigningController::pdf()` serves `signed_path`) must still load.
8. Tamper test: open the signed PDF in any editor, change one character, save, run `pdfsig`
   again. Expected: `Signature Validation: Signature is Invalid` — proving the seal works.

Finally `docker exec esign-app ./vendor/bin/pint --test` and `npm run build` still pass.

## About "Trusted"

Verifiers show **Certificate: Trusted** only when the certificate chains to a root in the Adobe
Approved Trust List (AATL) / EU trust list. Dropbox Sign pays for such a certificate. A
self-signed cert can never be "Trusted"; that is a business purchase (GlobalSign, Sectigo,
Entrust, DigiCert all sell "document signing" certificates), not a code change. When one is
bought, the only code touch is: put its PEM + key in `PDF_SIGN_CERT` / `PDF_SIGN_KEY` and pass
the issuer chain PEM as the 4th argument (`$extraCerts`) of `setSignature()`.

## Gotchas

- **GD is mandatory** (step 1). The error only appears at signing time, so a missing extension
  looks like "signing works in my head but the request 500s".
- TCPDF signs during `Output()`, so `setSignature()` must be called **before** `Output()` —
  anywhere before is fine; before the page loop is clearest.
- Keep the `'file://'` prefix on both paths. Passing a bare path makes OpenSSL try to parse the
  path string itself as PEM and fail with "unable to get private key".
- Don't switch `setasign\Fpdi\Fpdi` anywhere else — only the signed-PDF builder needs TCPDF.
- Free FPDI cannot import PDFs that use compressed object streams (PDF 1.5+ "object streams").
  That limitation exists today with FPDF too and is not part of this issue.
- The verifier's "Signed on: 1970" date in the Dropbox screenshot is a quirk of that website, not
  something to replicate.
- `storage/certs/` must be in `.gitignore` **before** the first commit on this branch. Run
  `git status` and make sure `signing.key` is not listed.

## Definition of done

- [ ] GD added to all three Docker build blocks; `php -m` in the container lists `gd`
- [ ] `tecnickcom/tcpdf` in `composer.json` / `composer.lock`
- [ ] `config/signing.php` + three `PDF_SIGN_*` env vars; `storage/certs/` gitignored
- [ ] `SigningController::finish()` uses `setasign\Fpdi\Tcpdf\Fpdi`, disables header/footer/
      margins/auto page break, calls `setSignature()` with the signer names as Reason
- [ ] Missing cert/key throws a clear `RuntimeException`
- [ ] `pdfsig` reports `Signature is Valid` on a downloaded signed PDF; tampered copy reports Invalid
- [ ] Docupilot shows a `Signature #1` block with Status "Signature valid"
- [ ] Signature images render identically to before (position, size, transparency, no TCPDF
      header/footer)
- [ ] `pint --test` and `npm run build` pass
- [ ] Branch `feature/pdf-digital-signature`, PR to `main`. Suggested commit message:

```
feat: seal signed PDFs with a PKCS#7 digital signature

Signed documents only carried the signature images, so verifiers saw
no signature at all and any edit went undetected. Build the final PDF
with TCPDF (via FPDI's TCPDF bridge) and certify it with the configured
certificate so verifiers report "Signature valid".
```
