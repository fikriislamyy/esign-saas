<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentSigner;
use App\Models\DocumentSignatureField;
use Inertia\Inertia;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\SignatureRequestMail;
use App\Services\SigningOtpService;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\SigningPricingService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;

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

            if (!$this->otpService->isVerified($signer)) {
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

        abort_unless(
            $this->otpService->isVerified($signer),
            403,
            'OTP verification required.'
        );

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
                !is_array($signature) ||
                empty($signature['image'])
            ) {
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
            \Log::info(
                'SIGNING: wallet debit starting',
                [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                    'signature_plot_count' =>
                        $signaturePlotCount,
                    'required_usd_cents' =>
                        $requiredUsdCents,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Debit wallet
            |--------------------------------------------------------------------------
            */

            $walletTransaction = $walletService->debit(
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
                    'signature_plot_count' =>
                        $signaturePlotCount,
                    'price_per_plot_usd_cents' =>
                        $pricingService
                            ->pricePerSignaturePlot(),
                ],
            );

            \Log::info(
                'SIGNING: wallet debited',
                [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                    'wallet_transaction_id' =>
                        $walletTransaction->id,
                    'amount_usd_cents' =>
                        $walletTransaction
                            ->amount_usd_cents,
                    'balance_after_usd_cents' =>
                        $walletTransaction
                            ->balance_after_usd_cents,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Save signatures
            |--------------------------------------------------------------------------
            */

            foreach (
                $validated['signatures']
                as $fieldId => $signature
            ) {
                $field = $requiredFields
                    ->firstWhere('id', $fieldId);

                $field->update([
                    'signature_image' =>
                        $signature['image'],
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

            \Log::info(
                'SIGNING: signer marked signed',
                [
                    'document_id' => $document->id,
                    'signer_id' => $signer->id,
                ]
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Notify next signer
        |--------------------------------------------------------------------------
        */

        $isSequential = $document->signers()
            ->where('signing_order', '>', 0)
            ->exists();

        \Log::info('SIGNING: workflow check', [
            'document_id' => $document->id,
            'is_sequential' => $isSequential,
            'current_signer_id' => $signer->id,
            'current_signer_name' => $signer->name,
            'current_signer_email' => $signer->email,
            'current_signer_order' => $signer->signing_order,
        ]);

        if ($isSequential) {
            $allSigners = $document->signers()
                ->orderBy('signing_order')
                ->get();

            \Log::info(
                'SIGNING: all signer states',
                [
                    'document_id' => $document->id,
                    'signers' => $allSigners
                        ->map(fn ($item) => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'email' => $item->email,
                            'signing_order' =>
                                $item->signing_order,
                            'status' => $item->status,
                            'signed_at' =>
                                $item->signed_at,
                            'has_token' =>
                                !empty($item->token),
                        ])
                        ->values()
                        ->toArray(),
                ]
            );

            $nextSigner = $document->signers()
                ->whereNull('signed_at')
                ->where(
                    'signing_order',
                    '>',
                    $signer->signing_order
                )
                ->orderBy('signing_order', 'asc')
                ->first();

            \Log::info(
                'SIGNING: next signer lookup',
                [
                    'document_id' => $document->id,
                    'current_signer_id' => $signer->id,
                    'current_signer_order' =>
                        $signer->signing_order,
                    'next_signer_id' =>
                        $nextSigner?->id,
                    'next_signer_name' =>
                        $nextSigner?->name,
                    'next_signer_email' =>
                        $nextSigner?->email,
                    'next_signer_order' =>
                        $nextSigner?->signing_order,
                ]
            );

            if ($nextSigner) {
                try {
                    if (!$nextSigner->token) {
                        $nextSigner->update([
                            'token' =>
                                \Illuminate\Support\Str::uuid(),
                        ]);

                        $nextSigner->refresh();
                    }

                    $otp = $this->otpService->generate(
                        $nextSigner
                    );

                    Mail::to($nextSigner->email)
                        ->send(
                            new SignatureRequestMail(
                                $nextSigner->load(
                                    'document'
                                ),
                                $otp
                            )
                        );

                    $nextSigner->update([
                        'status' => 'email_sent',
                    ]);

                    \Log::info(
                        'SIGNING: next signer notified',
                        [
                            'document_id' =>
                                $document->id,
                            'signer_id' =>
                                $nextSigner->id,
                            'email' =>
                                $nextSigner->email,
                        ]
                    );
                } catch (\Throwable $e) {
                    \Log::error(
                        'SIGNING: failed to notify next signer',
                        [
                            'document_id' =>
                                $document->id,
                            'current_signer_id' =>
                                $signer->id,
                            'next_signer_id' =>
                                $nextSigner->id,
                            'exception' =>
                                get_class($e),
                            'message' =>
                                $e->getMessage(),
                            'file' =>
                                $e->getFile(),
                            'line' =>
                                $e->getLine(),
                            'trace' =>
                                $e->getTraceAsString(),
                        ]
                    );

                    throw $e;
                }
            }
        }

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

        if (!is_dir($tempDirectory)) {
            mkdir(
                $tempDirectory,
                0755,
                true
            );
        }

        $sourcePath = $tempDirectory .
            '/' .
            $document->id .
            '-source.pdf';

        $temporarySignedPath = $tempDirectory .
            '/' .
            $document->id .
            '-signed.pdf';

        /*
        |--------------------------------------------------------------------------
        | Generate signed PDF
        |--------------------------------------------------------------------------
        */

        try {
            /*
            |--------------------------------------------------------------------------
            | Download original PDF from B2
            |--------------------------------------------------------------------------
            */

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

            $pdf = new Fpdi();

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
                    if (!$field->signature_image) {
                        continue;
                    }

                    $tmp = $tempDirectory .
                        '/' .
                        $field->id .
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
                'F',
                $temporarySignedPath
            );

            /*
            |--------------------------------------------------------------------------
            | Upload signed PDF to B2
            |--------------------------------------------------------------------------
            */

            $signedPath =
                'documents/signed/' .
                $document->id .
                '.pdf';

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

            if (!$uploaded) {
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
                    fn ($field) =>
                        $field->signed_at !== null
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

            return response()->json([
                'success' => true,
            ]);
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
            return back()->withErrors([
                'otp' => 'Too many incorrect attempts. Please request a new code.',
            ]);
        }

        if (
            !$signer->otp_expires_at ||
            now()->greaterThan($signer->otp_expires_at)
        ) {
            return back()->withErrors([
                'otp' => 'This verification code has expired. Please request a new code.',
            ]);
        }

        if (
            !$this->otpService->verify(
                $signer,
                $validated['otp']
            )
        ) {
            return back()->withErrors([
                'otp' => 'The verification code is incorrect.',
            ]);
        }

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
            return back()->withErrors([
                'otp' => 'Please wait before requesting another code.',
            ]);
        }

        $otp = $this->otpService->generate($signer);

        Mail::to($signer->email)
            ->send(
                new SignatureRequestMail(
                    $signer->load('document'),
                    $otp
                )
            );

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
            . str_repeat('*', max(strlen($name) - 2, 1))
            . '@'
            . $domain;
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
            abort(
                403,
                'The previous signer must complete signing before you can sign this document.'
            );
        }
    }
}
