<?php

declare(strict_types=1);

require __DIR__ . '/env.php';

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types.
 */
return [
    'id' => 'ownlane-api-tests',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        \app\tests\Support\MailerBootstrap::class,
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'language' => 'en-GB',
    'components' => [
        'db' => $db,
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'messageClass' => \yii\symfonymailer\Message::class,
            'useFileTransport' => true,
            'viewPath' => '@app/mail',
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => true,
            'enableStrictParsing' => false,
            'rules' => [
                'GET health' => 'health/index',
                'POST auth/register' => 'auth/register',
                'POST auth/login' => 'auth/login',
                'POST auth/logout' => 'auth/logout',
                'GET auth/me' => 'auth/me',
            ],
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => false,
            'enableSession' => true,
        ],
        'session' => [
            'class' => \yii\web\Session::class,
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
        ],
    ],
    'container' => [
        'definitions' => [
            \app\services\AuthService::class => \app\services\AuthService::class,
        ],
    ],
    'params' => $params,
];
