<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use Illuminate\Http\Request;
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

                'canManageMembers' =>
                $request->user()
                    ->canManageMembers(),
            ]
        );
    }
}
