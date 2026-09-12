<?php

declare(strict_types=1);

namespace app\services;

use app\components\PortalContext;
use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerLocation;
use app\models\Organisation;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

class LearnerLocationService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForPortal(): array
    {
        $account = PortalContext::requireAccount();

        return $this->listForLearnerScoped((int) $account->organisation_id, (int) $account->learner_id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForLearner(int $learnerId): array
    {
        $this->findLearnerOwned($learnerId);

        return $this->listForLearnerScoped(TenantContext::requireOrganisationId(), $learnerId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listForLearnerScoped(int $organisationId, int $learnerId): array
    {
        /** @var LearnerLocation[] $rows */
        $rows = LearnerLocation::find()
            ->andWhere(['organisation_id' => $organisationId, 'learner_id' => $learnerId])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(fn (LearnerLocation $row) => $this->toArray($row), $rows);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createForPortal(array $data): array
    {
        $account = PortalContext::requireAccount();

        return $this->createScoped((int) $account->organisation_id, (int) $account->learner_id, $data, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(int $learnerId, array $data): array
    {
        $org = $this->requireOrganisation();
        $this->findLearnerOwned($learnerId);

        return $this->createScoped((int) $org->id, $learnerId, $data, false);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function createScoped(int $organisationId, int $learnerId, array $data, bool $fromPortal = false): array
    {
        $learner = Learner::findOne([
            'id' => $learnerId,
            'organisation_id' => $organisationId,
            'archived_at' => null,
        ]);
        if (!$learner instanceof Learner) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        $label = trim((string) ($data['label'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        if ($label === '' || $address === '') {
            throw new BadRequestHttpException('Label and address are required.');
        }

        $icon = $this->resolveIcon($data['icon'] ?? 'pin');
        $makeDefault = !empty($data['is_default']) || $this->countForLearner($organisationId, $learnerId) === 0;

        if ($makeDefault) {
            $this->clearDefaults($organisationId, $learnerId);
        }

        $row = new LearnerLocation();
        $row->organisation_id = $organisationId;
        $row->learner_id = $learnerId;
        $row->label = mb_substr($label, 0, 64);
        $row->icon = $icon;
        $row->address = $address;
        $row->usage = $this->resolveUsage($data['usage'] ?? LearnerLocation::USAGE_BOTH);
        $row->is_default = $makeDefault;
        $row->sort_order = $this->nextSort($organisationId, $learnerId);
        $now = gmdate('Y-m-d H:i:s');
        $row->created_at = $now;
        $row->updated_at = $now;

        if (!$row->save()) {
            throw new BadRequestHttpException('Could not save that place.');
        }

        if ($makeDefault) {
            $this->syncLearnerDefault($learner, $address);
        }

        if ($fromPortal) {
            (new LearnerProfileChangeService())->record(
                $learner,
                'portal_place',
                'Added a pickup place',
                [['field' => 'place', 'from' => null, 'to' => $label . ': ' . $address]],
            );
        }

        return $this->toArray($row);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $learnerId, int $id, array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $learner = $this->findLearnerOwned($learnerId);
        $row = $this->findOwnedScoped($orgId, $learnerId, $id);

        return $this->updateRow($learner, $row, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateForPortal(int $id, array $data): array
    {
        $account = PortalContext::requireAccount();
        $learner = Learner::findOne([
            'id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
        ]);
        if (!$learner instanceof Learner) {
            throw new NotFoundHttpException('Pupil not found.');
        }
        $row = $this->findOwnedScoped((int) $account->organisation_id, (int) $account->learner_id, $id);

        return $this->updateRow($learner, $row, $data, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function updateRow(Learner $learner, LearnerLocation $row, array $data, bool $fromPortal = false): array
    {
        $beforeLabel = (string) $row->label;
        $beforeAddress = (string) $row->address;

        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                throw new BadRequestHttpException('Label is required.');
            }
            $row->label = mb_substr($label, 0, 64);
        }
        if (array_key_exists('address', $data)) {
            $address = trim((string) $data['address']);
            if ($address === '') {
                throw new BadRequestHttpException('Address is required.');
            }
            $row->address = $address;
        }
        if (array_key_exists('icon', $data)) {
            $row->icon = $this->resolveIcon($data['icon']);
        }
        if (array_key_exists('usage', $data)) {
            $row->usage = $this->resolveUsage($data['usage']);
        }
        if (!empty($data['is_default'])) {
            $this->clearDefaults((int) $row->organisation_id, (int) $row->learner_id);
            $row->is_default = true;
        }

        $row->updated_at = gmdate('Y-m-d H:i:s');
        if (!$row->save()) {
            throw new BadRequestHttpException('Could not update that place.');
        }

        if ($row->is_default) {
            $this->syncLearnerDefault($learner, (string) $row->address);
        }

        if ($fromPortal) {
            $changes = [];
            if ($beforeLabel !== (string) $row->label) {
                $changes[] = ['field' => 'label', 'from' => $beforeLabel, 'to' => (string) $row->label];
            }
            if ($beforeAddress !== (string) $row->address) {
                $changes[] = ['field' => 'address', 'from' => $beforeAddress, 'to' => (string) $row->address];
            }
            if ($changes !== []) {
                (new LearnerProfileChangeService())->record(
                    $learner,
                    'portal_place',
                    'Updated a pickup place',
                    $changes,
                );
            }
        }

        return $this->toArray($row);
    }

    public function delete(int $learnerId, int $id): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $learner = $this->findLearnerOwned($learnerId);
        $row = $this->findOwnedScoped($orgId, $learnerId, $id);

        return $this->deleteRow($learner, $row);
    }

    public function deleteForPortal(int $id): array
    {
        $account = PortalContext::requireAccount();
        $learner = Learner::findOne([
            'id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
        ]);
        if (!$learner instanceof Learner) {
            throw new NotFoundHttpException('Pupil not found.');
        }
        $row = $this->findOwnedScoped((int) $account->organisation_id, (int) $account->learner_id, $id);
        $label = (string) $row->label;
        $address = (string) $row->address;
        $result = $this->deleteRow($learner, $row);
        (new LearnerProfileChangeService())->record(
            $learner,
            'portal_place',
            'Removed a pickup place',
            [['field' => 'place', 'from' => $label . ': ' . $address, 'to' => null]],
        );

        return $result;
    }

    private function deleteRow(Learner $learner, LearnerLocation $row): array
    {
        $orgId = (int) $row->organisation_id;
        $learnerId = (int) $row->learner_id;
        $wasDefault = (bool) $row->is_default;
        $row->delete();

        if ($wasDefault) {
            /** @var LearnerLocation|null $next */
            $next = LearnerLocation::find()
                ->andWhere(['organisation_id' => $orgId, 'learner_id' => $learnerId])
                ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                ->one();
            if ($next instanceof LearnerLocation) {
                $next->is_default = true;
                $next->updated_at = gmdate('Y-m-d H:i:s');
                $next->save(false, ['is_default', 'updated_at']);
                $this->syncLearnerDefault($learner, (string) $next->address);
            } else {
                $this->syncLearnerDefault($learner, null);
            }
        }

        return ['ok' => true];
    }

    public function setDefault(int $learnerId, int $id): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $learner = $this->findLearnerOwned($learnerId);
        $row = $this->findOwnedScoped($orgId, $learnerId, $id);
        $this->clearDefaults($orgId, $learnerId);
        $row->is_default = true;
        $row->updated_at = gmdate('Y-m-d H:i:s');
        $row->save(false, ['is_default', 'updated_at']);
        $this->syncLearnerDefault($learner, (string) $row->address);

        return $this->toArray($row);
    }

    public function setDefaultForPortal(int $id): array
    {
        $account = PortalContext::requireAccount();
        $learner = Learner::findOne([
            'id' => (int) $account->learner_id,
            'organisation_id' => (int) $account->organisation_id,
        ]);
        if (!$learner instanceof Learner) {
            throw new NotFoundHttpException('Pupil not found.');
        }
        $row = $this->findOwnedScoped((int) $account->organisation_id, (int) $account->learner_id, $id);
        $previousDefault = LearnerLocation::find()
            ->andWhere([
                'organisation_id' => (int) $account->organisation_id,
                'learner_id' => (int) $account->learner_id,
                'is_default' => true,
            ])
            ->one();
        $from = $previousDefault instanceof LearnerLocation
            ? (string) $previousDefault->label . ': ' . (string) $previousDefault->address
            : null;
        $this->clearDefaults((int) $account->organisation_id, (int) $account->learner_id);
        $row->is_default = true;
        $row->updated_at = gmdate('Y-m-d H:i:s');
        $row->save(false, ['is_default', 'updated_at']);
        $this->syncLearnerDefault($learner, (string) $row->address);
        (new LearnerProfileChangeService())->record(
            $learner,
            'portal_place',
            'Changed default pickup',
            [['field' => 'default_pickup', 'from' => $from, 'to' => (string) $row->label . ': ' . (string) $row->address]],
        );

        return $this->toArray($row);
    }

    public function findDefault(int $learnerId, ?int $organisationId = null): ?LearnerLocation
    {
        $orgId = $organisationId ?? TenantContext::requireOrganisationId();
        /** @var LearnerLocation|null $row */
        $row = LearnerLocation::find()
            ->andWhere([
                'organisation_id' => $orgId,
                'learner_id' => $learnerId,
                'is_default' => true,
            ])
            ->one();

        return $row instanceof LearnerLocation ? $row : null;
    }

    public function findOwnedLocation(int $learnerId, int $id): LearnerLocation
    {
        return $this->findOwnedScoped(TenantContext::requireOrganisationId(), $learnerId, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(LearnerLocation $row): array
    {
        return [
            'id' => (int) $row->id,
            'learner_id' => (int) $row->learner_id,
            'label' => $row->label,
            'icon' => $row->icon,
            'address' => $row->address,
            'usage' => $row->usage ?: LearnerLocation::USAGE_BOTH,
            'usage_label' => LearnerLocation::usageLabel((string) ($row->usage ?: LearnerLocation::USAGE_BOTH)),
            'is_default' => (bool) $row->is_default,
            'sort_order' => (int) $row->sort_order,
            'shared_with_instructor' => true,
        ];
    }

    private function resolveUsage(mixed $value): string
    {
        $usage = is_string($value) ? trim($value) : LearnerLocation::USAGE_BOTH;
        if (!in_array($usage, LearnerLocation::usages(), true)) {
            return LearnerLocation::USAGE_BOTH;
        }

        return $usage;
    }

    private function resolveIcon(mixed $value): string
    {
        $icon = is_string($value) ? trim($value) : 'pin';
        if (!in_array($icon, LearnerLocation::ICONS, true)) {
            return 'pin';
        }

        return $icon;
    }

    private function countForLearner(int $organisationId, int $learnerId): int
    {
        return (int) LearnerLocation::find()
            ->andWhere(['organisation_id' => $organisationId, 'learner_id' => $learnerId])
            ->count();
    }

    private function nextSort(int $organisationId, int $learnerId): int
    {
        $max = (int) LearnerLocation::find()
            ->andWhere(['organisation_id' => $organisationId, 'learner_id' => $learnerId])
            ->max('sort_order');

        return $max + 1;
    }

    private function clearDefaults(int $organisationId, int $learnerId): void
    {
        LearnerLocation::updateAll(
            ['is_default' => false],
            ['organisation_id' => $organisationId, 'learner_id' => $learnerId],
        );
    }

    private function syncLearnerDefault(Learner $learner, ?string $address): void
    {
        $learner->default_pickup_address = $address;
        $learner->updated_at = gmdate('Y-m-d H:i:s');
        $learner->save(false, ['default_pickup_address', 'updated_at']);
    }

    private function findOwnedScoped(int $organisationId, int $learnerId, int $id): LearnerLocation
    {
        $row = LearnerLocation::find()
            ->andWhere([
                'id' => $id,
                'organisation_id' => $organisationId,
                'learner_id' => $learnerId,
            ])
            ->one();
        if (!$row instanceof LearnerLocation) {
            throw new NotFoundHttpException('Place not found.');
        }

        return $row;
    }

    private function findLearnerOwned(int $learnerId): Learner
    {
        $learner = TenantContext::scopeByOrganisation(Learner::find())
            ->andWhere(['id' => $learnerId, 'archived_at' => null])
            ->one();
        if (!$learner instanceof Learner) {
            throw new NotFoundHttpException('Pupil not found.');
        }

        return $learner;
    }

    private function requireOrganisation(): Organisation
    {
        if (Yii::$app->user->isGuest) {
            throw new UnauthorizedHttpException('Authentication required.');
        }
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }
}
