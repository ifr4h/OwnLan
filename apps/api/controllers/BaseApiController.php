<?php

declare(strict_types=1);

namespace app\controllers;

use yii\web\Controller;
use yii\web\Response;

/**
 * JSON API controllers: no CSRF (cookie session + SameSite + CORS allowlist).
 */
abstract class BaseApiController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        return parent::beforeAction($action);
    }
}
