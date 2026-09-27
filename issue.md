# Add application logging across necessary modules and make it searchable in SigNoz

## Task

> Please add logging management to all necessary functions or modules so that the logs can be accessed in SigNoz.

This is an implementation plan for a junior programmer or a smaller AI model. Complete the steps in order. The checkboxes represent work to implement and verify, not completed functionality. Repository baseline inspected on 2026-09-27.

## 1. Expected result and scope

Operators can use SigNoz to see successful business operations, rejected operations, recovered failures, and unexpected errors in authentication, documents, signing, billing, payments, wallet operations, organization management, templates, external services, mail submission, and scheduled expiry commands.

Each event has a fixed searchable name, consistent severity, safe structured attributes, and trace/span correlation when an active span exists. Application behavior remains the same when logging is disabled or the collector is unavailable.

“Necessary functions” means business state changes, important rejection branches, external I/O failures, and command summaries. Getters, calculations, model serialization, page rendering, polling success, and every loop iteration do not need individual business logs. Existing request logs cover ordinary reads. Operational logs are best effort; they are not a durable financial or legal audit ledger.

This issue extends the existing backend pipeline. Browser logging, SQL/query logging, container log collection, a new logging UI, production deployment, and alert delivery are outside scope.

## 2. What already exists

Read these files before editing them. Do not implement the earlier observability setup again.

| File | Current behavior / consequence |
| --- | --- |
| `composer.json`, `composer.lock` | Laravel 10, PHP 8.3, and OpenTelemetry packages are already installed. No new logging dependency is expected. |
| `app/Observability/Telemetry.php` | Exports only `http.request.completed`, `application.exception`, `command.completed`, and `observability.smoke`. Unregistered event names are silently dropped. Severity currently handles INFO, WARN, and ERROR. |
| `app/Observability/TelemetryAttributes.php` | Allows a small set of HTTP, command, outcome, provider, and exception-class attributes. New keys are dropped unless added. It limits strings but does not currently validate enum values. |
| `app/Observability/TelemetryFactory.php` | Already builds an OTLP log exporter, resource metadata, bounded batching, and transports with timeouts and no retries. |
| `app/Providers/ObservabilityServiceProvider.php` | Lazily creates telemetry and shuts it down at application termination. |
| `app/Http/Middleware/TraceRequest.php` | Owns HTTP request instrumentation. |
| `app/Exceptions/Handler.php` | Reports exception types through telemetry and preserves JSON/Inertia rendering. |
| `app/Services/PakasirFulfillmentService.php` | Has a `payment.fulfill` span, but no business outcome event. |
| `app/Console/Commands/ExpireDocuments.php`, `ExpireSubscriptions.php` | Already use `runCommand()`; individual warnings still use Laravel logs. |
| `config/logging.php` | Default Laravel stack writes to `single`; `daily` and `stderr` also exist. These logs are not automatically sent to SigNoz. |
| `config/observability.php`, `.env.observability.example` | Existing opt-in telemetry settings. |
| `docker-compose.observability.yml`, `docker/php/observability-fpm.conf` | Pass observability configuration into HTTP and CLI runtimes. |
| `docs/observability.md` | Existing Docker setup, smoke check, privacy rules, troubleshooting, and rollback. |
| `tests/Unit/Observability/TelemetryTest.php` | In-memory exporters and existing correlation/privacy/failure tests to reuse. |
| `tests/Feature/Observability/RequestTelemetryTest.php` | Existing request integration tests. |

Important gaps: business events are missing; legacy logs sometimes contain identifiers, paths, provider data, and exception messages; recovered errors may never become an HTTP 500; `recordExceptionType()` currently skips errors without a valid span; nested reporting can emit the same exception more than once.

## 3. Implementation decisions

