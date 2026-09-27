<?php

namespace Tests\Feature\Observability;

use App\Observability\Telemetry;
use Illuminate\Support\Facades\Route;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter as LogExporter;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter as MetricExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use RuntimeException;
use Tests\TestCase;

class RequestTelemetryTest extends TestCase
{
    private function bindTelemetry(): array
    {
        $spans = new SpanExporter;
        $metrics = new MetricExporter;
        $logs = new LogExporter;
        $this->app->instance(Telemetry::class, new Telemetry(
            (new TracerProviderBuilder)->addSpanProcessor(new SimpleSpanProcessor($spans))->build(),
            (new MeterProviderBuilder)->addReader(new ExportingReader($metrics))->build(),
            (new LoggerProviderBuilder)->addLogRecordProcessor(new SimpleLogRecordProcessor($logs))->build(),
        ));

        return [$spans, $logs];
    }

    public function test_laravel_middleware_records_rendered_status_without_exporting_token(): void
    {
        [$spans, $logs] = $this->bindTelemetry();
        Route::get('/__telemetry_test__/{token}', fn () => response('unavailable', 503));

        $this->get('/__telemetry_test__/private-signing-token?otp=secret-value')->assertStatus(503);

        $this->assertCount(1, $spans->getSpans());
        $span = $spans->getSpans()[0];
        $this->assertSame('GET /__telemetry_test__/{token}', $span->getName());
        $this->assertSame(503, $span->getAttributes()->toArray()['http.response.status_code']);
        $this->assertSame('http.request.completed', $logs->getStorage()[0]->getBody());
        $this->assertSame('ERROR', $logs->getStorage()[0]->getSeverityText());
        $this->assertStringNotContainsString('private-signing-token', json_encode([$span->getAttributes()->toArray(), $logs->getStorage()[0]->getAttributes()->toArray()]));
        $this->assertStringNotContainsString('secret-value', json_encode([$span->getAttributes()->toArray(), $logs->getStorage()[0]->getAttributes()->toArray()]));
    }

    public function test_exception_rendered_by_laravel_keeps_safe_error_telemetry(): void
    {
        [$spans, $logs] = $this->bindTelemetry();
        Route::get('/__telemetry_exception__/{token}', fn () => throw new RuntimeException('private-exception-message'));

        $this->getJson('/__telemetry_exception__/private-signing-token')->assertStatus(500);

        $span = $spans->getSpans()[0];
        $this->assertSame('GET /__telemetry_exception__/{token}', $span->getName());
        $this->assertSame(500, $span->getAttributes()->toArray()['http.response.status_code']);
        $this->assertSame(RuntimeException::class, $span->getAttributes()->toArray()['error.type']);
        $allExported = json_encode([
            $span->getAttributes()->toArray(),
            array_map(fn ($log) => [$log->getBody(), $log->getAttributes()->toArray()], $logs->getStorage()->getArrayCopy()),
        ]);
        $this->assertStringNotContainsString('private-signing-token', $allExported);
        $this->assertStringNotContainsString('private-exception-message', $allExported);
    }
}
