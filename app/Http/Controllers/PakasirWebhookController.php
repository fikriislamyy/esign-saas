<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use App\Observability\Telemetry;
use App\Services\PakasirFulfillmentService;
use Illuminate\Http\Request;

class PakasirWebhookController extends Controller
{
    public function handle(Request $request, PakasirFulfillmentService $fulfillment)
    {
        app(Telemetry::class)->event('payment.webhook.received', [
            'app.outcome' => 'success',
            'payment.provider' => 'pakasir',
        ]);

        $orderId = $request->input('order_id');

        if (! $orderId) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'invalid_payload',
                'payment.provider' => 'pakasir',
            ]);

            return response()->json(['error' => 'order_id missing'], 400);
        }

        $record = SubscriptionPayment::where('order_id', $orderId)->first()
            ?? WalletTopup::where('order_id', $orderId)->first();

        if (! $record) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'unknown_record',
                'payment.provider' => 'pakasir',
            ]);

            return response()->json(['received' => true]);
        }

        if ($record->provider !== 'pakasir') {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'wrong_provider',
                'payment.provider' => 'pakasir',
            ]);

            return response()->json(['received' => true]);
        }

        $expectedIdr = $fulfillment->amountIdr($record);

        if ((int) $request->input('amount') !== $expectedIdr) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'amount_mismatch',
                'payment.provider' => 'pakasir',
            ]);

            return response()->json(['received' => true]);
        }

        if ($request->input('project') !== config('services.pakasir.project')) {
            app(Telemetry::class)->event('payment.webhook.rejected', [
                'app.outcome' => 'rejected',
                'app.reason' => 'project_mismatch',
                'payment.provider' => 'pakasir',
            ]);

            return response()->json(['received' => true]);
        }

        $fulfillment->fulfill($record);

        return response()->json(['received' => true]);
    }
}
