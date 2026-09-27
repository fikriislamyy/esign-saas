<?php

namespace App\Console\Commands;

use App\Observability\Telemetry;
use Illuminate\Console\Command;

class ObservabilitySmoke extends Command
{
    protected $signature = 'observability:smoke {--fail : Produce a synthetic failure}';

    protected $description = 'Emit a local synthetic observability sample';

    public function handle(Telemetry $telemetry): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('The smoke command is restricted to local and testing environments.');

            return self::FAILURE;
        }
        if (! $telemetry->enabled()) {
            $this->error('Observability is disabled.');

            return self::FAILURE;
        }

        $traceId = $telemetry->smoke((bool) $this->option('fail'));
        $telemetry->forceFlush();
        $this->info("Telemetry export attempted for trace {$traceId}.");

        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }
}
