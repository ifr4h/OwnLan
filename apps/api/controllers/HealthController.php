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
        $status = 500;
        if ($exception instanceof \yii\web\HttpException) {
            $status = (int) $exception->statusCode;
        }

        Yii::$app->response->statusCode = $status ?: 500;

        $message = 'Unexpected error';
        if ($exception instanceof \yii\web\HttpException) {
            $message = $exception->getMessage() ?: $message;
        } elseif (YII_DEBUG && $exception !== null) {
            $message = $exception->getMessage() ?: $message;
        }

        return [
            'status' => 'error',
            'message' => $message,
        ];
    }
}
