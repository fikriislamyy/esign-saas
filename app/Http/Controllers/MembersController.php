<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Observability\Telemetry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $request->user()->organization;

        return Inertia::render(
            'Members/Index',
            [
                'members' => $organization
                    ->users()
                    ->select(
                        'id',
                        'name',
                        'email',
                        'role'
                    )
                    ->get(),

                'invitations' => Invitation::query()
                    ->where(
                        'organization_id',
                        $organization->id
                    )
                    ->whereNull('accepted_at')
                    ->latest()
                    ->get([
                        'id',
                        'email',
                        'role',
                        'token',
                        'created_at',
                    ]),

                'canManageMembers' => $request->user()
                    ->canManageMembers(),
            ]
        );
    }

    public function updateRole(
        Request $request,
        User $user
    ) {
        $actor = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Permission
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $actor?->canManageMembers(),
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Organization isolation
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $user->organization_id ===
            $actor->organization_id,
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent self role changes
        |--------------------------------------------------------------------------
        */

        if ($user->id === $actor->id) {
            app(Telemetry::class)->event('organization.member.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'self_change',
            ]);

            return back()->withErrors([
                'role' => 'You cannot change your own role.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Owner protection
        |--------------------------------------------------------------------------
        */

        if ($user->role === 'owner') {
            app(Telemetry::class)->event('organization.member.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'owner_protected',
            ]);

            return back()->withErrors([
                'role' => 'The organization owner cannot be changed.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate role
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'role' => [
                'required',
                Rule::in([
                    'member',
                    'admin',
                ]),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        $user->update([
            'role' => $validated['role'],
        ]);

        app(Telemetry::class)->eventAfterCommit('organization.member.role_changed', ['app.outcome' => 'success']);

        return back()->with(
            'success',
            'Member role updated successfully.'
        );
    }
}
