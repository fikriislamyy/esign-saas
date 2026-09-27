<?php

namespace App\Http\Controllers;

use App\Mail\OrganizationInvitationMail;
use App\Models\Invitation;
use App\Observability\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(
            $request->user()->canManageMembers(),
            403
        );

        $planService = app(\App\Services\PlanService::class);
        $organization = $request->user()->organization;

        if (! $planService->canAddMember($organization)) {
            app(Telemetry::class)->event('organization.invitation.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'plan_limit',
            ]);

            return back()->withErrors([
                'email' => 'You have reached the member limit for your plan. Upgrade to invite more people.',
            ]);
        }

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
            app(Telemetry::class)->event('organization.invitation.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'already_member',
            ]);

            return back()->withErrors([
                'email' => 'User already belongs to this organization.',
            ]);
        }

        $invitation = Invitation::create([
            'organization_id' => $request->user()->organization_id,

            'email' => $request->email,

            'role' => $request->role,

            'token' => Str::random(64),
        ]);

        app(Telemetry::class)->submitMail('organization_invitation', fn () => Mail::to(
            $invitation->email
        )->send(new OrganizationInvitationMail($invitation)));
        app(Telemetry::class)->eventAfterCommit('organization.invitation.sent', ['app.outcome' => 'success']);

        return back()->with(
            'success',
            'Invitation sent.'
        );
    }

    public function resend(
        Invitation $invitation
    ) {
        abort_unless(
            auth()->user()->canManageMembers(),
            403
        );

        app(Telemetry::class)->submitMail('organization_invitation', fn () => Mail::to(
            $invitation->email
        )->send(new OrganizationInvitationMail($invitation)));
        app(Telemetry::class)->event('organization.invitation.resent', ['app.outcome' => 'success']);

        return back()->with(
            'success',
            'Invitation resent.'
        );
    }

    public function destroy(
        Request $request,
        Invitation $invitation
    ) {

        $user = $request->user();

        abort_unless(
            $user?->isOwner(),
            403
        );

        abort_unless(
            $invitation->organization_id ===
            $user->organization_id,
            403
        );

        abort_if(
            $invitation->accepted_at !== null,
            422,
            'This invitation has already been accepted.'
        );

        $invitation->delete();
        app(Telemetry::class)->eventAfterCommit('organization.invitation.revoked', ['app.outcome' => 'success']);

        return back()->with(
            'success',
            'Invitation revoked successfully.'
        );
    }
}
