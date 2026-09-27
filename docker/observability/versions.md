# Observability version lock

Selected on 2026-09-26. Update these together and repeat the Docker smoke checks before changing them.

| Component | Version / image |
| --- | --- |
| Foundry CLI | `v0.2.17`; Linux amd64 archive SHA256 `51f41204b8048cd1f7e278fb5d2ba5d82d2ee8fb619bfe9330e2f8ceffc0d886` |
| SigNoz UI/API | `signoz/signoz:v0.143.0` |
| SigNoz OTel ingester and migrator | `signoz/signoz-otel-collector:v0.144.12` |
| ClickHouse and Keeper | `clickhouse/clickhouse-server:25.12.5`, `clickhouse/clickhouse-keeper:25.12.5` |
| SigNoz metadata PostgreSQL | `postgres:16.11` (pulled manifest digest `sha256:468e1f126ca5af849799cda06ac9b03d8090aae9fa5163408b3e8da44fad0702`) |
| PHP SDK | `open-telemetry/sdk:1.15.0`, `open-telemetry/api:1.10.0` |
| OTLP exporter | `open-telemetry/exporter-otlp:1.4.0` |
| PHP HTTP transport | `php-http/guzzle7-adapter:1.1.0` |
| Protobuf runtime | `google/protobuf:v5.36.2` |

PHP package transitive versions are fixed by `composer.lock`. The project locks Composer's dependency resolution platform to PHP 8.3 because `inertiajs/inertia-laravel:v0.6.11` rejects host PHP 8.4, while the application Docker images use PHP 8.3. Use the PHP 8.3 container to run Composer and production platform checks.

Foundry's default casting generated moving `latest` tags for the SigNoz components. `casting.yaml` replaces them with the two versions above; the generated `casting.yaml.lock` records the selected component configuration. Foundry generated the ClickHouse versions shown above. PostgreSQL is pinned to patch version 16.11 after the host's `postgres:16` image proved unusable (its entrypoint file was zero bytes); the 16.11 entrypoint was verified with `postgres --version`.

The application uses manual PHP SDK instrumentation. It exports OTLP HTTP/protobuf to `/v1/traces`, `/v1/metrics`, and `/v1/logs`. The locked SDK provides `BatchSpanProcessor`, `BatchLogRecordProcessor`, `ExportingReader`, and `MetricExporter(..., Temporality::DELTA)`. Each transport has a 200 ms default timeout and zero transport retries. An HTTP request or CLI command flushes its providers after ending the operation.

Upstream references:

- [SigNoz Docker installation](https://signoz.io/docs/install/docker/)
- [Foundry v0.2.17 release](https://github.com/SigNoz/foundry/releases/tag/v0.2.17)
- [SigNoz v0.143.0 release](https://github.com/SigNoz/signoz/releases/tag/v0.143.0)
- [SigNoz OTel Collector v0.144.12 release](https://github.com/SigNoz/signoz-otel-collector/releases/tag/v0.144.12)
- [PHP OpenTelemetry exporters](https://opentelemetry.io/docs/languages/php/exporters/)
