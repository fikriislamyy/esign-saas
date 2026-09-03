<?php

namespace App\Http\Middleware;

use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $organization = $request->user()?->organization;

        $wallet = $organization?->wallet;

        $usdToIdrRate = null;
        $balanceIdr = null;

        if ($wallet) {
            $usdToIdrRate = app(ExchangeRateService::class)
                ->usdToIdr();

            if ($usdToIdrRate !== null) {
                $balanceIdr = round(
                    ($wallet->balance_usd_cents / 100)
                    * $usdToIdrRate
                );
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
            ],

            'wallet' => $wallet
                ? [
                    'balanceUsdCents' => (int) $wallet->balance_usd_cents,
                    'balanceIdr' => $balanceIdr,
                    'usdToIdrRate' => $usdToIdrRate,
                ]
                : null,
        ]);
    }
}
