<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateService
{
    private const CACHE_KEY = 'billing.exchange_rates.usd';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * Get the current USD -> IDR exchange rate.
     *
     * Returns the number of IDR for 1 USD.
     */
    public function usdToIdr(): ?float
    {
        $apiKey = config('services.currencyfreaks.key');

        if (!$apiKey) {
            return null;
        }

        try {
            $rates = Cache::remember(
                self::CACHE_KEY,
                self::CACHE_TTL_SECONDS,
                function () use ($apiKey) {
                    $response = Http::timeout(5)
                        ->acceptJson()
                        ->get(
                            'https://api.currencyfreaks.com/v2.0/rates/latest',
                            [
                                'apikey' => $apiKey,
                                'symbols' => 'IDR',
                            ]
                        );

                    $response->throw();

                    $rate = data_get(
                        $response->json(),
                        'rates.IDR'
                    );

                    if (
                        $rate === null ||
                        !is_numeric($rate) ||
                        (float) $rate <= 0
                    ) {
                        throw new RuntimeException(
                            'CurrencyFreaks returned an invalid USD to IDR rate.'
                        );
                    }

                    return [
                        'IDR' => (float) $rate,
                    ];
                }
            );

            return isset($rates['IDR'])
                ? (float) $rates['IDR']
                : null;
        } catch (\Throwable $exception) {
            report($exception);

            /*
             * FX is used for display at this stage.
             *
             * We deliberately return null instead of preventing
             * the entire Billing page from loading when the
             * external FX provider is temporarily unavailable.
             */
            return null;
        }
    }

    /**
     * Convert USD to IDR using the current display rate.
     */
    public function convertUsdToIdr(float $usd): ?float
    {
        $rate = $this->usdToIdr();

        if ($rate === null) {
            return null;
        }

        return $usd * $rate;
    }
}
