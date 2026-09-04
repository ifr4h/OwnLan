<?php

declare(strict_types=1);

namespace app\services;

use app\components\CompanionContext;
use app\components\Money;
use app\components\OrganisationTime;
use app\components\PortalContext;
use app\components\TheoryCertificate;
use app\models\CompanionAccount;
use app\models\Learner;
use app\models\LearnerCompanion;
use app\models\LearnerPackage;
use app\models\Lesson;
use app\models\LessonCharge;
use app\models\LessonResource;
use app\models\Organisation;
use app\models\PrivatePracticeSession;
use app\models\RouteMoment;
use DateTimeImmutable;
use DateTimeZone;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Broad Companion service — DEFERRED / NOT CURRENTLY EXPOSED for beta.
 *
 * Architecture preserved for future purpose-limited access (e.g. Payment Contact).
 * HTTP endpoints gated by CompanionFeature. See docs/17-companion-access-decision.md.
 *
 * Security tests in CompanionServiceTest remain active at service layer.
 */
class CompanionService
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function inviteFromLearner(array $data): array
    {
        $account = PortalContext::requireAccount();
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Name and a valid email are required.');
        }

        $permissions = $this->normalizePermissions($data['permissions'] ?? []);
        if (!array_filter($permissions)) {
            throw new BadRequestHttpException('Choose at least one thing they can help with.');
        }

        $now = $this->now();
        $companion = CompanionAccount::findByEmail($email);
        $rawToken = null;
        if ($companion === null) {
            $companion = new CompanionAccount();
            $companion->email = $email;
            $companion->name = $name;
            $companion->generateAuthKey();
            $rawToken = Yii::$app->security->generateRandomString(48);
            $companion->invite_token_hash = hash('sha256', $rawToken);
            $companion->invite_expires_at = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify('+14 days')
                ->format('Y-m-d H:i:s');
            $companion->created_at = $now;
            $companion->updated_at = $now;
            if (!$companion->save()) {
                throw new BadRequestHttpException('Could not create companion invite.');
            }
        } elseif (!$companion->isActivated) {
            $rawToken = Yii::$app->security->generateRandomString(48);
            $companion->invite_token_hash = hash('sha256', $rawToken);
            $companion->invite_expires_at = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->modify('+14 days')
                ->format('Y-m-d H:i:s');
            $companion->name = $name;
            $companion->updated_at = $now;
            $companion->save(false);
        }

        $existing = LearnerCompanion::findOne([
            'learner_id' => (int) $account->learner_id,
            'companion_account_id' => (int) $companion->id,
        ]);
        if ($existing !== null && $existing->revoked_at === null) {
            throw new BadRequestHttpException('This person already has access.');
        }

        $link = $existing ?? new LearnerCompanion();
        $link->organisation_id = (int) $account->organisation_id;
        $link->learner_id = (int) $account->learner_id;
        $link->companion_account_id = (int) $companion->id;
        $link->display_name = $name;
        $rel = trim((string) ($data['relationship_label'] ?? ''));
        $link->relationship_label = $rel === '' ? null : mb_substr($rel, 0, 40);
        $link->permissions_json = json_encode($permissions, JSON_THROW_ON_ERROR);
        $link->invited_by = 'learner';
        $link->revoked_at = null;
        $link->created_at = $link->created_at ?: $now;
        $link->updated_at = $now;
        if (!$link->save()) {
            throw new BadRequestHttpException('Could not save companion.');
        }

        return [
            'companion' => $this->serializeLink($link, $companion),
            'invite_path' => $rawToken !== null
                ? '/companion/join?token=' . urlencode($rawToken)
                : null,
            'already_had_account' => $rawToken === null && $companion->isActivated,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForLearner(): array
    {
        $account = PortalContext::requireAccount();
        /** @var LearnerCompanion[] $rows */
        $rows = LearnerCompanion::find()
            ->andWhere([
                'learner_id' => (int) $account->learner_id,
                'organisation_id' => (int) $account->organisation_id,
                'revoked_at' => null,
            ])
            ->with('companionAccount')
            ->orderBy(['id' => SORT_ASC])
            ->all();

        return array_map(
            fn (LearnerCompanion $l) => $this->serializeLink($l, $l->companionAccount),
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updatePermissions(int $linkId, array $data): array
    {
        $account = PortalContext::requireAccount();
        $link = LearnerCompanion::findOne([
            'id' => $linkId,
            'learner_id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
            'revoked_at' => null,
        ]);
        if ($link === null) {
            throw new NotFoundHttpException('Companion not found.');
        }
        if (array_key_exists('permissions', $data)) {
            $permissions = $this->normalizePermissions($data['permissions']);
            if (!array_filter($permissions)) {
                throw new BadRequestHttpException('Choose at least one thing they can help with.');
            }
            $link->permissions_json = json_encode($permissions, JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('display_name', $data)) {
            $name = trim((string) $data['display_name']);
            if ($name !== '') {
                $link->display_name = $name;
            }
        }
        if (array_key_exists('relationship_label', $data)) {
            $rel = trim((string) $data['relationship_label']);
            $link->relationship_label = $rel === '' ? null : mb_substr($rel, 0, 40);
        }
        $link->updated_at = $this->now();
        $link->save(false);

        return $this->serializeLink($link, $link->companionAccount);
    }

    /**
     * @return array{status: string}
     */
    public function revoke(int $linkId): array
    {
        $account = PortalContext::requireAccount();
        $link = LearnerCompanion::findOne([
            'id' => $linkId,
            'learner_id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
        ]);
        if ($link === null) {
            throw new NotFoundHttpException('Companion not found.');
        }
        $link->revoked_at = $this->now();
        $link->updated_at = $link->revoked_at;
        $link->save(false, ['revoked_at', 'updated_at']);

        return ['status' => 'ok'];
    }

    /**
     * @return array<string, mixed>
     */
    public function peekInvite(string $token): array
    {
        $companion = $this->findByInviteToken($token);
        $link = LearnerCompanion::find()
            ->andWhere(['companion_account_id' => (int) $companion->id, 'revoked_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return [
            'companion_name' => $companion->name,
            'email' => $companion->email,
            'already_activated' => $companion->isActivated,
            'learner_first_name' => $link?->learner?->first_name,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function activate(array $data): array
    {
        $token = (string) ($data['token'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 8) {
            throw new BadRequestHttpException('Password must be at least 8 characters.');
        }
        $companion = $this->findByInviteToken($token);
        $companion->setPassword($password);
        $companion->activated_at = $this->now();
        $companion->invite_token_hash = null;
        $companion->invite_expires_at = null;
        $companion->updated_at = $companion->activated_at;
        $companion->save(false);
        $this->loginCompanion($companion);

        return $this->currentPayload();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function login(array $data): array
    {
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $companion = CompanionAccount::findByEmail($email);
        if ($companion === null || !$companion->isActivated || !$companion->validatePassword($password)) {
            throw new UnauthorizedHttpException('Incorrect email or password.');
        }
        $this->loginCompanion($companion);

        return $this->currentPayload();
    }

    public function logout(): void
    {
        if (Yii::$app->has('companionUser')) {
            Yii::$app->companionUser->logout();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function currentPayload(): array
    {
        $account = CompanionContext::requireAccount();
        /** @var LearnerCompanion[] $links */
        $links = LearnerCompanion::find()
            ->andWhere([
                'companion_account_id' => (int) $account->id,
                'revoked_at' => null,
            ])
            ->with('learner')
            ->all();

        $learners = [];
        foreach ($links as $link) {
            $learners[] = [
                'link_id' => (int) $link->id,
                'learner_id' => (int) $link->learner_id,
                'organisation_id' => (int) $link->organisation_id,
                'first_name' => $link->learner?->first_name,
                'full_name' => $link->learner?->fullName,
                'display_label' => $link->display_name,
                'relationship_label' => $link->relationship_label,
                'permissions' => $link->permissions(),
            ];
        }

        return [
            'account' => [
                'id' => (int) $account->id,
                'email' => $account->email,
                'name' => $account->name,
            ],
            'learners' => $learners,
        ];
    }

    /**
     * Permission-filtered home for one learner.
     *
     * @return array<string, mixed>
     */
    public function learnerHome(int $learnerId): array
    {
        $link = CompanionContext::requireLink($learnerId);
        $perms = $link->permissions();
        /** @var Learner $learner */
        $learner = Learner::findOne([
            'id' => $learnerId,
            'organisation_id' => (int) $link->organisation_id,
        ]);
        if ($learner === null) {
            throw new NotFoundHttpException('Learner not found.');
        }
        /** @var Organisation $org */
        $org = Organisation::findOne((int) $link->organisation_id);
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $payload = [
            'learner' => [
                'id' => (int) $learner->id,
                'first_name' => $learner->first_name,
                'full_name' => $learner->fullName,
            ],
            'permissions' => $perms,
            'provenance' => 'companion_view',
        ];

        if ($perms['lessons']) {
            $next = Lesson::find()
                ->andWhere([
                    'organisation_id' => (int) $org->id,
                    'learner_id' => $learnerId,
                    'status' => Lesson::STATUS_SCHEDULED,
                ])
                ->andWhere(['>=', 'starts_at', $nowUtc->format('Y-m-d H:i:s')])
                ->orderBy(['starts_at' => SORT_ASC])
                ->one();
            $payload['next_lesson'] = $next instanceof Lesson
                ? $this->serializeLessonBrief($next, $org)
                : null;
        }

        if ($perms['progress']) {
            $payload['next_focus'] = $learner->next_focus;
            $payload['last_lesson_summary'] = $learner->last_lesson_summary;
        }

        if ($perms['money']) {
            $credit = (int) (LearnerPackage::find()
                ->andWhere([
                    'learner_id' => $learnerId,
                    'organisation_id' => (int) $org->id,
                    'status' => LearnerPackage::STATUS_ACTIVE,
                ])
                ->sum('remaining_minutes') ?? 0);
            $owed = 0;
            foreach (LessonCharge::find()->andWhere([
                'learner_id' => $learnerId,
                'organisation_id' => (int) $org->id,
                'status' => LessonCharge::STATUS_OUTSTANDING,
            ])->all() as $charge) {
                $owed += $charge->outstandingPence();
            }
            $payload['money'] = [
                'credit_minutes' => $credit,
                'credit_label' => $this->hoursLabel($credit) . ' remaining',
                'amount_due_pence' => $owed,
                'amount_due_label' => Money::formatPence($owed),
                // Never expose instructor revenue / margins.
            ];
        }

        if ($perms['test']) {
            $payload['theory'] = TheoryCertificate::statusPayload(
                $learner->theory_status,
                $learner->theory_pass_date,
                $nowUtc,
            );
            $tests = (new TestJourneyService())->build($learner, $org, $nowUtc);
            $payload['practical_test'] = $tests;
        }

        if ($perms['practice']) {
            $payload['practice_focus'] = $learner->next_focus;
            $payload['practice_resources'] = LessonResource::find()
                ->andWhere([
                    'organisation_id' => (int) $org->id,
                    'learner_id' => $learnerId,
                    'learner_visible' => true,
                ])
                ->orderBy(['id' => SORT_DESC])
                ->limit(5)
                ->all();
            $payload['practice_resources'] = array_map(static fn (LessonResource $r) => [
                'id' => (int) $r->id,
                'title' => $r->title,
                'note' => $r->learner_visible_note,
                'lesson_id' => (int) $r->lesson_id,
                'scene' => $r->scene_json ? (json_decode($r->scene_json, true) ?: []) : [],
            ], $payload['practice_resources']);

            $sessions = PrivatePracticeSession::find()
                ->andWhere([
                    'organisation_id' => (int) $org->id,
                    'learner_id' => $learnerId,
                ])
                ->orderBy(['practised_at' => SORT_DESC])
                ->limit(8)
                ->all();
            $payload['recent_practice'] = array_map(static function (PrivatePracticeSession $s) {
                return [
                    'id' => (int) $s->id,
                    'practised_at' => $s->practised_at,
                    'duration_minutes' => (int) $s->duration_minutes,
                    'skill_codes' => $s->skillCodes(),
                    'feeling' => $s->feeling,
                    'learner_reflection' => $s->note,
                    'companion_note' => $s->companion_note,
                    'provenance' => [
                        'feeling' => 'learner_self_report',
                        'learner_reflection' => 'learner_self_report',
                        'companion_note' => $s->companion_note !== null ? 'companion' : null,
                    ],
                ];
            }, $sessions);
            $payload['latest_practice_session_id'] = $sessions !== []
                ? (int) $sessions[0]->id
                : null;
        }

        if ($perms['learn']) {
            $payload['can_open_learn'] = true;
        }

        if ($perms['routes']) {
            $payload['can_open_routes'] = true;
        }

        return $payload;
    }

    /**
     * Companion adds optional note on a private practice session (practice permission).
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function addPracticeNote(int $learnerId, int $sessionId, array $data): array
    {
        $link = CompanionContext::requirePermission($learnerId, 'practice');
        $session = PrivatePracticeSession::findOne([
            'id' => $sessionId,
            'learner_id' => $learnerId,
            'organisation_id' => (int) $link->organisation_id,
        ]);
        if ($session === null) {
            throw new NotFoundHttpException('Practice session not found.');
        }
        $note = trim((string) ($data['companion_note'] ?? ''));
        $session->companion_note = $note === '' ? null : $note;
        $session->companion_account_id = (int) CompanionContext::requireAccount()->id;
        $session->updated_at = $this->now();
        $session->save(false, ['companion_note', 'companion_account_id', 'updated_at']);

        return [
            'id' => (int) $session->id,
            'companion_note' => $session->companion_note,
            'provenance' => 'companion',
        ];
    }

    /**
     * @param mixed $raw
     * @return array<string, bool>
     */
    private function normalizePermissions(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $out = [];
        foreach (LearnerCompanion::PERMISSIONS as $key) {
            $out[$key] = !empty($raw[$key]);
        }
        // Booking reserved for later — accept but never grant booking actions yet.
        $out['booking'] = false;

        return $out;
    }

    private function findByInviteToken(string $token): CompanionAccount
    {
        if ($token === '') {
            throw new BadRequestHttpException('Invite token required.');
        }
        $hash = hash('sha256', $token);
        $companion = CompanionAccount::findOne(['invite_token_hash' => $hash]);
        if ($companion === null) {
            throw new NotFoundHttpException('Invite not found.');
        }
        if ($companion->invite_expires_at !== null && $companion->invite_expires_at < $this->now()) {
            throw new BadRequestHttpException('This invite has expired.');
        }

        return $companion;
    }

    private function loginCompanion(CompanionAccount $companion): void
    {
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        Yii::$app->user->logout();
        Yii::$app->companionUser->login($companion, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLink(LearnerCompanion $link, ?CompanionAccount $account): array
    {
        return [
            'id' => (int) $link->id,
            'display_name' => $link->display_name,
            'relationship_label' => $link->relationship_label,
            'email' => $account?->email,
            'activated' => $account?->isActivated ?? false,
            'permissions' => $link->permissions(),
            'permission_labels' => $this->permissionLabels($link->permissions()),
        ];
    }

    /**
     * @param array<string, bool> $perms
     * @return list<string>
     */
    private function permissionLabels(array $perms): array
    {
        $map = [
            'lessons' => 'Lessons & schedule',
            'money' => 'Payments & lesson credit',
            'progress' => 'Progress',
            'learn' => 'Learning resources',
            'routes' => 'Routes',
            'test' => 'Test information',
            'practice' => 'Private practice',
        ];
        $labels = [];
        foreach ($map as $key => $label) {
            if (!empty($perms[$key])) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLessonBrief(Lesson $lesson, Organisation $org): array
    {
        $local = OrganisationTime::utcToLocal($lesson->starts_at, $org);

        return [
            'id' => (int) $lesson->id,
            'starts_at_display' => OrganisationTime::formatLocalDisplay($local),
            'starts_at_time' => $local->format('g:i A'),
            'duration_minutes' => (int) $lesson->duration_minutes,
            'pickup_address' => $lesson->pickup_address,
        ];
    }

    private function hoursLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 hours';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m === 0) {
            return $h === 1 ? '1 hour' : $h . ' hours';
        }
        if ($h > 0) {
            return $h . 'h ' . $m . 'm';
        }

        return $minutes . ' minutes';
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
