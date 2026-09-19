<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use App\Services\PakasirService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PakasirWebhookController extends Controller
{
    public function handle(Request $request, PakasirService $pakasir)
    {
        $orderId = $request->input('order_id');

        if (! $orderId) {
            return response()->json(['error' => 'order_id missing'], 400);
        }

        $payment = SubscriptionPayment::where('order_id', $orderId)->first();
        $topup = $payment ? null : WalletTopup::where('order_id', $orderId)->first();

        $record = $payment ?? $topup;

        if (! $record) {
            Log::warning('Pakasir webhook for unknown order', ['order_id' => $orderId]);

            return response()->json(['received' => true]);
        }

        // subscription_payments.amount is already IDR. wallet_topups.amount is USD,
        // so the rupiah figure lives in metadata (see BillingTopupController).
        $expectedIdr = $payment
            ? (int) $payment->amount
            : (int) ($topup->metadata['amount_idr'] ?? 0);

        if ($record->provider !== 'pakasir') {
            Log::warning('Pakasir webhook for non-Pakasir payment', [
                'order_id' => $orderId,
                'provider' => $record->provider,
            ]);

            return response()->json(['received' => true]);
        }

        if ((int) $request->input('amount') !== $expectedIdr) {
            Log::warning('Pakasir webhook amount mismatch', [
                'order_id' => $orderId,
                'expected' => $expectedIdr,
                'received' => $request->input('amount'),
            ]);

            return response()->json(['received' => true]);
        }

        if ($request->input('project') !== config('services.pakasir.project')) {
            Log::warning('Pakasir webhook project mismatch', [
                'order_id' => $orderId,
                'expected' => config('services.pakasir.project'),
                'received' => $request->input('project'),
            ]);

            return response()->json(['received' => true]);
        }

        $detail = $pakasir->transactionDetail($orderId, $expectedIdr);

        $transaction = $detail['transaction'] ?? [];
        $status = $transaction['status'] ?? null;

        if ($status !== 'completed') {
            Log::info('Pakasir transaction not completed', [
                'order_id' => $orderId,
                'status' => $status,
            ]);

            return response()->json(['received' => true]);
        }

        if ($payment) {
            DB::transaction(function () use ($payment) {
                $payment = SubscriptionPayment::lockForUpdate()->find($payment->id);

                if ($payment->status === 'paid') {
                    return;
                }

                $payment->update(['status' => 'paid', 'paid_at' => now()]);

                $payment->organization->subscription->update([
                    'plan' => $payment->plan,
                    'status' => 'active',
                    'provider' => 'pakasir',
                    'subscribed_at' => now(),
                    'expired_at' => now()->addDays(30),
                ]);
            });

            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($topup, $orderId) {
            $topup = WalletTopup::lockForUpdate()->find($topup->id);

            if ($topup->status === 'paid') {
                return;
            }

            app(WalletService::class)->credit(
                organization: $topup->organization,
                sourceCurrency: $topup->currency,
                sourceAmount: (float) $topup->amount,
                exchangeRate: (float) $topup->exchange_rate,
                amountUsdCents: (int) $topup->wallet_amount_usd_cents,
                type: 'topup',
                description: 'Wallet top-up via QRIS',
                reference: $topup,
                createdBy: $topup->created_by,
                metadata: ['pakasir_order_id' => $orderId],
            );

            $topup->update(['status' => 'paid', 'paid_at' => now()]);
        });

        return response()->json(['received' => true]);
    }
}
