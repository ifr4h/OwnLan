<?php

declare(strict_types=1);

namespace app\services;

use app\components\TenantContext;
use app\models\Learner;
use app\models\Organisation;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Organisation-scoped pupil management.
 */
class LearnerService
{
    /**
     * Active pupils for the current organisation, optional search.
     *
     * @return list<array<string, mixed>>
     */
    public function listActive(?string $query = null): array
    {
        $q = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(['lifecycle' => Learner::LIFECYCLE_ACTIVE])
            ->orderBy(['last_name' => SORT_ASC, 'first_name' => SORT_ASC]);

        $term = trim((string) $query);
        if ($term !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term)) . '%';
            $q->andWhere(
                'LOWER(first_name) LIKE :t
                 OR LOWER(last_name) LIKE :t
                 OR LOWER(CONCAT(first_name, \' \', last_name)) LIKE :t
                 OR LOWER(COALESCE(email, \'\')) LIKE :t
                 OR REPLACE(mobile, \' \', \'\') LIKE :mobile',
                [
                    ':t' => $like,
                    ':mobile' => '%' . preg_replace('/\s+/', '', $term) . '%',
                ],
            );
        }

        return array_map(
            static fn (Learner $learner) => $learner->toListArray(),
            $q->all(),
        );
    }

    /**
     * Waiting-list pupils for the organisation (gap matching eligible foundation).
     *
     * @return list<array<string, mixed>>
     */
    public function listWaiting(): array
    {
        $q = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['archived_at' => null])
            ->andWhere(['lifecycle' => Learner::LIFECYCLE_WAITING])
            ->orderBy(['waiting_list_joined_at' => SORT_ASC, 'last_name' => SORT_ASC]);

        return array_map(
            static fn (Learner $learner) => $learner->toListArray(),
            $q->all(),
        );
    }

    /**
     * @throws NotFoundHttpException
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        $learner = $this->findOwned($id);
        $payload = $this->withTestJourney($learner);
        $payload['finance'] = (new FinanceService())->summaryForLearner($id);
        $payload['portal'] = (new PortalAuthService())->statusForLearner($id);
        $payload['continuity'] = (new ContinuityService())->contextForLearner($id);

        return $payload;
    }

    /**
     * Create — only require what an instructor needs to start teaching.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     */
    public function create(array $data): array
    {
        $learner = new Learner();
        $learner->organisation_id = TenantContext::requireOrganisationId();
        $this->applyCreateFields($learner, $data);

        $now = gmdate('Y-m-d H:i:s');
        $learner->created_at = $now;
        $learner->updated_at = $now;

        if (!$learner->save()) {
            throw new BadRequestHttpException($this->firstError($learner));
        }

        return $this->withTestJourney($learner);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function update(int $id, array $data): array
    {
        $learner = $this->findOwned($id);
        $this->applyUpdateFields($learner, $data);
        $learner->updated_at = gmdate('Y-m-d H:i:s');

        if (!$learner->save()) {
            throw new BadRequestHttpException($this->firstError($learner));
        }

        return $this->withTestJourney($learner);
    }

    /**
     * Soft-archive. Does not delete history OwnLane will attach later.
     *
     * @return array<string, mixed>
     * @throws NotFoundHttpException
     * @throws BadRequestHttpException
     */
    public function archive(int $id): array
    {
        $learner = $this->findOwned($id);
        if ($learner->isArchived) {
            throw new BadRequestHttpException('This pupil is already archived.');
        }

        $learner->archived_at = gmdate('Y-m-d H:i:s');
        $learner->updated_at = $learner->archived_at;

        if (!$learner->save(true, ['archived_at', 'updated_at'])) {
            throw new BadRequestHttpException($this->firstError($learner));
        }

        return $this->withTestJourney($learner);
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findOwned(int $id): Learner
    {
        /** @var Learner|null $learner */
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $id])
            ->one();

        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    private function applyCreateFields(Learner $learner, array $data): void
    {
        $learner->first_name = $this->requiredString($data, 'first_name', 'First name');
        $learner->last_name = $this->requiredString($data, 'last_name', 'Last name');
        $learner->mobile = $this->normaliseMobile($this->requiredString($data, 'mobile', 'Mobile number'));
        $learner->email = $this->optionalEmail($data['email'] ?? null);
        $learner->default_pickup_address = $this->optionalText($data['default_pickup_address'] ?? null);
        $learner->private_notes = $this->optionalText($data['private_notes'] ?? null);
        $learner->lifecycle = Learner::LIFECYCLE_ACTIVE;
        // Test fields are edit-time / intake — keep manual create fast.
        $learner->test_date = null;
        $learner->test_centre = null;
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    private function applyUpdateFields(Learner $learner, array $data): void
    {
        if (array_key_exists('first_name', $data)) {
            $learner->first_name = $this->requiredString($data, 'first_name', 'First name');
        }
        if (array_key_exists('last_name', $data)) {
            $learner->last_name = $this->requiredString($data, 'last_name', 'Last name');
        }
        if (array_key_exists('mobile', $data)) {
            $learner->mobile = $this->normaliseMobile($this->requiredString($data, 'mobile', 'Mobile number'));
        }
        if (array_key_exists('email', $data)) {
            $learner->email = $this->optionalEmail($data['email']);
        }
        if (array_key_exists('default_pickup_address', $data)) {
            $learner->default_pickup_address = $this->optionalText($data['default_pickup_address']);
        }
        if (array_key_exists('test_date', $data)) {
            $learner->test_date = $this->optionalDate($data['test_date']);
        }
        if (array_key_exists('test_centre', $data)) {
            $learner->test_centre = $this->optionalText($data['test_centre'], 255);
        }
        if (array_key_exists('private_notes', $data)) {
            $learner->private_notes = $this->optionalText($data['private_notes']);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @throws BadRequestHttpException
     */
    private function requiredString(array $data, string $key, string $label): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new BadRequestHttpException("{$label} is required.");
        }

        return $value;
    }

    private function optionalText(mixed $value, ?int $max = null): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if ($max !== null && mb_strlen($text) > $max) {
            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    /**
     * @throws BadRequestHttpException
     */
    private function optionalEmail(mixed $value): ?string
    {
        $email = $this->optionalText($value, 255);
        if ($email === null) {
            return null;
        }
        $email = mb_strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Enter a valid email address.');
        }

        return $email;
    }

    /**
     * @throws BadRequestHttpException
     */
    private function optionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = trim((string) $value);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dt === false || $dt->format('Y-m-d') !== $date) {
            throw new BadRequestHttpException('Test date must be YYYY-MM-DD.');
        }

        return $date;
    }

    /**
     * Keep digits, spaces, +, and leading 0 — enough for UK mobiles without a phone lib.
     *
     * @throws BadRequestHttpException
     */
    private function normaliseMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        $compact = preg_replace('/[^\d+]/', '', $mobile) ?? '';
        if ($compact === '' || strlen(preg_replace('/\D/', '', $compact) ?? '') < 10) {
            throw new BadRequestHttpException('Enter a valid mobile number.');
        }

        return $mobile;
    }

    /**
     * @return array<string, mixed>
     */
    private function withTestJourney(Learner $learner): array
    {
        $payload = $learner->toApiArray();
        $org = Organisation::findOne(['id' => (int) $learner->organisation_id]);
        if ($org !== null) {
            $payload['test_journey'] = (new TestJourneyService())->build($learner, $org);
        } else {
            $payload['test_journey'] = null;
        }
        $payload['theory'] = \app\components\TheoryCertificate::statusPayload(
            $learner->theory_status,
            $learner->theory_pass_date,
        );

        return $payload;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors ? (string) reset($errors) : 'Unable to save.';
    }
}
