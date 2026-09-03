<?php

namespace App\Services;

class SigningPricingService
{
    /**
     * Price for one signature plot, in USD cents.
     *
     * Example:
     * 10 cents = $0.10 per signature plot.
     */
    public function pricePerSignaturePlot(): int
    {
        return 10;
    }

    public function calculateCost(int $signaturePlotCount): int
    {
        return $signaturePlotCount * $this->pricePerSignaturePlot();
    }
}
