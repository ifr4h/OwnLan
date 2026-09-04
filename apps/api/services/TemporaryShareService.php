<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\models\LessonResource;
use app\models\LessonRoute;
use app\models\LearningContent;
use app\models\TemporaryShare;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Temporary one-resource sharing — not ongoing Companion access.
 */
class TemporaryShareService
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $account = PortalContext::requireAccount();
        $type = (string) ($data['resource_type'] ?? '');
        $resourceId = (int) ($data['resource_id'] ?? 0);
        if (!in_array($type, [
            TemporaryShare::TYPE_ROUTE,
            TemporaryShare::TYPE_LESSON_RESOURCE,
            TemporaryShare::TYPE_CONTENT,
            TemporaryShare::TYPE_PRACTICE_PLAN,
        ], true) || $resourceId < 1) {
            throw new BadRequestHttpException('Choose what to share.');
        }

        $this->assertOwnsResource(
            (int) $account->organisation_id,
            (int) $account->learner_id,
            $type,
            $resourceId,
        );

        $raw = Yii::$app->security->generateRandomString(48);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $row = new TemporaryShare();
        $row->organisation_id = (int) $account->organisation_id;
        $row->learner_id = (int) $account->learner_id;
        $row->resource_type = $type;
        $row->resource_id = $resourceId;
        $row->token_hash = hash('sha256', $raw);
        $row->expires_at = $now->modify('+7 days')->format('Y-m-d H:i:s');
        $row->created_at = $now->format('Y-m-d H:i:s');
        $row->save(false);

        return [
            'path' => '/share/' . urlencode($raw),
            'expires_at' => $row->expires_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function peek(string $token): array
    {
        if ($token === '') {
            throw new BadRequestHttpException('Share link required.');
        }
        $row = TemporaryShare::findOne(['token_hash' => hash('sha256', $token)]);
        if ($row === null || !$row->isValid()) {
            throw new NotFoundHttpException('This share link is no longer available.');
        }

        $payload = $this->minimalPayload($row);

        return [
            'resource_type' => $row->resource_type,
            'expires_at' => $row->expires_at,
            'resource' => $payload,
            'note' => 'Temporary share — not full account access.',
        ];
    }

    public function revoke(int $id): array
    {
        $account = PortalContext::requireAccount();
        $row = TemporaryShare::findOne([
            'id' => $id,
            'organisation_id' => (int) $account->organisation_id,
            'learner_id' => (int) $account->learner_id,
        ]);
        if ($row === null) {
            throw new NotFoundHttpException('Share not found.');
        }
        $row->revoked_at = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $row->save(false, ['revoked_at']);

        return ['status' => 'ok'];
    }

    private function assertOwnsResource(int $orgId, int $learnerId, string $type, int $resourceId): void
    {
        $ok = match ($type) {
            TemporaryShare::TYPE_ROUTE => LessonRoute::find()
                ->andWhere([
                    'id' => $resourceId,
                    'organisation_id' => $orgId,
                    'learner_id' => $learnerId,
                    'learner_visible' => true,
                    'deleted_at' => null,
                ])
                ->exists(),
            TemporaryShare::TYPE_LESSON_RESOURCE => LessonResource::find()
                ->andWhere([
                    'id' => $resourceId,
                    'organisation_id' => $orgId,
                    'learner_id' => $learnerId,
                    'learner_visible' => true,
                ])
                ->exists(),
            TemporaryShare::TYPE_CONTENT => LearningContent::find()
                ->andWhere(['id' => $resourceId, 'status' => LearningContent::STATUS_PUBLISHED])
                ->exists(),
            TemporaryShare::TYPE_PRACTICE_PLAN => true,
            default => false,
        };
        if (!$ok) {
            throw new NotFoundHttpException('Resource not found.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalPayload(TemporaryShare $row): array
    {
        return match ($row->resource_type) {
            TemporaryShare::TYPE_ROUTE => (static function () use ($row) {
                $route = LessonRoute::findOne([
                    'id' => (int) $row->resource_id,
                    'learner_visible' => true,
                    'deleted_at' => null,
                ]);
                if ($route === null) {
                    return ['title' => 'Route unavailable'];
                }

                return [
                    'title' => $route->label ?: 'Lesson route',
                    'distance_metres' => $route->distance_metres !== null ? (int) $route->distance_metres : null,
                    'duration_seconds' => $route->duration_seconds !== null ? (int) $route->duration_seconds : null,
                    // Intentionally omit full polyline / home addresses.
                ];
            })(),
            TemporaryShare::TYPE_LESSON_RESOURCE => (static function () use ($row) {
                $res = LessonResource::findOne([
                    'id' => (int) $row->resource_id,
                    'learner_visible' => true,
                ]);
                if ($res === null) {
                    return ['title' => 'Explanation unavailable'];
                }

                return [
                    'title' => $res->title,
                    'note' => $res->learner_visible_note,
                ];
            })(),
            TemporaryShare::TYPE_CONTENT => (static function () use ($row) {
                $c = LearningContent::findOne([
                    'id' => (int) $row->resource_id,
                    'status' => LearningContent::STATUS_PUBLISHED,
                ]);

                return [
                    'title' => $c?->title ?? 'Learning resource',
                    'summary' => $c?->summary,
                    'slug' => $c?->slug,
                ];
            })(),
            default => ['title' => 'Shared item'],
        };
    }
}