1. Use `Telemetry::event()` as the common business logging entry point. Extend its event catalog and validation. Keep the existing OTLP exporter and lifecycle.
2. Do not forward all Laravel `Log::*` messages or tail `laravel.log` into SigNoz. Migrate relevant application calls explicitly, using fixed event names and reviewed attributes.
3. Preserve Laravel's local exception reporting. For migrated business logs, replace verbose debug calls with structured events; retain a local equivalent only where existing operational needs require it, using the same sanitized attributes.
4. Centralize event names, allowed attributes, and default severities in a proposed `app/Observability/LogEventCatalog.php`. Keep the existing four events supported. Unknown names must fail closed: drop them without affecting application behavior; tests must detect unregistered call sites.
5. Default business outcomes to INFO, expected abnormal rejection/recovery to WARN, and unexpected operation failures to ERROR. Normal pending payments, duplicate webhook deliveries, and intentionally ignored webhook types are INFO. Do not classify every HTTP 4xx or routine validation error as an application ERROR.
6. Add `OBSERVABILITY_LOG_LEVEL` with default `INFO`, accepting `INFO`, `WARN`, and `ERROR`. Normalize case and fall back to INFO for invalid values. This controls exported logs independently of Laravel `LOG_LEVEL` and trace sampling. Do not add DEBUG events in this issue.
7. Export logs even when the current trace is unsampled. Events outside a span still export, with no fabricated trace/span identifiers. Keep business identifiers out of metric dimensions.
8. Log committed state changes only after the outermost successful database commit. Use Laravel's transaction callback mechanism after verifying its installed implementation; execute immediately when no transaction is active. Capture safe scalar attributes and the originating OTel context when registering a deferred callback, reactivate that context during emission, and always detach it. Rollbacks must discard success events. Do not flush or perform exporter network I/O inside a transaction.

## 4. Event schema and privacy contract

Use the fixed event name for both OTel event name and log body, matching the current implementation. Resource metadata already contains service name, version, namespace, and environment. The SDK supplies timestamp and active trace/span context; do not copy client-supplied IDs into trusted fields.

| Attribute | Allowed values / type |
| --- | --- |
| `app.module` | Fixed enum: `auth`, `document`, `signing`, `billing`, `payment`, `wallet`, `organization`, `template`, `integration`, `mail`, `scheduler`. |
| `app.operation` | Fixed operation token registered for that event, such as `upload`, `verify_otp`, `fulfill`, or `delete`. Never a request value. |
| `app.outcome` | `success`, `failure`, `rejected`, `skipped`, `pending`, or `partial`. |
| `app.reason` | Per-event fixed enum, for example `invalid_otp`, `rate_limited`, `insufficient_balance`, `duplicate`, `amount_mismatch`, `provider_unavailable`, `storage_failure`. |
| `payment.provider` | `stripe` or `pakasir`. |
| `integration.provider` | Fixed configured labels such as `stripe`, `pakasir`, `currencyfreaks`, `recaptcha`, `mail`, or `storage`; never a URL. |
| `error.type` | Exception class only, passed from a caught Throwable. No exception message or stack. |
| `app.duration_ms` | Optional finite, nonnegative numeric elapsed time for external operations; calculate with `hrtime(true)`. |
| `app.processed_count`, `app.skipped_count`, `app.failed_count` | Nonnegative integer summary counts; define what each counts per command. |
| `app.dry_run` | Boolean for command summaries. |
| Existing HTTP / command keys | Preserve their current behavior and filtering. |

Each event accepts only its own documented subset of attributes. Reject arrays, objects, unknown keys, invalid enum values, and nonfinite numbers. Limit string lengths. Retain existing generic filtering for spans/metrics; use a separate event-specific filter so logging additions do not unintentionally broaden trace and metric attributes.

Never include passwords, OTPs, signing/invitation/reset tokens, authorization or cookie headers, webhook signatures, request/response bodies, raw URLs or query strings, email addresses, names, IP addresses, document contents or paths, signatures, payment/card details, amounts, wallet balances, external customer/order IDs, SQL, Redis keys, or arbitrary exception messages. This first implementation also omits internal user, organization, and document IDs; investigate individual operations through trace IDs. Do not serialize models or spread `$request->all()` into context. Truncation is not redaction.

## 5. Required coverage checklist

