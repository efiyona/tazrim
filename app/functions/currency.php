<?php

if (!function_exists('tazrim_debug_log')) {
    function tazrim_debug_log(string $location, string $message, array $data = [], string $hypothesis_id = 'unknown', ?string $run_id = null): void
    {
        $payload = [
            'sessionId' => '677ec9',
            'runId' => $run_id ?: uniqid('run_', true),
            'hypothesisId' => $hypothesis_id,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => round(microtime(true) * 1000),
        ];
        @file_put_contents(ROOT_PATH . '/.cursor/debug-677ec9.log', json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}

if (!function_exists('tazrim_normalize_currency_code')) {
    function tazrim_normalize_currency_code($currency_code): string
    {
        $currency = strtoupper(trim((string) $currency_code));
        return in_array($currency, ['ILS', 'USD'], true) ? $currency : 'ILS';
    }
}

if (!function_exists('tazrim_currency_symbol')) {
    function tazrim_currency_symbol($currency_code): string
    {
        return tazrim_normalize_currency_code($currency_code) === 'USD' ? '$' : '₪';
    }
}

if (!function_exists('tazrim_format_money')) {
    function tazrim_format_money($amount, $currency_code = 'ILS', int $decimals = 0): string
    {
        $currency = tazrim_normalize_currency_code($currency_code);
        $symbol = tazrim_currency_symbol($currency);
        $formatted = number_format((float) $amount, $decimals);
        return $currency === 'USD' ? $symbol . $formatted : $formatted . ' ' . $symbol;
    }
}

if (!function_exists('tazrim_load_live_fx_rate')) {
    function tazrim_load_live_fx_rate(string $base_currency, string $quote_currency): ?array
    {
        $base = tazrim_normalize_currency_code($base_currency);
        $quote = tazrim_normalize_currency_code($quote_currency);
        if ($base === $quote) {
            return [
                'rate' => 1.0,
                'provider' => 'local',
                'fetched_at' => date('Y-m-d H:i:s'),
                'is_stale' => false,
            ];
        }

        $url = 'https://api.frankfurter.dev/v2/rates?base=' . rawurlencode($base) . '&quotes=' . rawurlencode($quote);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!is_string($response) || $response === '' || $http_code < 200 || $http_code >= 300) {
            return null;
        }

        $decoded = json_decode($response, true);
        $payload = $decoded;
        if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
            $payload = $decoded[0];
        }

        $rate = isset($payload['rate']) ? (float) $payload['rate'] : 0.0;
        if ($rate <= 0 && isset($payload['rates'][$quote])) {
            $rate = (float) $payload['rates'][$quote];
        }
        if ($rate <= 0) {
            return null;
        }

        $fetched_at = isset($payload['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $payload['date'])
            ? $payload['date'] . ' 00:00:00'
            : date('Y-m-d H:i:s');

        return [
            'rate' => $rate,
            'provider' => 'frankfurter',
            'fetched_at' => $fetched_at,
            'is_stale' => false,
        ];
    }
}

if (!function_exists('tazrim_get_fx_rate')) {
    function tazrim_get_fx_rate(mysqli $conn, string $base_currency, string $quote_currency, int $fresh_hours = 24): array
    {
        $base = tazrim_normalize_currency_code($base_currency);
        $quote = tazrim_normalize_currency_code($quote_currency);
        $run_id = uniqid('fx_', true);
        if ($base === $quote) {
            // #region agent log
            tazrim_debug_log('app/functions/currency.php:95', 'FX bypassed because currencies match', [
                'base' => $base,
                'quote' => $quote,
            ], 'H1', $run_id);
            // #endregion
            return [
                'rate' => 1.0,
                'provider' => 'local',
                'fetched_at' => date('Y-m-d H:i:s'),
                'is_stale' => false,
            ];
        }

        $base_esc = mysqli_real_escape_string($conn, $base);
        $quote_esc = mysqli_real_escape_string($conn, $quote);
        $cache_query = "SELECT rate, provider, fetched_at
                        FROM fx_rates_cache
                        WHERE base_currency = '$base_esc' AND quote_currency = '$quote_esc'
                        LIMIT 1";
        $cache_result = mysqli_query($conn, $cache_query);
        $cache_row = $cache_result ? mysqli_fetch_assoc($cache_result) : null;

        if ($cache_row && !empty($cache_row['fetched_at'])) {
            $age_seconds = time() - strtotime($cache_row['fetched_at']);
            if ($age_seconds >= 0 && $age_seconds <= ($fresh_hours * 3600)) {
                // #region agent log
                tazrim_debug_log('app/functions/currency.php:120', 'FX cache hit', [
                    'base' => $base,
                    'quote' => $quote,
                    'rate' => (float) $cache_row['rate'],
                    'fetched_at' => (string) $cache_row['fetched_at'],
                    'age_seconds' => $age_seconds,
                ], 'H1', $run_id);
                // #endregion
                return [
                    'rate' => (float) $cache_row['rate'],
                    'provider' => (string) $cache_row['provider'],
                    'fetched_at' => (string) $cache_row['fetched_at'],
                    'is_stale' => false,
                ];
            }
        }

        $live_rate = tazrim_load_live_fx_rate($base, $quote);
        if ($live_rate) {
            // #region agent log
            tazrim_debug_log('app/functions/currency.php:136', 'FX live fetch succeeded', [
                'base' => $base,
                'quote' => $quote,
                'rate' => (float) $live_rate['rate'],
                'fetched_at' => (string) $live_rate['fetched_at'],
            ], 'H1', $run_id);
            // #endregion
            $rate = (float) $live_rate['rate'];
            $provider = mysqli_real_escape_string($conn, (string) $live_rate['provider']);
            $fetched_at = mysqli_real_escape_string($conn, (string) $live_rate['fetched_at']);

            mysqli_query(
                $conn,
                "INSERT INTO fx_rates_cache (base_currency, quote_currency, rate, provider, fetched_at)
                 VALUES ('$base_esc', '$quote_esc', $rate, '$provider', '$fetched_at')
                 ON DUPLICATE KEY UPDATE
                     rate = VALUES(rate),
                     provider = VALUES(provider),
                     fetched_at = VALUES(fetched_at)"
            );

            return $live_rate;
        }

        if ($cache_row) {
            // #region agent log
            tazrim_debug_log('app/functions/currency.php:156', 'FX live fetch failed, using stale cache', [
                'base' => $base,
                'quote' => $quote,
                'rate' => (float) $cache_row['rate'],
                'fetched_at' => (string) $cache_row['fetched_at'],
            ], 'H1', $run_id);
            // #endregion
            return [
                'rate' => (float) $cache_row['rate'],
                'provider' => (string) $cache_row['provider'],
                'fetched_at' => (string) $cache_row['fetched_at'],
                'is_stale' => true,
            ];
        }

        // #region agent log
        tazrim_debug_log('app/functions/currency.php:169', 'FX fetch failed with no cache fallback', [
            'base' => $base,
            'quote' => $quote,
        ], 'H1', $run_id);
        // #endregion
        throw new RuntimeException('לא הצלחנו למשוך שער המרה כרגע.');
    }
}

if (!function_exists('tazrim_convert_amount_to_ils')) {
    function tazrim_convert_amount_to_ils(mysqli $conn, float $amount, string $currency_code): array
    {
        $currency = tazrim_normalize_currency_code($currency_code);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive.');
        }

        if ($currency === 'ILS') {
            return [
                'original_amount' => $amount,
                'currency_code' => 'ILS',
                'converted_amount' => round($amount, 2),
                'rate' => 1.0,
                'provider' => 'local',
                'fetched_at' => date('Y-m-d H:i:s'),
                'is_stale' => false,
            ];
        }

        $rate_info = tazrim_get_fx_rate($conn, $currency, 'ILS');

        return [
            'original_amount' => $amount,
            'currency_code' => $currency,
            'converted_amount' => round($amount * (float) $rate_info['rate'], 2),
            'rate' => (float) $rate_info['rate'],
            'provider' => (string) $rate_info['provider'],
            'fetched_at' => (string) $rate_info['fetched_at'],
            'is_stale' => !empty($rate_info['is_stale']),
        ];
    }
}
