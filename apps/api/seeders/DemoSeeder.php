<?php

declare(strict_types=1);

namespace app\seeders;

use app\components\EncodedPolyline;
use app\components\OrganisationTime;
use app\models\CompanionAccount;
use app\models\Enquiry;
use app\models\Expense;
use app\models\FinancialGoal;
use app\models\Vehicle;
use app\models\Instructor;
use app\models\Learner;
use app\models\LearnerAvailability;
use app\models\LearnerCompanion;
use app\models\LearnerIntake;
use app\models\LearnerPackage;
use app\models\LearnerPortalAccount;
use app\models\LearnerSkillProgress;
use app\models\LearnerSkillSelfAssessment;
use app\models\MockTest;
use app\models\MockTestFault;
use app\models\LearningActivity;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\LessonResource;
use app\models\LessonRoute;
use app\models\LessonSkill;
use app\models\Membership;
use app\models\Organisation;
use app\models\PackageCreditUsage;
use app\models\Payment;
use app\models\PaymentAllocation;
use app\models\PrivatePracticeSession;
use app\models\ProgressSkill;
use app\models\RouteMoment;
use app\models\TeachingResource;
use app\models\User;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\base\Exception;

/**
 * Realistic UK solo-instructor demo dataset for product walkthroughs.
 */
final class DemoSeeder
{
    public const DEMO_EMAIL = 'instructor';
    public const DEMO_PASSWORD = 'instructor';
    public const DEMO_NAME = 'Amina Yusuf';
    public const DEMO_ORG = 'Amina Yusuf Driving Tuition';

    /** Activated learner portal showcase login (Sarah Ahmed pupil). */
    public const PORTAL_EMAIL = 'learner';
    public const PORTAL_PASSWORD = 'learner';
    public const PORTAL_LEARNER_CONTACT_EMAIL = 'sarah.ahmed@example.com';

    private bool $fresh;
    private string $email;
    private string $password;
    private string $name;
    private string $orgName;
    private bool $passwordReset;

    /** @var array<string, int> */
    private array $counts = [
        'pupils' => 0,
        'waiting' => 0,
        'lessons' => 0,
        'packages' => 0,
        'payments' => 0,
        'expenses' => 0,
        'intakes' => 0,
        'availability' => 0,
        'skill_ratings' => 0,
        'routes' => 0,
        'portal_accounts' => 0,
        'teaching_resources' => 0,
        'route_moments' => 0,
        'lesson_resources' => 0,
        'private_practice' => 0,
        'companions' => 0,
        'learning_activities' => 0,
    ];

    private ?string $sampleIntakePath = null;

    private ?string $sampleProfilePath = null;
    private ?string $portalLoginEmail = null;

    /**
     * @param array{
     *   email?: string,
     *   password?: string,
     *   name?: string,
     *   organisation?: string,
     *   reset_password?: bool
     * } $options
     */
    public function __construct(bool $fresh = false, array $options = [])
    {
        $this->fresh = $fresh;
        $this->email = mb_strtolower(trim((string) ($options['email'] ?? self::DEMO_EMAIL)));
        $this->password = (string) ($options['password'] ?? self::DEMO_PASSWORD);
        $this->name = trim((string) ($options['name'] ?? self::DEMO_NAME));
        $this->orgName = trim((string) ($options['organisation'] ?? self::DEMO_ORG));
        $this->passwordReset = !empty($options['reset_password']);
    }

    /**
     * @return array{
     *   email: string,
     *   password: string|null,
     *   password_note: string,
     *   organisation: string,
     *   counts: array<string, int>,
     *   sample_intake_path: string|null,
     *   portal_email: string|null,
     *   portal_password: string|null
     * }
     * @throws Exception
     */
    public function run(): array
    {
        $passwordForLogin = null;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if ($this->fresh) {
                $this->wipeTargetAccountData();
            }

            $createdUser = User::findByEmail($this->email) === null;
            [$user, $org, $instructor, $passwordForLogin] = $this->ensureInstructor($createdUser);
            $this->configureOrganisation($org);

            $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $tz = OrganisationTime::timezoneFor($org);
            $todayLocal = $nowUtc->setTimezone($tz)->setTime(0, 0, 0);

            // Always refresh operational data for this org.
            if (!$this->fresh || !$createdUser) {
                $this->wipeOrganisationDataOnly((int) $org->id);
            }

            $pupils = $this->seedPupils($org, $instructor, $user, $todayLocal, $nowUtc);
            $this->enrichShowcaseLearner($org, $pupils, $todayLocal);
            $this->seedDenseCalendarDay($org, $instructor, $pupils, $todayLocal);
            $this->seedWaitingList($org, $todayLocal);
            $this->seedIntakes($org, $instructor, $todayLocal);
            $this->seedPublicProfileAndEnquiry($org, $instructor);
            $this->seedExpenses($org, $user, $todayLocal);
            $this->seedAccountsDemo($org, $user, $todayLocal);

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        return [
            'email' => $this->email,
            'password' => $passwordForLogin,
            'password_note' => $passwordForLogin !== null
                ? 'Use the password below.'
                : 'Existing account — your current password is unchanged.',
            'organisation' => $this->orgName !== '' ? $this->orgName : self::DEMO_ORG,
            'counts' => $this->counts,
            'sample_intake_path' => $this->sampleIntakePath,
            'sample_profile_path' => $this->sampleProfilePath,
            'portal_email' => $this->portalLoginEmail,
            'portal_password' => $this->portalLoginEmail !== null ? self::PORTAL_PASSWORD : null,
            'companions' => $this->portalLoginEmail !== null ? [
                [
                    'email' => 'fatima.hassan@example.com',
                    'password' => 'companionpass1',
                    'role' => 'Mum — Lessons + Payments + Test',
                ],
                [
                    'email' => 'yusuf.hassan@example.com',
                    'password' => 'companionpass1',
                    'role' => 'Brother — Practice + Learn + Routes',
                ],
            ] : [],
        ];
    }

    /**
     * Wipe org data for the target account. Only delete the user row for the
     * disposable default demo login.
     */
    private function wipeTargetAccountData(): void
    {
        // Migrate away from the old `demo` login id.
        if ($this->email === self::DEMO_EMAIL) {
            $legacy = User::findByEmail('demo');
            if ($legacy !== null && mb_strtolower((string) $legacy->email) !== self::DEMO_EMAIL) {
                $memberships = Membership::find()->andWhere(['user_id' => (int) $legacy->id])->all();
                foreach ($memberships as $membership) {
                    $this->wipeOrganisation((int) $membership->organisation_id);
                }
                Yii::$app->db->createCommand()
                    ->delete('{{%users}}', ['id' => (int) $legacy->id])
                    ->execute();
            }
        }

        $user = User::findByEmail($this->email);
        if ($user === null) {
            return;
        }

        $memberships = Membership::find()->andWhere(['user_id' => (int) $user->id])->all();
        foreach ($memberships as $membership) {
            $this->wipeOrganisation((int) $membership->organisation_id);
        }

        if ($this->email === self::DEMO_EMAIL) {
            Yii::$app->db->createCommand()
                ->delete('{{%users}}', ['id' => (int) $user->id])
                ->execute();
        }
    }

    private function wipeOrganisation(int $orgId): void
    {
        $db = Yii::$app->db;
        foreach ([
            '{{%payment_allocations}}',
            '{{%package_credit_usages}}',
            '{{%lesson_charges}}',
            '{{%payments}}',
            '{{%learner_packages}}',
            '{{%expenses}}',
            '{{%lesson_resources}}',
            '{{%route_moments}}',
            '{{%learning_activities}}',
            '{{%temporary_shares}}',
            '{{%learner_companions}}',
            '{{%private_practice_sessions}}',
            '{{%teaching_resources}}',
            '{{%lesson_routes}}',
            '{{%lesson_skills}}',
            '{{%learner_skill_progress}}',
            '{{%learner_portal_accounts}}',
            '{{%lessons}}',
            '{{%lesson_series}}',
            '{{%learner_availability}}',
            '{{%learner_intakes}}',
            '{{%learners}}',
            '{{%instructors}}',
            '{{%memberships}}',
        ] as $table) {
            $db->createCommand()->delete($table, ['organisation_id' => $orgId])->execute();
        }
        // Companion accounts may be shared across orgs in theory; wipe demo-linked ones via orphan cleanup.
        $db->createCommand(
            'DELETE FROM {{%companion_accounts}} WHERE id NOT IN (SELECT companion_account_id FROM {{%learner_companions}})',
        )->execute();
        $db->createCommand()->delete('{{%organisations}}', ['id' => $orgId])->execute();
    }

    /**
     * @return array{0: User, 1: Organisation, 2: Instructor, 3: string|null}
     * @throws Exception
     */
    private function ensureInstructor(bool $wasMissing): array
    {
        $existing = User::findByEmail($this->email);
        $passwordForLogin = null;

        if ($existing !== null) {
            if ($this->passwordReset || $wasMissing) {
                $existing->setPassword($this->password);
                $existing->updated_at = gmdate('Y-m-d H:i:s');
                $existing->save(false, ['password_hash', 'updated_at']);
                $passwordForLogin = $this->password;
            }

            if ($this->name !== '' && $this->name !== self::DEMO_NAME) {
                $existing->name = $this->name;
                $existing->updated_at = gmdate('Y-m-d H:i:s');
                $existing->save(false, ['name', 'updated_at']);
            }

            /** @var Membership|null $membership */
            $membership = Membership::find()->andWhere(['user_id' => (int) $existing->id])->one();
            if ($membership !== null) {
                $org = Organisation::findOne((int) $membership->organisation_id);
                $instructor = Instructor::find()
                    ->andWhere(['organisation_id' => (int) $org->id, 'user_id' => (int) $existing->id])
                    ->one();
                if ($org === null || $instructor === null) {
                    throw new Exception('Account incomplete — try --fresh=1');
                }
                if ($this->name !== '' && $this->name !== self::DEMO_NAME) {
                    $instructor->display_name = $this->name;
                    $instructor->updated_at = gmdate('Y-m-d H:i:s');
                    $instructor->save(false, ['display_name', 'updated_at']);
                }

                return [$existing, $org, $instructor, $passwordForLogin];
            }

            // User exists but org was wiped — rebuild tenancy shell.
            [$org, $instructor] = $this->createTenancyShell($existing);

            return [$existing, $org, $instructor, $passwordForLogin ?? $this->password];
        }

        $now = gmdate('Y-m-d H:i:s');
        $user = new User();
        $user->email = $this->email;
        $user->name = $this->name !== '' ? $this->name : self::DEMO_NAME;
        $user->setPassword($this->password);
        $user->generateAuthKey();
        $user->created_at = $now;
        $user->updated_at = $now;
        $this->mustSave($user);

        [$org, $instructor] = $this->createTenancyShell($user);

        return [$user, $org, $instructor, $this->password];
    }

    /**
     * @return array{0: Organisation, 1: Instructor}
     */
    private function createTenancyShell(User $user): array
    {
        $now = gmdate('Y-m-d H:i:s');

        $org = new Organisation();
        $org->name = $this->orgName !== '' ? $this->orgName : self::DEMO_ORG;
        $org->timezone = Organisation::DEFAULT_TIMEZONE;
        $org->default_hourly_rate_pence = 3800;
        $org->default_lesson_duration_minutes = 60;
        $org->contact_email = $this->publicContactEmail();
        $org->contact_phone = '07700900100';
        $org->work_days = json_encode([1, 2, 3, 4, 5, 6], JSON_THROW_ON_ERROR);
        $org->work_start_time = '08:00';
        $org->work_end_time = '19:00';
        $org->created_at = $now;
        $org->updated_at = $now;
        $this->mustSave($org);

        $membership = new Membership();
        $membership->user_id = (int) $user->id;
        $membership->organisation_id = (int) $org->id;
        $membership->role = Membership::ROLE_OWNER;
        $membership->created_at = $now;
        $this->mustSave($membership);

        $instructor = new Instructor();
        $instructor->organisation_id = (int) $org->id;
        $instructor->user_id = (int) $user->id;
        $instructor->display_name = $user->name;
        $instructor->created_at = $now;
        $instructor->updated_at = $now;
        $this->mustSave($instructor);

        return [$org, $instructor];
    }

