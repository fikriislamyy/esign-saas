<?php

namespace App\Observability;

final class LogEventCatalog
{
    private const OUTCOMES = ['success', 'failure', 'rejected', 'skipped', 'pending', 'partial'];

    private const PROVIDERS = ['stripe', 'pakasir'];

    private const INTEGRATIONS = ['stripe', 'pakasir', 'currencyfreaks', 'recaptcha'];

    /**
     * Event definitions are deliberately explicit. Adding a call site without
     * reviewing its data contract must not expand what is exported.
     *
     * @var array<string, array{module?: string, operation?: string, operations?: list<string>, severity: string, keys: list<string>, reasons?: list<string>}>
     */
    private const EVENTS = [
        'http.request.completed' => ['severity' => 'INFO', 'keys' => ['http.request.method', 'http.route', 'http.response.status_code']],
        'application.exception' => ['severity' => 'ERROR', 'keys' => ['error.type']],
        'command.completed' => ['severity' => 'INFO', 'keys' => ['app.command.name', 'app.command.exit_code', 'app.outcome']],
        'observability.smoke' => ['severity' => 'INFO', 'keys' => []],

        'auth.login.rejected' => ['module' => 'auth', 'operation' => 'login', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['invalid_credentials', 'rate_limited', 'invalid_otp', 'missing_session']],
        'auth.login.completed' => ['module' => 'auth', 'operation' => 'login', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.logout.completed' => ['module' => 'auth', 'operation' => 'logout', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.registration.completed' => ['module' => 'auth', 'operation' => 'register', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.password.reset_requested' => ['module' => 'auth', 'operation' => 'request_password_reset', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.password.reset_completed' => ['module' => 'auth', 'operation' => 'reset_password', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.password.changed' => ['module' => 'auth', 'operation' => 'change_password', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.profile.updated' => ['module' => 'auth', 'operation' => 'update_profile', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.profile.deleted' => ['module' => 'auth', 'operation' => 'delete_profile', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.email_verification.completed' => ['module' => 'auth', 'operation' => 'verify_email', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'auth.otp.sent' => ['module' => 'auth', 'operation' => 'send_otp', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['login', 'email_verification']],
        'auth.otp.rejected' => ['module' => 'auth', 'operation' => 'verify_otp', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['invalid_otp', 'expired', 'rate_limited']],

        'document.upload.completed' => ['module' => 'document', 'operation' => 'upload', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'document.upload.rejected' => ['module' => 'document', 'operation' => 'upload', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['plan_limit', 'storage_limit']],
        'document.upload.failed' => ['module' => 'document', 'operation' => 'upload', 'severity' => 'ERROR', 'keys' => ['app.outcome', 'app.reason', 'error.type'], 'reasons' => ['storage_failure']],
        'document.prepare.completed' => ['module' => 'document', 'operation' => 'prepare', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'document.send.completed' => ['module' => 'document', 'operation' => 'send', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.failed_count']],
        'document.send.rejected' => ['module' => 'document', 'operation' => 'send', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['no_signers', 'no_fields', 'insufficient_balance']],
        'document.send.failed' => ['module' => 'document', 'operation' => 'send', 'severity' => 'ERROR', 'keys' => ['app.outcome', 'app.reason', 'error.type'], 'reasons' => ['mail_failure']],
        'document.signers.changed' => ['module' => 'document', 'operation' => 'change_signers', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['added', 'removed', 'reordered', 'workflow_changed']],
        'document.fields.changed' => ['module' => 'document', 'operation' => 'change_fields', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['added', 'updated', 'removed']],

        'signing.otp.sent' => ['module' => 'signing', 'operation' => 'send_otp', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'signing.otp.verified' => ['module' => 'signing', 'operation' => 'verify_otp', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'signing.otp.rejected' => ['module' => 'signing', 'operation' => 'verify_otp', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['invalid_otp', 'expired', 'rate_limited']],
        'signing.submit.completed' => ['module' => 'signing', 'operation' => 'submit', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'signing.submit.rejected' => ['module' => 'signing', 'operation' => 'submit', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['invalid_otp', 'expired', 'wrong_order', 'already_signed', 'missing_fields', 'invalid_signature', 'insufficient_balance']],
        'signing.submit.failed' => ['module' => 'signing', 'operation' => 'submit', 'severity' => 'ERROR', 'keys' => ['app.outcome', 'app.reason', 'error.type'], 'reasons' => ['processing_failure', 'storage_failure', 'pdf_failure']],
        'signing.document.completed' => ['module' => 'signing', 'operation' => 'complete_document', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'signing.workflow.advanced' => ['module' => 'signing', 'operation' => 'advance_workflow', 'severity' => 'INFO', 'keys' => ['app.outcome']],

        'billing.topup.created' => ['module' => 'billing', 'operation' => 'create_topup', 'severity' => 'INFO', 'keys' => ['app.outcome', 'payment.provider']],
        'billing.topup.rejected' => ['module' => 'billing', 'operation' => 'create_topup', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason', 'payment.provider'], 'reasons' => ['provider_unavailable', 'invalid_amount']],
        'billing.subscription.created' => ['module' => 'billing', 'operation' => 'subscribe', 'severity' => 'INFO', 'keys' => ['app.outcome', 'payment.provider']],
        'billing.subscription.renewed' => ['module' => 'billing', 'operation' => 'renew', 'severity' => 'INFO', 'keys' => ['app.outcome', 'payment.provider']],
        'billing.subscription.past_due' => ['module' => 'billing', 'operation' => 'renew', 'severity' => 'WARN', 'keys' => ['app.outcome', 'payment.provider']],
        'billing.subscription.cancelled' => ['module' => 'billing', 'operation' => 'cancel', 'severity' => 'INFO', 'keys' => ['app.outcome', 'payment.provider']],
        'billing.subscription.downgraded' => ['module' => 'billing', 'operation' => 'downgrade', 'severity' => 'INFO', 'keys' => ['app.outcome', 'payment.provider']],
        'billing.subscription.rejected' => ['module' => 'billing', 'operation' => 'subscribe', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason', 'payment.provider'], 'reasons' => ['provider_unavailable', 'missing_configuration']],

        'payment.webhook.received' => ['module' => 'payment', 'operation' => 'webhook', 'severity' => 'INFO', 'keys' => ['app.outcome', 'payment.provider']],
        'payment.webhook.rejected' => ['module' => 'payment', 'operation' => 'webhook', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason', 'payment.provider', 'error.type'], 'reasons' => ['invalid_signature', 'invalid_payload', 'unknown_record', 'amount_mismatch', 'project_mismatch', 'wrong_provider', 'missing_configuration']],
        'payment.webhook.skipped' => ['module' => 'payment', 'operation' => 'webhook', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.reason', 'payment.provider'], 'reasons' => ['ignored_type', 'duplicate', 'pending']],
        'payment.fulfillment.completed' => ['module' => 'payment', 'operation' => 'fulfill', 'severity' => 'INFO', 'keys' => ['app.outcome', 'payment.provider']],
        'payment.fulfillment.skipped' => ['module' => 'payment', 'operation' => 'fulfill', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.reason', 'payment.provider'], 'reasons' => ['duplicate', 'pending']],
        'payment.fulfillment.rejected' => ['module' => 'payment', 'operation' => 'fulfill', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason', 'payment.provider'], 'reasons' => ['sandbox_required']],

        'wallet.credit.completed' => ['module' => 'wallet', 'operation' => 'credit', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'wallet.credit.failed' => ['module' => 'wallet', 'operation' => 'credit', 'severity' => 'ERROR', 'keys' => ['app.outcome', 'error.type']],
        'wallet.debit.completed' => ['module' => 'wallet', 'operation' => 'debit', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'wallet.debit.rejected' => ['module' => 'wallet', 'operation' => 'debit', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['insufficient_balance']],
        'wallet.debit.failed' => ['module' => 'wallet', 'operation' => 'debit', 'severity' => 'ERROR', 'keys' => ['app.outcome', 'error.type']],

        'organization.invitation.sent' => ['module' => 'organization', 'operation' => 'send_invitation', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'organization.invitation.resent' => ['module' => 'organization', 'operation' => 'resend_invitation', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'organization.invitation.revoked' => ['module' => 'organization', 'operation' => 'revoke_invitation', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'organization.invitation.accepted' => ['module' => 'organization', 'operation' => 'accept_invitation', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'organization.invitation.rejected' => ['module' => 'organization', 'operation' => 'send_invitation', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['plan_limit', 'already_member', 'already_accepted']],
        'organization.member.role_changed' => ['module' => 'organization', 'operation' => 'change_member_role', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'organization.member.rejected' => ['module' => 'organization', 'operation' => 'change_member_role', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['self_change', 'owner_protected']],
        'organization.settings.updated' => ['module' => 'organization', 'operation' => 'update_settings', 'severity' => 'INFO', 'keys' => ['app.outcome']],

        'template.upload.completed' => ['module' => 'template', 'operation' => 'upload', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'template.upload.rejected' => ['module' => 'template', 'operation' => 'upload', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['plan_limit']],
        'template.delete.completed' => ['module' => 'template', 'operation' => 'delete', 'severity' => 'INFO', 'keys' => ['app.outcome']],
        'template.delete.partial' => ['module' => 'template', 'operation' => 'delete', 'severity' => 'WARN', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['storage_failure']],
        'template.fields.changed' => ['module' => 'template', 'operation' => 'change_fields', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.reason'], 'reasons' => ['added', 'updated', 'removed']],

        'integration.request.completed' => ['module' => 'integration', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.operation', 'integration.provider', 'app.duration_ms'], 'operations' => ['create_qris', 'transaction_detail', 'simulate_payment', 'verify_recaptcha', 'fetch_exchange_rate', 'stripe_customer', 'stripe_setup_intent', 'stripe_subscription', 'stripe_retrieve_subscription', 'stripe_cancel_subscription', 'stripe_payment_intent']],
        'integration.request.failed' => ['module' => 'integration', 'severity' => 'ERROR', 'keys' => ['app.outcome', 'app.operation', 'app.reason', 'integration.provider', 'app.duration_ms', 'error.type'], 'reasons' => ['provider_unavailable', 'invalid_response', 'not_configured'], 'operations' => ['create_qris', 'transaction_detail', 'simulate_payment', 'verify_recaptcha', 'fetch_exchange_rate', 'stripe_customer', 'stripe_setup_intent', 'stripe_subscription', 'stripe_retrieve_subscription', 'stripe_cancel_subscription', 'stripe_payment_intent']],
        'mail.submission.completed' => ['module' => 'mail', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.operation'], 'operations' => ['login_otp', 'email_verification', 'organization_invitation', 'signature_request']],
        'mail.submission.failed' => ['module' => 'mail', 'severity' => 'ERROR', 'keys' => ['app.outcome', 'app.operation', 'app.reason', 'error.type'], 'reasons' => ['transport_failure'], 'operations' => ['login_otp', 'email_verification', 'organization_invitation', 'signature_request']],

        'documents.expiry.summary' => ['module' => 'scheduler', 'operation' => 'expire_documents', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.processed_count', 'app.skipped_count', 'app.failed_count', 'app.dry_run']],
        'subscriptions.expiry.summary' => ['module' => 'scheduler', 'operation' => 'expire_subscriptions', 'severity' => 'INFO', 'keys' => ['app.outcome', 'app.processed_count', 'app.skipped_count', 'app.failed_count', 'app.dry_run']],
    ];

    /** @return array{attributes: array<string, bool|int|float|string>, severity: string}|null */
    public static function normalize(string $name, array $attributes, ?string $severity = null): ?array
    {
        $definition = self::EVENTS[$name] ?? null;
        if ($definition === null || array_diff(array_keys($attributes), $definition['keys'])) {
            return null;
        }
        if ((in_array('app.outcome', $definition['keys'], true) && ! array_key_exists('app.outcome', $attributes))
            || (isset($definition['reasons']) && ! array_key_exists('app.reason', $attributes))) {
            return null;
        }

        $normalized = [];
        foreach ($attributes as $key => $value) {
            if (! self::validValue($key, $value, $definition)) {
                return null;
            }
            $normalized[$key] = is_string($value) ? substr($value, 0, 128) : $value;
        }

        if (isset($definition['module'])) {
            $normalized['app.module'] = $definition['module'];
        }
        if (isset($definition['operation']) && ! isset($normalized['app.operation'])) {
            $normalized['app.operation'] = $definition['operation'];
        }

        $severity = strtoupper($severity ?? $definition['severity']);
        if (! in_array($severity, ['INFO', 'WARN', 'ERROR'], true)) {
            $severity = $definition['severity'];
        }

        return ['attributes' => $normalized, 'severity' => $severity];
    }

    public static function minimumSeverity(string $severity): string
    {
        $severity = strtoupper($severity);

        return in_array($severity, ['INFO', 'WARN', 'ERROR'], true) ? $severity : 'INFO';
    }

    public static function meetsThreshold(string $severity, string $minimum): bool
    {
        $rank = ['INFO' => 1, 'WARN' => 2, 'ERROR' => 3];

        return $rank[$severity] >= $rank[self::minimumSeverity($minimum)];
    }

    private static function validValue(string $key, mixed $value, array $definition): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        return match ($key) {
            'app.outcome' => is_string($value) && in_array($value, self::OUTCOMES, true),
            'app.reason' => is_string($value) && in_array($value, $definition['reasons'] ?? [], true),
            'app.operation' => is_string($value) && (! isset($definition['operations']) || in_array($value, $definition['operations'], true)),
            'payment.provider' => is_string($value) && in_array($value, self::PROVIDERS, true),
            'integration.provider' => is_string($value) && in_array($value, self::INTEGRATIONS, true),
            'error.type' => is_string($value) && preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1,
            'app.duration_ms' => (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0,
            'app.processed_count', 'app.skipped_count', 'app.failed_count' => is_int($value) && $value >= 0,
            'app.dry_run' => is_bool($value),
            'http.response.status_code', 'app.command.exit_code' => is_int($value),
            default => is_string($value) || is_bool($value) || is_int($value) || (is_float($value) && is_finite($value)),
        };
    }
}
