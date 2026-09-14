<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Mail\SignatureRequestMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Services\SigningOtpService;
use App\Services\SigningPricingService;
use App\Services\WalletService;

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
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
            ],
        ]);

        $file = $request->file('file');

        $documentDisk = env('DOCUMENTS_DISK', 'documents');

        $path = $file->store(
            'documents',
            $documentDisk
        );

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

            'members' => $request
                ->user()
                ->organization
                ->users()
                ->select(
                    'id',
                    'name',
                    'email'
                )
                ->orderBy('name')
                ->get(),

            'signerFieldCounts' => $signerFieldCounts,

            'canSendForSignature' =>
                $canSendForSignature,
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
            return back()->withErrors([
                'document' =>
                    'Document has no signature plots assigned to its signers.',
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

        \Log::info(
            'DOCUMENT: wallet balance check before send',
            [
                'document_id' => $document->id,
                'organization_id' => $document->organization_id,
                'total_signature_plots' => $totalSignaturePlots,
                'price_per_plot_usd_cents' =>
                    $pricingService->pricePerSignaturePlot(),
                'required_usd_cents' => $requiredUsdCents,
                'wallet_balance_usd_cents' => $walletBalanceUsdCents,
            ]
        );

        if ($walletBalanceUsdCents < $requiredUsdCents) {
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
            if (!$signer->token) {
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
                fn ($signer) =>
                    (int) $signer->signing_order > 0
            )
            ->sortBy('signing_order')
            ->values();

        $isSequential = $sequentialSigners->isNotEmpty();

        /*
        |--------------------------------------------------------------------------
        | Sequential Signing
        |--------------------------------------------------------------------------
        */

        if ($isSequential) {

            $firstSigner = $sequentialSigners->first();

            \Log::info(
                'DOCUMENT: initial sequential signer selected',
                [
                    'document_id' => $document->id,
                    'signer_id' => $firstSigner->id,
                    'name' => $firstSigner->name,
                    'email' => $firstSigner->email,
                    'signing_order' => $firstSigner->signing_order,
                ]
            );

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

            \Log::info(
                'DOCUMENT: sending initial signing email',
                [
                    'document_id' => $document->id,
                    'signer_id' => $firstSigner->id,
                    'email' => $firstSigner->email,
                    'signing_order' => $firstSigner->signing_order,
                ]
            );

            Mail::to($firstSigner->email)
                ->send(
                    new SignatureRequestMail(
                        $firstSigner->load('document'),
                        $otp
                    )
                );

            /*
            |--------------------------------------------------------------------------
            | Mark first signer as notified
            |--------------------------------------------------------------------------
            */

            $firstSigner->update([
                'status' => 'email_sent',
            ]);

            \Log::info(
                'DOCUMENT: initial signing email sent',
                [
                    'document_id' => $document->id,
                    'signer_id' => $firstSigner->id,
                    'email' => $firstSigner->email,
                ]
            );

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

                \Log::info(
                    'DOCUMENT: sending parallel signing email',
                    [
                        'document_id' => $document->id,
                        'signer_id' => $signer->id,
                        'email' => $signer->email,
                        'signing_order' => $signer->signing_order,
                    ]
                );

                Mail::to($signer->email)
                    ->send(
                        new SignatureRequestMail(
                            $signer->load('document'),
                            $otp
                        )
                    );

                $signer->update([
                    'status' => 'email_sent',
                ]);
            }
        }

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

        $filePath = $document->signed_path;

        if (!$filePath) {
            $filePath = $document->file_path;
        }

        abort_unless(
            $filePath,
            404
        );

        return Storage::disk(
            env('DOCUMENTS_DISK', 'documents')
        )->response(
            $filePath,
            $document->name . '.pdf',
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

        $filePath = $document->signed_path;

        if (!$filePath) {
            $filePath = $document->file_path;
        }

        abort_unless(
            $filePath,
            404
        );

        return Storage::disk(
            env('DOCUMENTS_DISK', 'documents')
        )->download(
            $filePath,
            $document->name . '.pdf'
        );
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

        return response()->json([
            'success' => true,
            'message' => 'Document preparation completed.',
        ]);
    }

}