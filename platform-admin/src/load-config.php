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
    $privateConfig = require $privateConfigFile;

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
