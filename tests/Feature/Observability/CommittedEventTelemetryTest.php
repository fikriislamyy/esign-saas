<?php

namespace Tests\Feature\Observability;

use App\Observability\Telemetry;
use Illuminate\Support\Facades\DB;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter as LogExporter;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use Tests\TestCase;

class CommittedEventTelemetryTest extends TestCase
{
    public function test_success_event_is_emitted_only_after_outer_transaction_commits(): void
    {
        $logs = new LogExporter;
        $telemetry = new Telemetry(
            (new TracerProviderBuilder)->build(),
            null,
            (new LoggerProviderBuilder)->addLogRecordProcessor(new SimpleLogRecordProcessor($logs))->build(),
        );

        DB::beginTransaction();
        DB::beginTransaction();
        $telemetry->eventAfterCommit('wallet.credit.completed', ['app.outcome' => 'success']);
        DB::commit();

        $this->assertCount(0, $logs->getStorage());

        DB::commit();

        $this->assertCount(1, $logs->getStorage());
    }

    public function test_success_event_is_discarded_when_transaction_rolls_back(): void
    {
        $logs = new LogExporter;
        $telemetry = new Telemetry(
            (new TracerProviderBuilder)->build(),
            null,
            (new LoggerProviderBuilder)->addLogRecordProcessor(new SimpleLogRecordProcessor($logs))->build(),
        );

        DB::beginTransaction();
        $telemetry->eventAfterCommit('wallet.credit.completed', ['app.outcome' => 'success']);
        DB::rollBack();

        $this->assertCount(0, $logs->getStorage());
    }
}
