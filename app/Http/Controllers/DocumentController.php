<?php

namespace App\Http\Controllers;

use App\Mail\SignatureRequestMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Observability\Telemetry;
use App\Services\SigningOtpService;
use App\Services\SigningPricingService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(
        protected SigningOtpService $otpService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $documents = $user->organization
            ->documents()
            ->with(['uploader', 'signers'])
            ->when($user->role === 'member', function ($query) use ($user) {
                $query->whereHas('signers', function ($query) use ($user) {
                    $query->where('email', $user->email);
                });
            })
            ->latest()
            ->get();

        $documents = $documents
            ->map(function ($document) {
                $document->created_at_human = $document->created_at->diffForHumans();

                return $document;
            });

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:pdf', 'mimes:pdf', 'max:5120'],
        ], [
            'file.extensions' => 'Only PDF files can be uploaded.',
            'file.mimes' => 'Only PDF files can be uploaded.',
            'file.max' => 'The file must be 5 MB or smaller.',
        ]);

        $file = $request->file('file');

        $organization = $request->user()->organization;
        $planService = app(\App\Services\PlanService::class);

        if (! $planService->canUploadDocument($organization)) {
            app(Telemetry::class)->event('document.upload.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'plan_limit',
            ]);

            $limits = $planService->limits($organization)['documents'];

            return back()->withErrors([
                'file' => "Your plan allows {$limits['limit']} documents per {$limits['period']}. Upgrade to upload more.",
            ]);
        }

        if (! $planService->canStore($organization, $file->getSize())) {
            app(Telemetry::class)->event('document.upload.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'storage_limit',
            ]);

            return back()->withErrors([
                'file' => 'This upload would exceed your plan\'s storage limit.',
            ]);
        }

        $documentDisk = config('documents.disk');

        try {
            $path = $file->store(
                'documents',
                $documentDisk
            );
        } catch (Throwable $error) {
            app(Telemetry::class)->event('document.upload.failed', [
                'app.outcome' => 'failure',
                'app.reason' => 'storage_failure',
                'error.type' => $error::class,
            ]);
            throw $error;
        }

        Document::create([
            'organization_id' => $request->user()->organization_id,
            'owner_id' => $request->user()->id,

            'name' => pathinfo(
                $file->getClientOriginalName(),
                PATHINFO_FILENAME
            ),

            'file_path' => $path,

            'file_size' => $file->getSize(),

            'mime_type' => $file->getMimeType(),

            'status' => 'draft',
        ]);

        app(Telemetry::class)->eventAfterCommit('document.upload.completed', ['app.outcome' => 'success']);

        return back();
    }

    public function show(
        Request $request,
        Document $document
    ): Response {
        abort_unless(
            $document->organization_id ===
            $request->user()->organization_id,
            403
        );

        abort_if($document->status === 'expired', 410, 'This document has expired and its file was removed.');

        $document->load([
            'uploader',
            'organization',
            'signers' => function ($query) {
                $query->withCount('fields');
            },
        ]);

        $document->created_at_human =
            $document->created_at->diffForHumans();

        $signerFieldCounts = $document->signers
            ->mapWithKeys(fn ($signer) => [
                $signer->id => (int) $signer->fields_count,
            ]);

        $canSendForSignature =
            $document->status === 'draft'
            && $document->signers->isNotEmpty()
            && $document->signers->every(function ($signer) use (
                $signerFieldCounts
            ) {
                return ($signerFieldCounts[$signer->id] ?? 0) > 0;
            });

        return Inertia::render('Documents/Show', [
            'document' => $document,

            'signerFieldCounts' => $signerFieldCounts,

            'canSendForSignature' => $canSendForSignature,
        ]);
    }

    public function destroy(
        Request $request,
        DocumentSigner $signer
    ) {
        abort_unless(
            $signer->document->organization_id ===
            $request->user()->organization_id,
            403
        );

        abort_if(
            $signer->document->status !== 'draft',
            403
        );

        $signer->delete();

        return back();
    }

    public function send(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id ===
            $request->user()->organization_id,
            403
        );

        abort_if(
            $document->status !== 'draft',
            422
        );

        /*
        |--------------------------------------------------------------------------
        | Load signers + their signature fields
        |--------------------------------------------------------------------------
        */

        $signers = $document->signers()
            ->with('fields')
            ->orderBy('signing_order', 'asc')
            ->get();

        if ($signers->isEmpty()) {
            app(Telemetry::class)->event('document.send.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'no_signers',
            ]);

            return back()->withErrors([
                'document' => 'Document has no signers.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Check wallet balance
        |--------------------------------------------------------------------------
        */

        $totalSignaturePlots = $signers->sum(
            fn ($signer) => $signer->fields->count()
        );

        if ($totalSignaturePlots <= 0) {
            app(Telemetry::class)->event('document.send.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'no_fields',
            ]);

            return back()->withErrors([
                'document' => 'Document has no signature plots assigned to its signers.',
            ]);
        }

        $pricingService = app(
            SigningPricingService::class
        );

        $walletService = app(
            WalletService::class
        );

        $requiredUsdCents = $pricingService
            ->calculateCost($totalSignaturePlots);

        $walletBalanceUsdCents = $walletService
            ->getBalance($document->organization);

        if ($walletBalanceUsdCents < $requiredUsdCents) {
            app(Telemetry::class)->event('document.send.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'insufficient_balance',
            ]);

            $requiredUsd = number_format(
                $requiredUsdCents / 100,
                2
            );

            $availableUsd = number_format(
                $walletBalanceUsdCents / 100,
                2
            );

            return back()->withErrors([
                'wallet' => sprintf(
                    'Insufficient wallet balance. This document contains %d signature plots and requires $%s, but your current wallet balance is $%s.',
                    $totalSignaturePlots,
                    $requiredUsd,
                    $availableUsd
                ),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure every signer has a token
        |--------------------------------------------------------------------------
        */

        foreach ($signers as $signer) {
            if (! $signer->token) {
                $signer->update([
                    'token' => Str::uuid(),
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Mark document as sent
        |--------------------------------------------------------------------------
        */

        $document->update([
            'status' => 'sent',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Determine workflow
        |--------------------------------------------------------------------------
        */

        $sequentialSigners = $signers
            ->filter(
                fn ($signer) => (int) $signer->signing_order > 0
            )
            ->sortBy('signing_order')
            ->values();

        $isSequential = $sequentialSigners->isNotEmpty();

        /*
        |--------------------------------------------------------------------------
        | Sequential Signing
        |--------------------------------------------------------------------------
        */

        try {
            if ($isSequential) {

                $firstSigner = $sequentialSigners->first();

                /*
                |--------------------------------------------------------------------------
                | Generate OTP
                |--------------------------------------------------------------------------
                */

                $otp = $this->otpService->generate(
                    $firstSigner
                );

                /*
                |--------------------------------------------------------------------------
                | Send email
                |--------------------------------------------------------------------------
                */

                app(Telemetry::class)->submitMail('signature_request', fn () => Mail::to($firstSigner->email)
                    ->send(
                        new SignatureRequestMail(
                            $firstSigner->load('document'),
                            $otp
                        )
                    ));
                app(Telemetry::class)->event('signing.otp.sent', ['app.outcome' => 'success']);

                /*
                |--------------------------------------------------------------------------
                | Mark first signer as notified
                |--------------------------------------------------------------------------
                */

                $firstSigner->update([
                    'status' => 'email_sent',
                ]);

            } else {

                /*
                |--------------------------------------------------------------------------
                | Parallel Signing
                |--------------------------------------------------------------------------
                */

                foreach ($signers as $signer) {

                    $otp = $this->otpService->generate(
                        $signer
                    );

                    app(Telemetry::class)->submitMail('signature_request', fn () => Mail::to($signer->email)
                        ->send(
                            new SignatureRequestMail(
                                $signer->load('document'),
                                $otp
                            )
                        ));
                    app(Telemetry::class)->event('signing.otp.sent', ['app.outcome' => 'success']);

                    $signer->update([
                        'status' => 'email_sent',
                    ]);
                }
            }
        } catch (Throwable $error) {
            app(Telemetry::class)->event('document.send.failed', [
                'app.outcome' => 'partial',
                'app.reason' => 'mail_failure',
                'error.type' => $error::class,
            ]);
            throw $error;
        }

        app(Telemetry::class)->eventAfterCommit('document.send.completed', [
            'app.outcome' => 'success',
            'app.failed_count' => 0,
        ]);

        return back()->with(
            'success',
            'Document sent for signature.'
        );
    }

    public function prepare(
        Request $request,
        Document $document
    ): Response {

        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

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

                'templates' => $request->user()->organization
                    ->templates()
                    ->with('signatureFields:id,template_id,page,x,y,width,height')
                    ->latest()
                    ->get(['id', 'name', 'created_at']),
            ]
        );
    }

    public function preview(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        abort_if($document->status === 'expired', 410, 'This document has expired and its file was removed.');

        $filePath = $document->signed_path;

        if (! $filePath) {
            $filePath = $document->file_path;
        }

        abort_unless(
            $filePath,
            404
        );

        return Storage::disk(
            config('documents.disk')
        )->response(
            $filePath,
            $document->name.'.pdf',
            [
                'Content-Disposition' => 'inline',
            ]
        );
    }

    public function download(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        abort_if($document->status === 'expired', 410, 'This document has expired and its file was removed.');

        $filePath = $document->signed_path;

        if (! $filePath) {
            $filePath = $document->file_path;
        }

        abort_unless(
            $filePath,
            404
        );

        return Storage::disk(
            config('documents.disk')
        )->download(
            $filePath,
            $document->name.'.pdf'
        );
    }

    public function pdf(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        $filePath = $document->signed_path ?: $document->file_path;

        abort_unless(
            $filePath,
            404
        );

        $contents = Storage::disk(config('documents.disk'))->get($filePath);

        return response()->json([
            'data' => base64_encode($contents),
        ]);
    }

    public function finishPrepare(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id ===
                $request->user()->organization_id,
            403
        );

        abort_if(
            $document->status !== 'draft',
            422,
            'Document is no longer in draft status.'
        );

        app(Telemetry::class)->event('document.prepare.completed', ['app.outcome' => 'success']);

        return response()->json([
            'success' => true,
            'message' => 'Document preparation completed.',
        ]);
    }
}