Create `docs/observability/log-events.md` during implementation. Expand each event family below into exact names and record its owner method, severity, permitted attributes, reasons, and test. Family notation is planning shorthand, not an event name to emit. Give every row a final covered/not-applicable status with a reason.

| Module and starting files | Required events and branches |
| --- | --- |
| Auth: `app/Http/Controllers/Auth/*`, `app/Http/Requests/Auth/LoginRequest.php`, `LoginOtpService`, `EmailVerificationOtpService` | `auth.login.*`, `auth.logout.completed`, `auth.registration.completed`, `auth.password.*`, `auth.email_verification.*`, `auth.otp.*`: successful final authentication, credential/OTP rejection, throttle rejection, resend, password reset/update, verification. Generating an OTP is not successful login. Log accepted reset requests without revealing whether an account exists. |
| Documents: `DocumentController` | `document.upload.*`, `document.delete.*`, `document.prepare.*`, `document.send.*`: state changes, plan/storage/balance rejection, upload/delete failure, sending with partial recipient failure. Emit only once at the method that owns the operation. |
| Signer setup: `DocumentSignerController`, `DocumentSignatureFieldController` | `document.signers.changed`, `document.fields.changed`: signer additions/removals/reorder/workflow changes and field create/update/delete; no coordinates, names, or signatures. |
| Signing: `SigningController`, `SigningOtpService` | `signing.otp.*`, `signing.submit.*`, `signing.document.completed`, `signing.workflow.advanced`: expired/invalid/order rejection, OTP outcomes, PDF/storage failures, successful signer submission, final document completion, next-recipient mail failure. Inspect existing caught-error branches as well as successful returns. |
| Billing: `BillingTopupController`, `SubscriptionController` | `billing.topup.*`, `billing.subscription.*`: checkout/payment creation, downgrade, missing configuration, provider failure, and business rejection. Creating checkout does not mean payment succeeded. |
| Stripe: `StripeWebhookController` | `payment.webhook.*`, `payment.fulfillment.*`, `billing.subscription.*`: invalid signature/payload, unknown records, ignored types, duplicates, checkout/payment-intent fulfillment, invoice paid/failed, subscription cancellation. Replace verbose RECEIVED/CONFIG logs without exporting payloads. |
| Pakasir: `PakasirWebhookController`, `PakasirSandboxPayController`, `PakasirFulfillmentService` | Webhook validation, unknown order, amount/project mismatch, pending status, sandbox rejection, duplicate/no-op, committed fulfillment and failure. Preserve `payment.fulfill` span. A true return from `fulfill()` can mean already paid; do not count that as a new fulfillment. |
| Wallet: `WalletService::credit`, `WalletService::debit` | `wallet.credit.*`, `wallet.debit.*`: committed mutations, duplicate reference/no-op, insufficient funds, unexpected failure. Preserve locks, transactions, idempotency, and financial calculations. |
| Organization: `InvitationController`, `InvitationAcceptController`, `MembersController`, `OrganizationSettingsController`, `ProfileController` | `organization.invitation.*`, `organization.member.*`, `organization.settings.updated`, `auth.profile.*`: invitation create/resend/revoke/accept/reject, role changes, settings changes, profile update/deletion. Remove verbose invitation debug messages. |
| Templates: `TemplateController`, `TemplateSignatureFieldController` | `template.upload.*`, `template.delete.*`, `template.fields.changed`: committed changes, quota rejection, storage failures. |
| Integrations: `PakasirService`, Stripe call sites, `ExchangeRateService`, `RecaptchaService` | `integration.request.completed` / `integration.request.failed`: actual provider I/O, operation, safe provider/status outcome, duration, failure class. Log exchange-rate fallback and reCAPTCHA rejection/configuration failures. `StripeService::client()` only exposes the SDK client; instrument the real call sites. Cache hits need no integration event. |
| Mail: `Mail::to(...)->send(...)` call sites and `LoginOtpService::send` | `mail.submission.completed` / `mail.submission.failed`: submission to the mail transport. This is not proof of delivery. Record fixed purpose as a registered operation, never recipient or mail content. No logging inside mailable rendering. |
| Scheduled work: `ExpireDocuments`, `ExpireSubscriptions` | `documents.expiry.summary`, `subscriptions.expiry.summary`: processed/skipped/failure counts and dry-run flag, including recoverable delete/provider failures. Retain existing `command.completed`; a zero exit code can coexist with a partial summary. Summarize loops instead of logging every successful row. |
| Framework errors: `Handler`, `Telemetry::recordExceptionType` | `application.exception` once per exception instance in an operation, including contexts without active spans. Preserve reporting rules and rendering. |

