<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use Illuminate\Http\Request;

class PaymentStatusController extends Controller
{
    public function show(Request $request, string $orderId)
    {
        $record = SubscriptionPayment::where('order_id', $orderId)->first()
            ?? WalletTopup::where('order_id', $orderId)->first();

        abort_unless(
            $record && $record->organization_id === $request->user()?->organization_id,
            404
        );

        return response()->json([
            'status' => $record->status,
            'paid_at' => $record->paid_at,
        ]);
    }
}
