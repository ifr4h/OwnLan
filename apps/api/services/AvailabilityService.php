<?php

declare(strict_types=1);

namespace app\services;

use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerAvailability;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Optional broad pupil availability windows — not detailed calendars.
 */
class AvailabilityService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForLearner(int $learnerId): array
    {
        $this->findLearnerOwned($learnerId);

        /** @var LearnerAvailability[] $rows */
        $rows = TenantContext::scopeByOrganisation(LearnerAvailability::find())
            ->andWhere(['learner_id' => $learnerId])
            ->orderBy(['weekday' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(static fn (LearnerAvailability $row) => $row->toPublicArray(), $rows);
    }

    /**
     * Full replace of availability windows for a pupil.
     *
     * @param list<array<string, mixed>> $windows
     * @return list<array<string, mixed>>
     */
    public function replaceForLearner(int $learnerId, array $windows): array
    {
        $learner = $this->findLearnerOwned($learnerId);
        $orgId = TenantContext::requireOrganisationId();
        $now = gmdate('Y-m-d H:i:s');

        $normalized = [];
        foreach ($windows as $index => $window) {
            if (!is_array($window)) {
                throw new BadRequestHttpException('Each availability entry must be an object.');
            }
            $normalized[] = $this->normalizeWindow($window, $index);
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            LearnerAvailability::deleteAll([
                'organisation_id' => $orgId,
                'learner_id' => (int) $learner->id,
            ]);

            $saved = [];
            foreach ($normalized as $window) {
                $row = new LearnerAvailability();
                $row->organisation_id = $orgId;
                $row->learner_id = (int) $learner->id;
                $row->weekday = $window['weekday'];
                $row->mode = $window['mode'];
                $row->start_time = $window['start_time'];
                $row->end_time = $window['end_time'];
                $row->created_at = $now;
                $row->updated_at = $now;
                if (!$row->save()) {
                    $errors = $row->getFirstErrors();
                    throw new BadRequestHttpException($errors ? (string) reset($errors) : 'Unable to save availability.');
                }
                $saved[] = $row->toPublicArray();
            }

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return $saved;
    }

    /**
     * @param array<string, mixed> $window
     * @return array{weekday: int, mode: string, start_time: string|null, end_time: string|null}
     */
    private function normalizeWindow(array $window, int $index): array
    {
        $label = 'Availability #' . ($index + 1);
        $weekday = (int) ($window['weekday'] ?? 0);
        if ($weekday < 1 || $weekday > 7) {
            throw new BadRequestHttpException($label . ': choose a day of the week.');
        }

        $mode = strtolower(trim((string) ($window['mode'] ?? '')));
        if (!in_array($mode, LearnerAvailability::modes(), true)) {
            throw new BadRequestHttpException($label . ': choose flexible, after, before, or between.');
        }

        $start = $this->normalizeTime($window['start_time'] ?? null);
        $end = $this->normalizeTime($window['end_time'] ?? null);

        if ($mode === LearnerAvailability::MODE_FLEXIBLE) {
            $start = null;
            $end = null;
        } elseif ($mode === LearnerAvailability::MODE_AFTER) {
            if ($start === null) {
                throw new BadRequestHttpException($label . ': set a time for “after”.');
            }
            $end = null;
        } elseif ($mode === LearnerAvailability::MODE_BEFORE) {
            if ($end === null) {
                // allow start_time as the "before" bound for quick UI
                if ($start !== null) {
                    $end = $start;
                    $start = null;
                } else {
                    throw new BadRequestHttpException($label . ': set a time for “before”.');
                }
            } else {
                $start = null;
            }
        } elseif ($mode === LearnerAvailability::MODE_BETWEEN) {
            if ($start === null || $end === null) {
                throw new BadRequestHttpException($label . ': set start and end times.');
            }
            if ($start >= $end) {
                throw new BadRequestHttpException($label . ': end time must be after start time.');
            }
        }

        return [
            'weekday' => $weekday,
            'mode' => $mode,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    private function normalizeTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = trim((string) $value);
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $text) !== 1) {
            throw new BadRequestHttpException('Times must be HH:MM.');
        }
        $parts = explode(':', $text);
        $h = (int) $parts[0];
        $m = (int) $parts[1];
        if ($h > 23 || $m > 59) {
            throw new BadRequestHttpException('Invalid time.');
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    private function findLearnerOwned(int $learnerId): Learner
    {
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId])
            ->one();
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }
}
