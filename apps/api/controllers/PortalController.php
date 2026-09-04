<?php

declare(strict_types=1);

namespace app\controllers;

use app\components\CompanionFeature;
use app\components\PortalContext;
use app\models\LessonResource;
use app\services\CompanionService;
use app\services\BookingHoldService;
use app\services\LearnerBookingAvailabilityService;
use app\services\LearnService;
use app\services\LessonBookingRequestService;
use app\services\LessonRouteService;
use app\services\LearnerSelfAssessmentService;
use app\services\MockTestService;
use app\services\OnlinePaymentService;
use app\services\PlaybackService;
use app\services\PasswordResetService;
use app\services\PortalAuthService;
use app\services\PortalHomeService;
use app\services\ProgressService;
use app\services\RouteMomentService;
use Yii;
use yii\filters\VerbFilter;

/**
 * Learner portal API — separate auth cookie from instructor.
 */
class PortalController extends BaseApiController
{
    private PortalAuthService $auth;
    private PasswordResetService $passwordReset;
    private PortalHomeService $home;
    private ProgressService $progress;
    private LessonRouteService $routes;
    private LearnService $learn;
    private RouteMomentService $moments;
    private PlaybackService $playback;
    private CompanionService $companions;
    private MockTestService $mocks;
    private LearnerSelfAssessmentService $selfAssessment;
    private LessonBookingRequestService $booking;
    private LearnerBookingAvailabilityService $bookingAvailability;
    private OnlinePaymentService $onlinePayments;
    private BookingHoldService $bookingHolds;

