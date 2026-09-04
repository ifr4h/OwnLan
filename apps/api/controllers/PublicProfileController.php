<?php

declare(strict_types=1);

namespace app\controllers;

use app\services\EnquiryService;
use app\services\PublicAnalyticsService;
use app\services\PublicProfileService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Public instructor profile — no session auth.
 */
class PublicProfileController extends BaseApiController
{
    private PublicProfileService $profiles;
    private EnquiryService $enquiries;
    private PublicAnalyticsService $analytics;

    public function init(): void
    {
        parent::init();
        $this->profiles = new PublicProfileService();
        $this->enquiries = new EnquiryService();
        $this->analytics = new PublicAnalyticsService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'profile' => ['GET', 'HEAD'],
                    'photo' => ['GET', 'HEAD'],
                    'cover' => ['GET', 'HEAD'],
                    'area-check' => ['GET'],
                    'enquire' => ['POST'],
                    'track' => ['POST'],
                ],
            ],
        ];
    }

    public function actionProfile(string $slug): array
    {
        return $this->profiles->publicBySlug($slug);
    }

    public function actionPhoto(string $slug): Response
    {
        return $this->binaryResponse($this->profiles->photoResponse($slug));
    }

    public function actionCover(string $slug): Response
    {
        return $this->binaryResponse($this->profiles->coverResponse($slug));
    }

    public function actionAreaCheck(string $slug): array
    {
        $postcode = (string) Yii::$app->request->get('postcode', '');

        return $this->enquiries->areaCheck($slug, $postcode);
    }

    public function actionEnquire(string $slug): array
    {
        return $this->enquiries->submitPublic($slug, (array) Yii::$app->request->bodyParams);
    }

    public function actionTrack(string $slug): array
    {
        $org = $this->profiles->findPublishedOrganisation($slug);
        $event = (string) (Yii::$app->request->bodyParams['event'] ?? '');
        $sourceTag = Yii::$app->request->bodyParams['source_tag'] ?? null;
        $this->analytics->track((int) $org->id, $event, is_string($sourceTag) ? $sourceTag : null);

        return ['status' => 'ok'];
    }

    /**
     * @param array{content: string, mime: string} $file
     */
    private function binaryResponse(array $file): Response
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $file['mime']);
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        $response->content = $file['content'];

        return $response;
    }
}