    private function wipeOrganisationDataOnly(int $orgId): void
    {
        $db = Yii::$app->db;
        foreach ([
            '{{%payment_allocations}}',
            '{{%package_credit_usages}}',
            '{{%lesson_charges}}',
            '{{%payments}}',
            '{{%learner_packages}}',
            '{{%mileage_logs}}',
            '{{%financial_goals}}',
            '{{%vehicles}}',
            '{{%expenses}}',
            '{{%lesson_resources}}',
            '{{%route_moments}}',
            '{{%learning_activities}}',
            '{{%temporary_shares}}',
            '{{%learner_companions}}',
            '{{%private_practice_sessions}}',
            '{{%teaching_resources}}',
            '{{%lesson_routes}}',
            '{{%lesson_skills}}',
            '{{%learner_skill_progress}}',
            '{{%learner_portal_accounts}}',
            '{{%lessons}}',
            '{{%lesson_series}}',
            '{{%learner_availability}}',
            '{{%learner_intakes}}',
            '{{%enquiries}}',
            '{{%learners}}',
        ] as $table) {
            $db->createCommand()->delete($table, ['organisation_id' => $orgId])->execute();
        }
    }

    /**
     * Activate Sarah's portal account (learner / learner) and attach rich skill + route history.
     *
     * @param list<Learner> $pupils
     */
    private function enrichShowcaseLearner(
        Organisation $org,
        array $pupils,
        DateTimeImmutable $todayLocal,
    ): void {
        $amina = null;
        foreach ($pupils as $learner) {
            if (mb_strtolower((string) $learner->email) === self::PORTAL_LEARNER_CONTACT_EMAIL) {
                $amina = $learner;
                break;
            }
        }
        if ($amina === null) {
            return;
        }

        $this->activatePortalAccount($org, $amina);
        $this->portalLoginEmail = self::PORTAL_EMAIL;

        /** @var Lesson[] $completed */
        $completed = Lesson::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'learner_id' => (int) $amina->id,
                'status' => Lesson::STATUS_COMPLETED,
            ])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        if ($completed === []) {
            return;
        }

        $skillsByCode = [];
        foreach (ProgressSkill::find()->andWhere(['active' => true])->all() as $skill) {
            $skillsByCode[$skill->code] = $skill;
        }
        if ($skillsByCode === []) {
            return;
        }

        $lessonSkillPlan = [
            ['moving_off', 'stopping', 'mirrors'],
            ['mirrors', 'signals', 't_junctions'],
            ['t_junctions', 'roundabouts'],
            ['roundabouts', 'meeting'],
            ['roundabouts', 'dual_carriageways'],
            ['bay_park', 'parallel_park'],
            ['dual_carriageways', 'hazards'],
            ['roundabouts', 'lane_choice'],
            ['follow_directions', 'decision_making'],
            ['sat_nav', 'lane_choice', 'roundabouts'],
            ['meeting', 'speed', 'positioning'],
            ['roundabouts', 'dual_carriageways', 'lane_choice'],
            ['sat_nav', 'follow_directions'],
            ['roundabouts', 'lane_choice', 'decision_making'],
            ['dual_carriageways', 'hazards', 'lane_choice'],
            ['sat_nav', 'roundabouts', 'decision_making'],
        ];

        $ratingProgressions = [
            'roundabouts' => ['introduced', 'practising', 'developing', 'developing', 'confident'],
            'dual_carriageways' => ['introduced', 'practising', 'developing'],
            'bay_park' => ['introduced', 'practising', 'developing'],
            'lane_choice' => ['introduced', 'practising', 'developing'],
            'sat_nav' => ['introduced', 'practising'],
            'mirrors' => ['practising', 'developing', 'confident'],
            't_junctions' => ['practising', 'developing', 'confident'],
            'moving_off' => ['developing', 'confident'],
            'decision_making' => ['introduced', 'practising', 'developing'],
        ];

        $ratingCursor = [];
        foreach ($completed as $index => $lesson) {
            $codes = $lessonSkillPlan[$index % count($lessonSkillPlan)];
            foreach ($codes as $code) {
                if (!isset($skillsByCode[$code])) {
                    continue;
                }
                $skill = $skillsByCode[$code];
                $this->attachLessonSkill($org, $lesson, $skill);

                if (isset($ratingProgressions[$code])) {
                    $step = $ratingCursor[$code] ?? 0;
                    $ratings = $ratingProgressions[$code];
                    $rating = $ratings[min($step, count($ratings) - 1)];
                    $ratingCursor[$code] = $step + 1;
                    $this->recordSkillRating($org, $amina, $lesson, $skill, $rating);
                }
            }
        }

        // Seed 4 learner-visible routes on the most recent completed lessons (MK area geometry).
        $routeLessons = array_slice(array_reverse($completed), 0, 4);
        $geometries = [
            $this->mkRouteBletchleyLoop(),
            $this->mkRouteCentral(),
            $this->mkRouteA5(),
            $this->mkRouteWestcroft(),
        ];
        $routes = [];
        foreach ($routeLessons as $i => $lesson) {
            $points = $geometries[$i] ?? $geometries[0];
            $routes[] = $this->seedLessonRoute($org, $amina, $lesson, $points, true);
        }

        $master = $this->seedRoundaboutMaster($org);
        if ($routes !== []) {
            $route = $routes[0];
            $lesson = Lesson::findOne((int) $route->lesson_id);
            if ($lesson instanceof Lesson) {
                $moment = $this->seedRouteMoment(
                    $org,
                    $route,
                    $lesson,
                    52.0061,
                    -0.7155,
                    22 * 60,
                    RouteMoment::KIND_ROUNDABOUT,
                    'Kingston Roundabout',
                    'Remember your lane position approaching the third exit.',
                    true,
                );
                $this->seedLessonResourceFromMaster($org, $amina, $lesson, $master, $moment);
            }
            if (isset($routes[1])) {
                $l2 = Lesson::findOne((int) $routes[1]->lesson_id);
                if ($l2 instanceof Lesson) {
                    $this->seedRouteMoment(
                        $org,
                        $routes[1],
                        $l2,
                        52.0365,
                        -0.7422,
                        40 * 60,
                        RouteMoment::KIND_EXPLAIN,
                        'Central MK merge',
                        'Check blind spot before joining the faster lane.',
                        true,
                    );
                }
            }
        }

        $this->seedPrivatePractice($org, $amina, $todayLocal);
        $this->seedMockHistory($org, $amina, $completed, $skillsByCode);
        $this->seedCompanionsAndActivity($org, $amina, $todayLocal);
    }

    /**
     * @param list<Lesson> $completedLessons
     * @param array<string, ProgressSkill> $skillsByCode
     */
    private function seedMockHistory(
        Organisation $org,
        Learner $learner,
        array $completedLessons,
        array $skillsByCode,
    ): void {
        if ($completedLessons === []) {
            return;
        }

        $instructor = Instructor::findOne(['organisation_id' => (int) $org->id]);
        if ($instructor === null) {
            return;
        }

        $lesson = $completedLessons[count($completedLessons) - 1];
        $plans = [
            ['date' => '-21 days', 'driving' => 8, 'serious' => 2, 'dangerous' => 0, 'result' => MockTest::RESULT_NOT_PASS],
            ['date' => '-9 days', 'driving' => 6, 'serious' => 1, 'dangerous' => 0, 'result' => MockTest::RESULT_NOT_PASS],
            ['date' => '-2 days', 'driving' => 4, 'serious' => 0, 'dangerous' => 0, 'result' => MockTest::RESULT_PASS],
        ];

        $roundabouts = $skillsByCode['roundabouts'] ?? null;
        $mirrors = $skillsByCode['mirrors'] ?? null;

        foreach ($plans as $plan) {
            $started = gmdate('Y-m-d H:i:s', strtotime($plan['date']));
            $finished = gmdate('Y-m-d H:i:s', strtotime($plan['date']) + 38 * 60);
            $mock = new MockTest();
            $mock->organisation_id = (int) $org->id;
            $mock->instructor_id = (int) $instructor->id;
            $mock->learner_id = (int) $learner->id;
            $mock->lesson_id = (int) $lesson->id;
            $mock->status = MockTest::STATUS_COMPLETED;
            $mock->result = $plan['result'];
            $mock->started_at = $started;
            $mock->finished_at = $finished;
            $mock->driving_faults_count = $plan['driving'];
            $mock->serious_faults_count = $plan['serious'];
            $mock->dangerous_faults_count = $plan['dangerous'];
            $mock->learner_summary = $plan['driving'] . ' driving faults';
            if ($plan['result'] === MockTest::RESULT_PASS) {
                $mock->instructor_note = 'Much smoother on roundabouts today.';
                $mock->suggested_next_focus = 'Junctions · Observation';
            }
            $mock->created_at = $finished;
            $mock->updated_at = $finished;
            $this->mustSave($mock);

            if ($roundabouts !== null) {
                for ($i = 0; $i < min(3, $plan['driving']); $i++) {
                    $this->seedMockFault($org, $mock, $roundabouts, MockTestFault::TYPE_DRIVING, 'roundabout_observation', 'Roundabouts · Observation', $started, $i * 300);
                }
            }
            if ($mirrors !== null && $plan['serious'] > 0) {
                $this->seedMockFault($org, $mock, $mirrors, MockTestFault::TYPE_SERIOUS, 'mirrors_before_change', 'Mirrors · Before changing direction', $started, 900);
            }
        }

        if (isset($skillsByCode['roundabouts'])) {
            $self = new LearnerSkillSelfAssessment();
            $self->organisation_id = (int) $org->id;
            $self->learner_id = (int) $learner->id;
            $self->skill_id = (int) $skillsByCode['roundabouts']->id;
            $self->confidence = LearnerSkillSelfAssessment::CONFIDENCE_STILL_PRACTISING;
            $self->note = 'Large roundabouts still feel busy';
            $self->recorded_at = gmdate('Y-m-d H:i:s');
            $self->created_at = gmdate('Y-m-d H:i:s');
            $this->mustSave($self);
        }
    }

    private function seedMockFault(
        Organisation $org,
        MockTest $mock,
        ProgressSkill $skill,
        string $type,
        string $code,
        string $label,
        string $startedAt,
        int $offsetSeconds,
    ): void {
        $recorded = gmdate('Y-m-d H:i:s', strtotime($startedAt) + $offsetSeconds);
        $fault = new MockTestFault();
        $fault->organisation_id = (int) $org->id;
        $fault->mock_test_id = (int) $mock->id;
        $fault->skill_id = (int) $skill->id;
        $fault->fault_type = $type;
        $fault->fault_code = $code;
        $fault->fault_label = $label;
        $fault->recorded_at = $recorded;
        $fault->elapsed_seconds = $offsetSeconds;
        $fault->created_at = $recorded;
        $this->mustSave($fault);
    }

    private function seedCompanionsAndActivity(
        Organisation $org,
        Learner $learner,
        DateTimeImmutable $todayLocal,
    ): void {
        $now = gmdate('Y-m-d H:i:s');

        // Companion 1: Mum — lessons + payments (not progress/practice)
        $mum = new CompanionAccount();
        $mum->email = 'fatima.hassan@example.com';
        $mum->name = 'Fatima Hassan';
        $mum->setPassword('companionpass1');
        $mum->generateAuthKey();
        $mum->activated_at = $now;
        $mum->created_at = $now;
        $mum->updated_at = $now;
        $this->mustSave($mum);

        $mumLink = new LearnerCompanion();
        $mumLink->organisation_id = (int) $org->id;
        $mumLink->learner_id = (int) $learner->id;
        $mumLink->companion_account_id = (int) $mum->id;
        $mumLink->display_name = 'Fatima';
        $mumLink->relationship_label = 'Mum';
        $mumLink->permissions_json = json_encode([
            'lessons' => true,
            'money' => true,
            'progress' => false,
            'learn' => false,
            'routes' => false,
            'test' => true,
            'practice' => false,
            'booking' => false,
        ], JSON_THROW_ON_ERROR);
        $mumLink->invited_by = 'learner';
        $mumLink->created_at = $now;
        $mumLink->updated_at = $now;
        $this->mustSave($mumLink);
        $this->counts['companions']++;

        // Companion 2: Brother — private practice + learning resources
        $bro = new CompanionAccount();
        $bro->email = 'yusuf.hassan@example.com';
        $bro->name = 'Yusuf Hassan';
        $bro->setPassword('companionpass1');
        $bro->generateAuthKey();
        $bro->activated_at = $now;
        $bro->created_at = $now;
        $bro->updated_at = $now;
        $this->mustSave($bro);

        $broLink = new LearnerCompanion();
        $broLink->organisation_id = (int) $org->id;
        $broLink->learner_id = (int) $learner->id;
        $broLink->companion_account_id = (int) $bro->id;
        $broLink->display_name = 'Yusuf';
        $broLink->relationship_label = 'Brother';
        $broLink->permissions_json = json_encode([
            'lessons' => false,
            'money' => false,
            'progress' => false,
            'learn' => true,
            'routes' => true,
            'test' => false,
            'practice' => true,
            'booking' => false,
        ], JSON_THROW_ON_ERROR);
        $broLink->invited_by = 'learner';
        $broLink->created_at = $now;
        $broLink->updated_at = $now;
        $this->mustSave($broLink);
        $this->counts['companions']++;

        // Companion note on most recent private practice
        $latestPractice = PrivatePracticeSession::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'learner_id' => (int) $learner->id,
            ])
            ->orderBy(['practised_at' => SORT_DESC])
            ->one();
        if ($latestPractice instanceof PrivatePracticeSession) {
            $latestPractice->companion_note = 'Much smoother approaching the larger roundabout.';
            $latestPractice->companion_account_id = (int) $bro->id;
            $latestPractice->note = 'Felt more confident on the third exit.';
            $latestPractice->updated_at = $now;
            $latestPractice->save(false);
        }

        // Third private practice for density
        $extraWhen = $todayLocal->sub(new DateInterval('P1D'))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
        $extra = new PrivatePracticeSession();
        $extra->organisation_id = (int) $org->id;
        $extra->learner_id = (int) $learner->id;
        $extra->practised_at = $extraWhen;
        $extra->duration_minutes = 50;
        $extra->skill_codes_json = json_encode(['roundabouts', 'lane_choice'], JSON_THROW_ON_ERROR);
        $extra->feeling = 'comfortable';
        $extra->note = null;
        $extra->created_at = $extraWhen;
        $extra->updated_at = $extraWhen;
        $this->mustSave($extra);
        $this->counts['private_practice']++;

        // Learning activities (never touch instructor ratings)
        foreach ([
            ['interactive-third-exit', LearningActivity::TYPE_OPENED],
            ['interactive-third-exit', LearningActivity::TYPE_SCENARIO],
            ['interactive-traffic-lights', LearningActivity::TYPE_OPENED],
            ['interactive-give-way', LearningActivity::TYPE_QUICK_REVIEW],
        ] as [$slug, $type]) {
            $act = new LearningActivity();
            $act->organisation_id = (int) $org->id;
            $act->learner_id = (int) $learner->id;
            $act->content_slug = $slug;
            $act->activity_type = $type;
            $act->result_json = $type === LearningActivity::TYPE_SCENARIO
                ? json_encode(['correct_steps' => 2], JSON_THROW_ON_ERROR)
                : null;
            $act->created_at = $now;
            $act->save(false);
            $this->counts['learning_activities']++;
        }
    }

    private function seedRoundaboutMaster(Organisation $org): TeachingResource
    {
        $now = gmdate('Y-m-d H:i:s');
        $scene = [
            'template' => 'multi_roundabout',
            'version' => 1,
            'objects' => [
                ['id' => 'car-1', 'kind' => 'learner_car', 'x' => 420, 'y' => 780, 'rotation' => -20, 'scale' => 1],
                ['id' => 'car-2', 'kind' => 'other_car', 'x' => 620, 'y' => 480, 'rotation' => 90, 'scale' => 1],
                ['id' => 'arrow-1', 'kind' => 'arrow', 'x' => 450, 'y' => 550, 'rotation' => -40, 'scale' => 1],
            ],
            'strokes' => [],
            'steps' => [
                ['id' => 's1', 'label' => 'Approach', 'objects' => [], 'strokes' => [], 'note' => 'Plan the third exit early.'],
                ['id' => 's2', 'label' => 'Position', 'objects' => [], 'strokes' => [], 'note' => 'Choose the correct lane.'],
                ['id' => 's3', 'label' => 'Observe', 'objects' => [], 'strokes' => [], 'note' => 'Give way to the right.'],
                ['id' => 's4', 'label' => 'Enter', 'objects' => [], 'strokes' => [], 'note' => 'Commit when safe.'],
                ['id' => 's5', 'label' => 'Follow lane', 'objects' => [], 'strokes' => [], 'note' => 'Stay with markings.'],
                ['id' => 's6', 'label' => 'Exit', 'objects' => [], 'strokes' => [], 'note' => 'Signal left, leave smoothly.'],
            ],
            'active_step' => 0,
            'map' => null,
        ];
        $resource = new TeachingResource();
        $resource->organisation_id = (int) $org->id;
        $resource->kind = TeachingResource::KIND_BOARD;
        $resource->title = 'Kingston Roundabout — spiral lanes';
        $resource->category = 'roundabouts';
        $resource->description = 'Reusable multi-lane / spiral positioning board.';
        $resource->template_code = 'multi_roundabout';
        $resource->scene_json = json_encode($scene, JSON_THROW_ON_ERROR);
        $resource->skill_codes_json = json_encode(['roundabouts', 'lane_choice'], JSON_THROW_ON_ERROR);
        $resource->is_favourite = true;
        $resource->created_at = $now;
        $resource->updated_at = $now;
        $this->mustSave($resource);
        $this->counts['teaching_resources']++;

        foreach ([
            ['Spiral roundabout positioning', 'roundabout', 'roundabouts'],
            ['Parallel parking reference', 'parking', 'manoeuvres'],
            ['A5 lane change', 'dual_carriageway', 'dual_carriageways'],
        ] as [$title, $template, $cat]) {
            $extra = new TeachingResource();
            $extra->organisation_id = (int) $org->id;
            $extra->kind = TeachingResource::KIND_BOARD;
            $extra->title = $title;
            $extra->category = $cat;
            $extra->template_code = $template;
            $extra->scene_json = json_encode([
                'template' => $template,
                'version' => 1,
                'objects' => [],
                'strokes' => [],
                'steps' => [['id' => 's1', 'label' => 'Step 1', 'objects' => [], 'strokes' => [], 'note' => '']],
                'active_step' => 0,
                'map' => null,
            ], JSON_THROW_ON_ERROR);
            $extra->is_favourite = $title === 'Spiral roundabout positioning';
            $extra->created_at = $now;
            $extra->updated_at = $now;
            $this->mustSave($extra);
            $this->counts['teaching_resources']++;
        }

        return $resource;
    }

    private function seedRouteMoment(
        Organisation $org,
        LessonRoute $route,
        Lesson $lesson,
        float $lat,
        float $lng,
        int $offsetSeconds,
        string $kind,
        string $label,
        string $note,
        bool $visible,
    ): RouteMoment {
        $now = $route->ended_at ?? gmdate('Y-m-d H:i:s');
        $moment = new RouteMoment();
        $moment->organisation_id = (int) $org->id;
        $moment->lesson_route_id = (int) $route->id;
        $moment->lesson_id = (int) $lesson->id;
        $moment->learner_id = (int) $lesson->learner_id;
        $start = $route->started_at
            ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $route->started_at, new DateTimeZone('UTC'))
            : false;
        $moment->recorded_at = $start
            ? $start->modify('+' . $offsetSeconds . ' seconds')->format('Y-m-d H:i:s')
            : $now;
        $moment->offset_seconds = $offsetSeconds;
        $moment->lat = $lat;
        $moment->lng = $lng;
        $moment->kind = $kind;
        $moment->label = $label;
        $moment->learner_note = $note;
        $moment->learner_visible = $visible;
        $moment->created_at = $now;
        $moment->updated_at = $now;
        $this->mustSave($moment);
        $this->counts['route_moments']++;

        return $moment;
    }

    private function seedLessonResourceFromMaster(
        Organisation $org,
        Learner $learner,
        Lesson $lesson,
        TeachingResource $master,
        RouteMoment $moment,
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $scene = $master->scene();
        if (isset($scene['steps'][1])) {
            $scene['steps'][1]['note'] = 'Hold left-of-centre a fraction longer before the third exit.';
        }
        $row = new LessonResource();
        $row->organisation_id = (int) $org->id;
        $row->lesson_id = (int) $lesson->id;
        $row->learner_id = (int) $learner->id;
        $row->source_resource_id = (int) $master->id;
        $row->kind = $master->kind;
        $row->title = $master->title;
        $row->scene_json = json_encode($scene, JSON_THROW_ON_ERROR);
        $row->learner_visible_note = 'Remember your lane position approaching the third exit.';
        $row->skill_codes_json = $master->skill_codes_json;
        $row->route_moment_id = (int) $moment->id;
        $row->learner_visible = true;
        $row->created_at = $now;
        $row->updated_at = $now;
        $this->mustSave($row);
        $this->counts['lesson_resources']++;
    }

    private function seedPrivatePractice(
        Organisation $org,
        Learner $learner,
        DateTimeImmutable $todayLocal,
    ): void {
        $sessions = [
            [$todayLocal->sub(new DateInterval('P2D')), 45, ['roundabouts', 'lane_choice'], 'okay'],
            [$todayLocal->sub(new DateInterval('P5D')), 40, ['t_junctions', 'meeting'], 'comfortable'],
        ];
        foreach ($sessions as [$when, $mins, $skills, $feeling]) {
            $utc = $when->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $row = new PrivatePracticeSession();
            $row->organisation_id = (int) $org->id;
            $row->learner_id = (int) $learner->id;
            $row->practised_at = $utc;
            $row->duration_minutes = $mins;
            $row->skill_codes_json = json_encode($skills, JSON_THROW_ON_ERROR);
            $row->feeling = $feeling;
            $row->note = null;
            $row->created_at = $utc;
            $row->updated_at = $utc;
            $this->mustSave($row);
            $this->counts['private_practice']++;
        }
    }

    private function activatePortalAccount(Organisation $org, Learner $learner): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $account = new LearnerPortalAccount();
        $account->organisation_id = (int) $org->id;
        $account->learner_id = (int) $learner->id;
        $account->email = self::PORTAL_EMAIL;
        $account->setPassword(self::PORTAL_PASSWORD);
        $account->generateAuthKey();
        $account->activated_at = $now;
        $account->invite_token_hash = null;
        $account->invite_expires_at = null;
        $account->created_at = $now;
        $account->updated_at = $now;
        $this->mustSave($account);
        $this->counts['portal_accounts']++;
    }

    private function attachLessonSkill(Organisation $org, Lesson $lesson, ProgressSkill $skill): void
    {
        $row = new LessonSkill();
        $row->organisation_id = (int) $org->id;
        $row->lesson_id = (int) $lesson->id;
        $row->skill_id = (int) $skill->id;
        $row->created_at = $lesson->completed_at ?? gmdate('Y-m-d H:i:s');
        $this->mustSave($row);
    }

    private function recordSkillRating(
        Organisation $org,
        Learner $learner,
        Lesson $lesson,
        ProgressSkill $skill,
        string $rating,
    ): void {
        $row = new LearnerSkillProgress();
        $row->organisation_id = (int) $org->id;
        $row->learner_id = (int) $learner->id;
        $row->skill_id = (int) $skill->id;
        $row->lesson_id = (int) $lesson->id;
        $row->rating = $rating;
        $row->recorded_at = $lesson->completed_at ?? gmdate('Y-m-d H:i:s');
        $row->created_at = $row->recorded_at;
        $this->mustSave($row);
        $this->counts['skill_ratings']++;
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     */
    private function seedLessonRoute(
        Organisation $org,
        Learner $learner,
        Lesson $lesson,
        array $points,
        bool $learnerVisible,
    ): LessonRoute {
        $started = $lesson->starts_at;
        $ended = $lesson->completed_at
            ?? (DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lesson->starts_at, new DateTimeZone('UTC'))
                ?: new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify('+' . (int) $lesson->duration_minutes . ' minutes')
                ->format('Y-m-d H:i:s');

        $startDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $started, new DateTimeZone('UTC'));
        $endDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ended, new DateTimeZone('UTC'));
        $duration = ($startDt && $endDt) ? max(0, $endDt->getTimestamp() - $startDt->getTimestamp()) : null;

        $bounds = EncodedPolyline::bounds($points);
        $route = new LessonRoute();
        $route->organisation_id = (int) $org->id;
        $route->lesson_id = (int) $lesson->id;
        $route->learner_id = (int) $learner->id;
        $route->status = LessonRoute::STATUS_COMPLETED;
        $route->started_at = $started;
        $route->ended_at = $ended;
        $route->duration_seconds = $duration;
        $route->distance_metres = EncodedPolyline::approximateDistanceMetres($points);
        $route->point_count = count($points);
        $route->encoded_polyline = EncodedPolyline::encode($points);
        $route->bounds_json = $bounds !== null ? json_encode($bounds, JSON_THROW_ON_ERROR) : null;
        $route->label = null;
        $route->learner_visible = $learnerVisible;
        $route->shared_at = $learnerVisible ? $ended : null;
        $route->deleted_at = null;
        $route->created_at = $ended;
        $route->updated_at = $ended;
        $this->mustSave($route);
        $this->counts['routes']++;

        return $route;
    }

    /** @return list<array{0: float, 1: float}> */
    private function mkRouteBletchleyLoop(): array
    {
        return [
            [51.9942, -0.7435], [51.9968, -0.7401], [52.0012, -0.7355],
            [52.0055, -0.7288], [52.0088, -0.7220], [52.0061, -0.7155],
            [52.0010, -0.7188], [51.9975, -0.7265], [51.9948, -0.7350],
            [51.9942, -0.7435],
        ];
    }

    /** @return list<array{0: float, 1: float}> */
    private function mkRouteCentral(): array
    {
        return [
            [52.0402, -0.7594], [52.0388, -0.7510], [52.0365, -0.7422],
            [52.0330, -0.7355], [52.0295, -0.7408], [52.0318, -0.7495],
            [52.0355, -0.7560], [52.0402, -0.7594],
        ];
    }

    /** @return list<array{0: float, 1: float}> */
    private function mkRouteA5(): array
    {
        return [
            [51.9940, -0.7430], [51.9985, -0.7520], [52.0050, -0.7625],
            [52.0125, -0.7710], [52.0180, -0.7655], [52.0105, -0.7540],
            [52.0020, -0.7455], [51.9940, -0.7430],
        ];
    }

    /** @return list<array{0: float, 1: float}> */
    private function mkRouteWestcroft(): array
    {
        return [
            [52.0080, -0.7805], [52.0115, -0.7720], [52.0155, -0.7635],
            [52.0120, -0.7550], [52.0065, -0.7605], [52.0035, -0.7710],
            [52.0080, -0.7805],
        ];
    }

    /** Org contact email must be a valid address even when login id is short (e.g. demo). */
    private function publicContactEmail(): string
    {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return $this->email;
        }

        return 'demo@ownlane.test';
    }

    private function configureOrganisation(Organisation $org): void
    {
        $org->name = $this->orgName !== '' ? $this->orgName : self::DEMO_ORG;
        $org->service_area = 'Milton Keynes, Bletchley, Newport Pagnell, Wolverton';
        $org->cancellation_policy = "Please give at least 48 hours' notice to cancel or rearrange a lesson.\n\n"
            . "Less than 48 hours' notice may be charged at the full lesson rate, "
            . "except for genuine illness or emergencies — just message me as soon as you can.\n\n"
            . 'Weather: if roads are unsafe I will rearrange at no charge.';
        $org->contact_phone = '07700900100';
        $org->contact_email = $this->publicContactEmail();
        $org->default_hourly_rate_pence = 3800;
        $org->default_lesson_duration_minutes = 60;
        $org->work_days = json_encode([1, 2, 3, 4, 5, 6], JSON_THROW_ON_ERROR);
        $org->work_start_time = '08:00';
        $org->work_end_time = '19:00';
        $org->updated_at = gmdate('Y-m-d H:i:s');
        $this->mustSave($org);
    }

    /**
     * @return list<Learner>
     */
    private function seedPupils(
        Organisation $org,
        Instructor $instructor,
        User $user,
        DateTimeImmutable $todayLocal,
        DateTimeImmutable $nowUtc,
    ): array {
        $defs = $this->pupilDefinitions($todayLocal);
        $learners = [];

        foreach ($defs as $def) {
            $learner = $this->createLearner($org, $def);
            $learners[] = $learner;

            if (!empty($def['availability'])) {
                $this->seedAvailability($org, $learner, $def['availability']);
            }

            if (!empty($def['package'])) {
                $this->seedPackage($org, $learner, $user, $def['package'], $todayLocal);
            }

            $this->seedLessonHistory($org, $instructor, $learner, $def, $todayLocal, $nowUtc);
        }

        return $learners;
    }

    /**
     * Pack "today" with a realistic teaching day for calendar QA:
     * mixed durations, one overlap, one travel-tight gap, one large gap, cancelled slot.
     *
     * @param list<Learner> $learners
     */
    private function seedDenseCalendarDay(
        Organisation $org,
        Instructor $instructor,
        array $learners,
        DateTimeImmutable $todayLocal,
    ): void {
        if (count($learners) < 8) {
            return;
        }

        // Clear any future scheduled lessons already created for today so the grid is deliberate.
        $dayStart = $todayLocal->setTime(0, 0);
        $dayEnd = $todayLocal->modify('+1 day')->setTime(0, 0);
        $startUtc = $dayStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $dayEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        Lesson::deleteAll([
            'and',
            ['organisation_id' => (int) $org->id],
            ['>=', 'starts_at', $startUtc],
            ['<', 'starts_at', $endUtc],
            ['status' => Lesson::STATUS_SCHEDULED],
        ]);

        $rate = (int) ($org->default_hourly_rate_pence ?: 3800);
        $slots = [
            ['i' => 0, 'time' => '08:00', 'duration' => 60],
            ['i' => 1, 'time' => '09:00', 'duration' => 60],
            ['i' => 2, 'time' => '10:00', 'duration' => 120], // 2hr
            // Large gap ~12:00–14:00
            ['i' => 3, 'time' => '14:00', 'duration' => 90],  // 90m
            ['i' => 4, 'time' => '15:40', 'duration' => 60],  // tight after 14:00–15:30
            ['i' => 5, 'time' => '16:00', 'duration' => 60],  // overlaps with next
            ['i' => 6, 'time' => '16:00', 'duration' => 60],  // deliberate overlap
            ['i' => 7, 'time' => '17:30', 'duration' => 60],
        ];

        foreach ($slots as $slot) {
            $learner = $learners[(int) $slot['i']];
            [$hh, $mm] = array_map('intval', explode(':', (string) $slot['time']));
            $localStart = $todayLocal->setTime($hh, $mm);
            $this->createLesson(
                $org,
                $instructor,
                $learner,
                $localStart,
                (int) $slot['duration'],
                Lesson::STATUS_SCHEDULED,
                $learner->default_pickup_address,
                null,
                null,
                null,
                $rate,
                null,
            );
        }

        // Cancelled afternoon slot (empty seat).
        $cancelLearner = $learners[8] ?? $learners[0];
        $cancelled = $this->createLesson(
            $org,
            $instructor,
            $cancelLearner,
            $todayLocal->setTime(12, 0),
            120,
            Lesson::STATUS_CANCELLED,
            $cancelLearner->default_pickup_address,
            null,
            null,
            null,
            $rate,
            null,
        );
        $cancelled->cancelled_at = gmdate('Y-m-d H:i:s');
        $cancelled->save(false, ['cancelled_at']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pupilDefinitions(DateTimeImmutable $todayLocal): array
    {
        $theoryRecent = $todayLocal->sub(new DateInterval('P10M'))->format('Y-m-d');
        $theoryExpiring = $todayLocal->sub(new DateInterval('P22M'))->format('Y-m-d');
        $practicalSoon = $todayLocal->add(new DateInterval('P18D'))->format('Y-m-d');
        $practicalLater = $todayLocal->add(new DateInterval('P45D'))->format('Y-m-d');

        return [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Ahmed',
                'mobile' => '07700901001',
                'email' => self::PORTAL_LEARNER_CONTACT_EMAIL,
                'pickup' => '12 Haddon Hill, Shenley Brook End, MK5 6HT',
                'transmission' => 'automatic',
                'theory_status' => 'passed',
                'theory_pass_date' => $theoryRecent,
                'test_date' => $practicalSoon,
                'test_centre' => 'Milton Keynes',
                'practical_test_time' => '10:12',
                'next_focus' => 'Independent lane choice on larger roundabouts',
                'last_summary' => 'Much more confident approaching larger roundabouts today.',
                'private_notes' => 'Prefers WhatsApp. Weekly lessons. Showcase portal learner.',
                'cadence' => 'weekly',
                'weekday' => 2,
                'time' => '17:00',
                'history_weeks' => 20,
                'varied_durations' => true,
                'portal_showcase' => true,
                'future' => [
                    ['offset_days' => 1, 'time' => '16:00', 'duration' => 90],
                    ['offset_days' => 8, 'time' => '17:00', 'duration' => 90],
                    ['offset_days' => 15, 'time' => '17:00', 'duration' => 60],
                    ['offset_days' => 22, 'time' => '16:30', 'duration' => 120],
                ],
                'package' => ['hours' => 10, 'remaining_hours' => 4, 'price' => 34000],
                'availability' => [[2, 'after', '16:00'], [4, 'after', '16:00'], [6, 'flexible']],
                'persona' => 'test_soon',
            ],
            [
                'first_name' => 'Jake',
                'last_name' => 'Thompson',
                'mobile' => '07700901002',
                'email' => 'jake.t@example.com',
                'pickup' => '4 Church Street, Wolverton, MK12 5JN',
                'transmission' => 'manual',
                'theory_status' => 'not_yet',
                'next_focus' => 'Moving off and stopping smoothly',
                'last_summary' => 'First few lessons — building confidence with clutch.',
                'private_notes' => 'Quite nervous. Keep first lessons quiet routes.',
                'cadence' => 'weekly',
                'weekday' => 3,
                'time' => '10:00',
                'history_weeks' => 3,
                'future' => [['offset_days' => 2, 'time' => '10:00'], ['offset_days' => 9, 'time' => '10:00']],
                'availability' => [[3, 'between', '09:00', '12:00'], [5, 'between', '09:00', '12:00']],
                'persona' => 'beginner',
            ],
            [
                'first_name' => 'Priya',
                'last_name' => 'Sharma',
                'mobile' => '07700901003',
                'email' => 'priya.sharma@example.com',
                'pickup' => '88 Queensway, Bletchley, MK2 2SB',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $theoryExpiring,
                'next_focus' => 'Bay parking and reverse parks',
                'last_summary' => 'Good progress on junctions. Parking still shaky.',
                'cadence' => 'weekly',
                'weekday' => 1,
                'time' => '18:00',
                'history_weeks' => 18,
                'future' => [['offset_days' => 6, 'time' => '18:00']],
                'package' => ['hours' => 20, 'remaining_hours' => 6, 'price' => 68000],
                'availability' => [[1, 'after', '17:00'], [3, 'after', '17:00']],
                'persona' => 'theory_expiring',
            ],
            [
                'first_name' => 'Callum',
                'last_name' => 'Wright',
                'mobile' => '07700901004',
                'email' => 'callum.w@example.com',
                'pickup' => '2 Station Road, Newport Pagnell, MK16 0AG',
                'transmission' => 'manual',
                'theory_status' => 'booked',
                'next_focus' => 'Roundabouts and dual carriageways',
                'last_summary' => 'Back after a break — rusty but improving fast.',
                'private_notes' => 'Returning after ~8 months off. Start with assessment routes.',
                'cadence' => 'fortnightly',
                'weekday' => 4,
                'time' => '14:00',
                'history_weeks' => 6,
                'gap_before_recent' => 32,
                'future' => [], // overdue — Needs you / continuity
                'availability' => [[4, 'between', '13:00', '16:00'], [6, 'morning']],
                'persona' => 'returning',
                'outstanding' => true,
            ],
            [
                'first_name' => 'Sophie',
                'last_name' => 'Nguyen',
                'mobile' => '07700901005',
                'email' => 'sophie.n@example.com',
                'pickup' => '15 Loughton Gate, Loughton, MK5 8HA',
                'transmission' => 'automatic',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P6M'))->format('Y-m-d'),
                'test_date' => $practicalLater,
                'test_centre' => 'Aylesbury',
                'practical_test_time' => '14:07',
                'next_focus' => 'Mock tests under slight pressure',
                'last_summary' => 'Nearly test ready. Needs polish on independent sat-nav.',
                'cadence' => 'twice_week',
                'weekday' => 2,
                'time' => '09:00',
                'history_weeks' => 20,
                'future' => [
                    ['offset_days' => 0, 'time' => '09:00'],
                    ['offset_days' => 3, 'time' => '16:00'],
                    ['offset_days' => 7, 'time' => '09:00'],
                ],
                'package' => ['hours' => 10, 'remaining_hours' => 2, 'price' => 36000],
                'availability' => [[2, 'morning'], [5, 'after', '15:00'], [6, 'flexible']],
                'persona' => 'almost_ready',
            ],
            [
                'first_name' => 'Omar',
                'last_name' => 'Khan',
                'mobile' => '07700901006',
                'email' => 'omar.khan@example.com',
                'pickup' => '31 Midsummer Boulevard, Central MK, MK9 3EA',
                'transmission' => 'manual',
                'theory_status' => 'not_yet',
                'next_focus' => 'Mirrors–signal–manoeuvre habit',
                'last_summary' => 'Improving observation. Still rushes junctions.',
                'cadence' => 'weekly',
                'weekday' => 5,
                'time' => '17:30',
                'history_weeks' => 9,
                'future' => [['offset_days' => 4, 'time' => '17:30']],
                'cancelled_soon' => ['offset_days' => 1, 'time' => '11:00'], // empty seat tomorrow
                'availability' => [[5, 'after', '17:00'], [6, 'afternoon']],
                'persona' => 'regular',
            ],
            [
                'first_name' => 'Ellie',
                'last_name' => 'Brooks',
                'mobile' => '07700901007',
                'email' => 'ellie.brooks@example.com',
                'pickup' => '7 Fulmer Street, Stony Stratford, MK11 1AA',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P4M'))->format('Y-m-d'),
                'next_focus' => 'Hill starts and clutch control',
                'last_summary' => 'Confidence growing on busier roads.',
                'cadence' => 'weekly',
                'weekday' => 6,
                'time' => '11:00',
                'history_weeks' => 11,
                'future' => [['offset_days' => 5, 'time' => '11:00'], ['offset_days' => 12, 'time' => '11:00']],
                'availability' => [[6, 'between', '10:00', '14:00']],
                'persona' => 'saturday',
            ],
            [
                'first_name' => 'Noah',
                'last_name' => 'Patel',
                'mobile' => '07700901008',
                'email' => 'noah.patel@example.com',
                'pickup' => '19 Watling Street, Bletchley, MK1 1BE',
                'transmission' => 'automatic',
                'theory_status' => 'booked',
                'next_focus' => 'Roundabout lane discipline',
                'last_summary' => 'Switched from another instructor — assessing baseline.',
                'private_notes' => 'From intake. Says ~20 previous hours. Do assessment lesson first.',
                'learner_reported' => [
                    'source' => 'learner_intake',
                    'provenance' => 'learner_supplied',
                    'skills_practised' => ['junctions', 'roundabouts', 'manoeuvres'],
                    'driving' => [
                        'had_lessons_before' => true,
                        'lesson_hours_band' => '20_40',
                        'last_drove' => '1_3_months',
                    ],
                    'about' => [
                        'goal' => 'switching',
                        'confidence' => 'a_little',
                        'instructor_should_know' => 'Previous instructor retired. Keen to pass this year.',
                    ],
                ],
                'cadence' => 'weekly',
                'weekday' => 1,
                'time' => '16:00',
                'history_weeks' => 2,
                'future' => [['offset_days' => 6, 'time' => '16:00']],
                'availability' => [[1, 'after', '15:00'], [4, 'after', '15:00']],
                'persona' => 'switcher',
            ],
            [
                'first_name' => 'Mia',
                'last_name' => 'Collins',
                'mobile' => '07700901009',
                'email' => 'mia.collins@example.com',
                'pickup' => '42 Bradwell Common Boulevard, MK13 8AN',
                'transmission' => 'manual',
                'theory_status' => 'not_yet',
                'next_focus' => 'Emergency stop and hazard awareness',
                'last_summary' => 'Solid basics. Ready for busier traffic.',
                'cadence' => 'weekly',
                'weekday' => 2,
                'time' => '15:00',
                'history_weeks' => 8,
                'future' => [['offset_days' => 0, 'time' => '15:00'], ['offset_days' => 7, 'time' => '15:00']],
                'availability' => [[2, 'afternoon'], [4, 'afternoon']],
                'persona' => 'today_busy',
            ],
            [
                'first_name' => 'Harry',
                'last_name' => 'Owen',
                'mobile' => '07700901010',
                'email' => 'harry.owen@example.com',
                'pickup' => '5 High Street, Olney, MK46 4EF',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P14M'))->format('Y-m-d'),
                'next_focus' => 'Country roads and speed awareness',
                'last_summary' => 'Good on town roads; rural roads need work.',
                'cadence' => 'weekly',
                'weekday' => 3,
                'time' => '18:00',
                'history_weeks' => 12,
                'future' => [['offset_days' => 2, 'time' => '18:00']],
                'package' => ['hours' => 10, 'remaining_hours' => 7, 'price' => 35000],
                'availability' => [[3, 'after', '17:00']],
                'persona' => 'rural',
            ],
            [
                'first_name' => 'Isla',
                'last_name' => 'Bennett',
                'mobile' => '07700901011',
                'email' => 'isla.b@example.com',
                'pickup' => '11 Crownhill Drive, Crownhill, MK8 0AS',
                'transmission' => 'automatic',
                'theory_status' => 'not_yet',
                'next_focus' => 'Pulling up on the right',
                'last_summary' => 'Quiet progress. Prefers shorter lessons when tired.',
                'cadence' => 'fortnightly',
                'weekday' => 5,
                'time' => '10:00',
                'history_weeks' => 10,
                'future' => [['offset_days' => 11, 'time' => '10:00']],
                'availability' => [[5, 'morning']],
                'persona' => 'fortnightly',
            ],
            [
                'first_name' => 'Leo',
                'last_name' => 'Garcia',
                'mobile' => '07700901012',
                'email' => 'leo.garcia@example.com',
                'pickup' => '28 Fishermead Boulevard, Fishermead, MK6 2LA',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P3M'))->format('Y-m-d'),
                'next_focus' => 'Test routes around MK centre',
                'last_summary' => 'Independent driving improving. Still hesitates at lights.',
                'cadence' => 'weekly',
                'weekday' => 4,
                'time' => '17:00',
                'history_weeks' => 15,
                'future' => [['offset_days' => 3, 'time' => '17:00'], ['offset_days' => 10, 'time' => '17:00']],
                'availability' => [[4, 'after', '16:30'], [6, 'morning']],
                'persona' => 'centre',
            ],
            [
                'first_name' => 'Chloe',
                'last_name' => 'Reid',
                'mobile' => '07700901013',
                'email' => 'chloe.reid@example.com',
                'pickup' => '9 Walton Hall Drive, Walton, MK7 6AA',
                'transmission' => 'manual',
                'theory_status' => 'not_yet',
                'next_focus' => 'Left and right turns at T-junctions',
                'last_summary' => 'Early stage — good attitude.',
                'cadence' => 'weekly',
                'weekday' => 1,
                'time' => '09:30',
                'history_weeks' => 4,
                'future' => [['offset_days' => 0, 'time' => '11:30'], ['offset_days' => 7, 'time' => '09:30']],
                'availability' => [[1, 'morning'], [3, 'morning']],
                'persona' => 'morning',
            ],
            [
                'first_name' => 'Ben',
                'last_name' => 'Foster',
                'mobile' => '07700901014',
                'email' => 'ben.foster@example.com',
                'pickup' => '16 Emerson Valley Drive, Emerson Valley, MK4 2DA',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P8M'))->format('Y-m-d'),
                'next_focus' => 'Dual carriageways and merging',
                'last_summary' => 'Ready to push onto faster roads.',
                'cadence' => 'weekly',
                'weekday' => 6,
                'time' => '09:00',
                'history_weeks' => 13,
                'future' => [['offset_days' => 5, 'time' => '09:00']],
                'outstanding' => true,
                'availability' => [[6, 'morning'], [6, 'afternoon']],
                'persona' => 'owes',
            ],
            [
                'first_name' => 'Zara',
                'last_name' => 'Ahmed',
                'mobile' => '07700901015',
                'email' => 'zara.ahmed@example.com',
                'pickup' => '3 Giffard Park Roundabout approach, MK14 5HA',
                'transmission' => 'automatic',
                'theory_status' => 'not_yet',
                'next_focus' => 'Observation at busy roundabouts',
                'last_summary' => 'Improving lane choice. Still late with signals sometimes.',
                'cadence' => 'weekly',
                'weekday' => 2,
                'time' => '18:30',
                'history_weeks' => 7,
                'future' => [['offset_days' => 7, 'time' => '18:30']],
                'availability' => [[2, 'evening'], [5, 'evening']],
                'persona' => 'evening',
            ],
            [
                'first_name' => 'Tom',
                'last_name' => 'Hughes',
                'mobile' => '07700901016',
                'email' => 'tom.hughes@example.com',
                'pickup' => '22 Newport Road, New Bradwell, MK13 0AD',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P2M'))->format('Y-m-d'),
                'test_date' => $todayLocal->add(new DateInterval('P10D'))->format('Y-m-d'),
                'test_centre' => 'Milton Keynes',
                'practical_test_time' => '08:40',
                'next_focus' => 'Final mock tests — quiet the nerves',
                'last_summary' => 'Test in 10 days. Focus on calm decision-making.',
                'private_notes' => 'Very anxious about test. Keep mocks supportive.',
                'cadence' => 'twice_week',
                'weekday' => 3,
                'time' => '16:00',
                'history_weeks' => 22,
                'future' => [
                    ['offset_days' => 1, 'time' => '16:00'],
                    ['offset_days' => 3, 'time' => '16:00'],
                    ['offset_days' => 6, 'time' => '10:00'],
                ],
                'package' => ['hours' => 5, 'remaining_hours' => 1, 'price' => 18000],
                'availability' => [[1, 'after', '15:00'], [3, 'after', '15:00'], [5, 'after', '15:00']],
                'persona' => 'test_imminent',
            ],
            [
                'first_name' => 'Freya',
                'last_name' => 'Walsh',
                'mobile' => '07700901017',
                'email' => 'freya.walsh@example.com',
                'pickup' => '8 Willen Lake approach, Willen, MK15 9HQ',
                'transmission' => 'manual',
                'theory_status' => 'not_yet',
                'next_focus' => 'Meeting traffic and narrow roads',
                'last_summary' => 'Steady learner. Benefits from clear goals each lesson.',
                'cadence' => 'weekly',
                'weekday' => 4,
                'time' => '10:30',
                'history_weeks' => 5,
                'future' => [['offset_days' => 3, 'time' => '10:30']],
                'availability' => [[4, 'morning']],
                'persona' => 'steady',
            ],
            [
                'first_name' => 'Ryan',
                'last_name' => 'Clarke',
                'mobile' => '07700901018',
                'email' => 'ryan.clarke@example.com',
                'pickup' => '14 Two Mile Ash, MK8 8AQ',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P11M'))->format('Y-m-d'),
                'next_focus' => 'Independent driving without prompts',
                'last_summary' => 'Capable — needs to trust own decisions more.',
                'cadence' => 'weekly',
                'weekday' => 5,
                'time' => '14:00',
                'history_weeks' => 16,
                // No future booking → continuity / Needs you
                'future' => [],
                'availability' => [[5, 'afternoon'], [6, 'flexible']],
                'persona' => 'due_rebook',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $def
     */
    private function createLearner(Organisation $org, array $def): Learner
    {
        $now = gmdate('Y-m-d H:i:s');
        $learner = new Learner();
        $learner->organisation_id = (int) $org->id;
        $learner->first_name = $def['first_name'];
        $learner->last_name = $def['last_name'];
        $learner->mobile = $def['mobile'];
        $learner->email = $def['email'] ?? null;
        $learner->default_pickup_address = $def['pickup'] ?? null;
        $learner->transmission = $def['transmission'] ?? null;
        $learner->theory_status = $def['theory_status'] ?? null;
        $learner->theory_pass_date = $def['theory_pass_date'] ?? null;
        $learner->test_date = $def['test_date'] ?? null;
        $learner->test_centre = $def['test_centre'] ?? null;
        $learner->practical_test_time = $def['practical_test_time'] ?? null;
        $learner->next_focus = $def['next_focus'] ?? null;
        $learner->last_lesson_summary = $def['last_summary'] ?? null;
        $learner->private_notes = $def['private_notes'] ?? null;
        $learner->lifecycle = Learner::LIFECYCLE_ACTIVE;
        $learner->availability_is_variable = !empty($def['availability_is_variable']);
        $learner->preferred_contact = $def['preferred_contact'] ?? 'whatsapp';
        if (!empty($def['learner_reported'])) {
            $learner->learner_reported_json = json_encode($def['learner_reported'], JSON_THROW_ON_ERROR);
        }
        $learner->created_at = $now;
        $learner->updated_at = $now;
        $this->mustSave($learner);
        $this->counts['pupils']++;

        return $learner;
    }

    /**
     * @param list<array<int|string>> $windows
     */
    private function seedAvailability(Organisation $org, Learner $learner, array $windows): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($windows as $w) {
            $row = new LearnerAvailability();
            $row->organisation_id = (int) $org->id;
            $row->learner_id = (int) $learner->id;
            $row->weekday = (int) $w[0];
            $mode = (string) $w[1];
            if ($mode === 'morning') {
                $row->mode = LearnerAvailability::MODE_BETWEEN;
                $row->start_time = '09:00';
                $row->end_time = '12:00';
            } elseif ($mode === 'afternoon') {
                $row->mode = LearnerAvailability::MODE_BETWEEN;
                $row->start_time = '12:00';
                $row->end_time = '17:00';
            } elseif ($mode === 'evening') {
                $row->mode = LearnerAvailability::MODE_AFTER;
                $row->start_time = '17:00';
            } elseif ($mode === 'flexible') {
                $row->mode = LearnerAvailability::MODE_FLEXIBLE;
            } else {
                $row->mode = $mode;
                $row->start_time = isset($w[2]) ? (string) $w[2] : null;
                $row->end_time = isset($w[3]) ? (string) $w[3] : null;
            }
            $row->created_at = $now;
            $row->updated_at = $now;
            $this->mustSave($row);
            $this->counts['availability']++;
        }
    }

    /**
     * @param array<string, mixed> $pkg
     */
    private function seedPackage(
        Organisation $org,
        Learner $learner,
        User $user,
        array $pkg,
        DateTimeImmutable $todayLocal,
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $purchasedMinutes = (int) ($pkg['hours'] * 60);
        $remainingMinutes = (int) (($pkg['remaining_hours'] ?? $pkg['hours']) * 60);
        $price = (int) $pkg['price'];
        $purchasedAt = $todayLocal->sub(new DateInterval('P' . random_int(20, 90) . 'D'))
            ->setTime(12, 0)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $payment = new Payment();
        $payment->organisation_id = (int) $org->id;
        $payment->learner_id = (int) $learner->id;
        $payment->amount_pence = $price;
        $payment->method = Payment::METHOD_BANK_TRANSFER;
        $payment->purpose = Payment::PURPOSE_PACKAGE;
        $payment->recorded_at = $purchasedAt;
        $payment->counts_as_income = true;
        $payment->created_by_user_id = (int) $user->id;
        $payment->notes = '10-hour block';
        $payment->created_at = $now;
        $payment->updated_at = $now;
        $this->mustSave($payment);
        $this->counts['payments']++;

        $package = new LearnerPackage();
        $package->organisation_id = (int) $org->id;
        $package->learner_id = (int) $learner->id;
        $package->label = ($pkg['hours'] ?? 10) . '-hour package';
        $package->purchased_minutes = $purchasedMinutes;
        $package->remaining_minutes = $remainingMinutes;
        $package->price_pence = $price;
        $package->payment_id = (int) $payment->id;
        $package->purchased_at = $purchasedAt;
        $package->status = $remainingMinutes > 0
            ? LearnerPackage::STATUS_ACTIVE
            : LearnerPackage::STATUS_EXHAUSTED;
        $package->created_at = $now;
        $package->updated_at = $now;
        $this->mustSave($package);
        $this->counts['packages']++;

        $payment->package_id = (int) $package->id;
        $payment->save(false, ['package_id']);
    }

    /**
     * @param array<string, mixed> $def
     */
    private function seedLessonHistory(
        Organisation $org,
        Instructor $instructor,
        Learner $learner,
        array $def,
        DateTimeImmutable $todayLocal,
        DateTimeImmutable $nowUtc,
    ): void {
        $weekday = (int) ($def['weekday'] ?? 2);
        $time = (string) ($def['time'] ?? '10:00');
        $historyWeeks = (int) ($def['history_weeks'] ?? 4);
        $rate = (int) ($org->default_hourly_rate_pence ?: 3800);
        $pickup = $learner->default_pickup_address;

        $notesPool = [
            ['Clutch control improving on hills.', 'Good work on moving off smoothly.', 'Hill starts'],
            ['Roundabout positioning better today.', 'You handled the busy roundabout well.', 'Lane discipline'],
            ['Mirrors still late at times.', 'Remember MSM — mirrors first.', 'Observation'],
            ['Bay park nearly there.', 'Reverse parks — keep it slow and check blind spots.', 'Parking'],
            ['Independent driving stretch went well.', 'Try deciding earlier at junctions.', 'Independent driving'],
            ['Dual carriageway merge was calm.', 'Keep checking mirrors before changing lane.', 'Dual carriageways'],
            ['Meeting traffic on narrow roads — good judgement.', 'Hold back earlier when space is tight.', 'Meeting situations'],
        ];

        $durationCycle = !empty($def['varied_durations'])
            ? [60, 90, 60, 120, 90, 60, 90, 60]
            : [60];

        $completedCount = 0;
        $package = LearnerPackage::find()
            ->andWhere(['learner_id' => (int) $learner->id, 'status' => LearnerPackage::STATUS_ACTIVE])
            ->one();

        for ($w = $historyWeeks; $w >= 1; $w--) {
            // Skip a chunk to simulate long break for returning pupils.
            if (!empty($def['gap_before_recent']) && $w > 3 && $w < (int) $def['gap_before_recent']) {
                continue;
            }

            $localDay = $this->previousWeekday($todayLocal, $weekday, $w);
            [$hh, $mm] = array_map('intval', explode(':', $time));
            $localStart = $localDay->setTime($hh, $mm);
            if ($localStart >= $todayLocal->setTime(23, 59)) {
                continue;
            }

            $note = $notesPool[$completedCount % count($notesPool)];
            $duration = $durationCycle[$completedCount % count($durationCycle)];
            $settlement = $package !== null ? 'package' : 'paid';
            $lesson = $this->createLesson(
                $org,
                $instructor,
                $learner,
                $localStart,
                $duration,
                Lesson::STATUS_COMPLETED,
                $pickup,
                $note[0],
                $note[1],
                $note[2],
                $rate,
                $settlement,
            );

            if ($package !== null && $package->remaining_minutes >= 0) {
                // Usage already reflected in remaining_minutes seed; record a few usages for realism.
                if ($completedCount < 4) {
                    $this->recordPackageUsage($org, $learner, $package, $lesson, $duration);
                }
            } elseif (!empty($def['outstanding']) && $completedCount === 0) {
                $this->createOutstandingCharge($org, $learner, $lesson, $rate);
                $lesson->settlement = 'outstanding';
                $lesson->save(false, ['settlement']);
            } else {
                $this->recordLessonPayment($org, $learner, $lesson, $rate);
            }

            $completedCount++;
        }

        foreach ($def['future'] ?? [] as $future) {
            $offset = (int) $future['offset_days'];
            $ftime = (string) $future['time'];
            $fdur = (int) ($future['duration'] ?? 60);
            [$hh, $mm] = array_map('intval', explode(':', $ftime));
            $localStart = $todayLocal->add(new DateInterval('P' . $offset . 'D'))->setTime($hh, $mm);
            // Skip past times today.
            if ($localStart->setTimezone(new DateTimeZone('UTC')) < $nowUtc) {
                continue;
            }
            $this->createLesson(
                $org,
                $instructor,
                $learner,
                $localStart,
                $fdur,
                Lesson::STATUS_SCHEDULED,
                $pickup,
                null,
                null,
                null,
                $rate,
                null,
            );
        }

        if (!empty($def['cancelled_soon'])) {
            $c = $def['cancelled_soon'];
            $offset = (int) $c['offset_days'];
            $ctime = (string) $c['time'];
            [$hh, $mm] = array_map('intval', explode(':', $ctime));
            $localStart = $todayLocal->add(new DateInterval('P' . $offset . 'D'))->setTime($hh, $mm);
            $lesson = $this->createLesson(
                $org,
                $instructor,
                $learner,
                $localStart,
                120,
                Lesson::STATUS_CANCELLED,
                $pickup,
                null,
                null,
                null,
                $rate,
                null,
            );
            $lesson->cancelled_at = gmdate('Y-m-d H:i:s');
            $lesson->save(false, ['cancelled_at']);
        }
    }

    private function previousWeekday(DateTimeImmutable $today, int $weekday, int $weeksAgo): DateTimeImmutable
    {
        // Find most recent occurrence of weekday, then subtract weeks.
        $iso = (int) $today->format('N');
        $delta = ($iso - $weekday + 7) % 7;
        if ($delta === 0) {
            $delta = 7; // don't use today as "past"
        }
        $day = $today->sub(new DateInterval('P' . $delta . 'D'));

        return $day->sub(new DateInterval('P' . (($weeksAgo - 1) * 7) . 'D'));
    }

    private function createLesson(
        Organisation $org,
        Instructor $instructor,
        Learner $learner,
        DateTimeImmutable $localStart,
        int $duration,
        string $status,
        ?string $pickup,
        ?string $instructorNotes,
        ?string $learnerSummary,
        ?string $nextFocus,
        int $pricePence,
        ?string $settlement,
    ): Lesson {
        $utc = OrganisationTime::localToUtc($localStart->format('Y-m-d H:i'), $org);
        $now = gmdate('Y-m-d H:i:s');

        $lesson = new Lesson();
        $lesson->organisation_id = (int) $org->id;
        $lesson->instructor_id = (int) $instructor->id;
        $lesson->learner_id = (int) $learner->id;
        $lesson->starts_at = $utc->format('Y-m-d H:i:s');
        $lesson->duration_minutes = $duration;
        $lesson->pickup_address = $pickup;
        $lesson->status = $status;
        $lesson->instructor_notes = $instructorNotes;
        $lesson->learner_summary = $learnerSummary;
        $lesson->next_focus = $nextFocus;
        $lesson->price_pence = $pricePence;
        $lesson->settlement = $settlement;
        if ($status === Lesson::STATUS_COMPLETED) {
            $lesson->completed_at = $utc->add(new DateInterval('PT' . $duration . 'M'))->format('Y-m-d H:i:s');
        }
        $lesson->created_at = $now;
        $lesson->updated_at = $now;
        $this->mustSave($lesson);
        $this->counts['lessons']++;

        return $lesson;
    }

    private function recordPackageUsage(
        Organisation $org,
        Learner $learner,
        LearnerPackage $package,
        Lesson $lesson,
        int $minutes,
    ): void {
        $usage = new PackageCreditUsage();
        $usage->organisation_id = (int) $org->id;
        $usage->learner_id = (int) $learner->id;
        $usage->package_id = (int) $package->id;
        $usage->lesson_id = (int) $lesson->id;
        $usage->minutes = $minutes;
        $usage->created_at = $lesson->completed_at ?? gmdate('Y-m-d H:i:s');
        $this->mustSave($usage);
    }

    private function createOutstandingCharge(
        Organisation $org,
        Learner $learner,
        Lesson $lesson,
        int $amount,
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $charge = new LessonCharge();
        $charge->organisation_id = (int) $org->id;
        $charge->learner_id = (int) $learner->id;
        $charge->lesson_id = (int) $lesson->id;
        $charge->amount_pence = $amount;
        $charge->amount_paid_pence = 0;
        $charge->status = LessonCharge::STATUS_OUTSTANDING;
        $charge->created_at = $now;
        $charge->updated_at = $now;
        $this->mustSave($charge);
    }

    private function recordLessonPayment(
        Organisation $org,
        Learner $learner,
        Lesson $lesson,
        int $amount,
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $payment = new Payment();
        $payment->organisation_id = (int) $org->id;
        $payment->learner_id = (int) $learner->id;
        $payment->amount_pence = $amount;
        $payment->method = random_int(0, 1) === 1 ? Payment::METHOD_CASH : Payment::METHOD_BANK_TRANSFER;
        $payment->purpose = Payment::PURPOSE_LESSON_BALANCE;
        $payment->recorded_at = $lesson->completed_at ?? $now;
        $payment->lesson_id = (int) $lesson->id;
        $payment->counts_as_income = true;
        $payment->created_at = $now;
        $payment->updated_at = $now;
        $this->mustSave($payment);
        $this->counts['payments']++;

        $charge = new LessonCharge();
        $charge->organisation_id = (int) $org->id;
        $charge->learner_id = (int) $learner->id;
        $charge->lesson_id = (int) $lesson->id;
        $charge->amount_pence = $amount;
        $charge->amount_paid_pence = $amount;
        $charge->status = LessonCharge::STATUS_PAID;
        $charge->created_at = $now;
        $charge->updated_at = $now;
        $this->mustSave($charge);

        $alloc = new PaymentAllocation();
        $alloc->organisation_id = (int) $org->id;
        $alloc->payment_id = (int) $payment->id;
        $alloc->lesson_charge_id = (int) $charge->id;
        $alloc->amount_pence = $amount;
        $alloc->created_at = $now;
        $this->mustSave($alloc);
    }

    private function seedWaitingList(Organisation $org, DateTimeImmutable $todayLocal): void
    {
        $waiting = [
            [
                'first_name' => 'Nadia',
                'last_name' => 'Okeke',
                'mobile' => '07700902001',
                'email' => 'nadia.okeke@example.com',
                'pickup' => 'MK4 · Emerson Valley',
                'transmission' => 'automatic',
                'theory_status' => 'not_yet',
                'availability_is_variable' => true,
                'preferred_contact' => 'whatsapp',
                'private_notes' => "From intake:\nGoal: Starting from scratch\nAvailability often changes (shift work).",
                'days_waiting' => 12,
                'availability' => [[2, 'evening'], [4, 'evening'], [6, 'flexible']],
                'learner_reported' => [
                    'source' => 'learner_intake',
                    'provenance' => 'learner_supplied',
                    'driving' => ['had_lessons_before' => false, 'driven_before' => 'no'],
                    'about' => ['goal' => 'from_scratch', 'confidence' => 'very_nervous'],
                ],
            ],
            [
                'first_name' => 'Daniel',
                'last_name' => 'Singh',
                'mobile' => '07700902002',
                'email' => 'daniel.singh@example.com',
                'pickup' => 'Bletchley MK2',
                'transmission' => 'manual',
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P5M'))->format('Y-m-d'),
                'preferred_contact' => 'sms',
                'private_notes' => "From intake:\nGoal: Switching instructors\nAround 15–20 previous lesson hours.",
                'days_waiting' => 5,
                'availability' => [[1, 'after', '16:00'], [3, 'after', '16:00'], [5, 'after', '16:00']],
                'learner_reported' => [
                    'source' => 'learner_intake',
                    'provenance' => 'learner_supplied',
                    'skills_practised' => ['junctions', 'roundabouts', 'dual_carriageways'],
                    'driving' => [
                        'had_lessons_before' => true,
                        'lesson_hours_band' => '10_20',
                        'last_drove' => 'this_month',
                    ],
                    'about' => ['goal' => 'switching', 'confidence' => 'okay'],
                ],
            ],
            [
                'first_name' => 'Grace',
                'last_name' => 'Mitchell',
                'mobile' => '07700902003',
                'email' => 'grace.m@example.com',
                'pickup' => 'Newport Pagnell MK16',
                'transmission' => 'manual',
                'theory_status' => 'booked',
                'preferred_contact' => 'email',
                'days_waiting' => 21,
                'availability' => [[6, 'morning'], [6, 'afternoon']],
                'private_notes' => 'Wants Saturday lessons only — school sixth form.',
            ],
            [
                'first_name' => 'Kai',
                'last_name' => 'Zhou',
                'mobile' => '07700902004',
                'email' => 'kai.zhou@example.com',
                'pickup' => 'Wolverton MK12',
                'transmission' => 'either',
                'theory_status' => 'not_yet',
                'availability_is_variable' => true,
                'days_waiting' => 3,
                'private_notes' => 'Flexible on car type. Can do daytime midweek at short notice.',
                'availability' => [[2, 'flexible'], [3, 'flexible'], [4, 'flexible']],
            ],
        ];

        $now = gmdate('Y-m-d H:i:s');
        foreach ($waiting as $def) {
            $joined = $todayLocal
                ->sub(new DateInterval('P' . (int) $def['days_waiting'] . 'D'))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');

            $learner = new Learner();
            $learner->organisation_id = (int) $org->id;
            $learner->first_name = $def['first_name'];
            $learner->last_name = $def['last_name'];
            $learner->mobile = $def['mobile'];
            $learner->email = $def['email'] ?? null;
            $learner->default_pickup_address = $def['pickup'] ?? null;
            $learner->transmission = $def['transmission'] ?? null;
            $learner->theory_status = $def['theory_status'] ?? null;
            $learner->theory_pass_date = $def['theory_pass_date'] ?? null;
            $learner->lifecycle = Learner::LIFECYCLE_WAITING;
            $learner->waiting_list_joined_at = $joined;
            $learner->availability_is_variable = !empty($def['availability_is_variable']);
            $learner->preferred_contact = $def['preferred_contact'] ?? null;
            $learner->private_notes = $def['private_notes'] ?? null;
            if (!empty($def['learner_reported'])) {
                $learner->learner_reported_json = json_encode($def['learner_reported'], JSON_THROW_ON_ERROR);
            }
            $learner->created_at = $joined;
            $learner->updated_at = $now;
            $this->mustSave($learner);
            $this->counts['waiting']++;

            if (!empty($def['availability'])) {
                $this->seedAvailability($org, $learner, $def['availability']);
            }
        }
    }

    private function seedIntakes(
        Organisation $org,
        Instructor $instructor,
        DateTimeImmutable $todayLocal,
    ): void {
        $now = gmdate('Y-m-d H:i:s');

        // 1) Open link for live /join demo
        $plainOpen = Yii::$app->security->generateRandomString(48);
        $open = new LearnerIntake();
        $open->organisation_id = (int) $org->id;
        $open->created_by_instructor_id = (int) $instructor->id;
        $open->invite_token_hash = hash('sha256', $plainOpen);
        $open->invite_expires_at = gmdate('Y-m-d H:i:s', time() + 14 * 86400);
        $open->status = LearnerIntake::STATUS_OPEN;
        $open->prefill_first_name = 'Sam';
        $open->prefill_mobile = '07700903001';
        $open->created_at = $now;
        $open->updated_at = $now;
        $this->mustSave($open);
        $this->counts['intakes']++;
        $this->sampleIntakePath = '/join?token=' . urlencode($plainOpen);

        // 2) Submitted — ready to review (experienced switcher)
        $submittedAnswers = [
            'identity' => [
                'first_name' => 'Yasmin',
                'last_name' => 'Ali',
                'mobile' => '07700903002',
                'email' => 'yasmin.ali@example.com',
                'default_pickup_address' => 'Shenley Church End, MK5',
            ],
            'driving' => [
                'had_lessons_before' => true,
                'driven_before' => 'both',
                'transmission' => 'manual',
                'lesson_hours_band' => '20_40',
                'last_drove' => 'over_6_months',
                'skills_practised' => ['junctions', 'roundabouts', 'manoeuvres', 'parking'],
                'remember_working_on' => 'Parallel parking and dual carriageways',
            ],
            'tests' => [
                'theory_status' => 'passed',
                'theory_pass_date' => $todayLocal->sub(new DateInterval('P20M'))->format('Y-m-d'),
                'practical_booked' => true,
                'practical_date' => $todayLocal->add(new DateInterval('P35D'))->format('Y-m-d'),
                'practical_time' => '11:27',
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
                'goal' => 'switching',
                'instructor_should_know' => 'Returning after a break — a bit nervous but keen to get test-ready.',
                'confidence' => 'a_little',
                'private_practice' => 'sometimes',
                'preferred_contact' => 'whatsapp',
            ],
            'terms_acknowledged' => true,
        ];

        $submitted = new LearnerIntake();
        $submitted->organisation_id = (int) $org->id;
        $submitted->created_by_instructor_id = (int) $instructor->id;
        $submitted->invite_token_hash = hash('sha256', Yii::$app->security->generateRandomString(48));
        $submitted->invite_expires_at = gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        $submitted->status = LearnerIntake::STATUS_SUBMITTED;
        $submitted->setAnswers($submittedAnswers);
        $submitted->submitted_at = $todayLocal->sub(new DateInterval('P1D'))
            ->setTime(19, 40)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
        $submitted->terms_acknowledged_at = $submitted->submitted_at;
        $submitted->created_at = $submitted->submitted_at;
        $submitted->updated_at = $submitted->submitted_at;
        $this->mustSave($submitted);
        $this->counts['intakes']++;

        // 3) Another submitted — beginner, mostly skipped optionals
        $beginner = new LearnerIntake();
        $beginner->organisation_id = (int) $org->id;
        $beginner->created_by_instructor_id = (int) $instructor->id;
        $beginner->invite_token_hash = hash('sha256', Yii::$app->security->generateRandomString(48));
        $beginner->invite_expires_at = gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        $beginner->status = LearnerIntake::STATUS_SUBMITTED;
        $beginner->setAnswers([
            'identity' => [
                'first_name' => 'Max',
                'last_name' => 'Turner',
                'mobile' => '07700903003',
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
                'changes_often' => true,
                'days' => [],
            ],
            'about' => [
                'goal' => 'from_scratch',
                'confidence' => 'very_nervous',
            ],
            'terms_acknowledged' => true,
        ]);
        $beginner->submitted_at = $todayLocal->setTime(8, 15)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
        $beginner->terms_acknowledged_at = $beginner->submitted_at;
        $beginner->created_at = $beginner->submitted_at;
        $beginner->updated_at = $beginner->submitted_at;
        $this->mustSave($beginner);
        $this->counts['intakes']++;

        // 4) Second open link (blank prefill)
        $plain2 = Yii::$app->security->generateRandomString(48);
        $open2 = new LearnerIntake();
        $open2->organisation_id = (int) $org->id;
        $open2->created_by_instructor_id = (int) $instructor->id;
        $open2->invite_token_hash = hash('sha256', $plain2);
        $open2->invite_expires_at = gmdate('Y-m-d H:i:s', time() + 14 * 86400);
        $open2->status = LearnerIntake::STATUS_OPEN;
        $open2->created_at = $now;
        $open2->updated_at = $now;
        $this->mustSave($open2);
        $this->counts['intakes']++;
    }

    private function seedExpenses(Organisation $org, User $user, DateTimeImmutable $todayLocal): void
    {
        $items = [
            [Expense::CATEGORY_FUEL, 5200, 2, 'Shell', 'Shell — full tank'],
            [Expense::CATEGORY_FUEL, 4850, 9, 'Tesco', 'Tesco petrol'],
            [Expense::CATEGORY_FUEL, 5100, 16, 'BP', 'BP Kingston'],
            [Expense::CATEGORY_INSURANCE, 8900, 20, 'Admiral', 'Monthly ADI insurance'],
            [Expense::CATEGORY_SERVICING, 12400, 28, 'Toyota dealer', 'Service + MOT prep'],
            [Expense::CATEGORY_PHONE, 2500, 5, 'EE', 'Business mobile'],
            [Expense::CATEGORY_ADS, 3500, 12, 'Meta', 'Facebook local ads'],
            [Expense::CATEGORY_TRAINING, 7500, 40, 'DVSA prep', 'Standards Check prep day'],
            [Expense::CATEGORY_PARKING, 1800, 7, 'Council', 'Parking permits'],
            [Expense::CATEGORY_FUEL, 4900, 23, 'Asda', 'Asda fuel'],
        ];

        $now = gmdate('Y-m-d H:i:s');
        foreach ($items as [$category, $amount, $daysAgo, $supplier, $notes]) {
            $expense = new Expense();
            $expense->organisation_id = (int) $org->id;
            $expense->amount_pence = $amount;
            $expense->category = $category;
            $expense->supplier = $supplier;
            $expense->spent_on = $todayLocal->sub(new DateInterval('P' . $daysAgo . 'D'))->format('Y-m-d');
            $expense->notes = $notes;
            $expense->created_by_user_id = (int) $user->id;
            $expense->created_at = $now;
            $expense->updated_at = $now;
            $this->mustSave($expense);
            $this->counts['expenses']++;
        }
    }

    private function seedAccountsDemo(Organisation $org, User $user, DateTimeImmutable $todayLocal): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $vehicle = new Vehicle();
        $vehicle->organisation_id = (int) $org->id;
        $vehicle->registration = 'AB23 XYZ';
        $vehicle->make = 'Toyota';
        $vehicle->model = 'Corolla';
        $vehicle->transmission = Vehicle::TRANSMISSION_AUTOMATIC;
        $vehicle->is_primary = true;
        $vehicle->is_active = true;
        $vehicle->created_at = $now;
        $vehicle->updated_at = $now;
        $this->mustSave($vehicle);

        $goal = new FinancialGoal();
        $goal->organisation_id = (int) $org->id;
        $goal->period_year = (int) $todayLocal->format('Y');
        $goal->period_month = (int) $todayLocal->format('n');
        $goal->target_pence = 400000;
        $goal->created_at = $now;
        $goal->updated_at = $now;
        $this->mustSave($goal);
    }

    private function seedPublicProfileAndEnquiry(Organisation $org, Instructor $instructor): void
    {
        $instructor->display_name = 'Amina Yusuf';
        $instructor->updated_at = gmdate('Y-m-d H:i:s');
        $this->mustSave($instructor);

        $org->profile_slug = 'amina-yusuf';
        $org->profile_status = 'published';
        $org->profile_acquisition_mode = 'open';
        $org->profile_intro = 'I teach automatic lessons in Milton Keynes, usually with pupils who want calm, clear instruction and a steady path to test day.';
        $org->profile_transmission = 'automatic';
        $org->profile_teaching_areas = json_encode([
            'Bletchley',
            'Fenny Stratford',
            'Westcroft',
            'Central Milton Keynes',
        ], JSON_THROW_ON_ERROR);
        $org->profile_public_pricing = json_encode([
            ['duration_minutes' => 60, 'price_pence' => 4200, 'label' => '60 minutes'],
            ['duration_minutes' => 90, 'price_pence' => 6300, 'label' => '90 minutes'],
            ['duration_minutes' => 120, 'price_pence' => 8400, 'label' => '2 hours'],
        ], JSON_THROW_ON_ERROR);
        $org->profile_vehicle_summary = 'Automatic Toyota Corolla';
        $org->profile_adi_status = 'adi';
        $org->profile_allow_waiting_list = true;
        $org->profile_dual_controls = true;
        $org->profile_accent_colour = '#2D6A4F';
        $org->profile_teaching_styles = json_encode(['calm_patient', 'structured', 'nervous_learners'], JSON_THROW_ON_ERROR);
        $org->profile_services = json_encode([
            [
                'id' => 'lesson-60',
                'name' => '60 minutes',
                'description' => null,
                'duration_minutes' => 60,
                'price_pence' => 4200,
                'type' => 'lesson',
                'public' => true,
            ],
            [
                'id' => 'lesson-90',
                'name' => '90 minutes',
                'description' => null,
                'duration_minutes' => 90,
                'price_pence' => 6300,
                'type' => 'lesson',
                'public' => true,
            ],
            [
                'id' => 'lesson-120',
                'name' => '2 hours',
                'description' => null,
                'duration_minutes' => 120,
                'price_pence' => 8400,
                'type' => 'lesson',
                'public' => true,
            ],
            [
                'id' => 'mock-test',
                'name' => 'Mock test',
                'description' => 'A structured practice test followed by a lesson review.',
                'duration_minutes' => 90,
                'price_pence' => 6300,
                'type' => 'mock_test',
                'public' => true,
            ],
        ], JSON_THROW_ON_ERROR);
        $org->profile_faqs = json_encode([
            [
                'question' => 'Do you teach complete beginners?',
                'answer' => 'Yes.',
            ],
            [
                'question' => 'Can I be collected from college?',
                'answer' => 'Pickup can be arranged within my teaching area.',
            ],
            [
                'question' => 'What areas do you cover?',
                'answer' => 'I usually teach in Bletchley, Fenny Stratford, Westcroft and Central Milton Keynes.',
            ],
            [
                'question' => 'How do I pay?',
                'answer' => 'Payment details are arranged directly with your instructor.',
            ],
        ], JSON_THROW_ON_ERROR);
        $org->updated_at = gmdate('Y-m-d H:i:s');
        $this->mustSave($org);

        $this->sampleProfilePath = '/instructors/amina-yusuf';

        $now = gmdate('Y-m-d H:i:s');
        $enquiry = new Enquiry();
        $enquiry->organisation_id = (int) $org->id;
        $enquiry->instructor_id = (int) $instructor->id;
        $enquiry->first_name = 'Amal';
        $enquiry->last_name = 'Khan';
        $enquiry->mobile = '07700900421';
        $enquiry->email = 'amal.khan@example.com';
        $enquiry->postcode = 'MK3 5QF';
        $enquiry->transmission = 'automatic';
        $enquiry->experience_band = 'few';
        $enquiry->desired_start = 'asap';
        $enquiry->theory_status = 'passed';
        $enquiry->setAvailability([
            'days' => [
                ['weekday' => 2, 'slots' => ['after_4']],
                ['weekday' => 4, 'slots' => ['after_4']],
                ['weekday' => 6, 'slots' => ['morning']],
            ],
        ]);
        $enquiry->message = 'Looking for evening or Saturday lessons near Bletchley.';
        $enquiry->source = Enquiry::SOURCE_PROFILE;
        $enquiry->status = Enquiry::STATUS_NEW;
        $enquiry->created_at = $now;
        $enquiry->updated_at = $now;
        $this->mustSave($enquiry);
        $this->counts['enquiries'] = ($this->counts['enquiries'] ?? 0) + 1;
    }

    private function mustSave(\yii\db\ActiveRecord $model): void
    {
        if (!$model->save()) {
            $errors = $model->getFirstErrors();
            $msg = $errors !== [] ? (string) reset($errors) : 'validation failed';
            throw new Exception(get_class($model) . ': ' . $msg);
        }
    }
}
