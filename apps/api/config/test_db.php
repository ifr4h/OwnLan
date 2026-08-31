<?php

declare(strict_types=1);

$db = require __DIR__ . '/db.php';

// Isolated test database — never point this at the main ownlane DB.
$db['dsn'] = getenv('TEST_DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=ownlane_test';
$db['username'] = getenv('TEST_DB_USERNAME') ?: (getenv('DB_USERNAME') ?: 'ownlane');
$db['password'] = getenv('TEST_DB_PASSWORD') !== false
    ? getenv('TEST_DB_PASSWORD')
    : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'ownlane');

return $db;
