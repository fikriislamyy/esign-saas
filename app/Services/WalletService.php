<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Exceptions\InsufficientWalletBalanceException;

class WalletService
{
    /**
     * Get or create the wallet belonging to an organization.
     */
    public function getOrCreateWallet(Organization $organization): Wallet
    {
        return $organization->wallet()->firstOrCreate(
            [
                'organization_id' => $organization->id,
            ],
            [
                'balance_usd_cents' => 0,
            ]
        );
    }

    /**
     * Get the current wallet balance in USD cents.
     */
    public function getBalance(Organization $organization): int
    {
        return (int) $this->getOrCreateWallet($organization)->balance_usd_cents;
    }

    /**
     * Credit the wallet.
     *
     * Example:
     *
     * source_currency = USD
     * source_amount   = 10.00
     * exchange_rate   = 1
     * amount_usd_cents = 1000
     */
    public function credit(
        Organization $organization,
        string $sourceCurrency,
        float $sourceAmount,
        float $exchangeRate,
        int $amountUsdCents,
        string $type = 'topup',
        ?string $description = null,
        ?Model $reference = null,
        ?string $createdBy = null,
        ?array $metadata = null,
    ): WalletTransaction {
        return DB::transaction(function () use (
            $organization,
            $sourceCurrency,
            $sourceAmount,
            $exchangeRate,
            $amountUsdCents,
            $type,
            $description,
            $reference,
            $createdBy,
            $metadata,
        ) {
            $wallet = $this->getLockedWallet($organization);

            $balanceBefore = (int) $wallet->balance_usd_cents;
            $balanceAfter = $balanceBefore + $amountUsdCents;

            $wallet->update([
                'balance_usd_cents' => $balanceAfter,
            ]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'organization_id' => $organization->id,

                'type' => $type,

                'source_currency' => strtoupper($sourceCurrency),
                'source_amount' => $sourceAmount,
                'exchange_rate' => $exchangeRate,

                'amount_usd_cents' => $amountUsdCents,

                'balance_before_usd_cents' => $balanceBefore,
                'balance_after_usd_cents' => $balanceAfter,

                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),

                'description' => $description,

                'created_by' => $createdBy,

                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Debit the wallet.
     *
     * Throws RuntimeException when the organization
     * does not have enough wallet balance.
     */
    public function debit(
        Organization $organization,
        string $sourceCurrency,
        float $sourceAmount,
        float $exchangeRate,
        int $amountUsdCents,
        string $type = 'signature',
        ?string $description = null,
        ?Model $reference = null,
        ?string $createdBy = null,
        ?array $metadata = null,
    ): WalletTransaction {
        return DB::transaction(function () use (
            $organization,
            $sourceCurrency,
            $sourceAmount,
            $exchangeRate,
            $amountUsdCents,
            $type,
            $description,
            $reference,
            $createdBy,
            $metadata,
        ) {
            $wallet = $this->getLockedWallet($organization);

            $balanceBefore = (int) $wallet->balance_usd_cents;

            if ($balanceBefore < $amountUsdCents) {
                throw new InsufficientWalletBalanceException();
            }

            $balanceAfter = $balanceBefore - $amountUsdCents;

            $wallet->update([
                'balance_usd_cents' => $balanceAfter,
            ]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'organization_id' => $organization->id,

                'type' => $type,

                'source_currency' => strtoupper($sourceCurrency),
                'source_amount' => $sourceAmount,
                'exchange_rate' => $exchangeRate,

                // Debit transactions are negative.
                'amount_usd_cents' => -abs($amountUsdCents),

                'balance_before_usd_cents' => $balanceBefore,
                'balance_after_usd_cents' => $balanceAfter,

                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),

                'description' => $description,

                'created_by' => $createdBy,

                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Lock the organization's wallet for update.
     *
     * This prevents two simultaneous requests from
     * modifying the balance incorrectly.
     */
    protected function getLockedWallet(Organization $organization): Wallet
    {
        $wallet = $organization->wallet()
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            $wallet = Wallet::create([
                'organization_id' => $organization->id,
                'balance_usd_cents' => 0,
            ]);

            /*
             * Re-fetch with lock after creation so the rest
             * of the transaction works against the locked row.
             */
            $wallet = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $wallet;
    }
}