<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateSignatureField;
use Illuminate\Http\Request;

class TemplateSignatureFieldController extends Controller
{
    public function store(Request $request, Template $template)
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'x' => ['required', 'numeric', 'min:0'],
            'y' => ['required', 'numeric', 'min:0'],
        ]);

        $field = TemplateSignatureField::create([
            'template_id' => $template->id,
            'page' => $validated['page'],
            'x' => $validated['x'],
            'y' => $validated['y'],
            'width' => 0.25,
            'height' => 0.06,
        ]);

        return response()->json(['success' => true, 'field' => $field]);
    }

    public function update(Request $request, TemplateSignatureField $signatureField)
    {
        abort_unless(
            $signatureField->template->organization_id === $request->user()->organization_id,
            403
        );

        $signatureField->update($request->only('x', 'y', 'width', 'height'));

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, TemplateSignatureField $signatureField)
    {
        abort_unless(
            $signatureField->template->organization_id === $request->user()->organization_id,
            403
        );

        $signatureField->delete();

        return response()->json(['success' => true]);
    }
}
