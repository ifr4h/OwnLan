<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\components\TheoryCertificate;
use app\models\Learner;
use app\models\LearnerAvailability;
use app\models\LearnerIntake;
use app\models\Organisation;
use app\services\AuthService;
use app\services\IntakeService;
use app\services\LearnerService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class IntakeServiceTest extends Unit
{
    protected UnitTester $tester;

    private IntakeService $intake;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, learner_portal_accounts, lessons, lesson_series, learner_availability, learner_intakes, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();

        $this->intake = new IntakeService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Intake Instructor',
            'email' => 'intake-instructor@example.com',
            'password' => 'password123',
        ]);
    }

    public function testCreatePeekSubmitAcceptFlow(): void
    {
        $invite = $this->intake->createInvite([
            'first_name' => 'Amina',
            'mobile' => '07700900111',
        ]);
        $this->assertSame('open', $invite['status']);
        $this->assertStringContainsString('/join?token=', $invite['invite_path']);
        $token = $this->tokenFromPath($invite['invite_path']);

        $peek = $this->intake->peekPublic($token);
        $this->assertSame('open', $peek['status']);
        $this->assertSame('Amina', $peek['prefill']['first_name']);
        $this->assertArrayHasKey('junctions', $peek['skill_groups']);

        $this->intake->submitPublic($token, $this->beginnerAnswers('Amina', 'Hassan', '07700900111'));

        try {
            $this->intake->peekPublic($token);
            $this->fail('Duplicate peek after submit should fail');
        } catch (BadRequestHttpException $e) {
            $this->assertStringContainsString('already', mb_strtolower($e->getMessage()));
        }

        try {
            $this->intake->submitPublic($token, $this->beginnerAnswers('Amina', 'Hassan', '07700900111'));
            $this->fail('Duplicate submit should fail');
        } catch (BadRequestHttpException) {
            // expected
        }

        $items = $this->intake->listForInstructor('submitted');
        $this->assertCount(1, $items);
        $id = (int) $items[0]['id'];

        $brief = $this->intake->review($id);
        $this->assertSame('Amina Hassan', $brief['headline']['full_name']);
        $this->assertSame('beginner', $brief['experience']['level']);
        $this->assertSame([], $brief['skills_learner_says']);
        $this->assertTrue($brief['actions']['can_accept']);

        $result = $this->intake->accept($id);
        $this->assertSame(Learner::LIFECYCLE_ACTIVE, $result['lifecycle']);

        $learner = Learner::findOne((int) $result['learner_id']);
        $this->assertNotNull($learner);
        $this->assertSame('Amina', $learner->first_name);
        $this->assertSame('automatic', $learner->transmission);
        $this->assertNotNull($learner->learner_reported_json);
        $reported = json_decode((string) $learner->learner_reported_json, true);
        $this->assertSame('learner_supplied', $reported['provenance']);
        $this->assertSame('learner_intake', $reported['source']);
    }

    public function testBeginnerBranchStripsExperienceFollowUps(): void
    {
        $invite = $this->intake->createInvite([]);
        $token = $this->tokenFromPath($invite['invite_path']);

        $answers = $this->beginnerAnswers('Sam', 'Lee', '07700900222');
        $answers['driving']['lesson_hours_band'] = '40_plus';
        $answers['driving']['skills_practised'] = ['junctions', 'roundabouts'];
        $answers['driving']['last_drove'] = 'over_a_year';

        $this->intake->submitPublic($token, $answers);
        $brief = $this->intake->review((int) $invite['id']);

        $this->assertSame('beginner', $brief['experience']['level']);
        $this->assertSame([], $brief['skills_learner_says']);
        $this->assertSame([], $brief['attention']);
    }

    public function testExperiencedLearnerBriefAndAttentionFlags(): void
    {
        $invite = $this->intake->createInvite([]);
        $token = $this->tokenFromPath($invite['invite_path']);

        $passDate = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-22 months')
            ->format('Y-m-d');
        $practical = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+21 days')
            ->format('Y-m-d');

        $this->intake->submitPublic($token, [
            'identity' => [
                'first_name' => 'Jordan',
                'last_name' => 'Patel',
                'mobile' => '07700900333',
                'email' => 'jordan@example.com',
                'default_pickup_address' => 'MK4',
            ],
            'driving' => [
                'had_lessons_before' => true,
                'driven_before' => 'both',
                'transmission' => 'manual',
                'lesson_hours_band' => '20_40',
                'last_drove' => 'over_6_months',
                'skills_practised' => ['junctions', 'roundabouts', 'manoeuvres'],
                'remember_working_on' => 'Clutch control',
            ],
            'tests' => [
                'theory_status' => 'passed',
                'theory_pass_date' => $passDate,
                'practical_booked' => true,
                'practical_date' => $practical,
                'practical_time' => '09:30',
                'practical_centre' => 'Milton Keynes',
            ],
            'availability' => [
                'changes_often' => false,
                'days' => [
                    ['weekday' => 2, 'slots' => ['evening']],
                    ['weekday' => 4, 'slots' => ['after_4']],
                    ['weekday' => 6, 'slots' => ['flexible']],
                ],
            ],
            'about' => [
                'goal' => 'pass_test',
                'instructor_should_know' => 'Switching instructors',
                'confidence' => 'a_little',
                'private_practice' => 'sometimes',
            ],
            'terms_acknowledged' => true,
        ]);

        $brief = $this->intake->review((int) $invite['id']);
        $this->assertSame('experienced', $brief['experience']['level']);
        $this->assertContains('Junctions', $brief['skills_learner_says']);
        $this->assertTrue($brief['practical']['booked']);
        $this->assertContains('Tue evening', $brief['availability']['labels']);

        $codes = array_column($brief['attention'], 'code');
        $this->assertContains('practical_soon', $codes);
        $this->assertContains('long_break', $codes);
        $this->assertContains('significant_experience', $codes);

        $result = $this->intake->accept((int) $invite['id']);
        $learner = Learner::findOne((int) $result['learner_id']);
        $this->assertSame('passed', $learner->theory_status);
        $this->assertSame($passDate, $learner->theory_pass_date);
        $this->assertSame($practical, $learner->test_date);
        $this->assertSame('09:30', $learner->practical_test_time);

        $windows = LearnerAvailability::find()->andWhere(['learner_id' => $learner->id])->count();
        $this->assertGreaterThanOrEqual(3, $windows);
    }

    public function testWaitlistThenPromoteToActive(): void
    {
        $invite = $this->intake->createInvite([]);
        $token = $this->tokenFromPath($invite['invite_path']);
        $this->intake->submitPublic($token, $this->beginnerAnswers('Wait', 'List', '07700900444'));

        $wait = $this->intake->addToWaitingList((int) $invite['id']);
        $this->assertSame(Learner::LIFECYCLE_WAITING, $wait['lifecycle']);

        $waiting = $this->learners->listWaiting();
        $this->assertCount(1, $waiting);
        $active = $this->learners->listActive();
        $this->assertCount(0, $active);

        $accept = $this->intake->accept((int) $invite['id']);
        $this->assertSame(Learner::LIFECYCLE_ACTIVE, $accept['lifecycle']);
        $this->assertSame((int) $wait['learner_id'], (int) $accept['learner_id']);
    }

    public function testRevokeAndExpireBlockPublicAccess(): void
    {
        $invite = $this->intake->createInvite([]);
        $token = $this->tokenFromPath($invite['invite_path']);
        $this->intake->revoke((int) $invite['id']);

        try {
            $this->intake->peekPublic($token);
            $this->fail('Revoked link should be blocked');
        } catch (ForbiddenHttpException) {
            // expected
        }

        $invite2 = $this->intake->createInvite([]);
        $token2 = $this->tokenFromPath($invite2['invite_path']);
        $row = LearnerIntake::findOne((int) $invite2['id']);
        $row->invite_expires_at = gmdate('Y-m-d H:i:s', time() - 3600);
        $row->save(false, ['invite_expires_at']);

        try {
            $this->intake->peekPublic($token2);
            $this->fail('Expired link should be blocked');
        } catch (BadRequestHttpException $e) {
            $this->assertStringContainsString('expired', mb_strtolower($e->getMessage()));
        }
    }

    public function testTenantIsolationOnReview(): void
    {
        $invite = $this->intake->createInvite([]);
        $token = $this->tokenFromPath($invite['invite_path']);
        $this->intake->submitPublic($token, $this->beginnerAnswers('Iso', 'Late', '07700900555'));
        $intakeId = (int) $invite['id'];

        Yii::$app->user->logout();
        TenantContext::clear();
        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'other-intake@example.com',
            'password' => 'password123',
        ]);

        try {
            $this->intake->review($intakeId);
            $this->fail('Cross-organisation review must fail');
        } catch (NotFoundHttpException) {
            // expected
        }

        try {
            $this->intake->accept($intakeId);
            $this->fail('Cross-organisation accept must fail');
        } catch (NotFoundHttpException) {
            // expected
        }
    }

    public function testSkipHeavyOptionalsStillAccepts(): void
    {
        $invite = $this->intake->createInvite([]);
        $token = $this->tokenFromPath($invite['invite_path']);
        $this->intake->submitPublic($token, [
            'identity' => [
                'first_name' => 'Min',
                'last_name' => 'Skip',
                'mobile' => '07700900666',
            ],
            'driving' => [
                'had_lessons_before' => false,
            ],
            'tests' => [],
            'availability' => ['changes_often' => true, 'days' => []],
            'about' => [],
        ]);

        $brief = $this->intake->review((int) $invite['id']);
        $this->assertTrue($brief['availability']['changes_often']);
        $result = $this->intake->accept((int) $invite['id']);
        $learner = Learner::findOne((int) $result['learner_id']);
        $this->assertTrue((bool) $learner->availability_is_variable);
        $this->assertNull($learner->theory_status);
    }

    public function testTheoryCertificateDerivesTwoYearExpiry(): void
    {
        $expiry = TheoryCertificate::expiryDate('2025-03-15');
        $this->assertSame('2027-03-15', $expiry?->format('Y-m-d'));

        $payload = TheoryCertificate::statusPayload(
            'passed',
            '2025-03-15',
            new \DateTimeImmutable('2025-04-01', new \DateTimeZone('UTC')),
        );
        $this->assertSame('ok', $payload['urgency']);
        $this->assertStringContainsString('valid until', $payload['label']);
    }

    /**
     * @return array<string, mixed>
     */
    private function beginnerAnswers(string $first, string $last, string $mobile): array
    {
        return [
            'identity' => [
                'first_name' => $first,
                'last_name' => $last,
                'mobile' => $mobile,
                'email' => null,
                'default_pickup_address' => null,
            ],
            'driving' => [
                'had_lessons_before' => false,
                'driven_before' => 'no',
                'transmission' => 'automatic',
            ],
            'tests' => [
                'theory_status' => 'not_yet',
                'practical_booked' => false,
            ],
            'availability' => [
                'changes_often' => false,
                'days' => [
                    ['weekday' => 3, 'slots' => ['afternoon']],
                ],
            ],
            'about' => [
                'goal' => 'learn_to_drive',
                'confidence' => 'okay',
            ],
            'terms_acknowledged' => false,
        ];
    }

    private function tokenFromPath(string $path): string
    {
        parse_str(parse_url($path, PHP_URL_QUERY) ?: '', $query);
        $token = $query['token'] ?? '';
        $this->assertNotSame('', $token);

        return $token;
    }
}
