<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\DocumentSigner;
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

        $alreadyExists = $document
            ->signers()
            ->where('email', $member->email)
            ->exists();

        if ($alreadyExists) {
            return back()->withErrors([
                'member_id' =>
                    'This member is already a signer.',
            ]);
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

        DocumentSigner::create([
            'document_id' => $document->id,

            'name' => $member->name,

            'email' => $member->email,

            'signing_order' => $signingOrder,
        ]);

        return back();
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
