<?php

declare(strict_types=1);

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$corsOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) (getenv('CORS_ORIGIN') ?: 'http://localhost:3000')),
)));

$config = [
    'id' => 'ownlane-api',
    'name' => 'OwnLane API',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'change-me',
            'parsers' => [
                'application/json' => \yii\web\JsonParser::class,
            ],
            // Enable cookie auth across Nuxt (localhost:3000) → API (localhost:8080) later
            'csrfParam' => '_csrf',
        ],
        'response' => [
            'format' => \yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
            'on beforeSend' => static function ($event) {
                /** @var \yii\web\Response $response */
                $response = $event->sender;
                $response->headers->set('X-Powered-By', 'OwnLane');
            },
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => false,
            'enableSession' => true,
            'identityCookie' => [
                'name' => '_identity',
                'httpOnly' => true,
                'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'health/error',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                'GET health' => 'health/index',
                'POST auth/register' => 'auth/register',
                'POST auth/login' => 'auth/login',
                'POST auth/logout' => 'auth/logout',
                'GET auth/me' => 'auth/me',
                'GET' => 'health/index',
            ],
        ],
        'session' => [
            'class' => \yii\web\Session::class,
            'cookieParams' => [
                'httponly' => true,
                'samesite' => \yii\web\Cookie::SAME_SITE_LAX,
                // Path / so the Nuxt /api proxy receives the session cookie.
                'path' => '/',
            ],
        ],
    ],
    'container' => [
        'definitions' => [
            \app\services\AuthService::class => \app\services\AuthService::class,
        ],
    ],
    'as corsFilter' => [
        'class' => \yii\filters\Cors::class,
        'cors' => [
            'Origin' => $corsOrigins,
            'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'Access-Control-Request-Headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'Access-Control-Allow-Credentials' => true,
            'Access-Control-Max-Age' => 86400,
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
