<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\LoginOtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginOtpController extends Controller
{
    public function __construct(
        private LoginOtpService $otp
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/LoginOtp', [
            'email' => $user->email,
            'status' => session('status'),
            'resendAfter' => $this->otp->retryAfter($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $this->otp->verify($user, $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => 'The code is invalid or has expired. Request a new one below.',
            ]);
        }

        $remember = (bool) $request->session()->get('login_otp.remember');
        $request->session()->forget('login_otp');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $wait = $this->otp->retryAfter($user);

        if ($wait > 0) {
            throw ValidationException::withMessages([
                'otp' => "Please wait {$wait} seconds before requesting another code.",
            ]);
        }

        $this->otp->send($user);

        return back()->with('status', 'login-code-sent');
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('login_otp.user_id');

        return $id ? User::find($id) : null;
    }
}
