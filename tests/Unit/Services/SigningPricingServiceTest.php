<?php

namespace Tests\Unit\Services;

use App\Services\SigningPricingService;
use Tests\TestCase;

class SigningPricingServiceTest extends TestCase
{
    public function test_price_per_signature_plot_is_10(): void
    {
        $service = new SigningPricingService();

        $this->assertSame(10, $service->pricePerSignaturePlot());
    }

    public function test_calculate_cost_of_zero_plots_is_zero(): void
    {
        $service = new SigningPricingService();

        $this->assertSame(0, $service->calculateCost(0));
    }

    public function test_calculate_cost_of_three_plots_is_30(): void
    {
        $service = new SigningPricingService();

        $this->assertSame(30, $service->calculateCost(3));
    }

    public function test_calculate_cost_of_1000_plots_is_10000(): void
    {
        $service = new SigningPricingService();

        $this->assertSame(10000, $service->calculateCost(1000));
    }
}
