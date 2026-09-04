<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\CalendarFeedService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Public calendar subscription feed — token auth only.
 */
class CalendarFeedController extends BaseApiController
{
    private CalendarFeedService $calendar;

    public function init(): void
    {
        parent::init();
        $this->calendar = new CalendarFeedService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'feed' => ['GET', 'HEAD'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        // No session auth — token in URL is the credential.
        return parent::beforeAction($action);
    }

    public function actionFeed(string $token): Response
    {
        try {
            $ics = $this->calendar->feedByToken($token);
        } catch (NotFoundHttpException $e) {
            throw $e;
        }

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Cache-Control', 'private, max-age=300');
        $response->content = $ics;

        return $response;
    }
}
