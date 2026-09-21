<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Services\LoginOtpService;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        if (! app(RecaptchaService::class)->isConfigured() && app()->isProduction()) {
            Log::error('reCAPTCHA is not configured; auth forms are unprotected', [
                'env_keys' => ['RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY'],
            ]);
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, LoginOtpService $otp): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->put('login_otp', [
            'user_id' => $user->id,
            'remember' => $request->boolean('remember'),
        ]);

        if ($otp->retryAfter($user) === 0) {
            $otp->send($user);
        }

        return redirect()->route('login.otp');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
