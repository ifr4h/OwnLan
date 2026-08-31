<?php

declare(strict_types=1);

return [
    'class' => \yii\db\Connection::class,
    'dsn' => getenv('DB_DSN') ?: 'pgsql:host=127.0.0.1;port=5432;dbname=ownlane',
    'username' => getenv('DB_USERNAME') ?: 'ownlane',
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'ownlane',
    'charset' => 'utf8',
];
