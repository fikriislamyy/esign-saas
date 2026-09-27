<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Mail\SignatureRequestMail;
use App\Models\DocumentSigner;
use App\Observability\Telemetry;
use App\Services\SigningOtpService;
use App\Services\SigningPricingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SigningController extends Controller
{
    public function __construct(
        protected SigningOtpService $otpService
    ) {}

    public function show(string $token)
    {
        $signer = DocumentSigner::with([
            'document.signatureFields.signer',
        ])
            ->where('token', $token)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Already Signed
        |--------------------------------------------------------------------------
        */

        if (
            $signer->status === 'signed' ||
            $signer->signed_at !== null
        ) {
            app(Telemetry::class)->event('signing.submit.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'already_signed',
            ]);

            abort(
                403,
                'This signing request has already been completed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Enforce Sequential Signing
        |--------------------------------------------------------------------------
        */

        $isSequential = $signer->document
            ->signers()
            ->where('signing_order', '>', 0)
            ->exists();

        if ($isSequential) {
            $this->ensureSigningOrder($signer);
        }

        /*
        |--------------------------------------------------------------------------
        | OTP Verification
        |--------------------------------------------------------------------------
        */

        if (! $this->otpService->isVerified($signer)) {
            return Inertia::render('Signing/Otp', [
                'token' => $signer->token,
                'email' => $this->maskEmail($signer->email),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Signing Page
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Signing/Show', [
            'signer' => $signer,
            'document' => $signer->document,
        ]);
    }

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

    public function finish(Request $request, string $token)
    {
        $signer = DocumentSigner::with([
            'document.signatureFields',
        ])
            ->where('token', $token)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Already signed
        |--------------------------------------------------------------------------
        */

        if (
            $signer->status === 'signed' ||
            $signer->signed_at !== null
        ) {
            app(Telemetry::class)->event('signing.submit.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'already_signed',
            ]);

            abort(
                403,
                'This signing request has already been completed.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | OTP verification
        |--------------------------------------------------------------------------
        */

        if (! $this->otpService->isVerified($signer)) {
            app(Telemetry::class)->event('signing.submit.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_otp',
            ]);
            abort(403, 'OTP verification required.');
        }

        /*
        |--------------------------------------------------------------------------
        | Validate submitted signatures
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'signatures' => [
                'required',
                'array',
            ],
        ]);

        $document = $signer->document;

        /*
        |--------------------------------------------------------------------------
        | Get this signer's signature plots
        |--------------------------------------------------------------------------
        */

        $requiredFields = $document->signatureFields
            ->where('signer_id', $signer->id);

        $signaturePlotCount = $requiredFields->count();

        if ($signaturePlotCount <= 0) {
            app(Telemetry::class)->event('signing.submit.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'missing_fields',
            ]);

            abort(
                400,
                'You have no signature fields assigned to this signing request.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure all required fields were submitted
        |--------------------------------------------------------------------------
        */

        if (
            count($validated['signatures']) <
            $signaturePlotCount
        ) {
            app(Telemetry::class)->event('signing.submit.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'missing_fields',
            ]);

            abort(
                400,
                'You must sign all required fields.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate every submitted field
        |--------------------------------------------------------------------------
        */

        foreach ($validated['signatures'] as $fieldId => $signature) {
            $field = $requiredFields
                ->firstWhere('id', $fieldId);

            abort_unless(
                $field,
                404,
                'Signature field not found.'
            );

            abort_unless(
                $field->signer_id === $signer->id,
                403
            );

            if (
                ! is_array($signature) ||
                empty($signature['image'])
            ) {
                app(Telemetry::class)->event('signing.submit.rejected', [
                    'app.outcome' => 'rejected',
                    'app.reason' => 'invalid_signature',
                ]);

                abort(
                    400,
                    'Invalid signature data.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate signing cost
        |--------------------------------------------------------------------------
        */

        $pricingService = app(
            SigningPricingService::class
        );

        $walletService = app(
            WalletService::class
        );

        $requiredUsdCents = $pricingService
            ->calculateCost($signaturePlotCount);

        /*
        |--------------------------------------------------------------------------
        | Debit + signing
        |--------------------------------------------------------------------------
        */

        DB::beginTransaction();
        $failureReason = 'processing_failure';

        try {
            DB::transaction(function () use (
                $document,
                $signer,
                $validated,
                $requiredFields,
                $signaturePlotCount,
                $requiredUsdCents,
                $walletService,
                $pricingService,
            ) {
                /*
                |--------------------------------------------------------------------------
                | Debit wallet
                |--------------------------------------------------------------------------
                */

                $walletService->debit(
                    organization: $document->organization,
                    sourceCurrency: 'USD',
                    sourceAmount: $requiredUsdCents / 100,
                    exchangeRate: 1,
                    amountUsdCents: $requiredUsdCents,
                    type: 'signature',
                    description: 'Document signature',
                    reference: $signer,
                    createdBy: null,
                    metadata: [
                        'document_id' => $document->id,
                        'document_signer_id' => $signer->id,
                        'signature_plot_count' => $signaturePlotCount,
                        'price_per_plot_usd_cents' => $pricingService
                            ->pricePerSignaturePlot(),
                    ],
                );

                /*
                |--------------------------------------------------------------------------
                | Save signatures
                |--------------------------------------------------------------------------
                */

                foreach (
                    $validated['signatures'] as $fieldId => $signature
                ) {
                    $field = $requiredFields
                        ->firstWhere('id', $fieldId);

                    $field->update([
                        'signature_image' => $signature['image'],
                        'signed_at' => now(),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Mark signer as signed
                |--------------------------------------------------------------------------
                */

                $signer->update([
                    'signed_at' => now(),
                    'status' => 'signed',
                ]);

            });

            /*
            |--------------------------------------------------------------------------
            | Reload latest data
            |--------------------------------------------------------------------------
            */

            $document = $document->fresh([
                'signatureFields',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Storage configuration
            |--------------------------------------------------------------------------
            */

            $documentDisk = env(
                'DOCUMENTS_DISK',
                'documents'
            );

            $tempDirectory = sys_get_temp_dir();

            if (! is_dir($tempDirectory)) {
                mkdir(
                    $tempDirectory,
                    0755,
                    true
                );
            }

            $sourcePath = $tempDirectory.
                '/'.
                $document->id.
                '-source.pdf';

            $temporarySignedPath = $tempDirectory.
                '/'.
                $document->id.
                '-signed.pdf';

            /*
            |--------------------------------------------------------------------------
            | Generate signed PDF
            |--------------------------------------------------------------------------
            */

            /*
            |--------------------------------------------------------------------------
            | Validate PDF signing certificate
            |--------------------------------------------------------------------------
            */

            foreach (['certificate', 'private_key'] as $key) {
                $path = config("signing.$key");

                if (! $path || ! is_readable($path)) {
                    throw new \RuntimeException(
                        "PDF signing $key is not configured or not readable: ".($path ?: '(empty)')
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Download original PDF from B2
            |--------------------------------------------------------------------------
            */

            $failureReason = 'storage_failure';
            $sourceStream = Storage::disk(
                $documentDisk
            )->readStream(
                $document->file_path
            );

            if ($sourceStream === false) {
                throw new \RuntimeException(
                    'Unable to read the source document from storage.'
                );
            }

            $destinationStream = fopen(
                $sourcePath,
                'wb'
            );

            if ($destinationStream === false) {
                fclose($sourceStream);

                throw new \RuntimeException(
                    'Unable to create the temporary source PDF.'
                );
            }

            stream_copy_to_stream(
                $sourceStream,
                $destinationStream
            );

            fclose($sourceStream);
            fclose($destinationStream);

            /*
            |--------------------------------------------------------------------------
            | Build signed PDF
            |--------------------------------------------------------------------------
            */

            $failureReason = 'pdf_failure';
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi;

            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);

            $signerNames = $document->signers()
                ->whereNotNull('signed_at')
                ->orderBy('signing_order')
                ->pluck('name')
                ->all();

            $pdf->setSignature(
                'file://'.config('signing.certificate'),
                'file://'.config('signing.private_key'),
                config('signing.password'),
                '',
                1,
                [
                    'Name' => config('app.name'),
                    'Location' => '',
                    'Reason' => 'Signed by '.implode(', ', $signerNames),
                    'ContactInfo' => config('mail.from.address'),
                ]
            );

            $pageCount = $pdf->setSourceFile(
                $sourcePath
            );

            for (
                $page = 1;
                $page <= $pageCount;
                $page++
            ) {
                $template = $pdf->importPage(
                    $page
                );

                $size = $pdf->getTemplateSize(
                    $template
                );

                $pdf->AddPage(
                    $size['orientation'],
                    [
                        $size['width'],
                        $size['height'],
                    ]
                );

                $pdf->useTemplate(
                    $template
                );

                $fields = $document->signatureFields
                    ->where('page', $page);

                foreach ($fields as $field) {
                    if (! $field->signature_image) {
                        continue;
                    }

                    $tmp = $tempDirectory.
                        '/'.
                        $field->id.
                        '.png';

                    $imageData = preg_replace(
                        '#^data:image/\w+;base64,#i',
                        '',
                        $field->signature_image
                    );

                    $decodedImage = base64_decode(
                        $imageData,
                        true
                    );

                    if ($decodedImage === false) {
                        throw new \RuntimeException(
                            "Unable to decode signature image for field {$field->id}."
                        );
                    }

                    file_put_contents(
                        $tmp,
                        $decodedImage
                    );

                    $pdf->Image(
                        $tmp,
                        $field->x *
                            $size['width'],
                        $field->y *
                            $size['height'],
                        $field->width *
                            $size['width'],
                        $field->height *
                            $size['height']
                    );

                    if (file_exists($tmp)) {
                        unlink($tmp);
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Save signed PDF temporarily
            |--------------------------------------------------------------------------
            */

            $pdf->Output(
                $temporarySignedPath,
                'F'
            );

            /*
            |--------------------------------------------------------------------------
            | Upload signed PDF to B2
            |--------------------------------------------------------------------------
            */

            $signedPath =
                'documents/signed/'.
                $document->id.
                '.pdf';

            $failureReason = 'storage_failure';
            $signedStream = fopen(
                $temporarySignedPath,
                'rb'
            );

            if ($signedStream === false) {
                throw new \RuntimeException(
                    'Unable to open the generated signed PDF.'
                );
            }

            $uploaded = Storage::disk(
                $documentDisk
            )->put(
                $signedPath,
                $signedStream
            );

            fclose($signedStream);

            if (! $uploaded) {
                throw new \RuntimeException(
                    'Unable to upload the signed PDF to storage.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Determine document completion
            |--------------------------------------------------------------------------
            */

            $completed = $document->signatureFields
                ->every(
                    fn ($field) => $field->signed_at !== null
                );

            /*
            |--------------------------------------------------------------------------
            | Update document
            |--------------------------------------------------------------------------
            */

            $document->update([
                'signed_path' => $signedPath,
                'status' => $completed
                    ? 'completed'
                    : 'sent',
            ]);

            DB::commit();
            app(Telemetry::class)->event('signing.submit.completed', ['app.outcome' => 'success']);
            if ($completed) {
                app(Telemetry::class)->event('signing.document.completed', ['app.outcome' => 'success']);
            }
        } catch (InsufficientWalletBalanceException $e) {
            DB::rollBack();
            app(Telemetry::class)->event('signing.submit.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'insufficient_balance',
            ]);

            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            app(Telemetry::class)->event('signing.submit.failed', [
                'app.outcome' => 'failure',
                'app.reason' => $failureReason,
                'error.type' => $e::class,
            ]);

            throw $e;
        } finally {
            /*
            |--------------------------------------------------------------------------
            | Clean up temporary files
            |--------------------------------------------------------------------------
            */

            if (
                isset($sourcePath) &&
                file_exists($sourcePath)
            ) {
                unlink($sourcePath);
            }

            if (
                isset($temporarySignedPath) &&
                file_exists($temporarySignedPath)
            ) {
                unlink($temporarySignedPath);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Notify next signer
        |--------------------------------------------------------------------------
        */

        $isSequential = $document->signers()
            ->where('signing_order', '>', 0)
            ->exists();

        if ($isSequential) {
            $nextSigner = $document->signers()
                ->whereNull('signed_at')
                ->where(
                    'signing_order',
                    '>',
                    $signer->signing_order
                )
                ->orderBy('signing_order', 'asc')
                ->first();

            if ($nextSigner) {
                if (! $nextSigner->token) {
                    $nextSigner->update([
                        'token' => \Illuminate\Support\Str::uuid(),
                    ]);

                    $nextSigner->refresh();
                }

                $otp = $this->otpService->generate(
                    $nextSigner
                );

                app(Telemetry::class)->submitMail('signature_request', fn () => Mail::to($nextSigner->email)
                    ->send(
                        new SignatureRequestMail(
                            $nextSigner->load(
                                'document'
                            ),
                            $otp
                        )
                    ));
                app(Telemetry::class)->event('signing.otp.sent', ['app.outcome' => 'success']);

                $nextSigner->update([
                    'status' => 'email_sent',
                ]);

                app(Telemetry::class)->eventAfterCommit('signing.workflow.advanced', ['app.outcome' => 'success']);
            }
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function verifyOtp(
        Request $request,
        string $token
    ) {
        $signer = DocumentSigner::where(
            'token',
            $token
        )->firstOrFail();

        if (
            $signer->status === 'signed' ||
            $signer->signed_at !== null
        ) {
            abort(
                403,
                'This signing request has already been completed.'
            );
        }

        $isSequential = $signer->document
            ->signers()
            ->where('signing_order', '>', 0)
            ->exists();

        if ($isSequential) {
            $this->ensureSigningOrder($signer);
        }

        $validated = $request->validate([
            'otp' => [
                'required',
                'digits:6',
            ],
        ]);

        if ($signer->otp_attempts >= 5) {
            app(Telemetry::class)->event('signing.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'rate_limited',
            ]);

            return back()->withErrors([
                'otp' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (
            ! $signer->otp_expires_at ||
            now()->greaterThan($signer->otp_expires_at)
        ) {
            app(Telemetry::class)->event('signing.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'expired',
            ]);

            return back()->withErrors([
                'otp' => 'This verification code has expired. Please request a new code.',
            ]);
        }

        if (
            ! $this->otpService->verify(
                $signer,
                $validated['otp']
            )
        ) {
            app(Telemetry::class)->event('signing.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_otp',
            ]);

            return back()->withErrors([
                'otp' => 'The verification code is incorrect.',
            ]);
        }

        app(Telemetry::class)->eventAfterCommit('signing.otp.verified', ['app.outcome' => 'success']);

        return redirect()->route(
            'signing.show',
            $signer->token
        );
    }

    public function resendOtp(string $token)
    {
        $signer = DocumentSigner::where(
            'token',
            $token
        )->firstOrFail();

        if (
            $signer->otp_last_sent_at &&
            $signer->otp_last_sent_at->greaterThan(
                now()->subSeconds(60)
            )
        ) {
            app(Telemetry::class)->event('signing.otp.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'rate_limited',
            ]);

            return back()->withErrors([
                'otp' => 'Please wait before requesting another code.',
            ]);
        }

        $otp = $this->otpService->generate($signer);

        app(Telemetry::class)->submitMail('signature_request', fn () => Mail::to($signer->email)
            ->send(
                new SignatureRequestMail(
                    $signer->load('document'),
                    $otp
                )
            ));
        app(Telemetry::class)->event('signing.otp.sent', ['app.outcome' => 'success']);

        return back()->with(
            'success',
            'A new verification code has been sent.'
        );
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);

        $visible = substr($name, 0, 2);

        return $visible
            .str_repeat('*', max(strlen($name) - 2, 1))
            .'@'
            .$domain;
    }

    public function completed(string $token)
    {
        $signer = DocumentSigner::where(
            'token',
            $token
        )->firstOrFail();

        abort_unless(
            $signer->status === 'signed' &&
            $signer->signed_at !== null,
            403,
            'This signing request has not been completed.'
        );

        return Inertia::render('Signing/Completed', [
            'signer' => [
                'name' => $signer->name,
            ],
        ]);
    }

    private function ensureSigningOrder(DocumentSigner $signer): void
    {
        $hasPreviousSigner = $signer->document
            ->signers()
            ->where('signing_order', '<', $signer->signing_order)
            ->whereNull('signed_at')
            ->exists();

        if ($hasPreviousSigner) {
            app(Telemetry::class)->event('signing.submit.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'wrong_order',
            ]);

            abort(
                403,
                'The previous signer must complete signing before you can sign this document.'
            );
        }
    }
}
