<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Minimal readiness endpoint for local scaffolding and future uptime checks.
 */
class HealthController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionIndex(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $database = 'ok';
        try {
            Yii::$app->db->createCommand('SELECT 1')->queryScalar();
        } catch (\Throwable $e) {
            $database = 'error';
            Yii::$app->response->statusCode = 503;
        }

        return [
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'service' => 'ownlane-api',
            'database' => $database,
            'time' => gmdate('c'),
        ];
    }

    public function actionError(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $exception = Yii::$app->errorHandler->exception;
        $status = $exception && method_exists($exception, 'statusCode')
            ? (int) $exception->statusCode
            : 500;

        Yii::$app->response->statusCode = $status ?: 500;

        return [
            'status' => 'error',
            'message' => $exception?->getMessage() ?: 'Unexpected error',
        ];
    }
}
