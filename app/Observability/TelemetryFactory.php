<?php

namespace App\Observability;

use OpenTelemetry\API\Behavior\Internal\Logging;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Logs\LoggerProviderBuilder;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use Throwable;

final class TelemetryFactory
{
    public static function make(array $config, bool $console, string $environment): Telemetry
    {
        if (! ($config['enabled'] ?? false)) {
            return new Telemetry;
        }

        try {
            Logging::setLogWriter(new SafeOtelLogWriter);
            if (($config['protocol'] ?? null) !== 'http/protobuf') {
                throw new \InvalidArgumentException('Only OTLP HTTP/protobuf is supported');
            }
            $ratio = (float) ($config['sample_ratio'] ?? 1);
            if ($ratio < 0 || $ratio > 1) {
                throw new \InvalidArgumentException('Trace sample ratio must be between 0 and 1');
            }
            $timeout = max(0.01, min(0.3, (int) ($config['export_timeout_ms'] ?? 200) / 1000));
            $endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
            if (! filter_var($endpoint, FILTER_VALIDATE_URL) || preg_match('~/v1/(traces|metrics|logs)$~', $endpoint)) {
                throw new \InvalidArgumentException('Invalid OTLP endpoint');
            }
            $resource = ResourceInfo::create(Attributes::create([
                'service.name' => (string) ($console ? $config['console_service_name'] : $config['http_service_name']),
                'service.namespace' => 'esign-saas',
                'service.version' => (string) ($config['service_version'] ?? 'local'),
                'deployment.environment.name' => $environment,
                'deployment.environment' => $environment,
            ]));
            $transport = new OtlpHttpTransportFactory;
            $send = static fn (string $signal) => $transport->create(
                $endpoint.'/v1/'.$signal,
                'application/x-protobuf',
                timeout: $timeout,
                maxRetries: 0,
            );
            $traces = (new TracerProviderBuilder)
                ->setResource($resource)
                ->setSampler(new ParentBased(new TraceIdRatioBasedSampler($ratio)))
                ->addSpanProcessor(new BatchSpanProcessor(
                    new SpanExporter($send('traces')), Clock::getDefault(), 256, 5000, 300, 256, false,
                ))
                ->build();
            $metrics = (new MeterProviderBuilder)
                ->setResource($resource)
                ->addReader(new ExportingReader(new MetricExporter($send('metrics'), Temporality::DELTA)))
                ->build();
            $logs = (new LoggerProviderBuilder)
                ->setResource($resource)
                ->addLogRecordProcessor(new BatchLogRecordProcessor(
                    new LogsExporter($send('logs')), Clock::getDefault(), 256, 5000, 300, 256, false,
                ))
                ->build();

            return new Telemetry($traces, $metrics, $logs, (string) ($config['log_level'] ?? 'INFO'));
        } catch (Throwable $error) {
            // Telemetry configuration must never stop checkout, signing, or scheduled work.
            error_log('Observability unavailable: '.get_class($error));

            return new Telemetry;
        }
    }
}
