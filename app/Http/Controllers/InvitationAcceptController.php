<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Observability\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class InvitationAcceptController extends Controller
{
    public function show(string $token)
    {
        $invitation = Invitation::where(
            'token',
            $token
        )->firstOrFail();

        if ($invitation->accepted_at) {
            app(Telemetry::class)->event('organization.invitation.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'already_accepted',
            ]);
            abort(404);
        }

        return Inertia::render(
            'Invitations/Accept',
            [
                'invitation' => [
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'organization' => $invitation->organization->name,
                    'token' => $token,
                ],
            ]
        );
    }

    public function store(
        Request $request,
        string $token
    ) {
        $invitation = Invitation::where(
            'token',
            $token
        )->firstOrFail();

        if ($invitation->accepted_at) {
            app(Telemetry::class)->event('organization.invitation.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'already_accepted',
            ]);
            abort(404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'password' => [
                'required',
                'confirmed',
                'min:8',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create User
        |--------------------------------------------------------------------------
        */

        $user = User::create([
            'organization_id' => $invitation->organization_id,

            'role' => $invitation->role,

            'name' => $validated['name'],

            'email' => $invitation->email,

            'password' => Hash::make(
                $validated['password']
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Mark invitation as accepted
        |--------------------------------------------------------------------------
        */

        $invitation->update([
            'accepted_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Send email verification
        |--------------------------------------------------------------------------
        */

        $user->sendEmailVerificationNotification();

        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        Auth::login($user);

        app(Telemetry::class)->eventAfterCommit('organization.invitation.accepted', ['app.outcome' => 'success']);

        /*
        |--------------------------------------------------------------------------
        | Redirect to verification notice
        |--------------------------------------------------------------------------
        */

        return redirect()->route(
            'verification.notice'
        );
    }
}
