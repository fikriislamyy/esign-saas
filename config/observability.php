<?php

return [
    'enabled' => (bool) env('OBSERVABILITY_ENABLED', false),
    'http_service_name' => env('OTEL_SERVICE_NAME', 'esign-api'),
    'console_service_name' => env('OBSERVABILITY_CONSOLE_SERVICE_NAME', 'esign-scheduler'),
    'service_version' => env('OTEL_SERVICE_VERSION', 'local'),
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'),
    'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
    'sample_ratio' => (float) env('OTEL_TRACES_SAMPLER_ARG', 1.0),
    'log_level' => env('OBSERVABILITY_LOG_LEVEL', 'INFO'),
    'export_timeout_ms' => (int) env('OBSERVABILITY_EXPORT_TIMEOUT_MS', 200),
];
