<?php

$environment = env('APP_ENV', 'production');
$localProvider = in_array($environment, ['local', 'testing'], true) ? 'fake' : null;

return [
    'provider' => env('TRANSCRIPT_PROVIDER', $localProvider),

    'anonymous' => [
        'limit' => (int) env('ANONYMOUS_TRANSCRIPT_LIMIT', 3),
        'cookie_name' => env('ANONYMOUS_TRANSCRIPT_COOKIE', 'transcript_guest'),
        'cookie_lifetime_minutes' => (int) env('ANONYMOUS_TRANSCRIPT_COOKIE_LIFETIME', 525_600),
        'cookie_secure' => env('ANONYMOUS_TRANSCRIPT_COOKIE_SECURE', $environment === 'production'),
    ],

    'yt_dlp' => [
        'binary' => env('YT_DLP_BINARY', '/usr/local/bin/yt-dlp'),
        'js_runtime' => env('YT_DLP_JS_RUNTIME', 'node'),
        'timeout_seconds' => (float) env('YT_DLP_TIMEOUT_SECONDS', 120),
        'idle_timeout_seconds' => (float) env('YT_DLP_IDLE_TIMEOUT_SECONDS', 30),
        'temporary_path' => env('YT_DLP_TEMPORARY_PATH', sys_get_temp_dir()),
        'max_metadata_bytes' => (int) env('YT_DLP_MAX_METADATA_BYTES', 16_777_216),
        'max_process_stdout_bytes' => (int) env('YT_DLP_MAX_PROCESS_STDOUT_BYTES', 2_097_152),
        'max_stderr_bytes' => (int) env('YT_DLP_MAX_STDERR_BYTES', 524_288),
        'max_caption_bytes' => (int) env('YT_DLP_MAX_CAPTION_BYTES', 33_554_432),
        'max_segments' => (int) env('YT_DLP_MAX_SEGMENTS', 200_000),
    ],
];