Pure read paths in `DashboardController`, `BillingController::index`, `Api/PaymentStatusController::show`, landing pages, and getters/calculations in `PlanService` and `SigningPricingService` use existing request logs. Log a business limit rejection at the caller that acts on the result, not every call to a limit getter.

## 6. Step-by-step implementation

### Step 1 — Inventory and establish the baseline

- [ ] Read section 2 and `docs/observability.md`; inspect the working tree and preserve unrelated changes.
- [ ] Locate existing logs, catches, transactions, and external call sites:

```bash
rg -n 'Log::|logger\(|report\(|catch\s*\(' app
rg -n 'DB::transaction|afterCommit|Mail::|Http::|Storage::|->client\(' app
rg -n 'Telemetry|withinSpan|runCommand' app tests
```

- [ ] Create the event catalog documentation from section 5. For each existing log decide: replace with a catalog event, retain a sanitized local diagnostic, or remove redundant debugging. Record the decision.
- [ ] Run the existing observability tests in the configured test environment:

```bash
docker compose exec app php artisan test tests/Unit/Observability tests/Feature/Observability
```

**Done when:** every required module has named owners/events and baseline failures, if any, are recorded separately.

### Step 2 — Build and verify the shared logging contract

- [ ] Add `LogEventCatalog.php` with exact event names, default severity, required fields, allowed keys, and enum values. Keep definitions easy to scan; avoid an elaborate logging framework.
- [ ] Update `Telemetry::event()` to validate catalog entries, normalize severity, apply the configured minimum level, sanitize attributes, and use the existing logger provider. Do not let a malformed event throw into business code.
- [ ] Keep existing method calls compatible. New constructor/config options must have defaults so current direct `new Telemetry(...)` tests still work.
- [ ] Add the level to `config/observability.php` and pass it from `TelemetryFactory`. Read `env()` only in configuration files.
- [ ] Document and wire the environment variable through `.env.observability.example`, both services in `docker-compose.observability.yml`, and `docker/php/observability-fpm.conf` so CLI and FPM agree, including cached configuration.
- [ ] Add a small helper for emitting after commit using the context rule in section 3. Do not change when business transactions begin or end.
- [ ] Test catalog validation, privacy, threshold behavior, disabled mode, and after-commit/rollback behavior before instrumenting business modules.

Example target usage after the catalog supports this event:

```php
app(Telemetry::class)->event('signing.submit.rejected', [
    'app.module' => 'signing',
    'app.operation' => 'submit',
    'app.outcome' => 'rejected',
    'app.reason' => 'invalid_otp',
], 'WARN');
```

**Done when:** a catalog event reaches the in-memory exporter with the correct body, severity, safe fields, and active trace context. Unknown fields/names cannot leak data.

### Step 3 — Make failures reliable and avoid duplicate reporting

- [ ] Inspect `withinSpan()`, `recordExceptionType()`, and the handler together. Use weak references, such as a `WeakMap` keyed by Throwable, to prevent duplicate exception events without retaining exceptions indefinitely. Do not deduplicate by class or message: two different exceptions must remain distinct.
- [ ] Allow sanitized exception logs with no active span; only mutate span status/attributes when a valid span exists.
- [ ] A caught-and-recovered failure needs a specific business/integration event because it may never reach the global handler. A rethrown exception retains its original type and instance; centralized reporting owns `application.exception`.
- [ ] Business failure summaries and a generic exception event may coexist when they describe different facts, but do not add identical ERROR logs at each stack layer. Document the event owner.
- [ ] Preserve failure isolation, transport timeouts, bounded batching, and termination flushing. Do not add per-event `forceFlush()`, exporter retries, or a Laravel log channel that recursively calls telemetry.

