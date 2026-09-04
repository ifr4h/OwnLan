<?php

declare(strict_types=1);

namespace app\services;

use app\components\IcsCalendarBuilder;
use app\components\OrganisationTime;
use app\components\TenantContext;
use app\models\Lesson;
use app\models\Organisation;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class CalendarFeedService
{
    public const PRIVACY_FULL = 'full';
    public const PRIVACY_PRIVATE = 'private';

    /**
     * @return array<string, mixed>
     */
    public function settingsForOrganisation(Organisation $org): array
    {
        $token = (string) ($org->calendar_feed_token ?? '');
        $hasFeed = $token !== '';

        return [
            'connected' => $hasFeed,
            'subscription_url' => $hasFeed ? $this->subscriptionUrl($token) : null,
            'privacy_mode' => $this->privacyMode($org),
            'privacy_options' => [
                ['value' => self::PRIVACY_PRIVATE, 'label' => 'Private', 'description' => 'Shows “Driving lesson” without pupil name or pickup.'],
                ['value' => self::PRIVACY_FULL, 'label' => 'Full detail', 'description' => 'Shows pupil name and pickup address when set.'],
            ],
            'feed_created_at' => $org->calendar_feed_created_at,
            'refresh_note' => 'Changes appear when your calendar app refreshes the subscription.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function connect(Organisation $org): array
    {
        $this->issueToken($org);

        return $this->settingsForOrganisation($org);
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerate(Organisation $org): array
    {
        $this->issueToken($org);

        return $this->settingsForOrganisation($org);
    }

    public function revoke(Organisation $org): void
    {
        $org->calendar_feed_token = null;
        $org->calendar_feed_created_at = null;
        $org->save(false, ['calendar_feed_token', 'calendar_feed_created_at']);
    }

    public function updatePrivacy(Organisation $org, string $mode): void
    {
        if (!in_array($mode, [self::PRIVACY_FULL, self::PRIVACY_PRIVATE], true)) {
            throw new BadRequestHttpException('Privacy mode must be full or private.');
        }
        $org->calendar_privacy_mode = $mode;
        $org->save(false, ['calendar_privacy_mode']);
    }

    public function feedByToken(string $token): string
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 32) {
            throw new NotFoundHttpException('Calendar not found.');
        }

        /** @var Organisation|null $org */
        $org = Organisation::find()
            ->andWhere(['calendar_feed_token' => $token])
            ->one();
        if ($org === null) {
            throw new NotFoundHttpException('Calendar not found.');
        }

        return $this->buildFeed($org);
    }

    public function buildFeed(Organisation $org): string
    {
        $tz = OrganisationTime::timezoneFor($org);
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $windowStart = $nowUtc->modify('-1 day');
        $windowEnd = $nowUtc->modify('+1 year');

        /** @var Lesson[] $scheduled */
        $scheduled = Lesson::find()
            ->andWhere(['organisation_id' => (int) $org->id, 'status' => Lesson::STATUS_SCHEDULED])
            ->andWhere(['>=', 'starts_at', $windowStart->format('Y-m-d H:i:s')])
            ->andWhere(['<', 'starts_at', $windowEnd->format('Y-m-d H:i:s')])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        /** @var Lesson[] $cancelledFuture */
        $cancelledFuture = Lesson::find()
            ->andWhere(['organisation_id' => (int) $org->id, 'status' => Lesson::STATUS_CANCELLED])
            ->andWhere(['>', 'starts_at', $nowUtc->format('Y-m-d H:i:s')])
            ->andWhere(['>=', 'cancelled_at', $nowUtc->modify('-90 days')->format('Y-m-d H:i:s')])
            ->with(['learner'])
            ->orderBy(['starts_at' => SORT_ASC])
            ->all();

        $events = [];
        foreach ($scheduled as $lesson) {
            $events[] = $this->lessonToEvent($lesson, $org, false);
        }
        foreach ($cancelledFuture as $lesson) {
            $events[] = $this->lessonToEvent($lesson, $org, true);
        }

        return IcsCalendarBuilder::build($org->name . ' — OwnLane', $events, $tz);
    }

    /**
     * @return array<string, mixed>
     */
    private function lessonToEvent(Lesson $lesson, Organisation $org, bool $cancelled): array
    {
        $tz = OrganisationTime::timezoneFor($org);
        $startLocal = OrganisationTime::utcToLocal($lesson->starts_at, $org);
        $endLocal = $startLocal->modify('+' . (int) $lesson->duration_minutes . ' minutes');
        $privacy = $this->privacyMode($org);
        $learner = $lesson->learner;

        if ($privacy === self::PRIVACY_FULL && $learner !== null) {
            $summary = $learner->fullName . ' · Driving lesson';
            $location = trim((string) ($lesson->pickup_address ?? $learner->default_pickup_address ?? ''));
        } else {
            $summary = 'Driving lesson';
            $location = $privacy === self::PRIVACY_FULL
                ? trim((string) ($lesson->pickup_address ?? ''))
                : '';
        }

        $descriptionParts = [];
        if ($learner?->next_focus) {
            $descriptionParts[] = 'Next focus: ' . $learner->next_focus;
        }
        $descriptionParts[] = 'OwnLane lesson #' . (int) $lesson->id;

        $modified = $lesson->updated_at ?? $lesson->starts_at;

        return [
            'uid' => IcsCalendarBuilder::lessonUid((int) $lesson->id),
            'summary' => $summary,
            'description' => implode("\n", $descriptionParts),
            'location' => $location,
            'start' => $startLocal,
            'end' => $endLocal,
            'cancelled' => $cancelled,
            'last_modified' => new DateTimeImmutable($modified, new DateTimeZone('UTC')),
        ];
    }

    private function issueToken(Organisation $org): void
    {
        $org->calendar_feed_token = Yii::$app->security->generateRandomString(48);
        $org->calendar_feed_created_at = gmdate('Y-m-d H:i:s');
        $org->save(false, ['calendar_feed_token', 'calendar_feed_created_at']);
    }

    private function subscriptionUrl(string $token): string
    {
        $base = getenv('API_URL');
        if (!is_string($base) || trim($base) === '') {
            $base = rtrim((string) Yii::$app->request->hostInfo, '/');
        } else {
            $base = rtrim($base, '/');
        }

        return $base . '/calendar/' . rawurlencode($token) . '.ics';
    }

    private function privacyMode(Organisation $org): string
    {
        $mode = trim((string) ($org->calendar_privacy_mode ?? ''));

        return in_array($mode, [self::PRIVACY_FULL, self::PRIVACY_PRIVATE], true)
            ? $mode
            : self::PRIVACY_PRIVATE;
    }

    public function requireOwnedOrg(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne(['id' => $orgId]);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $org;
    }
}
