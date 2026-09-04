<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Learner;
use app\models\Lesson;
use app\services\AuthService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class LessonCompleteServiceTest extends Unit
{
    protected UnitTester $tester;

    private LessonService $lessons;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Complete Instructor',
            'email' => 'complete@example.com',
            'password' => 'password123',
        ]);
    }

    public function testCompleteStoresNotesAndUpdatesPupilContext(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Lee',
            'mobile' => '07700900111',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-01 10:00',
        ]);

        $done = $this->lessons->complete((int) $lesson['id'], [
            'client_mutation_id' => 'mut-complete-1',
            'instructor_notes' => 'Private: clutch control shaky',
            'learner_summary' => 'Good progress on mirrors',
            'next_focus' => 'Roundabouts',
        ]);

        $this->assertSame(Lesson::STATUS_COMPLETED, $done['status']);
        $this->assertSame('Private: clutch control shaky', $done['instructor_notes']);
        $this->assertSame('Good progress on mirrors', $done['learner_summary']);
        $this->assertSame('Roundabouts', $done['next_focus']);
        $this->assertSame('mut-complete-1', $done['client_mutation_id']);
        $this->assertNotNull($done['completed_at']);

        $learner = Learner::findOne(['id' => $pupil['id']]);
        $this->assertSame('Roundabouts', $learner->next_focus);
        $this->assertSame('Good progress on mirrors', $learner->last_lesson_summary);
    }

    public function testCompleteIsIdempotentForSameMutationId(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Idem',
            'last_name' => 'Potent',
            'mobile' => '07700900112',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-02 11:00',
        ]);

        $first = $this->lessons->complete((int) $lesson['id'], [
            'client_mutation_id' => 'mut-retry',
            'instructor_notes' => 'Keep this note',
            'next_focus' => 'Parking',
        ]);
        $second = $this->lessons->complete((int) $lesson['id'], [
            'client_mutation_id' => 'mut-retry',
            'instructor_notes' => 'Should not overwrite via replay',
            'next_focus' => 'Something else',
        ]);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('Keep this note', $second['instructor_notes']);
        $this->assertSame('Parking', $second['next_focus']);
        $this->assertSame(1, (int) Lesson::find()->where(['status' => Lesson::STATUS_COMPLETED])->count());
    }

    public function testPrivateNotesRemainDistinctFromLearnerSummary(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Vis',
            'last_name' => 'ible',
            'mobile' => '07700900113',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-03 12:00',
        ]);

        $done = $this->lessons->complete((int) $lesson['id'], [
            'instructor_notes' => 'Parent concern — keep private',
            'learner_summary' => 'Practised bay parking',
        ]);

        $this->assertSame('Parent concern — keep private', $done['instructor_notes']);
        $this->assertSame('Practised bay parking', $done['learner_summary']);
        $this->assertNotSame($done['instructor_notes'], $done['learner_summary']);
    }
}