    public function init(): void
    {
        parent::init();
        $this->auth = new PortalAuthService();
        $this->passwordReset = new PasswordResetService();
        $this->home = new PortalHomeService();
        $this->progress = new ProgressService();
        $this->routes = new LessonRouteService($this->progress);
        $this->learn = new LearnService($this->progress);
        $this->moments = new RouteMomentService();
        $this->playback = new PlaybackService($this->progress);
        $this->companions = new CompanionService();
        $this->mocks = new MockTestService();
        $this->selfAssessment = new LearnerSelfAssessmentService();
        $this->booking = new LessonBookingRequestService();
        $this->bookingAvailability = new LearnerBookingAvailabilityService();
        $this->onlinePayments = new OnlinePaymentService();
        $this->bookingHolds = new BookingHoldService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'peek-invite' => ['GET'],
                    'activate' => ['POST'],
                    'login' => ['POST'],
                    'logout' => ['POST'],
                    'me' => ['GET'],
                    'password-reset-request' => ['POST'],
                    'password-reset-peek' => ['GET'],
                    'password-reset-confirm' => ['POST'],
                    'home' => ['GET'],
                    'journey' => ['GET'],
                    'progress' => ['GET'],
                    'money' => ['GET'],
                    'payments' => ['GET'],
                    'pay-balance' => ['POST'],
                    'pay-package' => ['POST'],
                    'pay-booking-hold' => ['POST'],
                    'pay-confirm' => ['POST'],
                    'booking-hold' => ['POST'],
                    'recap' => ['GET'],
                    'prepare' => ['GET'],
                    'routes' => ['GET'],
                    'route-view' => ['GET'],
                    'learn' => ['GET'],
                    'learn-content' => ['GET'],
                    'skill-evidence' => ['GET'],
                    'mocks' => ['GET'],
                    'mock-view' => ['GET'],
                    'self-assessment' => ['POST'],
                    'practice-list' => ['GET'],
                    'practice-log' => ['POST'],
                    'lesson-resources' => ['GET'],
                    'playback' => ['GET'],
                    'learn-activity' => ['POST'],
                    'companions' => ['GET'],
                    'companion-invite' => ['POST'],
                    'companion-update' => ['PATCH', 'PUT'],
                    'companion-revoke' => ['POST'],
                    'booking-settings' => ['GET'],
                    'booking-availability' => ['GET'],
                    'booking-requests' => ['GET', 'POST'],
                    'booking-withdraw' => ['POST'],
                    'booking-accept-counter' => ['POST'],
                    'lesson-cancel' => ['POST'],
                ],
            ],
        ];
    }

    public function actionPeekInvite(): array
    {
        $token = (string) Yii::$app->request->get('token', '');

        return $this->auth->peekInvite($token);
    }

    public function actionActivate(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->auth->activate((array) Yii::$app->request->bodyParams);
    }

    public function actionLogin(): array
    {
        return $this->auth->login((array) Yii::$app->request->bodyParams);
    }

    public function actionLogout(): array
    {
        $this->auth->logout();

        return ['status' => 'ok'];
    }

    public function actionMe(): array
    {
        return $this->auth->currentPayload();
    }

    public function actionPasswordResetRequest(): array
    {
        return $this->passwordReset->requestPortal((array) Yii::$app->request->bodyParams);
    }

    public function actionPasswordResetPeek(): array
    {
        $token = (string) Yii::$app->request->get('token', '');

        return $this->passwordReset->peek($token, \app\models\PasswordResetToken::TYPE_PORTAL);
    }

    public function actionPasswordResetConfirm(): array
    {
        return $this->passwordReset->confirm(
            \app\models\PasswordResetToken::TYPE_PORTAL,
            (array) Yii::$app->request->bodyParams,
        );
    }

    public function actionHome(): array
    {
        return $this->home->home();
    }

    public function actionJourney(): array
    {
        return $this->playback->journeyExpanded();
    }

    public function actionProgress(): array
    {
        return $this->progress->portalOverview();
    }

    public function actionMoney(): array
    {
        $base = $this->home->money();
        try {
            $base['payments'] = $this->onlinePayments->portalPaymentContext();
        } catch (\Throwable) {
            $base['payments'] = ['online_payments_available' => false];
        }

        return $base;
    }

    public function actionPayments(): array
    {
        return $this->onlinePayments->portalPaymentContext();
    }

    public function actionPayBalance(): array
    {
        $chargeId = Yii::$app->request->bodyParams['lesson_charge_id'] ?? null;

        return $this->onlinePayments->startOutstandingCheckout(
            $chargeId !== null ? (int) $chargeId : null,
        );
    }

    public function actionPayPackage(): array
    {
        $offeringId = (int) (Yii::$app->request->bodyParams['package_offering_id'] ?? 0);

        return $this->onlinePayments->startPackageCheckout($offeringId);
    }

    public function actionPayBookingHold(): array
    {
        $holdId = (int) (Yii::$app->request->bodyParams['hold_id'] ?? 0);

        return $this->onlinePayments->startBookingHoldCheckout($holdId);
    }

    public function actionPayConfirm(string $token): array
    {
        return $this->onlinePayments->confirmCheckout($token);
    }

    public function actionBookingHold(): array
    {
        return $this->bookingHolds->createForPortal((array) Yii::$app->request->bodyParams);
    }

    public function actionRecap(int $id): array
    {
        return $this->home->recap($id);
    }

    public function actionPrepare(int $id): array
    {
        return $this->home->prepare($id);
    }

    public function actionRoutes(): array
    {
        return ['items' => $this->routes->portalList()];
    }

    public function actionRouteView(int $id): array
    {
        return $this->routes->portalView($id);
    }

    public function actionLearn(): array
    {
        return $this->learn->portalHome();
    }

    public function actionLearnContent(string $slug): array
    {
        return $this->learn->content($slug);
    }

    public function actionSkillEvidence(string $code): array
    {
        return $this->learn->skillEvidence($code);
    }

    public function actionMocks(): array
    {
        $account = PortalContext::requireAccount();

        return $this->mocks->listForLearner((int) $account->learner_id);
    }

    public function actionMockView(int $id): array
    {
        return $this->mocks->view($id, learnerVisible: true);
    }

    public function actionSelfAssessment(string $code): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->selfAssessment->recordForLearnerPortal($code, (array) Yii::$app->request->bodyParams);
    }

    public function actionPracticeList(): array
    {
        return ['items' => $this->learn->portalPracticeList()];
    }

    public function actionPracticeLog(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->learn->logPrivatePractice((array) Yii::$app->request->bodyParams);
    }

    public function actionLessonResources(int $id): array
    {
        $recap = $this->home->recap($id);
        $account = PortalContext::requireAccount();
        $rows = LessonResource::find()
            ->andWhere([
                'lesson_id' => $id,
                'organisation_id' => (int) $account->organisation_id,
                'learner_id' => (int) $account->learner_id,
                'learner_visible' => true,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        $resources = array_map(static function (LessonResource $r) {
            try {
                $scene = json_decode($r->scene_json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $scene = [];
            }

            return [
                'id' => (int) $r->id,
                'title' => $r->title,
                'note' => $r->learner_visible_note,
                'skill_codes' => $r->skillCodes(),
                'scene' => is_array($scene) ? $scene : [],
                'route_moment_id' => $r->route_moment_id !== null ? (int) $r->route_moment_id : null,
            ];
        }, $rows);
        $recap['resources'] = $resources;

        return $recap;
    }

    public function actionPlayback(int $id): array
    {
        return $this->playback->forPortalLesson($id);
    }

    public function actionLearnActivity(): array
    {
        Yii::$app->response->statusCode = 201;

        return $this->playback->recordActivity((array) Yii::$app->request->bodyParams);
    }

    public function actionCompanions(): array
    {
        CompanionFeature::requireEnabled();

        return ['items' => $this->companions->listForLearner()];
    }

    public function actionCompanionInvite(): array
    {
        CompanionFeature::requireEnabled();
        Yii::$app->response->statusCode = 201;

        return $this->companions->inviteFromLearner((array) Yii::$app->request->bodyParams);
    }

    public function actionCompanionUpdate(int $id): array
    {
        CompanionFeature::requireEnabled();

        return $this->companions->updatePermissions($id, (array) Yii::$app->request->bodyParams);
    }

    public function actionCompanionRevoke(int $id): array
    {
        CompanionFeature::requireEnabled();

        return $this->companions->revoke($id);
    }

    public function actionBookingSettings(): array
    {
        return $this->booking->portalSettings();
    }

    public function actionBookingAvailability(): array
    {
        return $this->bookingAvailability->forPortalLearner((array) Yii::$app->request->get());
    }

    public function actionBookingRequests(): array
    {
        if (Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 201;

            return $this->booking->createFromPortal((array) Yii::$app->request->bodyParams);
        }

        return ['items' => $this->booking->listForPortal()];
    }

    public function actionBookingWithdraw(int $id): array
    {
        return $this->booking->withdrawFromPortal($id);
    }

    public function actionBookingAcceptCounter(int $id): array
    {
        return $this->booking->acceptCounterFromPortal($id);
    }

    public function actionLessonCancel(int $id): array
    {
        return $this->booking->cancelLessonFromPortal($id, (array) Yii::$app->request->bodyParams);
    }
}
