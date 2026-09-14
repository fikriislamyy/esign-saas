<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use App\Services\ExchangeRateService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected ExchangeRateService $exchangeRateService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        abort_unless(
            $user?->isOwner(),
            403
        );

        $organization = $user->organization;

        abort_unless(
            $organization,
            403,
            'You do not belong to an organization.'
        );

        $wallet = $this->walletService
            ->getOrCreateWallet($organization);

        /*
        |--------------------------------------------------------------------------
        | Exchange rate
        |--------------------------------------------------------------------------
        */

        $usdToIdrRate = $this->exchangeRateService
            ->usdToIdr();

        $balanceUsd = $wallet->balance_usd_cents / 100;

        $balanceIdr = $usdToIdrRate !== null
            ? round($balanceUsd * $usdToIdrRate)
            : null;

        /*
        |--------------------------------------------------------------------------
        | Monthly statistics
        |--------------------------------------------------------------------------
        */

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlySignatureTransactions = WalletTransaction::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'signature')
            ->whereBetween('created_at', [
                $startOfMonth,
                $endOfMonth,
            ]);

        $monthlySignatureCount = (int) (
            (clone $monthlySignatureTransactions)
                ->get()
                ->sum(function ($transaction) {
                    return (int) data_get(
                        $transaction->metadata,
                        'signature_plot_count',
                        0
                    );
                })
        );

        $monthlySpentUsdCents = abs(
            (int) (clone $monthlySignatureTransactions)
                ->sum('amount_usd_cents')
        );

        /*
        |--------------------------------------------------------------------------
        | All-time statistics
        |--------------------------------------------------------------------------
        */

        $totalSignatureTransactions = WalletTransaction::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'signature');

        $totalSignatureCount = (int) (
            (clone $totalSignatureTransactions)
                ->get()
                ->sum(function ($transaction) {
                    return (int) data_get(
                        $transaction->metadata,
                        'signature_plot_count',
                        0
                    );
                })
        );

        $totalSpentUsdCents = abs(
            (int) (clone $totalSignatureTransactions)
                ->sum('amount_usd_cents')
        );

        /*
        |--------------------------------------------------------------------------
        | Transaction history
        |--------------------------------------------------------------------------
        */

        $transactions = WalletTransaction::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Billing/Index', [
            'wallet' => [
                'balanceUsdCents' => (int) $wallet->balance_usd_cents,
                'balanceIdr' => $balanceIdr,
                'usdToIdrRate' => $usdToIdrRate,
            ],

            'stats' => [
                'monthlySignatureCount' => $monthlySignatureCount,
                'monthlySpentUsdCents' => $monthlySpentUsdCents,
                'totalSignatureCount' => $totalSignatureCount,
                'totalSpentUsdCents' => $totalSpentUsdCents,
            ],

            'transactions' => $transactions,
        ]);
    }
}
