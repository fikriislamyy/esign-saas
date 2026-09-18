<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

class PaymentStatusController extends Controller
{
    public function show(Request $request, string $orderId)
    {
        $payment = SubscriptionPayment::where('order_id', $orderId)->first();

        abort_unless(
            $payment && $payment->organization_id === $request->user()?->organization_id,
            404
        );

        return response()->json([
            'status' => $payment->status,
            'paid_at' => $payment->paid_at,
        ]);
    }
}
