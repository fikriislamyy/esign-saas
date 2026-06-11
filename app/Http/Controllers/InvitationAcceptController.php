<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Invitation;
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

        abort_if(
            $invitation->accepted_at,
            404
        );

        return Inertia::render(
            'Invitations/Accept',
            [
                'invitation' => [
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'organization' =>
                        $invitation->organization->name,
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

        abort_if(
            $invitation->accepted_at,
            404
        );

        $request->validate([
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

        $user = User::create([
            'organization_id' =>
                $invitation->organization_id,

            'role' =>
                $invitation->role,

            'name' =>
                $request->name,

            'email' =>
                $invitation->email,

            'password' =>
                Hash::make(
                    $request->password
                ),
        ]);

        $invitation->update([
            'accepted_at' => now(),
        ]);

        Auth::login($user);

        return redirect()->route(
            'dashboard'
        );
    }
}
