<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $planService = app(PlanService::class);
        $organization = $request->user()->organization;

        return Inertia::render('Templates/Index', [
            'templates' => $templates,
            'templateQuota' => [
                'used' => $planService->templatesUsed($organization),
                'limit' => $planService->limits($organization)['templates'],
            ],
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

        $organization = $request->user()->organization;

        if (! app(PlanService::class)->canAddTemplate($organization)) {
            return back()->withErrors([
                'file' => 'You have reached the limit of 5 templates. Delete one to upload another.',
            ]);
        }

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

    public function pdf(Request $request, Template $template)
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        $contents = Storage::disk(env('DOCUMENTS_DISK', 'documents'))->get($template->file_path);

        return response()->json([
            'data' => base64_encode($contents),
        ]);
    }

    public function destroy(Request $request, Template $template)
    {
        abort_unless(
            $template->organization_id === $request->user()->organization_id,
            403
        );

        $path = $template->file_path;

        $template->delete();

        if (! Storage::disk(env('DOCUMENTS_DISK', 'documents'))->delete($path)) {
            Log::warning('Template file was not removed from storage', [
                'template_id' => $template->id,
                'path' => $path,
            ]);
        }

        return redirect()
            ->route('templates.index')
            ->with('status', 'Template deleted.');
    }
}
