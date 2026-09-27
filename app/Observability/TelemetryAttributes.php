<?php

namespace App\Observability;

final class TelemetryAttributes
{
    private const ALLOWED = [
        'http.request.method', 'http.route', 'http.response.status_code',
        'app.command.name', 'app.command.exit_code', 'app.outcome',
        'payment.provider', 'error.type',
    ];

    /** @return array<string, bool|int|float|string> */
    public static function filter(array $attributes): array
    {
        $filtered = [];
        foreach ($attributes as $key => $value) {
            if (! in_array($key, self::ALLOWED, true) || ! is_scalar($value)) {
                continue;
            }
            if (is_string($value)) {
                $value = substr($value, 0, 128);
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
