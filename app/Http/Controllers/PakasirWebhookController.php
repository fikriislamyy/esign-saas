<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use App\Services\PakasirFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PakasirWebhookController extends Controller
{
    public function handle(Request $request, PakasirFulfillmentService $fulfillment)
    {
        $orderId = $request->input('order_id');

        if (! $orderId) {
            return response()->json(['error' => 'order_id missing'], 400);
        }

        $record = SubscriptionPayment::where('order_id', $orderId)->first()
            ?? WalletTopup::where('order_id', $orderId)->first();

        if (! $record) {
            Log::warning('Pakasir webhook for unknown order', ['order_id' => $orderId]);

            return response()->json(['received' => true]);
        }

        if ($record->provider !== 'pakasir') {
            Log::warning('Pakasir webhook for non-Pakasir payment', [
                'order_id' => $orderId,
                'provider' => $record->provider,
            ]);

            return response()->json(['received' => true]);
        }

        $expectedIdr = $fulfillment->amountIdr($record);

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

        $fulfillment->fulfill($record);

        return response()->json(['received' => true]);
    }
}
