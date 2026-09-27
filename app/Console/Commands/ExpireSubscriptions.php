<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Observability\Telemetry;
use App\Services\StripeService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    private int $providerFailures = 0;

    protected $signature = 'subscriptions:expire
                            {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Return lapsed paid subscriptions to the free plan';

    public function handle(StripeService $stripe): int
    {
        return app(Telemetry::class)->runCommand('subscriptions:expire', fn (): int => $this->expire($stripe));
    }

    private function expire(StripeService $stripe): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->providerFailures = 0;

        $downgraded = 0;
        $skipped = 0;

        Subscription::query()
            ->whereIn('plan', ['pro', 'enterprise'])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->chunkById(100, function ($subscriptions) use ($stripe, $dryRun, &$downgraded, &$skipped) {
                foreach ($subscriptions as $subscription) {
                    // Trap 6: Stripe is the source of truth for Stripe subscriptions.
                    if ($subscription->provider === 'stripe' && $subscription->stripe_subscription_id) {
                        if ($this->stillActiveAtStripe($stripe, $subscription)) {
                            $skipped++;

                            continue;
                        }
                    }

                    if ($dryRun) {
                        $this->line("  would downgrade org {$subscription->organization_id} from {$subscription->plan}");
                        $downgraded++;

                        continue;
                    }

                    // Decision 3.4: same shape the Stripe cancellation webhook writes.
                    $subscription->update([
                        'plan' => 'free',
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);

                    $downgraded++;
                }
            });

        $this->info("Subscriptions downgraded: {$downgraded}");

        if ($skipped > 0) {
            $this->warn("Still active at Stripe, left alone: {$skipped} (a webhook was probably missed)");
        }

        app(Telemetry::class)->event('subscriptions.expiry.summary', [
            'app.outcome' => $this->providerFailures > 0 ? 'partial' : 'success',
            'app.processed_count' => $downgraded,
            'app.skipped_count' => $skipped,
            'app.failed_count' => $this->providerFailures,
            'app.dry_run' => $dryRun,
        ], $this->providerFailures > 0 ? 'WARN' : 'INFO');

        return self::SUCCESS;
    }

    protected function stillActiveAtStripe(StripeService $stripe, Subscription $subscription): bool
    {
        try {
            $remote = app(Telemetry::class)->trackIntegration(
                'stripe',
                'stripe_retrieve_subscription',
                fn () => $stripe->client()->subscriptions->retrieve($subscription->stripe_subscription_id, [])
            );
        } catch (\Throwable $e) {
            $this->providerFailures++;

            return true;
        }

        if (in_array($remote->status, ['active', 'trialing'], true)) {
            return true;
        }

        return false;
    }
}
