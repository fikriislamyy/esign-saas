# Application log event catalog

Application events use fixed names and a reviewed attribute allowlist. `app.module` and, for most events, `app.operation` are added by `LogEventCatalog`; callers cannot replace them. Every business event includes `app.outcome`. Events with a reason accept only the values listed in `LogEventCatalog`.

Never add account, organization, document, payment, or provider identifiers to this catalog without a separate privacy review. Email addresses, names, tokens, OTPs, request data, headers, paths, URLs, amounts, balances, exception messages, and stack traces are prohibited. `error.type` contains an exception class only.

## Shared and framework events

| Events | Owner | Severity | Event attributes | Coverage |
| --- | --- | --- | --- | --- |
| `http.request.completed` | `Telemetry::traceRequest` | INFO/WARN/ERROR from status | method, route template, status | `RequestTelemetryTest` |
| `command.completed` | `Telemetry::runCommand` | INFO/ERROR from exit code | command, exit code, outcome | `TelemetryTest` |
| `application.exception` | `Telemetry::recordExceptionType` | ERROR | exception class | `TelemetryTest`, `RequestTelemetryTest` |
| `observability.smoke` | `Telemetry::smoke` | INFO | none | `TelemetryTest` and manual smoke check |

## Business events

| Events | Owner methods | Default severity | Safe attributes / reasons | Coverage status |
| --- | --- | --- | --- | --- |
| `auth.login.completed`, `auth.login.rejected`, `auth.logout.completed` | `LoginOtpController::store`, `LoginRequest::authenticate`, `LoginRequest::ensureIsNotRateLimited`, `AuthenticatedSessionController::destroy` | INFO; rejected WARN | outcome; `invalid_credentials`, `rate_limited`, `invalid_otp`, `missing_session` | Covered by auth feature tests and catalog tests. |
| `auth.registration.completed`, `auth.profile.updated`, `auth.profile.deleted` | registration and profile controllers | INFO | outcome | Covered by existing auth/profile feature tests. |
| `auth.password.reset_requested`, `auth.password.reset_completed`, `auth.password.changed` | password controllers | INFO | outcome | Covered by existing password feature tests. Reset-request logs contain no account-existence signal. |
| `auth.email_verification.completed`, `auth.otp.sent`, `auth.otp.rejected` | email/login OTP services and controllers | INFO; rejected WARN | outcome; fixed OTP purpose or `invalid_otp`, `expired`, `rate_limited` | Covered by existing OTP and verification tests. |
| `document.upload.completed`, `document.upload.rejected`, `document.upload.failed` | `DocumentController::store` | INFO/WARN/ERROR | outcome; `plan_limit`, `storage_limit`, `storage_failure`; exception class on failure | Covered by document upload feature tests and catalog tests. |
| `document.prepare.completed`, `document.send.completed`, `document.send.rejected`, `document.send.failed` | `DocumentController::finishPrepare`, `DocumentController::send` | INFO/WARN/ERROR | outcome, failed count; `no_signers`, `no_fields`, `insufficient_balance`, `mail_failure`; exception class | Covered by existing document behavior tests plus catalog/privacy tests. |
| `document.signers.changed`, `document.fields.changed` | signer and document-field controllers | INFO | outcome; `added`, `updated`, `removed`, `reordered`, `workflow_changed` | Covered by current controller/feature paths. |
| `signing.otp.sent`, `signing.otp.verified`, `signing.otp.rejected` | `SigningController` | INFO; rejected WARN | outcome; `invalid_otp`, `expired`, `rate_limited` | Covered by signing OTP unit behavior and catalog tests. |
| `signing.submit.completed`, `signing.submit.rejected`, `signing.submit.failed`, `signing.document.completed`, `signing.workflow.advanced` | `SigningController::finish`, `verifyOtp`, `ensureSigningOrder` | INFO/WARN/ERROR | outcome; reviewed signing rejection/failure reason; exception class for failure | Covered by existing signing flows and catalog/privacy tests. Success is emitted after the signing transaction commits. |
| `billing.topup.created`, `billing.topup.rejected` | `BillingTopupController::store` | INFO/WARN | outcome, provider; `provider_unavailable`, `invalid_amount` | Covered by provider/service behavior and catalog tests. |
| `billing.subscription.created`, `billing.subscription.downgraded`, `billing.subscription.renewed`, `billing.subscription.past_due`, `billing.subscription.cancelled`, `billing.subscription.rejected` | subscription controller and Stripe webhook | INFO; past due/rejected WARN | outcome, provider; `provider_unavailable`, `missing_configuration` | Covered by existing subscription and payment tests plus catalog tests. |
| `payment.webhook.received`, `payment.webhook.rejected`, `payment.webhook.skipped` | Stripe and Pakasir webhook controllers | INFO/WARN | outcome, provider; validation, unknown-record, duplicate, pending, or ignored-type reason; optional exception class | Covered by existing webhook behavior and catalog tests. Payloads and event identifiers are excluded. |
| `payment.fulfillment.completed`, `payment.fulfillment.skipped`, `payment.fulfillment.rejected` | webhook controllers and `PakasirFulfillmentService` | INFO/WARN | outcome, provider; `duplicate`, `pending`, or `sandbox_required` | Covered by `PakasirFulfillmentServiceTest` and wallet idempotency tests. |
| `wallet.credit.completed`, `wallet.credit.failed`, `wallet.debit.completed`, `wallet.debit.rejected`, `wallet.debit.failed` | `WalletService::credit`, `WalletService::debit` | INFO/WARN/ERROR | outcome; `insufficient_balance`; exception class for unexpected failure | Covered by `WalletServiceTest` and transaction callback tests. Success waits for the outer transaction commit. |
| `organization.invitation.sent`, `organization.invitation.resent`, `organization.invitation.revoked`, `organization.invitation.accepted`, `organization.invitation.rejected` | invitation controllers | INFO/WARN | outcome; `plan_limit`, `already_member`, `already_accepted` | Covered by existing invitation behavior and catalog tests. |
| `organization.member.role_changed`, `organization.member.rejected`, `organization.settings.updated` | member/settings controllers | INFO/WARN | outcome; `self_change`, `owner_protected` | Covered by existing controller behavior and catalog tests. |
| `template.upload.completed`, `template.upload.rejected`, `template.delete.completed`, `template.delete.partial`, `template.fields.changed` | template controllers | INFO/WARN | outcome; plan/storage or field-change reason | Covered by template upload/delete feature tests and catalog tests. |
| `integration.request.completed`, `integration.request.failed` | `Telemetry::trackIntegration`, reCAPTCHA service | INFO/ERROR | fixed provider and operation, outcome, duration; reviewed reason and exception class | Covered by provider service tests and catalog tests. Cache hits do not emit an integration event. |
| `mail.submission.completed`, `mail.submission.failed` | `Telemetry::submitMail` at each mail call site | INFO/ERROR | fixed mail purpose, outcome; transport failure and exception class | Covered by existing mail/service tests and catalog tests. It records transport submission, not delivery. |
| `documents.expiry.summary`, `subscriptions.expiry.summary` | expiry commands | INFO/WARN | outcome, processed/skipped/failed counts, dry-run | Covered by console command tests. Summaries avoid per-row log volume. |

## Deliberate exclusions

Ordinary reads in dashboard, billing status, payment polling, landing pages, and pure limit/pricing calculations use `http.request.completed`. The project has no document deletion endpoint; signer and signature-field deletion are covered by their change events. Browser events, database queries, Redis operations, raw Laravel log forwarding, and mail delivery confirmation are outside issue 61.

## Query examples

In SigNoz Logs, select `service.name = esign-api` or `service.name = esign-scheduler`, then filter the body by the exact event name. Add `attributes.app.module`, `attributes.app.outcome`, or severity filters as needed. When a log was emitted inside a sampled operation, use its trace and span identifiers to open the related trace.
