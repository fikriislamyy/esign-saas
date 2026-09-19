<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\PakasirService;
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

        if (! $payment) {
            Log::warning('Pakasir webhook for unknown order', ['order_id' => $orderId]);

            return response()->json(['received' => true]);
        }

        if ($payment->provider !== 'pakasir') {
            Log::warning('Pakasir webhook for non-Pakasir payment', [
                'order_id' => $orderId,
                'provider' => $payment->provider,
            ]);

            return response()->json(['received' => true]);
        }

        if ((int) $request->input('amount') !== (int) $payment->amount) {
            Log::warning('Pakasir webhook amount mismatch', [
                'order_id' => $orderId,
                'expected' => (int) $payment->amount,
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

        $detail = $pakasir->transactionDetail($orderId, (int) $payment->amount);

        $transaction = $detail['transaction'] ?? [];
        $status = $transaction['status'] ?? null;

        if ($status !== 'completed') {
            Log::info('Pakasir transaction not completed', [
                'order_id' => $orderId,
                'status' => $status,
            ]);

            return response()->json(['received' => true]);
        }

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
}
