<?php

$environment = env('APP_ENV', 'production');
$localProvider = in_array($environment, ['local', 'testing'], true) ? 'fake' : null;

return [
    'provider' => env('TRANSCRIPT_PROVIDER', $localProvider),
];
