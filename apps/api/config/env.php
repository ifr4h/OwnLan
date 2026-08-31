<?php

declare(strict_types=1);

/**
 * Load simple KEY=VALUE pairs from apps/api/.env into putenv/$_ENV.
 * Intentionally tiny — no dotenv package required for the MVP scaffold.
 */
$envFile = dirname(__DIR__) . '/.env';

if (!is_readable($envFile)) {
    return;
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    return;
}

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }

    [$name, $value] = explode('=', $line, 2);
    $name = trim($name);
    $value = trim($value);

    if ($name === '' || getenv($name) !== false) {
        continue;
    }

    putenv("$name=$value");
    $_ENV[$name] = $value;
}
