<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Observability\Telemetry;
use App\Services\LoginOtpService;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            app(Telemetry::class)->event('integration.request.failed', [
                'app.operation' => 'verify_recaptcha',
                'app.outcome' => 'failure',
                'app.reason' => 'not_configured',
                'integration.provider' => 'recaptcha',
                'app.duration_ms' => 0,
                'error.type' => \RuntimeException::class,
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

        app(Telemetry::class)->event('auth.logout.completed', ['app.outcome' => 'success']);

        return redirect('/');
    }
}
