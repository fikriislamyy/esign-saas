<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\EmailVerificationOtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    public function __construct(
        private EmailVerificationOtpService $otpService
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        if (! $this->otpService->verify($user, $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => 'The code is invalid or has expired.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
    }
}
