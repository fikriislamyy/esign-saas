# Local observability

The Laravel backend emits request and expiry-command traces, request/command metrics, and curated correlated logs through OpenTelemetry. SigNoz receives all three signals through its OTel ingester. Static assets served by nginx, browser activity, database queries, and Redis calls are outside this first instrumentation scope.

## Requirements

- A working local application environment and Docker Compose v2.
- At least 4 GB available for SigNoz, plus memory for the application.
- A Linux Docker Engine or a compatible host. See the current [SigNoz Docker guidance](https://signoz.io/docs/install/docker/) if ClickHouse Keeper repeatedly exits on Windows Docker Desktop.
- Foundry `v0.2.17`. Download the platform archive from its [release](https://github.com/SigNoz/foundry/releases/tag/v0.2.17), verify it against the release checksum file, and put `foundryctl` on `PATH`. Exact versions are in [the version lock](../docker/observability/versions.md).

The repository's `composer.lock` contains the PHP OTel dependencies. Install them with `composer install` in the PHP 8.3 application container after checking out this change. The production `Dockerfile` vendor stage uses the same lock file.
Both PHP-FPM Dockerfiles copy `docker/php/observability-fpm.conf`, which forwards the selected environment variables to FPM workers. Rebuild the app image when changing that file.

## Start

Run these commands from the repository root unless `cd` is shown. `docker network inspect` is safe if the network already exists.

```bash
docker network inspect esign-observability >/dev/null 2>&1 || docker network create esign-observability
cd docker/observability/signoz
foundryctl gauge -f casting.yaml
foundryctl forge -f casting.yaml
docker compose -p esign-observability -f pours/deployment/compose.yaml config --quiet
docker compose -p esign-observability -f pours/deployment/compose.yaml up -d
docker compose -p esign-observability -f pours/deployment/compose.yaml ps
cd ../../..
docker compose -f docker-compose.yml -f docker-compose.observability.yml up -d --build
```

The SigNoz UI is at `http://localhost:8080` and is bound to loopback. Create the initial local account in the UI. The application remains at `http://localhost:8000`. SigNoz storage and metadata are in its own named Docker volumes. Its ingester service is `ingester`, with the network alias `otel-collector` on the external `esign-observability` network. The ingester's OTLP HTTP receiver listens at port 4318 inside Docker and is not published to the host.

The Foundry `pours/` output is generated and ignored. Edit `casting.yaml`, then regenerate; do not edit generated Compose files. `casting.yaml.lock` records the generated component settings and must be reviewed after upgrades. The optional SigNoz MCP service is disabled to keep host port 8000 free.

## Configuration

The base application configuration defaults to `OBSERVABILITY_ENABLED=false`. Selecting `docker-compose.observability.yml` sets it to true unless the Compose environment explicitly sets it to false. See `.env.observability.example` for nonsecret example values. Compose interpolation and the container's Laravel `.env` are separate; the overlay explicitly passes these values into `app` and `scheduler`.

| Variable | Meaning / default |
| --- | --- |
| `OBSERVABILITY_ENABLED` | Enable SDK and export; false in base configuration, true in the selected overlay. |
| `OTEL_SERVICE_NAME` | HTTP service identity, default `esign-api`. |
| `OBSERVABILITY_CONSOLE_SERVICE_NAME` | CLI identity, default `esign-scheduler`. Selected at runtime so a shared config cache is safe. |
| `OTEL_SERVICE_VERSION` | Release label; default `local`. Set during deployment. |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | Collector base URL, default `http://otel-collector:4318`. Omit `/v1/...`. |
| `OTEL_EXPORTER_OTLP_PROTOCOL` | `http/protobuf`. |
| `OTEL_TRACES_SAMPLER_ARG` | Root trace probability from 0 to 1; default 1 locally. Remote parent decisions are respected. |
| `OBSERVABILITY_LOG_LEVEL` | Minimum exported application log severity: `INFO`, `WARN`, or `ERROR`; default `INFO`. Invalid values fall back to `INFO`. This does not change Laravel's local `LOG_LEVEL`. |
| `OBSERVABILITY_EXPORT_TIMEOUT_MS` | Per signal HTTP timeout; default 200 ms, clamped to 10–300 ms. Retries disabled. |

The SDK sets `service.namespace=esign-saas` and both `deployment.environment.name` and the legacy `deployment.environment` attribute from Laravel's `app.env`; the pinned SigNoz ingester uses the legacy name for its service metrics. Laravel cached configuration is supported. In a production image, set a private/TLS OTLP endpoint and supply credentials through deployment secrets, not Git. Size SigNoz separately; choose sampling and retention for the available storage. Never publish its unauthenticated OTLP receiver on a public interface.

## Check the data

From the repository root, use the telemetry-enabled app container:

```bash
docker compose -f docker-compose.yml -f docker-compose.observability.yml exec app php artisan observability:smoke
curl -fsS http://localhost:8000/ >/dev/null
```

The smoke command is restricted to Laravel's `local` and `testing` environments. It prints a trace ID and says export was attempted. Search for that ID in SigNoz's `esign-scheduler` service, and open the `observability.smoke` log. It cannot prove ingestion by itself: allow time for the collector and check the UI time range. `--fail` creates a synthetic error trace/event and returns nonzero without touching data.

For HTTP data, search for `esign-api`. The request span name uses the method and route template. A log named `http.request.completed` should share the span's trace and span IDs. The root route can return a redirect depending on auth state; its status should match the response.

Business events use fixed names such as `auth.login.rejected`, `payment.fulfillment.completed`, and `documents.expiry.summary`. Filter the log body by the exact name, then filter structured fields such as `attributes.app.module`, `attributes.app.outcome`, and severity. The complete reviewed catalog and ownership map is in [the application log event catalog](observability/log-events.md). Logs created outside a span remain searchable but do not contain fabricated trace or span identifiers.

Run expiry commands only against a disposable local database. `subscriptions:expire --dry-run` may still call Stripe for Stripe-backed subscriptions. Use synthetic non-Stripe fixtures or a mocked Stripe client for that check. Look under `esign-scheduler` for `artisan documents:expire` and `artisan subscriptions:expire` spans and `command.completed` logs.

Metrics have these names and units:

| Name | Type | Unit | Dimensions |
| --- | --- | --- | --- |
| `esign.http.requests` | Counter | `{request}` | method, route template, status |
| `esign.http.duration` | Histogram | seconds | method, route template, status |
| `esign.command.runs` | Counter | `{run}` | fixed command name, exit code, outcome |
| `esign.command.duration` | Histogram | seconds | fixed command name, exit code, outcome |
| `esign.observability.smoke` | Counter | `{run}` | none |

Counters and histograms export with delta temporality so independent PHP requests do not reset a cumulative series. Metrics record even when a trace is unsampled. HTTP timing covers Laravel response creation; it does not include streamed body delivery or telemetry export.
Duration histograms use boundaries at 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, and 30 seconds.

Import [the V2 dashboard template](observability/dashboard.json) through SigNoz's **Dashboards → New dashboard → Import JSON** screen. It uses the official V2 schema structure and includes HTTP request rate by route, response rate by status, p50/p95 HTTP duration, command runs by outcome, and p95 command duration. The duration panels query traces; with sampling below 1.0, they represent the sampled subset. The standalone `esign.http.duration` and `esign.command.duration` histograms remain available for unsampled aggregate analysis. Check the displayed metric names after ingestion and save any query adjustments from the selected SigNoz release. See [SigNoz dashboard import instructions](https://signoz.io/docs/dashboards/import-dashboard/). SigNoz's default retention shown in its [Docker guide](https://signoz.io/docs/install/docker/) is 7 days for traces/logs and 30 days for metrics; adjust it in UI settings if local disk is limited.

Optional server-error alert recipe: over a rolling five-minute window, query the sum of `esign.http.requests` where `http.response.status_code` is 500–599, divided by the sum of all `esign.http.requests`. Alert only when the ratio exceeds 5%, there are at least five server errors, and total requests are at least 20. Treat no data as no alert and monitor ingestion availability separately. Review the resulting series against a known request count before enabling delivery to anyone.

## Privacy and failure behavior

The telemetry allowlist contains method, route template, status, fixed command names/outcomes, provider name, and exception **class**. It extracts `traceparent` for parent correlation but ignores untrusted `tracestate` and baggage values. It excludes signing tokens, concrete URLs, query strings, headers, bodies, OTPs, document names/paths, payment amounts/IDs, SQL, Redis keys, exception messages, and stack traces. Existing Laravel file/stderr logs are retained locally; they are not forwarded wholesale to SigNoz.

Business mutation success events are queued until the outer database transaction commits. A rollback discards them. Logging remains best effort and is not a durable financial or legal audit ledger. Mail events confirm submission to the configured transport, not recipient delivery.

If the ingester is unavailable, an operation still returns its original result. Each signal transport has a bounded timeout and no retries. Data produced while SigNoz is down may be lost. To reproduce this safely, stop only `ingester`, make a harmless request, then start `ingester` and verify later traffic arrives. SDK diagnostics use a dedicated writer that prints only one sanitized failure class per process, without export URLs, payloads, or stack traces.

## Inspect and troubleshoot

```bash
cd docker/observability/signoz
docker compose -p esign-observability -f pours/deployment/compose.yaml ps
docker compose -p esign-observability -f pours/deployment/compose.yaml logs --tail=100 ingester
docker compose -p esign-observability -f pours/deployment/compose.yaml logs --tail=100 esign-observability-signoz-0
cd ../../..
docker compose -f docker-compose.yml -f docker-compose.observability.yml logs --tail=100 app scheduler
docker system df
```

If the UI loads but data is missing, check the enable flag, FPM container environment, service/time filters, `otel-collector` DNS alias, receiver port, and the three `/v1/...` endpoints. If only metrics are wrong, inspect delta temporality and test several separate requests. If CLI data works but HTTP does not, check PHP FPM configuration and Laravel's termination hook. If requests slow down during an outage, inspect the transport timeout and whether exporter retries were re-enabled.

## Stop or disable

Set `OBSERVABILITY_ENABLED=false` in the actual Compose/deployment environment and recreate app and scheduler. If Laravel configuration was cached, rebuild it with the new environment. To remove the overlay, recreate app and scheduler using the base Compose file; merely omitting the overlay from a later `exec` command does not alter running containers.

Stop SigNoz separately:

```bash
cd docker/observability/signoz
docker compose -p esign-observability -f pours/deployment/compose.yaml down
```

This preserves the SigNoz volumes. Do not use `down -v` for routine rollback. Restart SigNoz and repeat the smoke check when re-enabling telemetry.
