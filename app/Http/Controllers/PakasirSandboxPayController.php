<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use App\Services\PakasirFulfillmentService;
use App\Services\PakasirService;

class PakasirSandboxPayController extends Controller
{
    /**
     * Sandbox stand-in for a customer scanning the QR with a real wallet app.
     *
     * Reached by opening the link the QR encodes, so it has to be a GET. The
     * signed URL is the credential, exactly as possession of a real QR is.
     */
    public function __invoke(string $orderId, PakasirService $pakasir, PakasirFulfillmentService $fulfillment)
    {
        abort_unless(config('services.pakasir.auto_simulate'), 404);

        $record = SubscriptionPayment::where('order_id', $orderId)->first()
            ?? WalletTopup::where('order_id', $orderId)->first();

        abort_unless($record && $record->provider === 'pakasir', 404);

        if ($record->status === 'paid') {
            return $this->page('Already paid', 'This sandbox order was completed earlier. You can close this tab.');
        }

        $pakasir->simulatePayment($orderId, $fulfillment->amountIdr($record));

        $paid = $fulfillment->fulfill($record, sandboxOnly: true);

        return $paid
            ? $this->page('Payment simulated', 'Sandbox payment completed. Go back to the app — the dialog will update in a moment.')
            : $this->page('Not completed', 'Pakasir did not report this order as a completed sandbox transaction. Check the logs.');
    }

    protected function page(string $title, string $body)
    {
        $t = e($title);
        $b = e($body);

        return response(<<<HTML
        <!doctype html>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$t}</title>
        <body style="margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#08090a;color:#d0d6e0;padding:24px;text-align:center">
        <div><h1 style="font-size:20px;margin:0 0 8px;color:#fff">{$t}</h1><p style="margin:0;max-width:32ch;line-height:1.6">{$b}</p></div>
        HTML);
    }
}
