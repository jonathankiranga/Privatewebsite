<?php
declare(strict_types=1);

/*
 * Load private hosting configuration.
 *
 * Deployment path:
 *   /home/<hosting-account>/private-config/platform-admin.php
 *
 * This file lives outside public_html. Existing hosting environment variables
 * take precedence; values in the private PHP file are fallbacks.
 */
$privateConfigFile = dirname(__DIR__, 3) . '/private-config/platform-admin.php';

if (is_file($privateConfigFile) && is_readable($privateConfigFile)) {
    /*
     * A BOM or whitespace outside PHP tags in the private config must never
     * reach the HTTP response before session cookies and headers are sent.
     * Capture and discard accidental output while still loading its return value.
     */
    ob_start();
    try {
        $privateConfig = require $privateConfigFile;
    } finally {
        $unexpectedOutput = ob_get_clean();
    }

    if ($unexpectedOutput !== '') {
        error_log('SmartNow private configuration emitted output; remove any BOM, whitespace, or closing PHP tag from platform-admin.php.');
    }

    if (is_array($privateConfig)) {
        foreach ($privateConfig as $key => $value) {
            if (!is_string($key) || $key === '' || !is_scalar($value)) {
                continue;
            }

            $existingValue = getenv($key);
            if ($existingValue === false || $existingValue === '') {
                $stringValue = (string)$value;
                putenv($key . '=' . $stringValue);
                $_ENV[$key] = $stringValue;
                $_SERVER[$key] = $stringValue;
            }
        }
    }
}
