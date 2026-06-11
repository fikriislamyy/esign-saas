<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrganizationInvitationMail;

class InvitationController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(
            $request->user()->canManageMembers(),
            403
        );

        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('invitations')
                ->where(function ($query) use ($request) {
                    return $query
                        ->where(
                            'organization_id',
                            $request->user()->organization_id
                        )
                        ->whereNull('accepted_at');
                }),
            ],

            'role' => [
                'required',
                'in:admin,member',
            ],
        ]);

        if (
            $request->user()
                ->organization
                ->users()
                ->where('email', $request->email)
                ->exists()
        ) {
            return back()->withErrors([
                'email' => 'User already belongs to this organization.',
            ]);
        }

        $invitation = Invitation::create([
            'organization_id' =>
                $request->user()->organization_id,

            'email' => $request->email,

            'role' => $request->role,

            'token' => Str::random(64),
        ]);

        Mail::to(
            $invitation->email
        )->send(
            new OrganizationInvitationMail(
                $invitation
            )
        );

        return back()->with(
            'success',
            'Invitation sent.'
        );
    }

    public function resend(
        Invitation $invitation
    )
    {
        abort_unless(
            auth()->user()->canManageMembers(),
            403
        );

        Mail::to(
            $invitation->email
        )->send(
            new OrganizationInvitationMail(
                $invitation
            )
        );

        return back()->with(
            'success',
            'Invitation resent.'
        );
    }

    public function destroy(Invitation $invitation)
    {

        abort_unless(
            auth()->user()->canManageMembers(),
            403
        );

        abort_unless(
            auth()->user()->organization_id ===
            $invitation->organization_id,
            403
        );

        $invitation->delete();

        return back();
    }
}