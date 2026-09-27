<?php

namespace App\Http\Controllers;

use App\Observability\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Organization', [
            'organization' => $request->user()->organization,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')
                ->ignore(
                    $request->user()->organization->id
                ), ],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $organization = $request->user()->organization;

        $data = [
            'name' => $request->name,
        ];

        if ($request->hasFile('logo')) {

            if (
                $organization->logo &&
                Storage::disk('public')->exists($organization->logo)
            ) {
                Storage::disk('public')->delete(
                    $organization->logo
                );
            }

            $data['logo'] = $request
                ->file('logo')
                ->store('organizations', 'public');
        }

        $organization->update($data);

        app(Telemetry::class)->eventAfterCommit('organization.settings.updated', ['app.outcome' => 'success']);

        return back()->with(
            'success',
            'Organization updated successfully.'
        );
    }
}
