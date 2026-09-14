<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = $request->user()->organization
            ->templates()
            ->withCount('signatureFields')
            ->latest()
            ->get()
            ->map(function ($template) {
                $template->created_at_human = $template->created_at->diffForHumans();

                return $template;
            });

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('file');

        $path = $file->store('templates', env('DOCUMENTS_DISK', 'documents'));

        Template::create([
            'organization_id' => $request->user()->organization_id,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back();
    }

    public function show(Request $request, Template $template): Response
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        $template->loadCount('signatureFields');

        $template->created_at_human = $template->created_at->diffForHumans();

        return Inertia::render('Templates/Show', [
            'template' => $template,
        ]);
    }

    public function prepare(Request $request, Template $template): Response
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        return Inertia::render('Templates/Prepare', [
            'template' => $template->load('signatureFields'),
        ]);
    }

    public function preview(Request $request, Template $template)
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        return Storage::disk(env('DOCUMENTS_DISK', 'documents'))->response(
            $template->file_path,
            $template->name . '.pdf',
            ['Content-Disposition' => 'inline']
        );
    }
}
