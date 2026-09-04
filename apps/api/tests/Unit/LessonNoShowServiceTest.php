<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\LessonSkill;
use app\models\Organisation;
use app\models\PackageCreditUsage;
use app\services\AuthService;
use app\services\EmptySeatService;
use app\services\FinanceService;
use app\services\LearnerService;
use app\services\LessonService;
use app\services\MorningBriefService;
use app\services\PortalAuthService;
use app\services\PortalHomeService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UnauthorizedHttpException;

class LessonNoShowServiceTest extends Unit
{
    protected UnitTester $tester;

    private LessonService $lessons;
    private LearnerService $learners;
    private FinanceService $finance;
    private Organisation $org;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, lesson_skills, learner_skill_progress, learner_portal_accounts, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        TenantContext::clear();

        $this->lessons = new LessonService();
        $this->learners = new LearnerService();
        $this->finance = new FinanceService();

        (new AuthService())->register([
            'name' => 'No-show Instructor',
            'email' => 'noshow@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->org->default_hourly_rate_pence = 4500;
        $this->org->save(false, ['default_hourly_rate_pence']);
    }

    private function pastStartsAtLocal(string $time = '10:00'): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-1 day')
            ->format('Y-m-d') . ' ' . $time;
    }

    public function testChargedNoShowCreatesOutstandingWithoutProgress(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Sarah',
            'last_name' => 'NoShow',
            'mobile' => '07700904001',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal(),
            'duration_minutes' => 90,
        ]);

        $done = $this->lessons->markNoShow((int) $lesson['id'], [
            'charge' => 'outstanding',
            'client_mutation_id' => 'mut-noshow-1',
        ]);

        $this->assertSame(Lesson::STATUS_NO_SHOW, $done['status']);
        $this->assertNotNull($done['no_show_at']);
        $this->assertNull($done['completed_at']);
        $this->assertSame('outstanding', $done['settlement']);
        $this->assertSame('No-show', $done['status_label']);
        $this->assertSame('£67.50 due', $done['financial_line']);
        $this->assertNull($done['learner_summary']);
        $this->assertSame(0, (int) LessonSkill::find()->count());
        $this->assertSame(1, (int) LessonCharge::find()->count());

        $summary = $this->finance->summaryForLearner((int) $pupil['id']);
        $this->assertTrue($summary['owes_money']);
        $this->assertSame(6750, $summary['amount_owed_pence']);
    }

    public function testWaivedNoShowCreatesNoCharge(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Jack',
            'last_name' => 'Waived',
            'mobile' => '07700904002',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('11:00'),
            'duration_minutes' => 60,
        ]);

        $done = $this->lessons->markNoShow((int) $lesson['id'], [
            'charge' => 'waived',
        ]);

        $this->assertSame(Lesson::STATUS_NO_SHOW, $done['status']);
        $this->assertSame(FinanceService::SETTLEMENT_WAIVED, $done['settlement']);
        $this->assertSame('No charge', $done['financial_line']);
        $this->assertSame(0, (int) LessonCharge::find()->count());
        $this->assertSame(0, (int) PackageCreditUsage::find()->count());

        $summary = $this->finance->summaryForLearner((int) $pupil['id']);
        $this->assertFalse($summary['owes_money']);
    }

    public function testChargedNoShowConsumesPackageCreditOnlyWhenExplicit(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Amina',
            'last_name' => 'Credit',
            'mobile' => '07700904003',
        ]);
        $this->finance->createPackage((int) $pupil['id'], [
            'purchased_hours' => 8,
            'price_pence' => 28000,
            'record_payment' => true,
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('12:00'),
            'duration_minutes' => 120,
        ]);

        $done = $this->lessons->markNoShow((int) $lesson['id'], [
            'charge' => 'package',
        ]);

        $this->assertSame(Lesson::STATUS_NO_SHOW, $done['status']);
        $this->assertSame('package', $done['settlement']);
        $this->assertSame('Charged to block credit', $done['financial_line']);
        $this->assertSame(1, (int) PackageCreditUsage::find()->count());
        $this->assertSame(0, (int) LessonCharge::find()->count());
        $this->assertSame(0, (int) LessonSkill::find()->count());

        $summary = $this->finance->summaryForLearner((int) $pupil['id']);
        $this->assertSame(360, $summary['credit_minutes']);
    }

    public function testNoShowIsIdempotentForSameMutationId(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Idem',
            'last_name' => 'NoShow',
            'mobile' => '07700904004',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('13:00'),
            'duration_minutes' => 60,
        ]);

        $first = $this->lessons->markNoShow((int) $lesson['id'], [
            'charge' => 'outstanding',
            'client_mutation_id' => 'mut-noshow-idem',
        ]);
        $second = $this->lessons->markNoShow((int) $lesson['id'], [
            'charge' => 'outstanding',
            'client_mutation_id' => 'mut-noshow-idem',
        ]);

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, (int) Lesson::find()->where(['status' => Lesson::STATUS_NO_SHOW])->count());
        $this->assertSame(1, (int) LessonCharge::find()->count());
    }

    public function testCannotMarkNoShowBeforeLessonStarts(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Early',
            'last_name' => 'Bird',
            'mobile' => '07700904005',
        ]);
        $future = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 days');
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $future->format('Y-m-d') . ' 10:00',
            'duration_minutes' => 60,
        ]);

        $this->expectException(BadRequestHttpException::class);
        $this->lessons->markNoShow((int) $lesson['id'], ['charge' => 'waived']);
    }

    public function testFutureCancellationStillOffersEmptySeatRecovery(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Future',
            'last_name' => 'Cancel',
            'mobile' => '07700904006',
        ]);
        $future = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+3 days');
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $future->format('Y-m-d') . ' 14:00',
            'duration_minutes' => 60,
        ]);

        $cancelled = $this->lessons->cancel((int) $lesson['id']);
        $this->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status']);
        $this->assertTrue($cancelled['empty_seat']['applicable']);
    }

    public function testPastNoShowDoesNotOfferEmptySeatRecovery(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Past',
            'last_name' => 'Slot',
            'mobile' => '07700904007',
        ]);
        $lessonRow = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('14:00'),
            'duration_minutes' => 60,
        ]);
        $done = $this->lessons->markNoShow((int) $lessonRow['id'], ['charge' => 'waived']);

        $lesson = Lesson::findOne((int) $done['id']);
        $recovery = (new EmptySeatService())->forCancelledLesson($lesson, $this->org);
        $this->assertFalse($recovery['applicable']);
    }

    public function testDiaryMonthTeachingMinutesExcludeNoShow(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Hours',
            'last_name' => 'Check',
            'mobile' => '07700904008',
        ]);
        $pastDay = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('-1 day');
        $pastLocal = $pastDay->format('Y-m-d');
        $completed = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $pastLocal . ' 09:00',
            'duration_minutes' => 60,
        ]);
        $this->lessons->complete((int) $completed['id']);

        $noShow = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $pastLocal . ' 11:00',
            'duration_minutes' => 90,
        ]);
        $this->lessons->markNoShow((int) $noShow['id'], ['charge' => 'waived']);

        $diary = $this->lessons->diary('month', $pastLocal);
        $day = null;
        foreach ($diary['days'] as $d) {
            if ($d['date'] === $pastLocal) {
                $day = $d;
                break;
            }
        }
        $this->assertNotNull($day);
        $this->assertSame(60, $day['teaching_minutes']);
    }

    public function testPortalShowsNeutralNoShowLabel(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Portal',
            'last_name' => 'Learner',
            'mobile' => '07700904009',
            'email' => 'portal.learner@example.com',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('15:00'),
            'duration_minutes' => 60,
        ]);
        $this->lessons->markNoShow((int) $lesson['id'], ['charge' => 'waived']);

        $portal = new PortalAuthService();
        $invite = $portal->inviteForLearner((int) $pupil['id']);
        parse_str((string) parse_url($invite['invite_path'], PHP_URL_QUERY), $query);
        $portal->activate(['token' => $query['token'], 'password' => 'learnerpass1']);

        Yii::$app->user->logout();
        $home = (new PortalHomeService())->home();
        $previous = $home['previous_lessons'][0] ?? null;
        $this->assertNotNull($previous);
        $this->assertSame(Lesson::STATUS_NO_SHOW, $previous['status']);
        $this->assertSame('Not attended', $previous['status_label']);
        $this->assertNull($previous['learner_summary']);
        $this->assertSame([], $previous['skills']);
    }

    public function testCrossTenantNoShowAttemptRejected(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Alpha',
            'last_name' => 'One',
            'mobile' => '07700904010',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('16:00'),
            'duration_minutes' => 60,
        ]);

        Yii::$app->user->logout();
        TenantContext::clear();
        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'other@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(\yii\web\NotFoundHttpException::class);
        $this->lessons->markNoShow((int) $lesson['id'], ['charge' => 'waived']);
    }

    public function testLearnerCannotMarkNoShowViaInstructorApi(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Learner',
            'last_name' => 'Only',
            'mobile' => '07700904011',
            'email' => 'learner.only@example.com',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('17:00'),
            'duration_minutes' => 60,
        ]);

        $portal = new PortalAuthService();
        $invite = $portal->inviteForLearner((int) $pupil['id']);
        parse_str((string) parse_url($invite['invite_path'], PHP_URL_QUERY), $query);
        $portal->activate(['token' => $query['token'], 'password' => 'learnerpass1']);

        Yii::$app->user->logout();

        $this->expectException(UnauthorizedHttpException::class);
        $this->lessons->markNoShow((int) $lesson['id'], ['charge' => 'waived']);
    }

    public function testCancelledLessonWithChargeCreatesOutstanding(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Late',
            'last_name' => 'Cancel',
            'mobile' => '07700904012',
        ]);
        $future = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+4 days');
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $future->format('Y-m-d') . ' 10:00',
            'duration_minutes' => 60,
        ]);

        $cancelled = $this->lessons->cancel((int) $lesson['id'], [
            'charge' => 'outstanding',
        ]);

        $this->assertSame(Lesson::STATUS_CANCELLED, $cancelled['status']);
        $this->assertSame('outstanding', $cancelled['settlement']);
        $this->assertSame(1, (int) LessonCharge::find()->count());
    }

    public function testMorningBriefDoesNotDuplicateNoShowAsEmptySeat(): void
    {
        $pupil = $this->learners->create([
            'first_name' => 'Brief',
            'last_name' => 'Check',
            'mobile' => '07700904013',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => $this->pastStartsAtLocal('18:00'),
            'duration_minutes' => 60,
        ]);
        $this->lessons->markNoShow((int) $lesson['id'], ['charge' => 'outstanding']);

        $needsYou = (new MorningBriefService())->needsYou($this->org);
        $emptySeatIds = array_values(array_filter(
            $needsYou,
            static fn (array $a) => $a['kind'] === 'empty_seat' && (int) ($a['lesson_id'] ?? 0) === (int) $lesson['id'],
        ));
        $this->assertSame([], $emptySeatIds);
    }
}
