<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\BookingSuggestionService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\NotFoundHttpException;

class BookingSuggestionServiceTest extends Unit
{
    protected UnitTester $tester;

    private BookingSuggestionService $suggestions;
    private LessonService $lessons;
    private LearnerService $learners;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lessons, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->suggestions = new BookingSuggestionService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Suggest Instructor',
            'email' => 'suggest@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
    }

    public function testNoHistoryFallsBackToDefaults(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'New',
            'last_name' => 'Pupil',
            'mobile' => '07700900001',
            'default_pickup_address' => '1 Default Road',
        ]);

        $after = OrganisationTime::localToUtc('2026-09-01 12:00', $this->org);
        $suggestion = $this->suggestions->suggestForLearner((int) $pupil['id'], $after);

        $this->assertSame(0, $suggestion['history_count']);
        $this->assertSame(60, $suggestion['duration_minutes']);
        $this->assertSame('default', $suggestion['duration_source']);
        $this->assertSame('1 Default Road', $suggestion['pickup_address']);
        $this->assertSame('learner_default', $suggestion['pickup_source']);
        $this->assertNull($suggestion['suggested_starts_at_local']);
        $this->assertSame('none', $suggestion['schedule_source']);
    }

    public function testInconsistentHistoryDoesNotForcePattern(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Mix',
            'last_name' => 'Pupil',
            'mobile' => '07700900002',
            'default_pickup_address' => 'Home Base',
        ]);
        $id = (int) $pupil['id'];

        // Varied weekdays, times, durations, pickups — no clear mode.
        $this->book($id, '2026-08-03 09:00', 60, 'Alpha Road');   // Mon
        $this->book($id, '2026-08-05 11:00', 90, 'Beta Road');    // Wed
        $this->book($id, '2026-08-07 14:00', 120, 'Gamma Road');  // Fri
        $this->book($id, '2026-08-10 16:00', 60, 'Delta Road');   // Mon
        $this->book($id, '2026-08-12 10:00', 90, 'Epsilon Road'); // Wed

        $after = OrganisationTime::localToUtc('2026-09-01 12:00', $this->org);
        $suggestion = $this->suggestions->suggestForLearner($id, $after);

        $this->assertSame(5, $suggestion['history_count']);
        $this->assertSame(60, $suggestion['duration_minutes']);
        $this->assertSame('default', $suggestion['duration_source']);
        $this->assertSame('Home Base', $suggestion['pickup_address']);
        $this->assertSame('learner_default', $suggestion['pickup_source']);
        $this->assertNull($suggestion['suggested_starts_at_local']);
        $this->assertSame('none', $suggestion['schedule_source']);
    }

    public function testClearRepeatedPatternPrefillsDurationPickupAndNextSlot(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Regular',
            'last_name' => 'Pupil',
            'mobile' => '07700900003',
            'default_pickup_address' => 'Profile Address',
        ]);
        $id = (int) $pupil['id'];

        // Three Wednesdays at 16:00, 2 hours, same pickup — clear pattern.
        $this->book($id, '2026-08-05 16:00', 120, 'College Gate'); // Wed
        $this->book($id, '2026-08-12 16:00', 120, 'College Gate'); // Wed
        $this->book($id, '2026-08-19 16:00', 120, 'College Gate'); // Wed

        // After Tue 1 Sep 2026 → next Wednesday is 2 Sep 2026 16:00
        $after = OrganisationTime::localToUtc('2026-09-01 12:00', $this->org);
        $suggestion = $this->suggestions->suggestForLearner($id, $after);

        $this->assertSame(3, $suggestion['history_count']);
        $this->assertSame(120, $suggestion['duration_minutes']);
        $this->assertSame('history', $suggestion['duration_source']);
        $this->assertSame('College Gate', $suggestion['pickup_address']);
        $this->assertSame('history', $suggestion['pickup_source']);
        $this->assertSame('pattern', $suggestion['schedule_source']);
        $this->assertSame('2026-09-02T16:00', $suggestion['suggested_starts_at_local']);
        $this->assertSame('Wednesday', $suggestion['schedule_pattern']['weekday']);
        $this->assertSame('16:00', $suggestion['schedule_pattern']['time']);
    }

    public function testTenantIsolation(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Owned',
            'last_name' => 'Pupil',
            'mobile' => '07700900004',
        ]);

        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'other-suggest@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(NotFoundHttpException::class);
        $this->suggestions->suggestForLearner((int) $pupil['id']);
    }

    private function book(int $learnerId, string $local, int $duration, string $pickup): void
    {
        $this->lessons->create([
            'learner_id' => $learnerId,
            'starts_at_local' => $local,
            'duration_minutes' => $duration,
            'pickup_address' => $pickup,
        ]);
    }
}
