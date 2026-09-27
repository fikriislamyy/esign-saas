<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Observability\Telemetry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExpireDocuments extends Command
{
    protected $signature = 'documents:expire
                            {--days= : Override the retention window}
                            {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Expire unsigned documents past the retention window and delete their files';

    public function handle(): int
    {
        return app(Telemetry::class)->runCommand('documents:expire', fn (): int => $this->expire());
    }

    private function expire(): int
    {
        $days = (int) ($this->option('days') ?: config('documents.retention_days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $disk = Storage::disk(config('documents.disk'));

        $expired = 0;
        $deleteFailures = 0;

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Expiring draft/sent documents created before {$cutoff}");

        Document::query()
            ->whereIn('status', ['draft', 'sent'])
            ->where('created_at', '<', $cutoff)
            ->chunkById(100, function ($documents) use ($disk, $dryRun, &$expired, &$deleteFailures) {
                foreach ($documents as $document) {
                    if ($dryRun) {
                        $this->line("  would expire {$document->id} ({$document->name})");
                        $expired++;

                        continue;
                    }

                    foreach ([$document->file_path, $document->signed_path] as $path) {
                        if (! $path) {
                            continue;
                        }

                        if (! $disk->delete($path)) {
                            $deleteFailures++;
                        }
                    }

                    $document->update(['status' => 'expired']);
                    $expired++;
                }
            });

        $this->info("Documents expired: {$expired}");

        if ($deleteFailures > 0) {
            $this->warn("Files that could not be deleted: {$deleteFailures} (see log)");
        }

        app(Telemetry::class)->event('documents.expiry.summary', [
            'app.outcome' => $deleteFailures > 0 ? 'partial' : 'success',
            'app.processed_count' => $expired,
            'app.skipped_count' => 0,
            'app.failed_count' => $deleteFailures,
            'app.dry_run' => $dryRun,
        ], $deleteFailures > 0 ? 'WARN' : 'INFO');

        return self::SUCCESS;
    }
}