**Done when:** nested reporting emits one generic exception record, separate exceptions emit separate records, and exporter failures never change the business response or exception.

### Step 4 — Instrument payments, wallet, and external calls

- [ ] Work through the billing, Stripe, Pakasir, wallet, and integration rows first, one module at a time.
- [ ] Enumerate every return/rejection/catch branch before adding events. Use fixed reasons for pending, duplicate, validation failure, and missing configuration.
- [ ] Register mutation success events after commit. For provider calls, record provider acceptance separately from database fulfillment; an external call can succeed even if a later local transaction rolls back.
- [ ] Capture correlation context inside active spans before any deferred success emission. Do not create a new root trace for every log.
- [ ] Preserve webhook signatures, status codes, retries, idempotency checks, locks, and payment calculations. Do not make a duplicate delivery appear to credit a wallet again.
- [ ] Extend existing tests in `tests/Unit/Services/` and add focused feature tests for webhook outcomes under `tests/Feature/Observability/`. Fake providers; never create real charges.

**Done when:** success, rejection, failure, duplicate, and rollback cases emit the expected events without changing payment or wallet state behavior.

### Step 5 — Instrument documents, signing, auth, organization, templates, and mail

- [ ] Complete the corresponding coverage rows. Inspect early returns and caught exceptions; success-only instrumentation is incomplete.
- [ ] Keep controller/service ownership explicit. For example, a service owns OTP verification; a controller owns establishing the authenticated session. These are different events.
- [ ] Treat sequential signing advancement, final completion, PDF writing, and mail submission as separate outcomes. A saved signature with failed follow-up mail is partial success, not an entirely failed signature.
- [ ] Replace debug logs that expose paths, URLs, identifiers, request data, or exception messages at migrated sites.
- [ ] Extend existing auth, document upload, template, and service tests, with new feature tests where coverage is missing. Use mail/storage/HTTP fakes and synthetic OTPs.

**Done when:** every required state change and recovered failure in these rows has an event and a focused assertion; logging does not change response contents or status codes.

### Step 6 — Instrument command summaries

- [ ] Retain the existing `runCommand()` wrapper and emit one domain summary inside its active span before it returns.
- [ ] Reuse current counts; add separate counters where needed to distinguish provider verification failure from remotely active subscriptions. Document document-count versus file-failure-count units.
- [ ] Mark partial work with `app.outcome=partial` and WARN even if the existing command still exits zero. Do not change command exit behavior as part of logging.
- [ ] Mark dry runs and describe counts as “would process.” Dry-run output must not imply committed mutations.
- [ ] Cover zero records, success, partial failure, thrown failure, and dry run using existing console tests. Avoid per-record success events that can overflow the 256-record batch queue.

**Done when:** summaries are correlated with command traces, distinguish partial work, and retain existing data/exit-code behavior.

### Step 7 — Verify automated behavior

Use the real `Telemetry` service with in-memory SDK exporters for integration assertions; mocking `event()` alone cannot catch events rejected by the catalog. Reuse the exporter setup in `TelemetryTest` and bind it into Laravel's container. Flush locally before examining records. Disable network export in tests.

Required scenarios:

- [ ] Correct body/event name, severity, resource service identity, and allowed context for each event family.
- [ ] Unknown event/key and invalid enum values are rejected; canary secrets placed in inputs and exception messages never appear in exported bodies or attributes.
- [ ] HTTP and command events have matching trace/span IDs; no context leaks into the next operation.
- [ ] An unsampled trace still exports business logs; a log without a span exports without an invented trace ID.
- [ ] INFO/WARN/ERROR thresholds, invalid configuration fallback, disabled mode, and cached configuration work.
- [ ] Success events wait for the outermost commit; nested rollback emits none. Use tests with real commit boundaries, not a fixture that keeps every test inside an uncommitted transaction.
- [ ] Duplicate payment/webhook delivery does not emit a second mutation success or alter balances twice.
- [ ] A recovered mail/storage/provider failure emits a usable outcome even when HTTP status is 200/302.
- [ ] Logger/exporter exceptions and stopped-collector behavior preserve business results; callbacks execute exactly once.
- [ ] Every row in section 5 links to relevant tests and remaining exclusions are explained.

