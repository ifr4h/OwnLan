<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Lesson;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\services\RecurringLessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;

class RecurringLessonServiceTest extends Unit
{
    protected UnitTester $tester;

    private RecurringLessonService $recurring;
    private LessonService $lessons;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();
        $this->recurring = new RecurringLessonService($this->lessons);
    }

    public function testPreviewAndCreateWeeklyByCount(): void
    {
        $this->register();
        $pupil = $this->learners->create([
            'first_name' => 'Mia',
            'last_name' => 'Patel',
            'mobile' => '07700900123',
            'default_pickup_address' => '12 High Street',
        ]);

        $preview = $this->recurring->preview([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-07 16:00',
            'occurrence_count' => 4,
            'duration_minutes' => 60,
        ]);

        $this->assertSame(4, $preview['count']);
        $this->assertSame(0, $preview['conflict_count']);
        $this->assertSame('2026-09-07T16:00', $preview['occurrences'][0]['starts_at_local']);
        $this->assertSame('2026-09-28T16:00', $preview['occurrences'][3]['starts_at_local']);

        $created = $this->recurring->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-07 16:00',
            'occurrence_count' => 4,
            'duration_minutes' => 60,
        ]);

        $this->assertSame(4, $created['count']);
        $this->assertNotNull($created['series_id']);
        $this->assertSame(4, (int) Lesson::find()->where(['series_id' => $created['series_id']])->count());
        $this->assertNotNull($created['items'][0]['series_id']);
    }

    public function testPreviewUntilDateAndConflicts(): void
    {
        $this->register();
        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Lee',
            'mobile' => '07700900444',
        ]);

        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-14 16:00',
            'duration_minutes' => 60,
        ]);

        $preview = $this->recurring->preview([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-07 16:00',
            'until_date' => '2026-09-21',
        ]);

        $this->assertSame(3, $preview['count']);
        $this->assertSame(1, $preview['conflict_count']);
        $this->assertTrue($preview['occurrences'][1]['has_conflict']);

        $this->expectException(BadRequestHttpException::class);
        $this->recurring->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-07 16:00',
            'until_date' => '2026-09-21',
        ]);
    }

    public function testCancelThisVsThisAndFuture(): void
    {
        $this->register();
        $pupil = $this->learners->create([
            'first_name' => 'Jo',
            'last_name' => 'Kim',
            'mobile' => '07700900555',
        ]);

        $created = $this->recurring->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-10-05 10:00',
            'occurrence_count' => 4,
        ]);

        $secondId = (int) $created['items'][1]['id'];
        $this->recurring->cancelOccurrence($secondId, 'this');
        $this->assertSame(
            3,
            (int) Lesson::find()->where([
                'series_id' => $created['series_id'],
                'status' => Lesson::STATUS_SCHEDULED,
            ])->count(),
        );

        $thirdId = (int) $created['items'][2]['id'];
        $result = $this->recurring->cancelOccurrence($thirdId, 'this_and_future');
        $this->assertSame('this_and_future', $result['scope']);
        $this->assertSame(2, $result['count']);
        $this->assertSame(
            1,
            (int) Lesson::find()->where([
                'series_id' => $created['series_id'],
                'status' => Lesson::STATUS_SCHEDULED,
            ])->count(),
        );
    }

    public function testUpdateThisAndFutureShiftsTimes(): void
    {
        $this->register();
        $pupil = $this->learners->create([
            'first_name' => 'Alex',
            'last_name' => 'Ng',
            'mobile' => '07700900666',
        ]);

        $created = $this->recurring->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-11-02 09:00',
            'occurrence_count' => 3,
            'duration_minutes' => 60,
        ]);

        $firstId = (int) $created['items'][0]['id'];
        $result = $this->recurring->updateOccurrence($firstId, [
            'starts_at_local' => '2026-11-02 10:00',
            'duration_minutes' => 90,
        ], 'this_and_future');

        $this->assertSame(3, $result['count']);
        $this->assertSame('2026-11-02T10:00', $result['items'][0]['starts_at_local']);
        $this->assertSame('2026-11-09T10:00', $result['items'][1]['starts_at_local']);
        $this->assertSame(90, $result['items'][2]['duration_minutes']);
    }

    private function register(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'recur@example.com',
            'password' => 'password123',
        ]);
    }
}
