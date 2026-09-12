<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Organisation;
use app\models\ProgressSkill;
use app\services\AuthService;
use app\services\DayWrapService;
use app\services\LearnerService;
use app\services\LessonService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Yii;

class DayWrapServiceTest extends Unit
{
    protected UnitTester $tester;

    private DayWrapService $wrap;
    private LessonService $lessons;
    private LearnerService $learners;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, lesson_skills, learner_skill_progress, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->wrap = new DayWrapService();
        $this->lessons = new LessonService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Wrap Instructor',
            'email' => 'daywrap@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->org->default_hourly_rate_pence = 4000;
        $this->org->save(false, ['default_hourly_rate_pence']);
    }

    public function testEmptyDay(): void
    {
        $result = $this->wrap->forDay($this->fixedNow('2026-09-01 18:00:00'));

        $this->assertTrue($result['empty']);
        $this->assertFalse($result['day_complete']);
        $this->assertSame('No lessons today', $result['headline']['lessons_line']);
        $this->assertSame([], $result['lessons']);
        $this->assertSame([], $result['stats']);
        $this->assertSame(0, $result['totals']['open_admin_count']);
    }

    public function testCompletedWithoutProgressNeedsAdmin(): void
    {
        $pupil = $this->createPupil('Sam');
        $lesson = $this->book($pupil['id'], '2026-09-01 09:00', 60);
        $this->lessons->complete((int) $lesson['id'], [
            'learner_summary' => 'Worked on junctions',
            'settlement' => 'charge',
        ]);

        $result = $this->wrap->forDay($this->fixedNow('2026-09-01 18:00:00'));

        $this->assertFalse($result['empty']);
        $this->assertSame(1, $result['totals']['completed_count']);
        $this->assertSame(60, $result['totals']['teaching_minutes']);
        $this->assertGreaterThan(0, $result['totals']['open_admin_count']);

        $row = $result['lessons'][0];
        $this->assertFalse($row['checks']['progress']);
        $this->assertTrue($row['checks']['notes']);
        $this->assertSame('Update progress', $row['cta']['label']);
        $this->assertStringContainsString('Progress not updated', $row['detail_line']);
    }

    public function testCompletedWithSkillsMarksProgressDone(): void
    {
        $pupil = $this->createPupil('Alex');
        $skill = ProgressSkill::findOne(['code' => 'roundabouts']);
        $this->assertNotNull($skill);

        $lesson = $this->book($pupil['id'], '2026-09-01 10:00', 90);
        $this->lessons->complete((int) $lesson['id'], [
            'instructor_notes' => 'Good lane discipline',
            'skill_ids' => [(int) $skill->id],
            'skill_ratings' => [(int) $skill->id => 'practising'],
            'settlement' => 'charge',
        ]);

        // Book next so money is the remaining admin item (or none if we ignore money for this assert)
        $this->book($pupil['id'], '2026-09-08 10:00', 90);

        $result = $this->wrap->forDay($this->fixedNow('2026-09-01 18:00:00'));
        $row = $result['lessons'][0];

        $this->assertTrue($row['checks']['progress']);
        $this->assertTrue($row['checks']['notes']);
        $this->assertTrue($row['checks']['next_booked']);
        $this->assertFalse($row['checks']['money_ok']);
        $this->assertSame('Record payment', $row['cta']['label']);
    }

    public function testOverdueScheduledIsStillOpen(): void
    {
        $pupil = $this->createPupil('Over');
        $this->book($pupil['id'], '2026-09-01 09:00', 60);

        $result = $this->wrap->forDay($this->fixedNow('2026-09-01 18:00:00'));

        $this->assertSame(1, $result['totals']['still_open_count']);
        $row = $result['lessons'][0];
        $this->assertTrue($row['checks']['still_open']);
        $this->assertTrue($row['checks']['overdue']);
        $this->assertSame('Still to complete', $row['status_label']);
        $this->assertSame('Open lesson', $row['cta']['label']);
    }

    public function testPackageSettlementIsMoneyOkAndNextCta(): void
    {
        $pupil = $this->createPupil('Pack');
        $finance = new \app\services\FinanceService();
        $finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 10,
            'price_pence' => 35000,
            'record_payment' => true,
            'payment_method' => 'bank_transfer',
        ]);

        $lesson = $this->book($pupil['id'], '2026-09-01 11:00', 60);
        $skill = ProgressSkill::findOne(['code' => 'roundabouts']);
        $this->assertNotNull($skill);
        $this->lessons->complete((int) $lesson['id'], [
            'skill_ids' => [(int) $skill->id],
            'skill_ratings' => [(int) $skill->id => 'developing'],
        ]);

        $result = $this->wrap->forDay($this->fixedNow('2026-09-01 19:00:00'));
        $row = $result['lessons'][0];

        $this->assertTrue($row['checks']['progress']);
        $this->assertTrue($row['checks']['money_ok']);
        $this->assertFalse($row['checks']['next_booked']);
        $this->assertSame('Book next', $row['cta']['label']);
        $this->assertStringContainsString('nothing left to collect', (string) $result['headline']['money_line']);
        $this->assertTrue($result['day_complete']);
        $this->assertSame('Teaching day done', $result['celebration']['title']);
        $this->assertNotEmpty($result['stats']);
        $this->assertSame('1', $result['stats'][0]['value']); // Done
    }

    public function testCancelledLessonsExcludedFromWrap(): void
    {
        $pupil = $this->createPupil('Skip');
        $lesson = $this->book($pupil['id'], '2026-09-01 14:00', 60);
        $this->lessons->cancel((int) $lesson['id']);

        $result = $this->wrap->forDay($this->fixedNow('2026-09-01 18:00:00'));

        $this->assertTrue($result['empty']);
        $this->assertSame([], $result['lessons']);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPupil(string $first): array
    {
        return $this->learners->create([
            'first_name' => $first,
            'last_name' => 'Pupil',
            'mobile' => '07700' . str_pad((string) random_int(100000, 999999), 6, '0'),
            'default_pickup_address' => $first . ' Road',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function book(int $learnerId, string $localYmdHi, int $duration): array
    {
        return $this->lessons->create([
            'learner_id' => $learnerId,
            'starts_at_local' => $localYmdHi,
            'duration_minutes' => $duration,
        ]);
    }

    private function fixedNow(string $localYmdHis): DateTimeImmutable
    {
        return OrganisationTime::localToUtc($localYmdHis, $this->org);
    }
}
