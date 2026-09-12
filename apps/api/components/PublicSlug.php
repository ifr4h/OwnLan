<?php

declare(strict_types=1);

namespace app\components;

use app\models\Organisation;
use app\models\ProfileSlugRedirect;
use yii\db\Query;

/**
 * Safe unique public slugs for instructor profiles.
 */
final class PublicSlug
{
    /** @var list<string> */
    public const RESERVED = [
        'admin', 'api', 'login', 'register', 'portal', 'join', 'settings', 'instructors',
        'learners', 'features', 'pricing', 'about', 'today', 'pupils', 'lessons', 'accounts',
        'services', 'search', 'calendar', 'share', 'companion', 'public', 'health', 'exports', 'intake',
        'enquiries', 'new', 'edit', 'preview', 'www', 'app', 'help', 'support', 'terms',
        'privacy', 'blog', 'marketplace',
    ];

    public static function fromName(string $name, ?int $excludeOrgId = null): string
    {
        $base = self::normalize($name);
        if ($base === '') {
            $base = 'instructor';
        }
        if (self::isReserved($base)) {
            $base .= '-driving';
        }
        if (!self::isTaken($base, $excludeOrgId)) {
            return $base;
        }
        for ($i = 2; $i <= 99; $i++) {
            $candidate = $base . '-' . $i;
            if (!self::isTaken($candidate, $excludeOrgId)) {
                return $candidate;
            }
        }

        return $base . '-' . bin2hex(random_bytes(3));
    }

    public static function normalize(string $input): string
    {
        $text = trim($input);
        if ($text === '') {
            return '';
        }
        if (function_exists('transliterator_transliterate')) {
            $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        } else {
            $text = strtolower($text);
        }
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        return mb_substr($text, 0, 80);
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED, true);
    }

    public static function isTaken(string $slug, ?int $excludeOrgId = null): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || self::isReserved($slug)) {
            return true;
        }

        $orgQuery = Organisation::find()->andWhere(['profile_slug' => $slug]);
        if ($excludeOrgId !== null) {
            $orgQuery->andWhere(['not', ['id' => $excludeOrgId]]);
        }
        if ($orgQuery->exists()) {
            return true;
        }

        return (new Query())
            ->from('{{%profile_slug_redirects}}')
            ->where(['old_slug' => $slug])
            ->exists();
    }

    public static function recordRedirect(int $organisationId, string $oldSlug): void
    {
        $oldSlug = strtolower(trim($oldSlug));
        if ($oldSlug === '') {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $exists = (new Query())
            ->from('{{%profile_slug_redirects}}')
            ->where(['old_slug' => $oldSlug])
            ->exists();
        if ($exists) {
            return;
        }
        \Yii::$app->db->createCommand()->insert('{{%profile_slug_redirects}}', [
            'organisation_id' => $organisationId,
            'old_slug' => $oldSlug,
            'created_at' => $now,
        ])->execute();
    }
}
