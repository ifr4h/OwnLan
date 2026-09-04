<?php

declare(strict_types=1);

namespace app\services;

use app\components\TenantContext;
use app\components\TheoryCertificate;
use app\models\Instructor;
use app\models\Learner;
use app\models\LearnerIntake;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\TooManyRequestsHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Smart Pupil Intake — adaptive learner conversation → instructor brief.
 */
class IntakeService
{
    public const SKILL_GROUPS = [
        'controls' => 'Controls & moving off',
        'junctions' => 'Junctions',
        'roundabouts' => 'Roundabouts',
        'dual_carriageways' => 'Dual carriageways',
        'manoeuvres' => 'Basic manoeuvres',
        'independent' => 'Independent driving',
        'parking' => 'Parking',
    ];

    /**
     * Instructor creates a shareable intake link (minimal prefill).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createInvite(array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $instructor = $this->currentInstructor($orgId);
        $now = gmdate('Y-m-d H:i:s');

        $intake = new LearnerIntake();
        $intake->organisation_id = $orgId;
        $intake->created_by_instructor_id = $instructor?->id;
        $plain = Yii::$app->security->generateRandomString(48);
        $intake->invite_token_hash = hash('sha256', $plain);
        $intake->invite_expires_at = gmdate('Y-m-d H:i:s', time() + LearnerIntake::INVITE_TTL_DAYS * 86400);
        $intake->status = LearnerIntake::STATUS_OPEN;
        $intake->prefill_first_name = $this->optionalName($data['first_name'] ?? null);
        $intake->prefill_last_name = $this->optionalName($data['last_name'] ?? null);
        $intake->prefill_mobile = $this->optionalMobile($data['mobile'] ?? null);
        $intake->prefill_email = $this->optionalEmail($data['email'] ?? null);
        $intake->created_at = $now;
        $intake->updated_at = $now;

        if (!$intake->save()) {
            throw new BadRequestHttpException($this->firstError($intake));
        }

        return $this->invitePayload($intake, $plain);
    }

    /**
     * @return array<string, mixed>
     */
    public function revoke(int $id): array
    {
        $intake = $this->findOwned($id);
        if ($intake->status !== LearnerIntake::STATUS_OPEN) {
            throw new BadRequestHttpException('Only open links can be revoked.');
        }
        $intake->revoked_at = gmdate('Y-m-d H:i:s');
        $intake->updated_at = $intake->revoked_at;
        $intake->save(false, ['revoked_at', 'updated_at']);

        return ['id' => (int) $intake->id, 'status' => 'revoked'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForInstructor(?string $status = null): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $q = LearnerIntake::find()
            ->andWhere(['organisation_id' => $orgId])
            ->orderBy(['updated_at' => SORT_DESC])
            ->limit(100);
        if ($status !== null && $status !== '') {
            $q->andWhere(['status' => $status]);
        }

        return array_map(fn (LearnerIntake $i) => $this->instructorListItem($i), $q->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function review(int $id): array
    {
        $intake = $this->findOwned($id);
        if (!in_array($intake->status, [
            LearnerIntake::STATUS_SUBMITTED,
            LearnerIntake::STATUS_ACCEPTED,
            LearnerIntake::STATUS_WAITING,
        ], true)) {
            throw new BadRequestHttpException('This intake is not ready to review yet.');
        }

        return $this->buildBrief($intake);
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(int $id): array
    {
        return $this->finalise($id, waiting: false);
    }

    /**
     * @return array<string, mixed>
     */
    public function addToWaitingList(int $id): array
    {
        return $this->finalise($id, waiting: true);
    }

    /**
     * Public peek — no sensitive instructor data beyond school-facing details.
     *
     * @return array<string, mixed>
     */
    public function peekPublic(string $token): array
    {
        $intake = $this->findByToken($token);
        $this->assertOpenOrExplain($intake);
        $org = Organisation::findOne(['id' => (int) $intake->organisation_id]);
        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => (int) $intake->organisation_id])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        return [
            'status' => 'open',
            'prefill' => [
                'first_name' => $intake->prefill_first_name,
                'last_name' => $intake->prefill_last_name,
                'mobile' => $intake->prefill_mobile,
                'email' => $intake->prefill_email,
            ],
            'instructor' => [
                'display_name' => $instructor?->display_name ?? $org?->name,
                'business_name' => $org?->name,
                'service_area' => $org?->service_area,
                'has_cancellation_policy' => $org?->cancellation_policy !== null
                    && trim((string) $org->cancellation_policy) !== '',
            ],
            'skill_groups' => self::SKILL_GROUPS,
            'sections' => ['you', 'driving', 'tests', 'availability', 'about', 'done'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function termsPublic(string $token): array
    {
        $intake = $this->findByToken($token);
        $this->assertOpenOrExplain($intake);
        $org = Organisation::findOne(['id' => (int) $intake->organisation_id]);

        return [
            'business_name' => $org?->name,
            'cancellation_policy' => $org?->cancellation_policy,
        ];
    }

    /**
     * @param array<string, mixed> $answers
     * @return array<string, mixed>
     */
    public function submitPublic(string $token, array $answers): array
    {
        $intake = $this->findByToken($token);
        $this->assertOpenOrExplain($intake);
        $this->guardSubmitRate($intake);

        $normalized = $this->normalizeAnswers($answers);
        $now = gmdate('Y-m-d H:i:s');
        $intake->setAnswers($normalized);
        $intake->status = LearnerIntake::STATUS_SUBMITTED;
        $intake->submitted_at = $now;
        if (!empty($normalized['terms_acknowledged'])) {
            $intake->terms_acknowledged_at = $now;
        }
        $intake->updated_at = $now;
        if (!$intake->save()) {
            throw new BadRequestHttpException($this->firstError($intake));
        }

        return [
            'status' => 'submitted',
            'message' => 'Thanks — your instructor will review this shortly.',
            'first_name' => $normalized['identity']['first_name'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function finalise(int $id, bool $waiting): array
    {
        $intake = $this->findOwned($id);
        $answers = $intake->getAnswers();
        $now = gmdate('Y-m-d H:i:s');

        // Fresh review — create pupil from answers.
        if ($intake->status === LearnerIntake::STATUS_SUBMITTED) {
            $tx = Yii::$app->db->beginTransaction();
            try {
                $learner = new Learner();
                $learner->organisation_id = (int) $intake->organisation_id;
                $learner->created_at = $now;
                $this->applyAnswersToLearner($learner, $answers, $intake, $waiting, $now);
                if (!$learner->save()) {
                    throw new BadRequestHttpException($this->firstError($learner));
                }

                $windows = $this->availabilityWindowsFromAnswers($answers);
                if ($windows !== []) {
                    (new AvailabilityService())->replaceForLearner((int) $learner->id, $windows);
                }

                $intake->learner_id = (int) $learner->id;
                $intake->status = $waiting ? LearnerIntake::STATUS_WAITING : LearnerIntake::STATUS_ACCEPTED;
                $intake->reviewed_at = $now;
                $intake->updated_at = $now;
                $intake->save(false);

                $tx->commit();
            } catch (\Throwable $e) {
                $tx->rollBack();
                throw $e;
            }

            return $this->finaliseResult($intake, $learner, $waiting);
        }

        // Move waiting ↔ active without recreating the pupil.
        if ($waiting && $intake->status === LearnerIntake::STATUS_ACCEPTED) {
            $learner = $this->learnerForIntake($intake);
            $learner->lifecycle = Learner::LIFECYCLE_WAITING;
            $learner->waiting_list_joined_at = $now;
            $learner->updated_at = $now;
            $learner->save(false, ['lifecycle', 'waiting_list_joined_at', 'updated_at']);
            $intake->status = LearnerIntake::STATUS_WAITING;
            $intake->updated_at = $now;
            $intake->save(false, ['status', 'updated_at']);

            return $this->finaliseResult($intake, $learner, true);
        }

        if (!$waiting && $intake->status === LearnerIntake::STATUS_WAITING) {
            $learner = $this->learnerForIntake($intake);
            $learner->lifecycle = Learner::LIFECYCLE_ACTIVE;
            $learner->waiting_list_joined_at = null;
            $learner->updated_at = $now;
            $learner->save(false, ['lifecycle', 'waiting_list_joined_at', 'updated_at']);
            $intake->status = LearnerIntake::STATUS_ACCEPTED;
            $intake->updated_at = $now;
            $intake->save(false, ['status', 'updated_at']);

            return $this->finaliseResult($intake, $learner, false);
        }

        throw new BadRequestHttpException('This intake cannot be updated that way right now.');
    }

    private function learnerForIntake(LearnerIntake $intake): Learner
    {
        if ($intake->learner_id === null) {
            throw new BadRequestHttpException('This intake is not linked to a pupil yet.');
        }
        $learner = Learner::findOne([
            'id' => (int) $intake->learner_id,
            'organisation_id' => (int) $intake->organisation_id,
        ]);
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found for this intake.');
        }

        return $learner;
    }

    /**
     * @return array<string, mixed>
     */
    private function finaliseResult(LearnerIntake $intake, Learner $learner, bool $waiting): array
    {
        return [
            'intake_id' => (int) $intake->id,
            'learner_id' => (int) $learner->id,
            'lifecycle' => $learner->lifecycle,
            'full_name' => $learner->fullName,
            'message' => $waiting
                ? $learner->fullName . ' is on your waiting list.'
                : $learner->fullName . ' is now a pupil.',
        ];
    }

    /**
     * Create a learner from intake-format answers (used by enquiry conversion).
     *
     * @param array<string, mixed> $answers
     */
    public function createLearnerFromAnswers(int $orgId, array $answers, bool $waiting): Learner
    {
        $now = gmdate('Y-m-d H:i:s');
        $learner = new Learner();
        $learner->organisation_id = $orgId;
        $learner->created_at = $now;
        $this->applyAnswersToLearner($learner, $answers, null, $waiting, $now);
        if (!$learner->save()) {
            throw new BadRequestHttpException($this->firstError($learner));
        }
        $windows = $this->availabilityWindowsFromAnswers($answers);
        if ($windows !== []) {
            (new AvailabilityService())->replaceForLearner((int) $learner->id, $windows);
        }

        return $learner;
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function applyAnswersToLearner(
        Learner $learner,
        array $answers,
        ?LearnerIntake $intake,
        bool $waiting,
        string $now,
    ): void {
        $identity = is_array($answers['identity'] ?? null) ? $answers['identity'] : [];
        $learner->first_name = trim((string) ($identity['first_name'] ?? ''));
        $learner->last_name = trim((string) ($identity['last_name'] ?? ''));
        $learner->mobile = trim((string) ($identity['mobile'] ?? ''));
        $email = trim((string) ($identity['email'] ?? ''));
        $learner->email = $email === '' ? null : mb_strtolower($email);
        $pickup = trim((string) ($identity['default_pickup_address'] ?? ''));
        $learner->default_pickup_address = $pickup === '' ? null : $pickup;

        if ($learner->first_name === '' || $learner->last_name === '' || $learner->mobile === '') {
            throw new BadRequestHttpException('Name and mobile are required to accept this pupil.');
        }

        $driving = is_array($answers['driving'] ?? null) ? $answers['driving'] : [];
        $tests = is_array($answers['tests'] ?? null) ? $answers['tests'] : [];
        $about = is_array($answers['about'] ?? null) ? $answers['about'] : [];
        $availability = is_array($answers['availability'] ?? null) ? $answers['availability'] : [];

        $transmission = $driving['transmission'] ?? null;
        $learner->transmission = in_array($transmission, ['manual', 'automatic', 'either'], true)
            ? $transmission
            : null;

        $theory = $tests['theory_status'] ?? null;
        $learner->theory_status = in_array($theory, ['passed', 'not_yet', 'booked'], true) ? $theory : null;
        // Only store pass date when they actually passed — booked dates stay in reported answers.
        $learner->theory_pass_date = $theory === 'passed'
            ? $this->optionalDate($tests['theory_pass_date'] ?? null)
            : null;

        if (($tests['practical_booked'] ?? null) === true) {
            $learner->test_date = $this->optionalDate($tests['practical_date'] ?? null);
            $centre = trim((string) ($tests['practical_centre'] ?? ''));
            $learner->test_centre = $centre === '' ? null : mb_substr($centre, 0, 255);
            $time = trim((string) ($tests['practical_time'] ?? ''));
            $learner->practical_test_time = preg_match('/^\d{2}:\d{2}$/', $time) === 1 ? $time : null;
        }

        $learner->availability_is_variable = !empty($availability['changes_often']);
        $contact = $about['preferred_contact'] ?? null;
        $learner->preferred_contact = in_array($contact, ['sms', 'whatsapp', 'email', 'call'], true)
            ? $contact
            : null;

        $learner->lifecycle = $waiting ? Learner::LIFECYCLE_WAITING : Learner::LIFECYCLE_ACTIVE;
        $learner->waiting_list_joined_at = $waiting ? $now : null;
        $learner->intake_id = $intake !== null ? (int) $intake->id : null;
        $learner->terms_acknowledged_at = $intake?->terms_acknowledged_at;
        $learner->updated_at = $now;

        $reported = [
            'source' => $intake !== null ? 'learner_intake' : 'enquiry',
            'intake_id' => $intake?->id,
            'submitted_at' => $intake?->submitted_at,
            'provenance' => 'learner_supplied',
            'driving' => $driving,
            'skills_practised' => array_values(array_filter(
                is_array($driving['skills_practised'] ?? null) ? $driving['skills_practised'] : [],
                static fn ($k) => is_string($k) && isset(self::SKILL_GROUPS[$k]),
            )),
            'about' => $about,
            'availability_note' => $availability['note'] ?? null,
        ];
        $learner->learner_reported_json = json_encode($reported, JSON_THROW_ON_ERROR);

        // Human context for instructor — never treated as assessed progress.
        $bits = [];
        if (!empty($about['instructor_should_know'])) {
            $bits[] = trim((string) $about['instructor_should_know']);
        }
        if (!empty($about['goal'])) {
            $bits[] = 'Goal: ' . trim((string) $about['goal']);
        }
        if ($bits !== []) {
            $existing = trim((string) ($learner->private_notes ?? ''));
            $block = "From intake:\n" . implode("\n", $bits);
            $learner->private_notes = $existing === '' ? $block : $existing . "\n\n" . $block;
        }
    }

    /**
     * @param array<string, mixed> $answers
     * @return list<array<string, mixed>>
     */
    public function availabilityWindowsFromAnswers(array $answers): array
    {
        $availability = is_array($answers['availability'] ?? null) ? $answers['availability'] : [];
        $days = is_array($availability['days'] ?? null) ? $availability['days'] : [];
        $windows = [];
        foreach ($days as $day) {
            if (!is_array($day)) {
                continue;
            }
            $weekday = (int) ($day['weekday'] ?? 0);
            if ($weekday < 1 || $weekday > 7) {
                continue;
            }
            $slots = is_array($day['slots'] ?? null) ? $day['slots'] : [];
            foreach ($slots as $slot) {
                $slot = (string) $slot;
                if ($slot === 'flexible') {
                    $windows[] = ['weekday' => $weekday, 'mode' => 'flexible'];
                } elseif ($slot === 'morning') {
                    $windows[] = [
                        'weekday' => $weekday,
                        'mode' => 'between',
                        'start_time' => '09:00',
                        'end_time' => '12:00',
                    ];
                } elseif ($slot === 'afternoon') {
                    $windows[] = [
                        'weekday' => $weekday,
                        'mode' => 'between',
                        'start_time' => '12:00',
                        'end_time' => '17:00',
                    ];
                } elseif ($slot === 'evening') {
                    $windows[] = [
                        'weekday' => $weekday,
                        'mode' => 'after',
                        'start_time' => '17:00',
                    ];
                } elseif ($slot === 'after_4') {
                    $windows[] = [
                        'weekday' => $weekday,
                        'mode' => 'after',
                        'start_time' => '16:00',
                    ];
                } elseif ($slot === 'before_2') {
                    $windows[] = [
                        'weekday' => $weekday,
                        'mode' => 'before',
                        'end_time' => '14:00',
                    ];
                }
            }
        }

        return $windows;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function normalizeAnswers(array $raw): array
    {
        $identity = is_array($raw['identity'] ?? null) ? $raw['identity'] : [];
        $first = trim((string) ($identity['first_name'] ?? ''));
        $last = trim((string) ($identity['last_name'] ?? ''));
        $mobile = trim((string) ($identity['mobile'] ?? ''));
        if ($first === '' || $last === '' || $mobile === '') {
            throw new BadRequestHttpException('Please tell us your name and mobile number.');
        }
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if (strlen($digits) < 10) {
            throw new BadRequestHttpException('That mobile number looks too short.');
        }
        $email = trim((string) ($identity['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('That email address looks invalid.');
        }

        $driving = is_array($raw['driving'] ?? null) ? $raw['driving'] : [];
        $hadLessons = $driving['had_lessons_before'] ?? null;
        if ($hadLessons === false || $hadLessons === 'no') {
            // Strip experience follow-ups for complete beginners.
            $driving = [
                'had_lessons_before' => false,
                'driven_before' => $driving['driven_before'] ?? 'no',
                'transmission' => $driving['transmission'] ?? null,
            ];
        }

        return [
            'identity' => [
                'first_name' => mb_substr($first, 0, 100),
                'last_name' => mb_substr($last, 0, 100),
                'mobile' => mb_substr($mobile, 0, 32),
                'email' => $email === '' ? null : mb_strtolower($email),
                'default_pickup_address' => $this->optionalText($identity['default_pickup_address'] ?? null),
            ],
            'driving' => $driving,
            'tests' => is_array($raw['tests'] ?? null) ? $raw['tests'] : [],
            'availability' => is_array($raw['availability'] ?? null) ? $raw['availability'] : [],
            'about' => is_array($raw['about'] ?? null) ? $raw['about'] : [],
            'terms_acknowledged' => !empty($raw['terms_acknowledged']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBrief(LearnerIntake $intake): array
    {
        $answers = $intake->getAnswers();
        $identity = is_array($answers['identity'] ?? null) ? $answers['identity'] : [];
        $driving = is_array($answers['driving'] ?? null) ? $answers['driving'] : [];
        $tests = is_array($answers['tests'] ?? null) ? $answers['tests'] : [];
        $availability = is_array($answers['availability'] ?? null) ? $answers['availability'] : [];
        $about = is_array($answers['about'] ?? null) ? $answers['about'] : [];

        $skills = [];
        foreach (is_array($driving['skills_practised'] ?? null) ? $driving['skills_practised'] : [] as $key) {
            if (is_string($key) && isset(self::SKILL_GROUPS[$key])) {
                $skills[] = self::SKILL_GROUPS[$key];
            }
        }

        $theory = TheoryCertificate::statusPayload(
            isset($tests['theory_status']) ? (string) $tests['theory_status'] : null,
            isset($tests['theory_pass_date']) ? (string) $tests['theory_pass_date'] : null,
        );

        $attention = $this->attentionFlags($driving, $tests, $theory);

        return [
            'id' => (int) $intake->id,
            'status' => $intake->status,
            'submitted_at' => $intake->submitted_at,
            'learner_id' => $intake->learner_id !== null ? (int) $intake->learner_id : null,
            'headline' => [
                'full_name' => trim(($identity['first_name'] ?? '') . ' ' . ($identity['last_name'] ?? '')),
                'tag' => 'New pupil',
                'transmission' => $driving['transmission'] ?? null,
                'area' => $identity['default_pickup_address'] ?? null,
            ],
            'contact' => [
                'mobile' => $identity['mobile'] ?? null,
                'email' => $identity['email'] ?? null,
                'pickup' => $identity['default_pickup_address'] ?? null,
                'preferred_contact' => $about['preferred_contact'] ?? null,
            ],
            'experience' => $this->experienceSummary($driving),
            'skills_learner_says' => $skills,
            'theory' => $theory,
            'practical' => [
                'booked' => ($tests['practical_booked'] ?? null) === true,
                'date' => $tests['practical_date'] ?? null,
                'time' => $tests['practical_time'] ?? null,
                'centre' => $tests['practical_centre'] ?? null,
            ],
            'availability' => [
                'changes_often' => !empty($availability['changes_often']),
                'labels' => $this->availabilityLabels($availability),
                'note' => $availability['note'] ?? null,
            ],
            'goal' => $about['goal'] ?? null,
            'instructor_should_know' => $about['instructor_should_know'] ?? null,
            'confidence' => $about['confidence'] ?? null,
            'private_practice' => $about['private_practice'] ?? null,
            'attention' => $attention,
            'terms_acknowledged_at' => $intake->terms_acknowledged_at,
            'actions' => [
                'can_accept' => $intake->status === LearnerIntake::STATUS_SUBMITTED
                    || $intake->status === LearnerIntake::STATUS_WAITING,
                'can_waitlist' => $intake->status === LearnerIntake::STATUS_SUBMITTED
                    || $intake->status === LearnerIntake::STATUS_ACCEPTED,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $driving
     * @return array<string, mixed>
     */
    private function experienceSummary(array $driving): array
    {
        $had = $driving['had_lessons_before'] ?? null;
        if ($had === false || $had === 'no') {
            $driven = (string) ($driving['driven_before'] ?? 'no');
            if ($driven === 'private') {
                $lines = ['Private practice only — no formal lessons yet'];
                if (!empty($driving['last_drove'])) {
                    $lines[] = 'Last drove: ' . $this->lastDroveLabel((string) $driving['last_drove']);
                }

                return [
                    'level' => 'private_practice',
                    'lines' => $lines,
                ];
            }

            return [
                'level' => 'beginner',
                'lines' => ['Completely new to lessons'],
            ];
        }

        $lines = [];
        if (!empty($driving['lesson_hours_band'])) {
            $lines[] = 'Around ' . $this->hoursBandLabel((string) $driving['lesson_hours_band']) . ' previous lesson hours';
        }
        if (!empty($driving['last_drove'])) {
            $lines[] = 'Last drove: ' . $this->lastDroveLabel((string) $driving['last_drove']);
        }
        if (($driving['driven_before'] ?? null) === 'private'
            || ($driving['driven_before'] ?? null) === 'both'
        ) {
            $lines[] = 'Some private practice';
        }
        if (!empty($driving['remember_working_on'])) {
            $lines[] = 'Remembers working on: ' . trim((string) $driving['remember_working_on']);
        }

        return [
            'level' => 'experienced',
            'lines' => $lines !== [] ? $lines : ['Has had lessons before'],
        ];
    }

    /**
     * @param array<string, mixed> $driving
     * @param array<string, mixed> $tests
     * @param array<string, mixed>|null $theory
     * @return list<array{code: string, message: string}>
     */
    private function attentionFlags(array $driving, array $tests, ?array $theory): array
    {
        $flags = [];
        if ($theory !== null && in_array($theory['urgency'] ?? '', ['soon', 'approaching', 'expired'], true)) {
            $flags[] = [
                'code' => 'theory_expiry',
                'message' => (string) $theory['label'],
            ];
        }
        if (($tests['practical_booked'] ?? null) === true && !empty($tests['practical_date'])) {
            $date = (string) $tests['practical_date'];
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('UTC'));
            if ($dt !== false) {
                $now = new DateTimeImmutable('today', new DateTimeZone('UTC'));
                $days = (int) floor(($dt->getTimestamp() - $now->getTimestamp()) / 86400);
                if ($days >= 0 && $days <= 56) {
                    $flags[] = [
                        'code' => 'practical_soon',
                        'message' => 'Practical test already booked in ' . $days . ' '
                            . ($days === 1 ? 'day' : 'days'),
                    ];
                }
            }
        }
        if (($driving['last_drove'] ?? null) === 'over_6_months' || ($driving['last_drove'] ?? null) === 'over_a_year') {
            $flags[] = [
                'code' => 'long_break',
                'message' => 'Returning after a long break',
            ];
        }
        $band = (string) ($driving['lesson_hours_band'] ?? '');
        if (in_array($band, ['20_40', '40_plus'], true)) {
            $flags[] = [
                'code' => 'significant_experience',
                'message' => 'Significant previous experience — consider an assessment lesson',
            ];
        }

        return $flags;
    }

    /**
     * @param array<string, mixed> $availability
     * @return list<string>
     */
    private function availabilityLabels(array $availability): array
    {
        $labels = [];
        $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $slotNames = [
            'morning' => 'morning',
            'afternoon' => 'afternoon',
            'evening' => 'evening',
            'flexible' => 'flexible',
            'after_4' => 'after 4pm',
            'before_2' => 'before 2pm',
        ];
        foreach (is_array($availability['days'] ?? null) ? $availability['days'] : [] as $day) {
            if (!is_array($day)) {
                continue;
            }
            $weekday = (int) ($day['weekday'] ?? 0);
            $name = $dayNames[$weekday] ?? null;
            if ($name === null) {
                continue;
            }
            foreach (is_array($day['slots'] ?? null) ? $day['slots'] : [] as $slot) {
                $slot = (string) $slot;
                if (isset($slotNames[$slot])) {
                    $labels[] = $name . ' ' . $slotNames[$slot];
                }
            }
        }
        if (!empty($availability['changes_often'])) {
            $labels[] = 'Availability often changes';
        }

        return $labels;
    }

    private function hoursBandLabel(string $band): string
    {
        return match ($band) {
            'under_5' => 'under 5',
            '5_10' => '5–10',
            '10_20' => '10–20',
            '20_40' => '20–40',
            '40_plus' => '40+',
            'not_sure' => 'an unknown number of',
            default => $band,
        };
    }

    private function lastDroveLabel(string $value): string
    {
        return match ($value) {
            'this_week' => 'this week',
            'this_month' => 'this month',
            '1_3_months' => '1–3 months ago',
            '3_6_months' => '3–6 months ago',
            'over_6_months' => 'over 6 months ago',
            'over_a_year' => 'over a year ago',
            'not_sure' => 'not sure',
            default => $value,
        };
    }

    private function findOwned(int $id): LearnerIntake
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var LearnerIntake|null $intake */
        $intake = LearnerIntake::find()
            ->andWhere(['id' => $id, 'organisation_id' => $orgId])
            ->one();
        if ($intake === null) {
            throw new NotFoundHttpException('Intake not found.');
        }

        return $intake;
    }

    private function findByToken(string $token): LearnerIntake
    {
        $token = trim($token);
        if ($token === '') {
            throw new BadRequestHttpException('This link is missing its code.');
        }
        $hash = hash('sha256', $token);
        /** @var LearnerIntake|null $intake */
        $intake = LearnerIntake::find()->andWhere(['invite_token_hash' => $hash])->one();
        if ($intake === null) {
            throw new NotFoundHttpException('This link is invalid or has already been used.');
        }

        return $intake;
    }

    private function assertOpenOrExplain(LearnerIntake $intake): void
    {
        if ($intake->isRevoked) {
            throw new ForbiddenHttpException('This link has been revoked. Ask your instructor for a new one.');
        }
        if ($intake->isExpired) {
            throw new BadRequestHttpException('This link has expired. Ask your instructor for a new one.');
        }
        if ($intake->status === LearnerIntake::STATUS_SUBMITTED) {
            throw new BadRequestHttpException('You’ve already sent your details. Your instructor will be in touch.');
        }
        if ($intake->status !== LearnerIntake::STATUS_OPEN) {
            throw new BadRequestHttpException('This link is no longer available.');
        }
    }

    private function guardSubmitRate(LearnerIntake $intake): void
    {
        // One submission per invite; light abuse guard on open invites by IP.
        if (!Yii::$app->has('cache') || Yii::$app->cache === null) {
            return;
        }
        $ip = (string) (Yii::$app->request->userIP ?? 'unknown');
        $cache = Yii::$app->cache;
        $key = 'intake_submit_' . $intake->id . '_' . $ip;
        $hits = (int) $cache->get($key);
        if ($hits >= 8) {
            throw new TooManyRequestsHttpException('Too many attempts. Please try again later.');
        }
        $cache->set($key, $hits + 1, 3600);
    }

    /**
     * @return array<string, mixed>
     */
    private function invitePayload(LearnerIntake $intake, string $plainToken): array
    {
        return [
            'id' => (int) $intake->id,
            'status' => $intake->status,
            'invite_path' => '/join?token=' . urlencode($plainToken),
            'invite_expires_at' => $intake->invite_expires_at,
            'message' => 'Share this link with your pupil. They fill in the useful bits — you barely have to.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instructorListItem(LearnerIntake $intake): array
    {
        $answers = $intake->getAnswers();
        $identity = is_array($answers['identity'] ?? null) ? $answers['identity'] : [];
        $name = trim(($identity['first_name'] ?? $intake->prefill_first_name ?? '') . ' '
            . ($identity['last_name'] ?? $intake->prefill_last_name ?? ''));

        return [
            'id' => (int) $intake->id,
            'status' => $intake->status,
            'display_name' => $name !== '' ? $name : 'Pupil link',
            'submitted_at' => $intake->submitted_at,
            'created_at' => $intake->created_at,
            'learner_id' => $intake->learner_id !== null ? (int) $intake->learner_id : null,
            'is_expired' => $intake->isExpired,
            'is_revoked' => $intake->isRevoked,
        ];
    }

    private function currentInstructor(int $orgId): ?Instructor
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }

        return Instructor::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'user_id' => (int) Yii::$app->user->id,
            ])
            ->one();
    }

    private function optionalName(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, 100);
    }

    private function optionalMobile(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : mb_substr($text, 0, 32);
    }

    private function optionalEmail(mixed $value): ?string
    {
        $text = mb_strtolower(trim((string) ($value ?? '')));
        if ($text === '') {
            return null;
        }
        if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Email looks invalid.');
        }

        return $text;
    }

    private function optionalText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function optionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = trim((string) $value);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return ($dt !== false && $dt->format('Y-m-d') === $date) ? $date : null;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'Could not save.';
    }
}
