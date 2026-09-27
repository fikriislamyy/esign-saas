<?php

namespace App\Observability;

use OpenTelemetry\API\Behavior\Internal\LogWriter\LogWriterInterface;
use Throwable;

final class SafeOtelLogWriter implements LogWriterInterface
{
    private bool $reported = false;

    public function write($level, string $message, array $context): void
    {
        if ($this->reported || ! in_array($level, ['warning', 'error', 'critical', 'alert', 'emergency'], true)) {
            return;
        }

        $this->reported = true;
        $type = ($context['exception'] ?? null) instanceof Throwable
            ? $context['exception']::class
            : 'ExporterFailure';
        error_log('OpenTelemetry internal failure: '.$type);
    }
}
