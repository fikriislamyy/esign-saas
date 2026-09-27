<?php

namespace Tests\Unit\Observability;

use App\Observability\Telemetry;
use App\Observability\TelemetryAttributes;
use App\Observability\TelemetryFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter as LogExporter;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter as MetricExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as SpanExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class TelemetryTest extends TestCase
{
    private function telemetry(string $minimumLogSeverity = 'INFO'): array
    {
        $spans = new SpanExporter;
        $metrics = new MetricExporter(temporality: Temporality::DELTA);
        $logs = new LogExporter;
        $telemetry = new Telemetry(
            (new TracerProviderBuilder)->addSpanProcessor(new SimpleSpanProcessor($spans))->build(),
            (new MeterProviderBuilder)->addReader(new ExportingReader($metrics))->build(),
            (new LoggerProviderBuilder)->addLogRecordProcessor(new SimpleLogRecordProcessor($logs))->build(),
            $minimumLogSeverity,
        );

        return [$telemetry, $spans, $metrics, $logs];
    }

    public function test_disabled_mode_never_changes_callback_or_exports(): void
    {
        $telemetry = TelemetryFactory::make(['enabled' => false], false, 'testing');
        $called = 0;
        $response = $telemetry->traceRequest(Request::create('/anything'), function () use (&$called): Response {
            $called++;

            return new Response('ok', 200);
        });

        $this->assertSame(1, $called);
        $this->assertSame('ok', $response->getContent());
        $this->assertNull($telemetry->smoke());
    }

    public function test_route_template_and_correlated_log_exclude_canary_secrets(): void
    {
        [$telemetry, $spans, $metrics, $logs] = $this->telemetry();
        $request = Request::create('/sign/secret-token?otp=super-secret', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer hidden-token',
            'HTTP_TRACEPARENT' => '00-0123456789abcdef0123456789abcdef-0123456789abcdef-01',
        ]);
        $request->setRouteResolver(fn () => new Route('GET', 'sign/{token}', fn () => null));
        $response = $telemetry->traceRequest($request, fn () => new Response('ok', 200));
        $telemetry->forceFlush();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $spans->getSpans());
        $span = $spans->getSpans()[0];
        $this->assertSame('GET /sign/{token}', $span->getName());
        $this->assertSame('0123456789abcdef0123456789abcdef', $span->getTraceId());
        $this->assertSame('0123456789abcdef', $span->getParentSpanId());
        $this->assertSame('sign/{token}', ltrim($span->getAttributes()->toArray()['http.route'], '/'));
        $this->assertCount(1, $logs->getStorage());
        $log = $logs->getStorage()[0];
        $this->assertSame('http.request.completed', $log->getBody());
        $this->assertSame($span->getTraceId(), $log->getSpanContext()->getTraceId());
        $this->assertSame($span->getSpanId(), $log->getSpanContext()->getSpanId());
        $exportedMetrics = $metrics->collect();
        $this->assertSame(['esign.http.requests', 'esign.http.duration'], array_map(fn ($metric) => $metric->name, $exportedMetrics));
        foreach ($exportedMetrics as $metric) {
            foreach ($metric->data->dataPoints as $point) {
                $this->assertStringNotContainsString('secret-token', json_encode($point->attributes->toArray()));
                $this->assertStringNotContainsString('super-secret', json_encode($point->attributes->toArray()));
            }
        }
        $this->assertStringNotContainsString('secret-token', json_encode([$span->getAttributes()->toArray(), $log->getAttributes()->toArray()]));
        $this->assertStringNotContainsString('super-secret', json_encode([$span->getAttributes()->toArray(), $log->getAttributes()->toArray()]));
        $this->assertFalse(Span::getCurrent()->getContext()->isValid());
    }

    public function test_exception_message_is_not_exported_and_context_is_detached(): void
    {
        [$telemetry, $spans, , $logs] = $this->telemetry();
        try {
            $telemetry->withinSpan('payment.fulfill', ['payment.provider' => 'pakasir', 'order.id' => 'private-order'],
                fn () => throw new RuntimeException('private-password'));
            $this->fail('Expected original exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('private-password', $error->getMessage());
        }

        $span = $spans->getSpans()[0];
        $this->assertSame('payment.fulfill', $span->getName());
        $this->assertArrayNotHasKey('order.id', $span->getAttributes()->toArray());
        $this->assertSame(RuntimeException::class, $span->getAttributes()->toArray()['error.type']);
        $this->assertSame(RuntimeException::class, $logs->getStorage()[0]->getAttributes()->toArray()['error.type']);
        $this->assertStringNotContainsString('private-password', json_encode([$span->getAttributes()->toArray(), $logs->getStorage()[0]->getAttributes()->toArray()]));
        $this->assertFalse(Span::getCurrent()->getContext()->isValid());
    }

    public function test_command_records_failed_exit_code_and_flushes_without_changing_result(): void
    {
        [$telemetry, $spans, $metrics, $logs] = $this->telemetry();
        $this->assertSame(7, $telemetry->runCommand('documents:expire', fn () => 7));
        $this->assertSame('artisan documents:expire', $spans->getSpans()[0]->getName());
        $this->assertSame('failure', $spans->getSpans()[0]->getAttributes()->toArray()['app.outcome']);
        $this->assertSame('command.completed', $logs->getStorage()[0]->getBody());
        $this->assertSame(['esign.command.runs', 'esign.command.duration'], array_map(fn ($metric) => $metric->name, $metrics->collect()));
        $this->assertFalse(Span::getCurrent()->getContext()->isValid());
    }

    public function test_child_span_is_nested_and_context_does_not_leak_to_next_request(): void
    {
        [$telemetry, $spans] = $this->telemetry();
        $request = Request::create('/first');
        $telemetry->traceRequest($request, function () use ($telemetry): Response {
            $telemetry->withinSpan('payment.fulfill', ['payment.provider' => 'pakasir'], fn (): bool => true);

            return new Response('ok');
        });
        $telemetry->traceRequest(Request::create('/second'), fn () => new Response('ok'));

        $exported = $spans->getSpans();
        $this->assertCount(3, $exported);
        $child = $exported[0];
        $first = $exported[1];
        $second = $exported[2];
        $this->assertSame('payment.fulfill', $child->getName());
        $this->assertSame($first->getSpanId(), $child->getParentSpanId());
        $this->assertSame($first->getTraceId(), $child->getTraceId());
        $this->assertNotSame($first->getTraceId(), $second->getTraceId());
        $this->assertFalse(Span::getCurrent()->getContext()->isValid());
    }

    public function test_allowlist_rejects_arbitrary_values(): void
    {
        $this->assertSame(['app.outcome' => 'success'], TelemetryAttributes::filter([
            'app.outcome' => 'success',
            'request.body' => 'secret',
            'error.type' => new RuntimeException('secret'),
        ]));
    }

    public function test_unsampled_request_still_records_metrics(): void
    {
        $spans = new SpanExporter;
        $metrics = new MetricExporter(temporality: Temporality::DELTA);
        $telemetry = new Telemetry(
            (new TracerProviderBuilder)->setSampler(new AlwaysOffSampler)
                ->addSpanProcessor(new SimpleSpanProcessor($spans))->build(),
            (new MeterProviderBuilder)->addReader(new ExportingReader($metrics))->build(),
        );

        $telemetry->traceRequest(Request::create('/first'), fn () => new Response('ok'));
        $telemetry->forceFlush();

        $this->assertCount(0, $spans->getSpans());
        $exported = $metrics->collect();
        $this->assertSame(['esign.http.requests', 'esign.http.duration'], array_map(fn ($metric) => $metric->name, $exported));
        $this->assertSame(1, $exported[0]->data->dataPoints[0]->value);
        $this->assertGreaterThanOrEqual(0, $exported[1]->data->dataPoints[0]->sum);
    }

    public function test_malformed_remote_parent_is_ignored(): void
    {
        [$telemetry, $spans] = $this->telemetry();
        $request = Request::create('/first', 'GET', [], [], [], [
            'HTTP_TRACEPARENT' => '00-secret-invalid-parent-01',
        ]);

        $telemetry->traceRequest($request, fn () => new Response('ok'));

        $this->assertCount(1, $spans->getSpans());
        $this->assertSame('0000000000000000', $spans->getSpans()[0]->getParentSpanId());
        $this->assertFalse(Span::getCurrent()->getContext()->isValid());
    }

    public function test_command_exception_is_rethrown_after_recording_failure(): void
    {
        [$telemetry, $spans, $metrics, $logs] = $this->telemetry();

        try {
            $telemetry->runCommand('documents:expire', fn () => throw new RuntimeException('private-command-error'));
            $this->fail('Expected original exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('private-command-error', $error->getMessage());
        }

        $this->assertSame('failure', $spans->getSpans()[0]->getAttributes()->toArray()['app.outcome']);
        $this->assertSame(RuntimeException::class, $spans->getSpans()[0]->getAttributes()->toArray()['error.type']);
        $this->assertSame('command.completed', $logs->getStorage()[0]->getBody());
        $this->assertSame(['esign.command.runs', 'esign.command.duration'], array_map(fn ($metric) => $metric->name, $metrics->collect()));
        $this->assertFalse(Span::getCurrent()->getContext()->isValid());
    }

    public function test_export_flush_failure_and_repeated_shutdown_do_not_change_operation_result(): void
    {
        [, $spans, , $logs] = $this->telemetry();
        $throwingMetrics = $this->createMock(\OpenTelemetry\SDK\Metrics\MeterProviderInterface::class);
        $throwingMetrics->method('forceFlush')->willThrowException(new RuntimeException('secret-export-endpoint'));
        $telemetry = new Telemetry(
            (new TracerProviderBuilder)->addSpanProcessor(new SimpleSpanProcessor($spans))->build(),
            $throwingMetrics,
            (new LoggerProviderBuilder)->addLogRecordProcessor(new SimpleLogRecordProcessor($logs))->build(),
        );

        $this->assertSame(0, $telemetry->runCommand('documents:expire', fn () => 0));
        $telemetry->shutdown();
        $telemetry->shutdown();

        $this->assertFalse($telemetry->enabled());
        $this->assertCount(1, $spans->getSpans());
        $this->assertCount(1, $logs->getStorage());
    }

    public function test_business_event_catalog_adds_fixed_context_and_rejects_unknown_data(): void
    {
        [$telemetry, , , $logs] = $this->telemetry();

        $telemetry->event('signing.submit.rejected', [
            'app.outcome' => 'rejected',
            'app.reason' => 'invalid_otp',
        ]);
        $telemetry->event('signing.submit.rejected', [
            'app.outcome' => 'rejected',
            'app.reason' => 'invalid_otp',
            'request.body' => 'canary-secret',
        ]);
        $telemetry->event('unknown.event', ['password' => 'canary-secret']);

        $this->assertCount(1, $logs->getStorage());
        $attributes = $logs->getStorage()[0]->getAttributes()->toArray();
        $this->assertSame('signing', $attributes['app.module']);
        $this->assertSame('submit', $attributes['app.operation']);
        $this->assertStringNotContainsString('canary-secret', json_encode($attributes));
    }

    public function test_log_threshold_filters_lower_severity_events(): void
    {
        [$telemetry, , , $logs] = $this->telemetry('WARN');

        $telemetry->event('auth.login.completed', ['app.outcome' => 'success']);
        $telemetry->event('auth.login.rejected', [
            'app.outcome' => 'rejected',
            'app.reason' => 'invalid_credentials',
        ]);

        $this->assertCount(1, $logs->getStorage());
        $this->assertSame('auth.login.rejected', $logs->getStorage()[0]->getBody());
        $this->assertSame('WARN', $logs->getStorage()[0]->getSeverityText());

        [$fallbackTelemetry, , , $fallbackLogs] = $this->telemetry('not-a-level');
        $fallbackTelemetry->event('auth.login.completed', ['app.outcome' => 'success']);

        $this->assertCount(1, $fallbackLogs->getStorage());
        $this->assertSame('INFO', $fallbackLogs->getStorage()[0]->getSeverityText());
    }

    public function test_exception_outside_span_is_exported_once_per_instance(): void
    {
        [$telemetry, , , $logs] = $this->telemetry();
        $first = new RuntimeException('canary-secret-one');
        $second = new RuntimeException('canary-secret-two');

        $telemetry->recordExceptionType($first);
        $telemetry->recordExceptionType($first);
        $telemetry->recordExceptionType($second);

        $this->assertCount(2, $logs->getStorage());
        $this->assertSame(RuntimeException::class, $logs->getStorage()[0]->getAttributes()->toArray()['error.type']);
        $this->assertStringNotContainsString('canary-secret', json_encode($logs->getStorage()));
    }

    public function test_unsampled_trace_still_exports_business_log(): void
    {
        $logs = new LogExporter;
        $telemetry = new Telemetry(
            (new TracerProviderBuilder)->setSampler(new AlwaysOffSampler)->build(),
            null,
            (new LoggerProviderBuilder)->addLogRecordProcessor(new SimpleLogRecordProcessor($logs))->build(),
        );

        $telemetry->withinSpan('unsampled', [], function () use ($telemetry): void {
            $telemetry->event('auth.login.completed', ['app.outcome' => 'success']);
        });

        $this->assertCount(1, $logs->getStorage());
        $this->assertSame('auth.login.completed', $logs->getStorage()[0]->getBody());
    }

    public function test_integration_wrapper_records_timing_without_changing_result(): void
    {
        [$telemetry, , , $logs] = $this->telemetry();

        $result = $telemetry->trackIntegration('pakasir', 'transaction_detail', fn (): string => 'provider-result');

        $this->assertSame('provider-result', $result);
        $this->assertSame('integration.request.completed', $logs->getStorage()[0]->getBody());
        $attributes = $logs->getStorage()[0]->getAttributes()->toArray();
        $this->assertSame('pakasir', $attributes['integration.provider']);
        $this->assertGreaterThanOrEqual(0, $attributes['app.duration_ms']);
    }

    public function test_integration_wrapper_rethrows_failure_and_invokes_callback_once(): void
    {
        [$telemetry, , , $logs] = $this->telemetry();
        $calls = 0;

        try {
            $telemetry->trackIntegration('pakasir', 'transaction_detail', function () use (&$calls): never {
                $calls++;
                throw new RuntimeException('canary-provider-secret');
            });
            $this->fail('Expected original provider exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('canary-provider-secret', $error->getMessage());
        }

        $this->assertSame(1, $calls);
        $this->assertCount(1, $logs->getStorage());
        $record = $logs->getStorage()[0];
        $this->assertSame('integration.request.failed', $record->getBody());
        $this->assertSame('provider_unavailable', $record->getAttributes()->toArray()['app.reason']);
        $this->assertStringNotContainsString('canary-provider-secret', json_encode($record->getAttributes()->toArray()));
    }

    public function test_mail_wrapper_rethrows_failure_and_exports_only_exception_class(): void
    {
        [$telemetry, , , $logs] = $this->telemetry();

        try {
            $telemetry->submitMail('login_otp', fn () => throw new RuntimeException('canary-mail-secret'));
            $this->fail('Expected original mail exception.');
        } catch (RuntimeException $error) {
            $this->assertSame('canary-mail-secret', $error->getMessage());
        }

        $this->assertCount(1, $logs->getStorage());
        $record = $logs->getStorage()[0];
        $this->assertSame('mail.submission.failed', $record->getBody());
        $this->assertSame(RuntimeException::class, $record->getAttributes()->toArray()['error.type']);
        $this->assertStringNotContainsString('canary-mail-secret', json_encode($record->getAttributes()->toArray()));
    }
}
