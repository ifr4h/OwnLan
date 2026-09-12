<?php

declare(strict_types=1);

namespace app\services;

use app\components\OrganisationTime;
use app\components\PublicContentSanitizer;
use app\components\TeachingAreaMatcher;
use app\components\TenantContext;
use app\models\Enquiry;
use app\models\Instructor;
use app\models\Learner;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\TooManyRequestsHttpException;

class EnquiryService
{
    private PublicProfileService $profiles;
    private EnquiryFitService $fit;
    private IntakeService $intake;
    private LessonService $lessons;

    public function __construct(
        ?PublicProfileService $profiles = null,
        ?EnquiryFitService $fit = null,
        ?IntakeService $intake = null,
        ?LessonService $lessons = null,
    ) {
        $this->profiles = $profiles ?? new PublicProfileService();
        $this->fit = $fit ?? new EnquiryFitService();
        $this->intake = $intake ?? new IntakeService();
        $this->lessons = $lessons ?? new LessonService();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function submitPublic(string $slug, array $data): array
    {
        $org = $this->profiles->findPublishedOrganisation($slug);
        $mode = $org->profile_acquisition_mode ?? PublicProfileService::ACQUISITION_CLOSED;

        if (!empty($data['website'])) {
            return $this->fakeSuccess($data);
        }

        $this->guardPublicRate($org);

        $waitingOnly = $mode === PublicProfileService::ACQUISITION_WAITING_LIST;
        $closed = $mode === PublicProfileService::ACQUISITION_CLOSED;
        if ($closed && empty($org->profile_allow_waiting_list)) {
            throw new BadRequestHttpException('This instructor is not taking enquiries at the moment.');
        }
        if ($closed && !$waitingOnly) {
            $waitingOnly = true;
        }

        $instructor = Instructor::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        $normalized = $this->normalizeSubmission($data);
        $now = gmdate('Y-m-d H:i:s');

        $enquiry = new Enquiry();
        $enquiry->organisation_id = (int) $org->id;
        $enquiry->instructor_id = $instructor?->id;
        $enquiry->first_name = $normalized['first_name'];
        $enquiry->last_name = $normalized['last_name'];
        $enquiry->email = $normalized['email'];
        $enquiry->mobile = $normalized['mobile'];
        $enquiry->postcode = $normalized['postcode'];
        $enquiry->transmission = $normalized['transmission'];
        $enquiry->experience_band = $normalized['experience_band'];
        $enquiry->setAvailability($normalized['availability']);
        $enquiry->desired_start = $normalized['desired_start'];
        $enquiry->theory_status = $normalized['theory_status'];
        $enquiry->practical_test_date = $normalized['practical_test_date'];
        $enquiry->message = $normalized['message'];
        $enquiry->service_interest = $this->optionalServiceInterest($data['service_interest'] ?? null);
        $enquiry->source = $waitingOnly ? Enquiry::SOURCE_WAITING_LIST : Enquiry::SOURCE_PROFILE;
        $enquiry->source_tag = $this->optionalSourceTag($data['source_tag'] ?? null);
        $enquiry->status = Enquiry::STATUS_NEW;
        $enquiry->created_at = $now;
        $enquiry->updated_at = $now;

        if (!$enquiry->save()) {
            throw new BadRequestHttpException($this->firstError($enquiry));
        }

        (new PublicAnalyticsService())->track(
            (int) $org->id,
            PublicAnalyticsService::EVENT_ENQUIRY_SUBMITTED,
            $enquiry->source_tag,
        );

        $instructorName = $instructor?->display_name ?? $org->name;

        return [
            'status' => 'submitted',
            'first_name' => $enquiry->first_name,
            'instructor_name' => $instructorName,
            'message' => 'Your enquiry has been sent to ' . $instructorName . '.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function areaCheck(string $slug, string $postcode): array
    {
        $org = $this->profiles->findPublishedOrganisation($slug);
        $areas = json_decode((string) ($org->profile_teaching_areas ?? ''), true);
        $areaList = is_array($areas) ? $areas : [];

        return (new TeachingAreaMatcher())->check($postcode, $areaList, $org->service_area);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForInstructor(?string $status = null, ?string $transmission = null): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $q = Enquiry::find()
            ->andWhere(['organisation_id' => $orgId])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(200);
        if ($status !== null && $status !== '' && $status !== 'all') {
            $q->andWhere(['status' => $status]);
        }
        if ($transmission !== null && $transmission !== '' && in_array($transmission, ['manual', 'automatic'], true)) {
            $q->andWhere(['transmission' => $transmission]);
        }

        return array_map(fn (Enquiry $e) => $this->listItem($e), $q->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function view(int $id): array
    {
        $enquiry = $this->findOwned($id);
        $org = Organisation::findOne(['id' => (int) $enquiry->organisation_id]);
        if ($org === null) {
            throw new NotFoundHttpException('Enquiry not found.');
        }

        return $this->detail($enquiry, $org);
    }

    /**
     * @return array<string, mixed>
     */
    public function markContacted(int $id): array
    {
        $enquiry = $this->findOwned($id);
        if (in_array($enquiry->status, [Enquiry::STATUS_DECLINED, Enquiry::STATUS_CONVERTED], true)) {
            throw new BadRequestHttpException('This enquiry cannot be updated.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $enquiry->status = Enquiry::STATUS_CONTACTED;
        $enquiry->contacted_at = $now;
        $enquiry->updated_at = $now;
        $enquiry->save(false, ['status', 'contacted_at', 'updated_at']);

        return $this->view($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function accept(int $id): array
    {
        $enquiry = $this->findOwned($id);
        if (!in_array($enquiry->status, [Enquiry::STATUS_NEW, Enquiry::STATUS_CONTACTED, Enquiry::STATUS_WAITING], true)) {
            throw new BadRequestHttpException('This enquiry cannot be accepted.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $enquiry->status = Enquiry::STATUS_ACCEPTED;
        $enquiry->reviewed_at = $now;
        $enquiry->updated_at = $now;
        $enquiry->save(false, ['status', 'reviewed_at', 'updated_at']);

        return array_merge($this->view($id), [
            'duplicates' => $this->findDuplicates($enquiry),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function addToWaitingList(int $id): array
    {
        return $this->convert($id, waiting: true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function decline(int $id, array $data = []): array
    {
        $enquiry = $this->findOwned($id);
        if (in_array($enquiry->status, [Enquiry::STATUS_DECLINED, Enquiry::STATUS_CONVERTED], true)) {
            throw new BadRequestHttpException('This enquiry is already closed.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        $enquiry->status = Enquiry::STATUS_DECLINED;
        $enquiry->decline_reason = $reason === '' ? null : mb_substr($reason, 0, 1000);
        $enquiry->reviewed_at = $now;
        $enquiry->updated_at = $now;
        $enquiry->save(false, ['status', 'decline_reason', 'reviewed_at', 'updated_at']);

        return $this->view($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function convert(int $id, bool $waiting = false): array
    {
        $enquiry = $this->findOwned($id);
        if ($enquiry->converted_learner_id !== null) {
            throw new BadRequestHttpException('This enquiry already has a pupil record.');
        }
        if (!in_array($enquiry->status, [
            Enquiry::STATUS_NEW,
            Enquiry::STATUS_CONTACTED,
            Enquiry::STATUS_ACCEPTED,
            Enquiry::STATUS_WAITING,
        ], true)) {
            throw new BadRequestHttpException('This enquiry cannot be converted.');
        }

        $answers = $this->answersFromEnquiry($enquiry);
        $learner = $this->intake->createLearnerFromAnswers((int) $enquiry->organisation_id, $answers, $waiting);

        $now = gmdate('Y-m-d H:i:s');
        $enquiry->converted_learner_id = (int) $learner->id;
        $enquiry->status = Enquiry::STATUS_CONVERTED;
        $enquiry->reviewed_at = $now;
        $enquiry->updated_at = $now;
        $enquiry->save(false, ['converted_learner_id', 'status', 'reviewed_at', 'updated_at']);

        return [
            'enquiry_id' => (int) $enquiry->id,
            'learner_id' => (int) $learner->id,
            'full_name' => $learner->fullName,
            'lifecycle' => $learner->lifecycle,
            'path' => '/pupils/' . (int) $learner->id,
            'message' => $waiting
                ? $learner->fullName . ' is on your waiting list.'
                : $learner->fullName . ' is now a pupil.',
            'suggested_times' => $this->fit->suggestedTimes(
                Organisation::findOne(['id' => (int) $enquiry->organisation_id]),
                $enquiry,
            )['items'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function bookFirstLesson(int $id, array $data): array
    {
        $enquiry = $this->findOwned($id);
        if ($enquiry->converted_learner_id === null) {
            throw new BadRequestHttpException('Add them as a pupil first.');
        }
        $startsLocal = trim((string) ($data['starts_at_local'] ?? ''));
        if ($startsLocal === '') {
            throw new BadRequestHttpException('Choose a lesson time.');
        }
        $duration = (int) ($data['duration_minutes'] ?? 0);
        if ($duration < 15) {
            $org = Organisation::findOne(['id' => (int) $enquiry->organisation_id]);
            $duration = $org?->defaultLessonDurationMinutes() ?? 60;
        }

        $lesson = $this->lessons->create([
            'learner_id' => (int) $enquiry->converted_learner_id,
            'starts_at_local' => $startsLocal,
            'duration_minutes' => $duration,
            'pickup_address' => trim((string) $enquiry->postcode),
        ]);

        return [
            'lesson_id' => (int) $lesson['id'],
            'path' => '/lessons/' . (int) $lesson['id'],
            'message' => 'First lesson booked.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $monthStart = gmdate('Y-m-01 00:00:00');
        $rows = Enquiry::find()
            ->select(['status', 'source', 'COUNT(*) AS cnt'])
            ->andWhere(['organisation_id' => $orgId])
            ->andWhere(['>=', 'created_at', $monthStart])
            ->groupBy(['status', 'source'])
            ->asArray()
            ->all();

        $byStatus = [];
        $bySource = [];
        $total = 0;
        foreach ($rows as $row) {
            $count = (int) $row['cnt'];
            $total += $count;
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + $count;
            $bySource[$row['source']] = ($bySource[$row['source']] ?? 0) + $count;
        }

        return [
            'month_label' => 'This month',
            'total' => $total,
            'new' => (int) ($byStatus[Enquiry::STATUS_NEW] ?? 0),
            'converted' => (int) ($byStatus[Enquiry::STATUS_CONVERTED] ?? 0),
            'waiting' => (int) ($byStatus[Enquiry::STATUS_WAITING] ?? 0),
            'declined' => (int) ($byStatus[Enquiry::STATUS_DECLINED] ?? 0),
            'by_source' => $bySource,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Enquiry $enquiry, Organisation $org): array
    {
        return [
            'id' => (int) $enquiry->id,
            'status' => $enquiry->status,
            'status_label' => $this->statusLabel($enquiry->status),
            'full_name' => $enquiry->getFullName(),
            'first_name' => $enquiry->first_name,
            'last_name' => $enquiry->last_name,
            'email' => $enquiry->email,
            'mobile' => $enquiry->mobile,
            'postcode' => $enquiry->postcode,
            'transmission' => $enquiry->transmission,
            'experience_band' => $enquiry->experience_band,
            'experience_label' => $this->experienceLabel((string) ($enquiry->experience_band ?? '')),
            'availability' => $enquiry->getAvailability(),
            'desired_start' => $enquiry->desired_start,
            'desired_start_label' => $this->desiredStartLabel((string) ($enquiry->desired_start ?? '')),
            'theory_status' => $enquiry->theory_status,
            'practical_test_date' => $enquiry->practical_test_date,
            'message' => $enquiry->message,
            'source' => $enquiry->source,
            'source_tag' => $enquiry->source_tag,
            'service_interest' => $enquiry->service_interest,
            'created_at' => $enquiry->created_at,
            'contacted_at' => $enquiry->contacted_at,
            'converted_learner_id' => $enquiry->converted_learner_id,
            'learner_path' => $enquiry->converted_learner_id
                ? '/pupils/' . (int) $enquiry->converted_learner_id
                : null,
            'fit' => $this->fit->context($org, $enquiry),
            'duplicates' => $this->findDuplicates($enquiry),
            'actions' => $this->availableActions($enquiry),
        ];
    }

    /**
     * @return list<string>
     */
    private function availableActions(Enquiry $enquiry): array
    {
        if ($enquiry->status === Enquiry::STATUS_CONVERTED) {
            return $enquiry->converted_learner_id ? ['book_lesson', 'open_pupil'] : [];
        }
        if ($enquiry->status === Enquiry::STATUS_DECLINED) {
            return [];
        }
        $actions = ['contact', 'decline'];
        if ($enquiry->status === Enquiry::STATUS_NEW) {
            $actions[] = 'mark_contacted';
        }
        if (in_array($enquiry->status, [Enquiry::STATUS_NEW, Enquiry::STATUS_CONTACTED, Enquiry::STATUS_WAITING], true)) {
            $actions[] = 'accept';
            $actions[] = 'add_pupil';
            $actions[] = 'waiting_list';
        }
        if ($enquiry->status === Enquiry::STATUS_ACCEPTED) {
            $actions[] = 'add_pupil';
            $actions[] = 'waiting_list';
        }

        return $actions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function findDuplicates(Enquiry $enquiry): array
    {
        $orgId = (int) $enquiry->organisation_id;
        $mobileDigits = preg_replace('/\D/', '', (string) $enquiry->mobile) ?? '';
        $email = mb_strtolower(trim((string) ($enquiry->email ?? '')));

        $q = Learner::find()
            ->andWhere(['organisation_id' => $orgId, 'archived_at' => null]);
        $conditions = ['or'];
        if ($mobileDigits !== '') {
            $conditions[] = "REPLACE(mobile, ' ', '') LIKE :mobile";
        }
        if ($email !== '') {
            $conditions[] = ['email' => $email];
        }
        if (count($conditions) <= 1) {
            return [];
        }
        $q->andWhere($conditions);
        if ($mobileDigits !== '') {
            $q->addParams([':mobile' => '%' . $mobileDigits . '%']);
        }

        $matches = [];
        foreach ($q->limit(5)->all() as $learner) {
            /** @var Learner $learner */
            $reasons = [];
            if ($email !== '' && mb_strtolower((string) ($learner->email ?? '')) === $email) {
                $reasons[] = 'Same email';
            }
            $learnerDigits = preg_replace('/\D/', '', (string) $learner->mobile) ?? '';
            if ($mobileDigits !== '' && $learnerDigits === $mobileDigits) {
                $reasons[] = 'Same mobile number';
            }
            $matches[] = [
                'learner_id' => (int) $learner->id,
                'full_name' => $learner->fullName,
                'reasons' => $reasons,
                'path' => '/pupils/' . (int) $learner->id,
            ];
        }

        return $matches;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeSubmission(array $data): array
    {
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        $postcode = strtoupper(trim((string) ($data['postcode'] ?? '')));
        if ($first === '' || $last === '' || $mobile === '' || $postcode === '') {
            throw new BadRequestHttpException('Please tell us your name, mobile and postcode.');
        }
        $digits = preg_replace('/\D/', '', $mobile) ?? '';
        if (strlen($digits) < 10) {
            throw new BadRequestHttpException('That mobile number looks too short.');
        }
        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('That email address looks invalid.');
        }
        $transmission = trim((string) ($data['transmission'] ?? ''));
        if (!in_array($transmission, ['manual', 'automatic', 'either', ''], true)) {
            throw new BadRequestHttpException('Choose manual or automatic.');
        }

        return [
            'first_name' => mb_substr($first, 0, 100),
            'last_name' => mb_substr($last, 0, 100),
            'mobile' => mb_substr($mobile, 0, 32),
            'email' => $email === '' ? null : mb_strtolower($email),
            'postcode' => mb_substr($postcode, 0, 16),
            'transmission' => $transmission === '' ? null : $transmission,
            'experience_band' => $this->normalizeExperience($data['experience_band'] ?? null),
            'availability' => is_array($data['availability'] ?? null) ? $data['availability'] : [],
            'desired_start' => $this->normalizeDesiredStart($data['desired_start'] ?? null),
            'theory_status' => $this->normalizeTheory($data['theory_status'] ?? null),
            'practical_test_date' => $this->optionalDate($data['practical_test_date'] ?? null),
            'message' => $this->optionalText($data['message'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function answersFromEnquiry(Enquiry $enquiry): array
    {
        $experience = (string) ($enquiry->experience_band ?? '');
        $hadLessons = !in_array($experience, ['new', ''], true);

        return [
            'identity' => [
                'first_name' => $enquiry->first_name,
                'last_name' => $enquiry->last_name,
                'mobile' => $enquiry->mobile,
                'email' => $enquiry->email,
                'default_pickup_address' => $enquiry->postcode,
            ],
            'driving' => [
                'had_lessons_before' => $hadLessons,
                'transmission' => $enquiry->transmission,
                'lesson_hours_band' => $this->experienceToHoursBand($experience),
            ],
            'tests' => [
                'theory_status' => $enquiry->theory_status,
                'practical_booked' => $enquiry->practical_test_date !== null,
                'practical_date' => $enquiry->practical_test_date,
            ],
            'availability' => $enquiry->getAvailability(),
            'about' => [
                'instructor_should_know' => $enquiry->message,
            ],
            'desired_start' => $enquiry->desired_start,
            'terms_acknowledged' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Enquiry $enquiry): array
    {
        $org = Organisation::findOne(['id' => (int) $enquiry->organisation_id]);
        $fitFlags = $org ? $this->fit->context($org, $enquiry)['flags'] : [];

        return [
            'id' => (int) $enquiry->id,
            'full_name' => $enquiry->getFullName(),
            'postcode' => $enquiry->postcode,
            'transmission' => $enquiry->transmission,
            'status' => $enquiry->status,
            'status_label' => $this->statusLabel($enquiry->status),
            'created_at' => $enquiry->created_at,
            'summary' => $this->listSummary($enquiry),
            'service_interest' => $enquiry->service_interest,
            'fit_hint' => $fitFlags[0]['label'] ?? null,
            'path' => '/pupils/enquiries/' . (int) $enquiry->id,
        ];
    }

    private function listSummary(Enquiry $enquiry): string
    {
        $bits = array_filter([
            $enquiry->service_interest ? 'Interested in: ' . $enquiry->service_interest : null,
            $enquiry->postcode,
            $enquiry->transmission ? ucfirst((string) $enquiry->transmission) : null,
            $this->desiredStartLabel((string) ($enquiry->desired_start ?? '')),
        ]);

        return implode(' · ', $bits);
    }

    private function findOwned(int $id): Enquiry
    {
        $orgId = TenantContext::requireOrganisationId();
        $enquiry = Enquiry::findOne(['id' => $id, 'organisation_id' => $orgId]);
        if ($enquiry === null) {
            throw new NotFoundHttpException('Enquiry not found.');
        }

        return $enquiry;
    }

    private function guardPublicRate(Organisation $org): void
    {
        $ip = (string) (Yii::$app->request->userIP ?? 'unknown');
        $key = 'enquiry_rate:' . (int) $org->id . ':' . sha1($ip);
        $cache = Yii::$app->cache;
        $count = (int) $cache->get($key);
        if ($count >= 5) {
            throw new TooManyRequestsHttpException('Too many enquiries. Try again later.');
        }
        $cache->set($key, $count + 1, 3600);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fakeSuccess(array $data): array
    {
        return [
            'status' => 'submitted',
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'instructor_name' => 'your instructor',
            'message' => 'Thanks — your enquiry has been sent.',
        ];
    }

    private function optionalSourceTag(mixed $tag): ?string
    {
        $tag = strtolower(trim((string) ($tag ?? '')));
        $allowed = ['instagram', 'facebook', 'website', 'referral'];

        return in_array($tag, $allowed, true) ? $tag : null;
    }

    private function optionalServiceInterest(mixed $value): ?string
    {
        $text = PublicContentSanitizer::singleLine((string) ($value ?? ''), 120);

        return $text;
    }

    private function normalizeExperience(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        $allowed = ['new', 'few', 'many', 'returning', 'passed_before'];

        return in_array($v, $allowed, true) ? $v : null;
    }

    private function normalizeDesiredStart(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        $allowed = ['asap', 'few_weeks', 'next_month', 'flexible', 'specific'];

        return in_array($v, $allowed, true) ? $v : null;
    }

    private function normalizeTheory(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        $allowed = ['passed', 'not_yet', 'booked'];

        return in_array($v, $allowed, true) ? $v : null;
    }

    private function optionalDate(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $v);

        return $dt !== false ? $dt->format('Y-m-d') : null;
    }

    private function optionalText(mixed $value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v === '' ? null : mb_substr($v, 0, 2000);
    }

    private function experienceLabel(string $band): ?string
    {
        return match ($band) {
            'new' => 'Completely new',
            'few' => 'A few lessons',
            'many' => 'Quite a few lessons',
            'returning' => 'Driven before, not recently',
            'passed_before' => 'Passed a test before',
            default => null,
        };
    }

    private function experienceToHoursBand(string $experience): ?string
    {
        return match ($experience) {
            'few' => 'under_10',
            'many' => '10_20',
            'returning' => 'over_20',
            default => null,
        };
    }

    private function desiredStartLabel(string $code): ?string
    {
        return match ($code) {
            'asap' => 'Wants to start soon',
            'few_weeks' => 'Next few weeks',
            'next_month' => 'Next month',
            'flexible' => 'Flexible',
            default => null,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Enquiry::STATUS_NEW => 'New',
            Enquiry::STATUS_CONTACTED => 'Contacted',
            Enquiry::STATUS_WAITING => 'Waiting',
            Enquiry::STATUS_ACCEPTED => 'Accepted',
            Enquiry::STATUS_DECLINED => 'Declined',
            Enquiry::STATUS_CONVERTED => 'Added as pupil',
            default => ucfirst($status),
        };
    }

    private function firstError(Enquiry $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors ? (string) reset($errors) : 'Unable to save enquiry.';
    }
}