Run focused tests after each module, then the PHP regression suite once integration is complete:

```bash
docker compose exec app php artisan test tests/Unit/Observability tests/Feature/Observability
docker compose exec app php artisan test
```

Run repository-required formatting/review checks for the implementation. Record exact commands and failures. A documentation-only planning change does not require executing this future implementation suite.

### Step 8 — Verify real SigNoz ingestion and write the runbook

- [ ] Start the existing stack using `docs/observability.md`. Do not regenerate or upgrade deployment versions just to add event names.
- [ ] Run the existing synthetic smoke command:

```bash
docker compose -f docker-compose.yml -f docker-compose.observability.yml exec app php artisan observability:smoke
```

- [ ] In a disposable local environment, exercise a successful operation, a rejected operation, and a synthetic provider/storage/mail failure. Use existing sandbox/fake facilities; do not run real charges or mail real recipients.
- [ ] Open SigNoz at `http://localhost:8080`, select Logs, select the recent time range, and filter resource `service.name` by `esign-api` or `esign-scheduler`.
- [ ] Search body for an exact catalog event, for example `signing.submit.rejected`. Inspect severity, structured attributes, environment, and trace/span IDs; follow a trace link when present. The current exporter puts the event name in the body, so queries need not assume how the UI exposes OTel event-name fields.
- [ ] Check one command summary using disposable fixtures. `subscriptions:expire --dry-run` can still call Stripe; use synthetic non-Stripe subscriptions or a mocked client.
- [ ] Stop only the `ingester` in the local SigNoz Compose project, repeat a harmless operation, verify unchanged application behavior and bounded export delays, then restart it and confirm later logs arrive. Lost records during the outage are acceptable; do not promise replay.
- [ ] Update `docs/observability.md` with level configuration, event examples, service/severity/module/trace filters, no-data troubleshooting, best-effort delivery, and rollback. Record the filters actually used with the installed UI.
- [ ] Document log retention and access controls using the installed SigNoz settings; do not invent retention defaults or enable external alert delivery. Changing retention or production access is a separate deployment decision.

**Done when:** capture evidence of actual business events in SigNoz, including service, event, severity, timestamp, and trace correlation. A smoke command saying “export attempted” is not proof of ingestion. If Docker/UI verification cannot be run, explicitly report it as incomplete.

## 7. Acceptance criteria

- [ ] All rows in the module coverage table are implemented and documented with exact event names, owners, safe attributes, and tests.
- [ ] Existing request, command, smoke, trace, and metric behavior remains intact.
- [ ] SigNoz shows business success, rejection, and failure events from HTTP and command operations.
- [ ] Events are searchable by service, event body, severity, module, outcome, and trace where available.
- [ ] Sensitive values cannot reach exported events; migrated local logs are sanitized as well.
- [ ] Transaction rollback and duplicate delivery do not create false mutation-success logs.
- [ ] Recovered failures are observable, and generic exceptions are not duplicated across nested handlers.
- [ ] Disabled telemetry and collector outages preserve application behavior with bounded overhead.
- [ ] Required tests pass; actual ingestion checks and any incomplete verification are documented.

## 8. Suggested implementation handoff

Deliver small changes in this order: (1) catalog/configuration/tests, (2) exception handling and transaction-safe logging, (3) payments/wallet/integrations, (4) documents/signing/auth/mail, (5) organization/templates/commands, (6) SigNoz verification and documentation. Finish each step's checks before moving on. Do not leave event names unregistered or accept a catalog-only implementation as completed module coverage.

Final implementation report: list files changed, modules covered, tests run, SigNoz evidence, and unresolved checks. Disable export through the existing `OBSERVABILITY_ENABLED=false` setting if rollback is needed; recreate affected containers and refresh cached Laravel configuration as documented. Preserve SigNoz storage volumes.
