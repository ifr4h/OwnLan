<?php

declare(strict_types=1);

namespace app\services;

use app\models\Instructor;
use app\models\Learner;
use app\models\Lesson;
use app\models\Organisation;

/**
 * Progressive first-value guidance — derived from real data, never a forced wizard.
 */
class OnboardingService
{
    /**
     * @return array<string, mixed>
     */
    public function statusFor(?Organisation $organisation, ?Instructor $instructor): array
    {
        if ($organisation === null) {
            return [
                'stage' => 'setup',
                'next_step' => 'complete_account',
                'pupil_count' => 0,
                'lesson_count' => 0,
                'upcoming_lesson_count' => 0,
                'suggest_business_confirm' => false,
                'checklist' => [],
            ];
        }

        $orgId = (int) $organisation->id;
        $pupilCount = (int) Learner::find()
            ->andWhere(['organisation_id' => $orgId, 'archived_at' => null])
            ->count();

        $lessonCount = (int) Lesson::find()
            ->andWhere(['organisation_id' => $orgId])
            ->andWhere(['!=', 'status', Lesson::STATUS_CANCELLED])
            ->count();

        $now = gmdate('Y-m-d H:i:s');
        $upcomingCount = (int) Lesson::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'status' => Lesson::STATUS_SCHEDULED,
            ])
            ->andWhere(['>=', 'starts_at', $now])
            ->count();

        $stage = 'done';
        $next = 'explore';
        if ($pupilCount === 0) {
            $stage = 'add_pupils';
            $next = 'add_or_import_pupils';
        } elseif ($lessonCount === 0) {
            $stage = 'book_lesson';
            $next = 'book_first_lesson';
        } elseif ($upcomingCount === 0) {
            $stage = 'use_today';
            $next = 'book_or_review_today';
        } else {
            $stage = 'done';
            $next = 'teach_today';
        }

        $suggestBusiness = $lessonCount === 0
            && $this->looksLikeDefaultBusinessName($organisation, $instructor);

        return [
            'stage' => $stage,
            'next_step' => $next,
            'pupil_count' => $pupilCount,
            'lesson_count' => $lessonCount,
            'upcoming_lesson_count' => $upcomingCount,
            'suggest_business_confirm' => $suggestBusiness,
            'business_name' => $organisation->name,
            'display_name' => $instructor?->display_name,
            'checklist' => [
                [
                    'id' => 'account',
                    'label' => 'Create account',
                    'done' => true,
                ],
                [
                    'id' => 'pupils',
                    'label' => 'Add or import pupils',
                    'done' => $pupilCount > 0,
                ],
                [
                    'id' => 'lesson',
                    'label' => 'Book a lesson',
                    'done' => $lessonCount > 0,
                ],
                [
                    'id' => 'today',
                    'label' => 'Use Today when you teach',
                    'done' => $lessonCount > 0,
                ],
            ],
        ];
    }

    private function looksLikeDefaultBusinessName(Organisation $organisation, ?Instructor $instructor): bool
    {
        $name = trim($organisation->name);
        if ($name === '' || strcasecmp($name, 'My driving school') === 0) {
            return true;
        }
        if ($instructor === null) {
            return false;
        }
        $first = trim(explode(' ', $instructor->display_name)[0] ?? '');
        if ($first === '') {
            return false;
        }

        return strcasecmp($name, "{$first}'s driving school") === 0;
    }
}
