<?php

namespace App\Services;

use App\Models\SubscriptionPayment;
use App\Models\WalletTopup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PakasirFulfillmentService
{
    public function __construct(
        protected PakasirService $pakasir,
        protected WalletService $wallet,
    ) {}

    /**
     * The rupiah figure Pakasir was asked to charge for this record.
     *
     * subscription_payments.amount is already IDR. wallet_topups.amount is the
     * USD the user typed, so its rupiah figure lives in metadata.
     */
    public function amountIdr(SubscriptionPayment|WalletTopup $record): int
    {
        return $record instanceof SubscriptionPayment
            ? (int) $record->amount
            : (int) ($record->metadata['amount_idr'] ?? 0);
    }

    /**
     * Confirm with Pakasir that the order is completed, then mark it paid locally.
     *
     * Returns true when the record is (now) paid. Returns false when Pakasir has
     * not completed it, or when $sandboxOnly is set and Pakasir does not report
     * the transaction as sandbox. Safe to call twice: a paid record is a no-op.
     */
    public function fulfill(SubscriptionPayment|WalletTopup $record, bool $sandboxOnly = false): bool
    {
        $detail = $this->pakasir->transactionDetail($record->order_id, $this->amountIdr($record));
        $transaction = $detail['transaction'] ?? [];

        if (($transaction['status'] ?? null) !== 'completed') {
            Log::info('Pakasir transaction not completed', [
                'order_id' => $record->order_id,
                'status' => $transaction['status'] ?? null,
            ]);

            return false;
        }

        if ($sandboxOnly && ($transaction['is_sandbox'] ?? false) !== true) {
            Log::error('Refused to auto-fulfil a non-sandbox Pakasir transaction', [
                'order_id' => $record->order_id,
            ]);

            return false;
        }

        if ($record instanceof SubscriptionPayment) {
            $this->fulfillSubscription($record);
        } else {
            $this->fulfillTopup($record);
        }

        return true;
    }

    protected function fulfillSubscription(SubscriptionPayment $payment): void
    {
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
    }

    protected function fulfillTopup(WalletTopup $topup): void
    {
        DB::transaction(function () use ($topup) {
            $topup = WalletTopup::lockForUpdate()->find($topup->id);

            if ($topup->status === 'paid') {
                return;
            }

            $this->wallet->credit(
                organization: $topup->organization,
                sourceCurrency: $topup->currency,
                sourceAmount: (float) $topup->amount,
                exchangeRate: (float) $topup->exchange_rate,
                amountUsdCents: (int) $topup->wallet_amount_usd_cents,
                type: 'topup',
                description: 'Wallet top-up via QRIS',
                reference: $topup,
                createdBy: $topup->created_by,
                metadata: ['pakasir_order_id' => $topup->order_id],
            );

            $topup->update(['status' => 'paid', 'paid_at' => now()]);
        });
    }
}
