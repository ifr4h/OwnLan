<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\LessonMessage;
use app\models\Organisation;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

class LessonMessageService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForLesson(int $lessonId): array
    {
        $lesson = $this->findLessonAccessible($lessonId);
        /** @var LessonMessage[] $rows */
        $rows = LessonMessage::find()
            ->andWhere([
                'lesson_id' => (int) $lesson->id,
                'organisation_id' => (int) $lesson->organisation_id,
            ])
            ->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(fn (LessonMessage $row) => $this->toArray($row), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function postAsInstructor(int $lessonId, string $body): array
    {
        $this->requireInstructorOrg();
        $lesson = $this->findLessonOwnedInstructor($lessonId);
        $text = trim($body);
        if ($text === '') {
            throw new BadRequestHttpException('Message cannot be empty.');
        }

        $msg = new LessonMessage();
        $msg->organisation_id = (int) $lesson->organisation_id;
        $msg->lesson_id = (int) $lesson->id;
        $msg->author_role = LessonMessage::ROLE_INSTRUCTOR;
        $msg->author_user_id = (int) Yii::$app->user->id;
        $msg->author_portal_account_id = null;
        $msg->body = mb_substr($text, 0, 4000);
        $msg->created_at = gmdate('Y-m-d H:i:s');
        if (!$msg->save()) {
            throw new BadRequestHttpException('Could not send message.');
        }

        return $this->toArray($msg);
    }

    /**
     * @return array<string, mixed>
     */
    public function postAsLearner(int $lessonId, string $body): array
    {
        $account = PortalContext::requireAccount();
        $lesson = $this->findLessonForPortalLearner($lessonId, (int) $account->learner_id);
        $text = trim($body);
        if ($text === '') {
            throw new BadRequestHttpException('Message cannot be empty.');
        }

        $msg = new LessonMessage();
        $msg->organisation_id = (int) $lesson->organisation_id;
        $msg->lesson_id = (int) $lesson->id;
        $msg->author_role = LessonMessage::ROLE_LEARNER;
        $msg->author_user_id = null;
        $msg->author_portal_account_id = (int) $account->id;
        $msg->body = mb_substr($text, 0, 4000);
        $msg->created_at = gmdate('Y-m-d H:i:s');
        if (!$msg->save()) {
            throw new BadRequestHttpException('Could not send message.');
        }

        return $this->toArray($msg);
    }

    /**
     * @return array{count: int, latest: array<string, mixed>|null}
     */
    public function previewForLesson(int $lessonId): array
    {
        $lesson = Lesson::findOne(['id' => $lessonId]);
        if (!$lesson instanceof Lesson) {
            return ['count' => 0, 'latest' => null];
        }
        $count = (int) LessonMessage::find()
            ->andWhere([
                'lesson_id' => $lessonId,
                'organisation_id' => (int) $lesson->organisation_id,
            ])
            ->count();
        /** @var LessonMessage|null $latest */
        $latest = LessonMessage::find()
            ->andWhere([
                'lesson_id' => $lessonId,
                'organisation_id' => (int) $lesson->organisation_id,
            ])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->one();

        return [
            'count' => $count,
            'latest' => $latest instanceof LessonMessage ? $this->toArray($latest) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(LessonMessage $msg): array
    {
        return [
            'id' => (int) $msg->id,
            'lesson_id' => (int) $msg->lesson_id,
            'author_role' => $msg->author_role,
            'body' => $msg->body,
            'created_at' => $msg->created_at,
        ];
    }

    private function findLessonAccessible(int $lessonId): Lesson
    {
        if (!Yii::$app->user->isGuest) {
            return $this->findLessonOwnedInstructor($lessonId);
        }
        $account = PortalContext::account();
        if ($account === null) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        return $this->findLessonForPortalLearner($lessonId, (int) $account->learner_id);
    }

    private function findLessonOwnedInstructor(int $lessonId): Lesson
    {
        $lesson = TenantContext::scopeByOrganisation(Lesson::find())
            ->andWhere(['id' => $lessonId])
            ->one();
        if (!$lesson instanceof Lesson) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        return $lesson;
    }

    private function findLessonForPortalLearner(int $lessonId, int $learnerId): Lesson
    {
        $lesson = PortalContext::scopeOwnLearner(Lesson::find())
            ->andWhere(['id' => $lessonId])
            ->one();
        if (!$lesson instanceof Lesson || (int) $lesson->learner_id !== $learnerId) {
            throw new NotFoundHttpException('Lesson not found.');
        }

        return $lesson;
    }

    private function requireInstructorOrg(): Organisation
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }
}
