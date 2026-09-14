<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use App\Models\DocumentSignatureField;

class DocumentSignatureFieldController extends Controller
{
    public function store(
        Request $request,
        Document $document
    ) {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        $validated = $request->validate([
            'signer_id' => [
                'required',
                'exists:document_signers,id',
            ],
            'page' => [
                'required',
                'integer',
                'min:1',
            ],
            'x' => [
                'required',
                'min:0',
            ],
            'y' => [
                'required',
                'min:0',
            ],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
        ]);

        abort_unless(
            $document->signers()->whereKey($validated['signer_id'])->exists(),
            422,
            'The selected signer does not belong to this document.'
        );

        $field = DocumentSignatureField::create([
            'document_id' => $document->id,
            'signer_id' => $validated['signer_id'],
            'page' => $validated['page'],
            'x' => $validated['x'],
            'y' => $validated['y'],
            'width' => $validated['width'] ?? 0.25,
            'height' => $validated['height'] ?? 0.06,
        ]);

        $field->load('signer');

        return response()->json([
            'success' => true,
            'field' => $field,
        ]);
    }

    public function update(
        Request $request,
        DocumentSignatureField $signatureField
    ) {
        $signatureField->update([
            'x' => $request->x,
            'y' => $request->y,
            'width' => $request->width,
            'height' => $request->height,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(
        DocumentSignatureField $signatureField
    ) {
        $signatureField->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroyAll(Request $request, Document $document)
    {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403
        );

        $document->signatureFields()->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
