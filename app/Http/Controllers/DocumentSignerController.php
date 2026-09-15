<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentSignerController extends Controller
{
    public function store(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        $request->validate([
            'member_id' => [
                'required',
                'exists:users,id',
            ],
        ]);

        $member = User::findOrFail(
            $request->member_id
        );

        $existing = $document
            ->signers()
            ->where('email', $member->email)
            ->first();

        if ($existing) {
            return response()->json(['signer' => $existing]);
        }

        $signers = $document
            ->signers()
            ->orderBy('signing_order')
            ->get();

        if ($signers->isEmpty()) {

            // first signer determines workflow
            $signingOrder = $request->boolean('signing_order')
                ? 1
                : 0;

        } else {

            $isSequential =
                $signers->first()->signing_order > 0;

            $signingOrder = $isSequential
                ? ($signers->max('signing_order') + 1)
                : 0;
        }

        $signer = DocumentSigner::create([
            'document_id' => $document->id,

            'name' => $member->name,

            'email' => $member->email,

            'signing_order' => $signingOrder,
        ]);

        return response()->json(['signer' => $signer], 201);
    }

    public function reorder(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        $request->validate([
            'signers' => ['required', 'array'],
            'signers.*.id' => ['required', 'uuid'],
        ]);

        $documentSignerIds = $document
            ->signers()
            ->pluck('id')
            ->toArray();

        $submittedIds = collect(
            $request->signers
        )->pluck('id')->toArray();

        sort($documentSignerIds);
        sort($submittedIds);

        if ($documentSignerIds !== $submittedIds) {
            abort(422);
        }

        foreach ($request->signers as $index => $signer) {

            DocumentSigner::where(
                'id',
                $signer['id']
            )->update([
                'signing_order' => $index + 1,
            ]);
        }

        return back();
    }

    public function updateWorkflow(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        abort_if(
            $document->status !== 'draft',
            403,
            'Workflow can only be changed while the document is a draft.'
        );

        $request->validate([
            'sequential' => ['required', 'boolean'],
        ]);

        $signers = $document->signers()->orderBy('signing_order')->get();

        if ($signers->isEmpty()) {
            return response()->json(['message' => 'No signers to update']);
        }

        $sequential = $request->boolean('sequential');

        foreach ($signers as $index => $signer) {
            $signer->update([
                'signing_order' => $sequential ? $index + 1 : 0,
            ]);
        }

        return response()->json([
            'signers' => $signers->fresh(),
            'sequential' => $sequential,
        ]);
    }

    public function destroy(DocumentSigner $signer)
    {
        $document = $signer->document;

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $document->organization_id === auth()->user()->organization_id,
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Only allow removing signers from draft documents
        |--------------------------------------------------------------------------
        */

        abort_if(
            $document->status !== 'draft',
            403,
            'Signers can only be removed while the document is a draft.'
        );

        /*
        |--------------------------------------------------------------------------
        | Delete Signer
        |--------------------------------------------------------------------------
        */

        $signer->delete();

        return back()->with(
            'success',
            'Signer removed successfully.'
        );
    }
}
