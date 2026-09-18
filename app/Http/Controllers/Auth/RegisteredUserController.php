<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'countries' => config('countries'),
            'termsHtml' => Str::markdown(
                file_get_contents(resource_path('markdown/terms-and-conditions.md')),
                ['html_input' => 'strip', 'allow_unsafe_links' => false],
            ),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $countries = config('countries');

        $validated = $request->validate([
            'organization_name' => 'required|string|max:255|unique:organizations,name',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'country_code' => ['required', 'string', Rule::in(array_column($countries, 'code'))],
            'phone_number' => ['required', 'string', 'regex:/^[0-9 ()+-]{6,20}$/'],
            'terms' => ['accepted'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'terms.accepted' => 'You must accept the Terms and Conditions to register.',
            'phone_number.regex' => 'Please enter a valid phone number.',
        ]);

        $dial = collect($countries)
            ->firstWhere('code', $validated['country_code'])['dial'];

        $local = ltrim(preg_replace('/\D/', '', $validated['phone_number']), '0');

        $phoneNumber = $dial.$local;

        $organization = Organization::create([
            'id' => (string) Str::uuid(),
            'name' => $validated['organization_name'],
        ]);

        $user = User::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'role' => 'owner',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'country_code' => $validated['country_code'],
            'phone_number' => $phoneNumber,
            'password' => Hash::make($validated['password']),
        ]);

        $organization->update([
            'owner_id' => $user->id,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
