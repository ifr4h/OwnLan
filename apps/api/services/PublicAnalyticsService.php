<?php

declare(strict_types=1);

namespace app\services;

use app\models\Organisation;
use Yii;
use yii\db\Query;

/**
 * First-party public page analytics — no PII in events.
 */
class PublicAnalyticsService
{
    public const EVENT_VIEW = 'view';
    public const EVENT_ENQUIRY_STARTED = 'enquiry_started';
    public const EVENT_ENQUIRY_SUBMITTED = 'enquiry_submitted';
    public const EVENT_SERVICE_CLICK = 'service_click';

    public function track(int $organisationId, string $eventType, ?string $sourceTag = null): void
    {
        if (!in_array($eventType, [
            self::EVENT_VIEW,
            self::EVENT_ENQUIRY_STARTED,
            self::EVENT_ENQUIRY_SUBMITTED,
            self::EVENT_SERVICE_CLICK,
        ], true)) {
            return;
        }

        $tag = $this->normaliseSourceTag($sourceTag);
        $sessionHash = $this->sessionHash();

        if ($eventType === self::EVENT_VIEW) {
            $key = 'profile_view:' . $organisationId . ':' . $sessionHash;
            if (Yii::$app->cache->get($key)) {
                return;
            }
            Yii::$app->cache->set($key, 1, 1800);
        }

        Yii::$app->db->createCommand()->insert('{{%profile_analytics_events}}', [
            'organisation_id' => $organisationId,
            'event_type' => $eventType,
            'source_tag' => $tag,
            'session_hash' => $sessionHash,
            'occurred_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForOrganisation(Organisation $org): array
    {
        $monthStart = gmdate('Y-m-01 00:00:00');
        $orgId = (int) $org->id;

        $views = (int) (new Query())
            ->from('{{%profile_analytics_events}}')
            ->where(['organisation_id' => $orgId, 'event_type' => self::EVENT_VIEW])
            ->andWhere(['>=', 'occurred_at', $monthStart])
            ->count();

        $enquiriesStarted = (int) (new Query())
            ->from('{{%profile_analytics_events}}')
            ->where(['organisation_id' => $orgId, 'event_type' => self::EVENT_ENQUIRY_STARTED])
            ->andWhere(['>=', 'occurred_at', $monthStart])
            ->count();

        $enquiriesSubmitted = (int) (new Query())
            ->from('{{%profile_analytics_events}}')
            ->where(['organisation_id' => $orgId, 'event_type' => self::EVENT_ENQUIRY_SUBMITTED])
            ->andWhere(['>=', 'occurred_at', $monthStart])
            ->count();

        $enquiriesConverted = (int) (new Query())
            ->from('{{%enquiries}}')
            ->where(['organisation_id' => $orgId, 'status' => 'converted'])
            ->andWhere(['>=', 'created_at', $monthStart])
            ->count();

        $bySource = (new Query())
            ->select(['source_tag', 'COUNT(*) AS cnt'])
            ->from('{{%profile_analytics_events}}')
            ->where(['organisation_id' => $orgId, 'event_type' => self::EVENT_ENQUIRY_SUBMITTED])
            ->andWhere(['>=', 'occurred_at', $monthStart])
            ->groupBy(['source_tag'])
            ->all();

        $sourceRows = [];
        foreach ($bySource as $row) {
            $label = $row['source_tag'] ?: 'direct';
            $sourceRows[$label] = (int) $row['cnt'];
        }

        return [
            'month_label' => 'This month',
            'visits' => $views,
            'enquiries_started' => $enquiriesStarted,
            'enquiries_submitted' => $enquiriesSubmitted,
            'pupils_added' => $enquiriesConverted,
            'enquiries_by_source' => $sourceRows,
        ];
    }

    private function sessionHash(): string
    {
        $ip = (string) (Yii::$app->request->userIP ?? '');
        $ua = (string) (Yii::$app->request->userAgent ?? '');

        return hash('sha256', $ip . '|' . $ua);
    }

    private function normaliseSourceTag(?string $tag): ?string
    {
        if ($tag === null || trim($tag) === '') {
            return null;
        }
        $tag = strtolower(trim($tag));
        $allowed = ['instagram', 'facebook', 'website', 'referral', 'tiktok', 'direct'];

        return in_array($tag, $allowed, true) ? $tag : null;
    }
}
