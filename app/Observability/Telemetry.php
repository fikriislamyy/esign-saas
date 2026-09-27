<?php

namespace App\Observability;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Logs\LoggerProviderInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Telemetry
{
    private const DURATION_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30];

    private bool $closed = false;

    private static bool $diagnosticReported = false;

    public function __construct(
        private readonly ?TracerProviderInterface $traces = null,
        private readonly ?MeterProviderInterface $metrics = null,
        private readonly ?LoggerProviderInterface $logs = null,
    ) {}

    public function enabled(): bool
    {
        return $this->traces !== null && ! $this->closed;
    }

    public function traceRequest(Request $request, Closure $next): mixed
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        try {
            $headers = [
                'traceparent' => $request->header('traceparent'),
            ];
            $parent = TraceContextPropagator::getInstance()->extract($headers);
            $span = $this->traces->getTracer('esign-saas')->spanBuilder('HTTP request')
                ->setParent($parent)->setSpanKind(SpanKind::KIND_SERVER)->startSpan();
            $scope = $span->activate();
        } catch (Throwable $error) {
            self::diagnostic($error);

            return $next($request);
        }

        $start = hrtime(true);
        $response = null;
        $failure = null;
        try {
            return $response = $next($request);
        } catch (Throwable $error) {
            $failure = $error;
            throw $error;
        } finally {
            try {
                $route = $request->route();
                $template = $route instanceof Route ? '/'.ltrim($route->uri(), '/') : 'unmatched';
                $method = strtoupper($request->method());
                if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
                    $method = '_OTHER';
                }
                $status = $response?->getStatusCode()
                    ?? ($failure instanceof HttpExceptionInterface ? $failure->getStatusCode() : 500);
                $attributes = TelemetryAttributes::filter([
                    'http.request.method' => $method,
                    'http.route' => $template,
                    'http.response.status_code' => $status,
                ]);
                $span->updateName($method.' '.$template);
                $span->setAttributes($attributes);
                if ($status >= 500) {
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }
                $this->event('http.request.completed', $attributes, $status >= 500 ? 'ERROR' : ($status >= 400 ? 'WARN' : 'INFO'));
                $meter = $this->metrics?->getMeter('esign-saas');
                $meter?->createCounter('esign.http.requests', '{request}')->add(1, $attributes);
                $meter?->createHistogram('esign.http.duration', 's', advisory: [
                    'ExplicitBucketBoundaries' => self::DURATION_BUCKETS,
                ])->record((hrtime(true) - $start) / 1e9, $attributes);
            } catch (Throwable $error) {
                self::diagnostic($error);
            } finally {
                try {
                    $span->end();
                } catch (Throwable $error) {
                    self::diagnostic($error);
                } finally {
                    $scope->detach();
                }
            }
        }
    }

    public function withinSpan(string $name, array $attributes, Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        try {
            $span = $this->traces->getTracer('esign-saas')->spanBuilder($name)
                ->setAttributes(TelemetryAttributes::filter($attributes))->startSpan();
            $scope = $span->activate();
        } catch (Throwable $error) {
            self::diagnostic($error);

            return $callback();
        }

        try {
            return $callback();
        } catch (Throwable $error) {
            $this->recordExceptionType($error);
            throw $error;
        } finally {
            try {
                $span->end();
            } catch (Throwable $error) {
                self::diagnostic($error);
            } finally {
                $scope->detach();
            }
        }
    }

    public function runCommand(string $name, Closure $callback): int
    {
        if (! $this->enabled()) {
            return $callback();
        }

        $started = hrtime(true);
        $exitCode = 1;
        try {
            return $exitCode = $this->withinSpan('artisan '.$name, ['app.command.name' => $name], function () use ($name, $callback, &$exitCode, $started): int {
                try {
                    return $exitCode = $callback();
                } finally {
                    $attributes = [
                        'app.command.name' => $name,
                        'app.command.exit_code' => $exitCode,
                        'app.outcome' => $exitCode === 0 ? 'success' : 'failure',
                    ];
                    try {
                        Span::getCurrent()->setAttributes(TelemetryAttributes::filter($attributes));
                        if ($exitCode !== 0) {
                            Span::getCurrent()->setStatus(StatusCode::STATUS_ERROR);
                        }
                        $this->event('command.completed', $attributes, $exitCode === 0 ? 'INFO' : 'ERROR');
                        $meter = $this->metrics?->getMeter('esign-saas');
                        $meter?->createCounter('esign.command.runs', '{run}')->add(1, $attributes);
                        $meter?->createHistogram('esign.command.duration', 's', advisory: [
                            'ExplicitBucketBoundaries' => self::DURATION_BUCKETS,
                        ])->record((hrtime(true) - $started) / 1e9, $attributes);
                    } catch (Throwable $error) {
                        self::diagnostic($error);
                    }
                }
            });
        } finally {
            $this->forceFlush();
        }
    }

    public function event(string $name, array $attributes = [], string $severity = 'INFO'): void
    {
        if (! $this->enabled() || ! in_array($name, ['http.request.completed', 'application.exception', 'command.completed', 'observability.smoke'], true)) {
            return;
        }
        try {
            $level = match ($severity) {
                'ERROR' => Severity::ERROR,
                'WARN' => Severity::WARN,
                default => Severity::INFO,
            };
            $this->logs?->getLogger('esign-saas')->logRecordBuilder()
                ->setEventName($name)
                ->setBody($name)
                ->setSeverityNumber($level)
                ->setSeverityText($severity)
                ->setAttributes(TelemetryAttributes::filter($attributes))
                ->emit();
        } catch (Throwable $error) {
            self::diagnostic($error);
        }
    }

    public function recordExceptionType(Throwable $error): void
    {
        if (! $this->enabled()) {
            return;
        }
        try {
            $span = Span::getCurrent();
            if (! $span->getContext()->isValid()) {
                return;
            }
            $type = $error::class;
            $span->setAttribute('error.type', $type);
            $span->setStatus(StatusCode::STATUS_ERROR);
            $this->event('application.exception', ['error.type' => $type], 'ERROR');
        } catch (Throwable $failure) {
            self::diagnostic($failure);
        }
    }

    public function smoke(bool $fail = false): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->withinSpan('observability.smoke', [], function () use ($fail): string {
            $this->metrics?->getMeter('esign-saas')->createCounter('esign.observability.smoke', '{run}')->add(1);
            $this->event('observability.smoke');
            if ($fail) {
                Span::getCurrent()->setStatus(StatusCode::STATUS_ERROR);
                $this->event('application.exception', ['error.type' => 'SyntheticSmokeFailure'], 'ERROR');
            }

            return Span::getCurrent()->getContext()->getTraceId();
        });
    }

    public function forceFlush(): void
    {
        if (! $this->enabled()) {
            return;
        }
        foreach ([$this->traces, $this->logs, $this->metrics] as $provider) {
            try {
                $provider?->forceFlush();
            } catch (Throwable $error) {
                self::diagnostic($error);
            }
        }
    }

    public function shutdown(): void
    {
        if (! $this->enabled()) {
            return;
        }
        $this->forceFlush();
        $this->closed = true;
        foreach ([$this->traces, $this->logs, $this->metrics] as $provider) {
            try {
                $provider?->shutdown();
            } catch (Throwable $error) {
                self::diagnostic($error);
            }
        }
    }

    private static function diagnostic(Throwable $error): void
    {
        if (self::$diagnosticReported) {
            return;
        }
        self::$diagnosticReported = true;
        error_log('Observability operation failed: '.$error::class);
    }
}
