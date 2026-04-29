#!/usr/bin/env php
<?php
/**
 * CLI: generate an image via Gemini API (free-tier image model, generateContent).
 * Does not use Imagen (paid). Model: gemini-2.5-flash-image.
 *
 * Usage:
 *   php scripts/generate-image.php "English image prompt" path/to/output.png
 *
 * Env TAZRIM_GEMINI_API_KEY or define in a local CLI wrapper (לא secrets.php גלובלי).
 */

declare(strict_types=1);

/** @var string Free-tier Gemini native image model (Nano Banana line). */
const GEMINI_FREE_IMAGE_MODEL = 'gemini-2.5-flash-image';

if (($argc ?? 0) < 3) {
    fwrite(STDERR, "Usage: php generate-image.php \"<English prompt>\" <output-path>\n");
    exit(1);
}

$prompt = $argv[1];
$outputPath = $argv[2];

if ($prompt === '') {
    fwrite(STDERR, "Error: prompt must not be empty.\n");
    exit(1);
}

require dirname(__DIR__) . '/secrets.php';

$cliGemini = getenv('TAZRIM_GEMINI_API_KEY') ?: '';
if ($cliGemini === '' && defined('GEMINI_CLI_KEY')) {
    $cliGemini = (string) constant('GEMINI_CLI_KEY');
}
if ($cliGemini === '') {
    fwrite(STDERR, "Error: Set TAZRIM_GEMINI_API_KEY env or GEMINI_CLI_KEY for CLI image generation.\n");
    exit(1);
}

$apiKey = $cliGemini;
$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
    . GEMINI_FREE_IMAGE_MODEL
    . ':generateContent';

$body = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt],
            ],
        ],
    ],
    'generationConfig' => [
        'responseModalities' => ['TEXT', 'IMAGE'],
    ],
], JSON_UNESCAPED_UNICODE);

if ($body === false) {
    fwrite(STDERR, "Error: failed to encode request JSON.\n");
    exit(1);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 180,
]);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close() is deprecated/no-op in PHP 8.5+; omit to avoid deprecation noise.

if ($response === false) {
    fwrite(STDERR, "cURL error: {$curlErr}\n");
    exit(1);
}

$data = json_decode($response, true);
if (!is_array($data)) {
    fwrite(STDERR, "Error: invalid JSON response (HTTP {$httpCode}).\n");
    fwrite(STDERR, substr($response, 0, 2000) . "\n");
    exit(1);
}

if (!empty($data['error']['message'])) {
    fwrite(STDERR, 'API error: ' . $data['error']['message'] . "\n");
    exit(1);
}

$binary = extractInlineImageFromGenerateContent($data);
if ($binary === null) {
    fwrite(STDERR, "Error: no image data in API response (HTTP {$httpCode}).\n");
    fwrite(STDERR, substr($response, 0, 2000) . "\n");
    exit(1);
}

$dir = dirname($outputPath);
if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, "Error: cannot create directory: {$dir}\n");
        exit(1);
    }
}

$written = file_put_contents($outputPath, $binary);
if ($written === false) {
    fwrite(STDERR, "Error: failed to write file: {$outputPath}\n");
    exit(1);
}

echo "SUCCESS\n";
exit(0);

/**
 * Parse generateContent response: candidates[].content.parts[].inlineData.data (base64).
 *
 * @param array<string, mixed> $data
 */
function extractInlineImageFromGenerateContent(array $data): ?string
{
    $candidates = $data['candidates'] ?? null;
    if (!is_array($candidates)) {
        return null;
    }

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $content = $candidate['content'] ?? null;
        if (!is_array($content)) {
            continue;
        }
        $parts = $content['parts'] ?? null;
        if (!is_array($parts)) {
            continue;
        }
        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (!is_array($inline)) {
                continue;
            }
            $b64 = $inline['data'] ?? null;
            if (!is_string($b64) || $b64 === '') {
                continue;
            }
            $raw = base64_decode($b64, true);
            if ($raw !== false) {
                return $raw;
            }
        }
    }

    return null;
}
