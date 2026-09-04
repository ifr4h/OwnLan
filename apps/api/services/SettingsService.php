<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Instructor;
use app\models\Organisation;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Minimum instructor/business settings for MVP workflows.
 */
class SettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();

        return $this->serialize($org, $instructor);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(array $data): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();
        $now = gmdate('Y-m-d H:i:s');

        if (array_key_exists('display_name', $data)) {
            $name = trim((string) $data['display_name']);
            if ($name === '') {
                throw new BadRequestHttpException('Display name is required.');
            }
            $instructor->display_name = mb_substr($name, 0, 255);
            $instructor->updated_at = $now;
        }

        if (array_key_exists('business_name', $data)) {
            $biz = trim((string) $data['business_name']);
            if ($biz === '') {
                throw new BadRequestHttpException('Business name is required.');
            }
            $org->name = mb_substr($biz, 0, 255);
        }

        if (array_key_exists('contact_phone', $data)) {
            $phone = trim((string) ($data['contact_phone'] ?? ''));
            $org->contact_phone = $phone === '' ? null : mb_substr($phone, 0, 32);
        }

        if (array_key_exists('contact_email', $data)) {
            $email = mb_strtolower(trim((string) ($data['contact_email'] ?? '')));
            if ($email === '') {
                $org->contact_email = null;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new BadRequestHttpException('Contact email looks invalid.');
            } else {
                $org->contact_email = $email;
            }
        }

        if (array_key_exists('timezone', $data)) {
            $tz = trim((string) $data['timezone']);
            $this->assertTimezone($tz);
            $org->timezone = $tz;
        }

        if (array_key_exists('default_lesson_duration_minutes', $data)) {
            $minutes = (int) $data['default_lesson_duration_minutes'];
            if ($minutes < 15 || $minutes > 480) {
                throw new BadRequestHttpException('Normal lesson duration must be between 15 and 480 minutes.');
            }
            $org->default_lesson_duration_minutes = $minutes;
        }

        if (array_key_exists('default_hourly_rate_pence', $data) || array_key_exists('default_hourly_rate', $data)) {
            $org->default_hourly_rate_pence = $this->resolveRatePence($data);
        }

        if (array_key_exists('service_area', $data)) {
            $area = trim((string) ($data['service_area'] ?? ''));
            $org->service_area = $area === '' ? null : $area;
        }

        if (array_key_exists('cancellation_policy', $data)) {
            $policy = trim((string) ($data['cancellation_policy'] ?? ''));
            $org->cancellation_policy = $policy === '' ? null : $policy;
        }

        if (array_key_exists('work_days', $data)
            || array_key_exists('work_start_time', $data)
            || array_key_exists('work_end_time', $data)
        ) {
            $days = array_key_exists('work_days', $data)
                ? $this->normalizeWorkDays($data['work_days'])
                : $org->workDays();
            $start = array_key_exists('work_start_time', $data)
                ? $this->normalizeClock((string) $data['work_start_time'], 'Start time')
                : $org->workStartTime();
            $end = array_key_exists('work_end_time', $data)
                ? $this->normalizeClock((string) $data['work_end_time'], 'End time')
                : $org->workEndTime();
            if ($this->clockToMinutes($end) <= $this->clockToMinutes($start)) {
                throw new BadRequestHttpException('Working hours end must be after start.');
            }
            $org->work_days = json_encode($days, JSON_THROW_ON_ERROR);
            $org->work_start_time = $start;
            $org->work_end_time = $end;
        }

        if (array_key_exists('booking_mode', $data)) {
            $mode = strtolower(trim((string) $data['booking_mode']));
            if (!in_array($mode, [
                Organisation::BOOKING_MODE_MANUAL,
                Organisation::BOOKING_MODE_REQUEST,
                Organisation::BOOKING_MODE_INSTANT,
            ], true)) {
                throw new BadRequestHttpException('Choose how pupils can book lessons.');
            }
            $org->booking_mode = $mode;
        }

        if (array_key_exists('learner_reschedule_mode', $data)) {
            $mode = strtolower(trim((string) $data['learner_reschedule_mode']));
            if (!in_array($mode, [
                Organisation::BOOKING_MODE_MANUAL,
                Organisation::BOOKING_MODE_REQUEST,
                Organisation::BOOKING_MODE_INSTANT,
            ], true)) {
                throw new BadRequestHttpException('Choose how pupils can reschedule.');
            }
            $org->learner_reschedule_mode = $mode;
        }

        if (array_key_exists('learner_can_cancel', $data)) {
            $org->learner_can_cancel = (bool) $data['learner_can_cancel'];
        }

        if (array_key_exists('booking_minimum_notice_hours', $data)) {
            $hours = (int) $data['booking_minimum_notice_hours'];
            if ($hours < 0 || $hours > 168) {
                throw new BadRequestHttpException('Minimum notice must be between 0 and 168 hours.');
            }
            $org->booking_minimum_notice_hours = $hours;
        }

        if (array_key_exists('booking_advance_weeks', $data)) {
            $weeks = (int) $data['booking_advance_weeks'];
            if ($weeks < 1 || $weeks > 52) {
                throw new BadRequestHttpException('Advance booking window must be between 1 and 52 weeks.');
            }
            $org->booking_advance_weeks = $weeks;
        }

        if (array_key_exists('booking_slot_increment_minutes', $data)) {
            $mins = (int) $data['booking_slot_increment_minutes'];
            if ($mins < 5 || $mins > 60) {
                throw new BadRequestHttpException('Slot spacing must be between 5 and 60 minutes.');
            }
            $org->booking_slot_increment_minutes = $mins;
        }

        if (array_key_exists('booking_allowed_durations', $data)) {
            $org->booking_allowed_durations = $this->normalizeAllowedDurations($data['booking_allowed_durations']);
        }

        $org->updated_at = $now;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$instructor->save()) {
                throw new BadRequestHttpException($this->firstError($instructor));
            }
            if (!$org->save()) {
                throw new BadRequestHttpException($this->firstError($org));
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        // Keep diary/book forms using fresh timezone immediately.
        OrganisationTime::timezoneFor($org);

        return $this->serialize($org, $instructor);
    }

    /**
     * @return array{0: Organisation, 1: Instructor}
     */
    private function requireOwnedSettings(): array
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }
        $orgId = TenantContext::requireOrganisationId();
        /** @var Organisation|null $org */
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new NotFoundHttpException('Business not found.');
        }

        /** @var Instructor|null $instructor */
        $instructor = Instructor::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'user_id' => (int) Yii::$app->user->id,
            ])
            ->one();
        if ($instructor === null) {
            throw new ForbiddenHttpException('Instructor profile required.');
        }

        return [$org, $instructor];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Organisation $org, Instructor $instructor): array
    {
        $rate = $org->default_hourly_rate_pence;
        $calendar = new CalendarFeedService();

        return [
            'display_name' => $instructor->display_name,
            'business_name' => $org->name,
            'contact_phone' => $org->contact_phone,
            'contact_email' => $org->contact_email,
            'timezone' => $org->timezone,
            'default_lesson_duration_minutes' => $org->defaultLessonDurationMinutes(),
            'default_hourly_rate_pence' => $rate !== null ? (int) $rate : null,
            'default_hourly_rate_label' => $rate !== null ? Money::formatPence((int) $rate) . '/hr' : null,
            'service_area' => $org->service_area,
            'cancellation_policy' => $org->cancellation_policy,
            'work_days' => $org->workDays(),
            'work_start_time' => $org->workStartTime(),
            'work_end_time' => $org->workEndTime(),
            'booking_mode' => $org->bookingMode(),
            'learner_reschedule_mode' => $org->learnerRescheduleMode(),
            'learner_can_cancel' => $org->learnerCanCancel(),
            'booking_minimum_notice_hours' => $org->bookingMinimumNoticeHours(),
            'booking_advance_weeks' => $org->bookingAdvanceWeeks(),
            'booking_slot_increment_minutes' => $org->bookingSlotIncrementMinutes(),
            'booking_allowed_durations' => $org->bookingAllowedDurations(),
            'calendar' => $calendar->settingsForOrganisation($org),
            'timezone_options' => ['Europe/London', 'Europe/Dublin'],
            'duration_options' => [30, 45, 60, 90, 120],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function calendarConnect(): array
    {
        [$org] = $this->requireOwnedSettings();
        $calendar = new CalendarFeedService();

        return $calendar->connect($org);
    }

    /**
     * @return array<string, mixed>
     */
    public function calendarRegenerate(): array
    {
        [$org] = $this->requireOwnedSettings();
        $calendar = new CalendarFeedService();

        return $calendar->regenerate($org);
    }

    public function calendarRevoke(): void
    {
        [$org] = $this->requireOwnedSettings();
        (new CalendarFeedService())->revoke($org);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function calendarUpdatePrivacy(array $data): array
    {
        [$org] = $this->requireOwnedSettings();
        $calendar = new CalendarFeedService();
        $calendar->updatePrivacy($org, (string) ($data['privacy_mode'] ?? ''));
        $org->refresh();

        return $calendar->settingsForOrganisation($org);
    }

    /**
     * @return array<string, mixed>
     */
    public function profileGet(): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();

        return (new PublicProfileService())->editorSettings($org, $instructor);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function profileUpdate(array $data): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();
        $org->refresh();

        return (new PublicProfileService())->update($org, $instructor, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePublish(): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();
        $org->refresh();

        return (new PublicProfileService())->publish($org, $instructor);
    }

    /**
     * @return array<string, mixed>
     */
    public function profileUnpublish(): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();
        $org->refresh();

        return (new PublicProfileService())->unpublish($org, $instructor);
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePreview(): array
    {
        [$org] = $this->requireOwnedSettings();
        $slug = (string) ($org->profile_slug ?? '');
        if ($slug === '') {
            throw new BadRequestHttpException('Save your profile URL before previewing.');
        }

        return (new PublicProfileService())->publicBySlug($slug, preview: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function profileUploadPhoto(): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();
        $org->refresh();

        return (new PublicProfileService())->uploadPhoto($org, $instructor);
    }

    public function profilePhotoResponse(): \yii\web\Response
    {
        [$org] = $this->requireOwnedSettings();
        if ($org->profile_photo_path === null || $org->profile_photo_path === '') {
            throw new NotFoundHttpException('Photo not found.');
        }
        $storage = new \app\components\ProfilePhotoStorage();
        $response = \Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $storage->mimeType((string) $org->profile_photo_path));
        $response->content = $storage->read((string) $org->profile_photo_path);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function profileUploadCover(): array
    {
        [$org, $instructor] = $this->requireOwnedSettings();
        $org->refresh();

        return (new PublicProfileService())->uploadCover($org, $instructor);
    }

    public function profileCoverResponse(): \yii\web\Response
    {
        [$org] = $this->requireOwnedSettings();
        $file = (new PublicProfileService())->editorCoverResponse($org);
        $response = \Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $file['mime']);
        $response->content = $file['content'];

        return $response;
    }

    /**
     * @param mixed $raw
     */
    private function normalizeAllowedDurations(mixed $raw): ?string
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }
        if (!is_array($raw)) {
            throw new BadRequestHttpException('Allowed lesson lengths must be a list.');
        }
        $durations = [];
        foreach ($raw as $value) {
            $n = (int) $value;
            if ($n < 15 || $n > 480) {
                throw new BadRequestHttpException('Lesson lengths must be between 15 and 480 minutes.');
            }
            $durations[] = $n;
        }
        $durations = array_values(array_unique($durations));
        sort($durations);
        if ($durations === []) {
            return null;
        }

        return json_encode($durations, JSON_THROW_ON_ERROR);
    }

    private function assertTimezone(string $tz): void
    {
        if ($tz === '') {
            throw new BadRequestHttpException('Timezone is required.');
        }
        try {
            new DateTimeZone($tz);
        } catch (\Exception) {
            throw new BadRequestHttpException('Timezone is invalid.');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveRatePence(array $data): int
    {
        if (array_key_exists('default_hourly_rate_pence', $data)
            && $data['default_hourly_rate_pence'] !== null
            && $data['default_hourly_rate_pence'] !== ''
        ) {
            $pence = (int) $data['default_hourly_rate_pence'];
            if ($pence < 0) {
                throw new BadRequestHttpException('Hourly rate cannot be negative.');
            }

            return $pence;
        }
        if (array_key_exists('default_hourly_rate', $data)
            && $data['default_hourly_rate'] !== null
            && $data['default_hourly_rate'] !== ''
        ) {
            try {
                return Money::poundsToPence($data['default_hourly_rate']);
            } catch (\InvalidArgumentException $e) {
                throw new BadRequestHttpException($e->getMessage());
            }
        }

        throw new BadRequestHttpException('Hourly rate is required.');
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizeWorkDays(mixed $raw): array
    {
        if (!is_array($raw)) {
            throw new BadRequestHttpException('Working days must be a list of weekdays.');
        }
        $days = [];
        foreach ($raw as $day) {
            $n = (int) $day;
            if ($n < 1 || $n > 7) {
                throw new BadRequestHttpException('Working days must be 1 (Mon) to 7 (Sun).');
            }
            $days[] = $n;
        }
        $days = array_values(array_unique($days));
        sort($days);
        if ($days === []) {
            throw new BadRequestHttpException('Choose at least one working day.');
        }

        return $days;
    }

    private function normalizeClock(string $raw, string $label): string
    {
        $raw = trim($raw);
        if (preg_match('/^\d{2}:\d{2}$/', $raw) !== 1) {
            throw new BadRequestHttpException($label . ' must be HH:MM.');
        }
        [$h, $m] = array_map('intval', explode(':', $raw));
        if ($h > 23 || $m > 59) {
            throw new BadRequestHttpException($label . ' is invalid.');
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    private function clockToMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'Could not save.';
    }
}
