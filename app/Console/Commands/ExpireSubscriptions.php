<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire
                            {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Return lapsed paid subscriptions to the free plan';

    public function handle(StripeService $stripe): int
    {
        $dryRun = (bool) $this->option('dry-run');

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

        return self::SUCCESS;
    }

    protected function stillActiveAtStripe(StripeService $stripe, Subscription $subscription): bool
    {
        try {
            $remote = $stripe->client()->subscriptions->retrieve(
                $subscription->stripe_subscription_id,
                []
            );
        } catch (\Throwable $e) {
            Log::warning('Could not verify subscription at Stripe; leaving it alone', [
                'subscription_id' => $subscription->id,
                'message' => $e->getMessage(),
            ]);

            return true;
        }

        if (in_array($remote->status, ['active', 'trialing'], true)) {
            Log::warning('Subscription looked expired locally but is active at Stripe', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
            ]);

            return true;
        }

        return false;
    }
}
