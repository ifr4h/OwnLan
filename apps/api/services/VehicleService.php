<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\ReceiptStorage;
use app\components\TenantContext;
use app\models\Expense;
use app\models\MileageLog;
use app\models\Organisation;
use app\models\Vehicle;
use DateTimeImmutable;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\UploadedFile;

class VehicleService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $org = $this->requireOrganisation();
        /** @var Vehicle[] $vehicles */
        $vehicles = TenantContext::scopeByOrganisation(Vehicle::find())
            ->orderBy(['is_primary' => SORT_DESC, 'is_active' => SORT_DESC, 'id' => SORT_ASC])
            ->all();

        return array_map(fn (Vehicle $v) => $this->serialize($v), $vehicles);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $org = $this->requireOrganisation();
        $now = gmdate('Y-m-d H:i:s');
        $vehicle = new Vehicle();
        $vehicle->organisation_id = (int) $org->id;
        $this->applyFields($vehicle, $data);
        $vehicle->created_at = $now;
        $vehicle->updated_at = $now;

        if (!empty($data['is_primary'])) {
            $this->clearPrimary((int) $org->id);
            $vehicle->is_primary = true;
        }

        if (!$vehicle->save()) {
            throw new BadRequestHttpException($this->firstError($vehicle));
        }

        return $this->serialize($vehicle);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $vehicle = $this->findVehicle($id);
        $this->applyFields($vehicle, $data);
        $vehicle->updated_at = gmdate('Y-m-d H:i:s');

        if (!empty($data['is_primary'])) {
            $this->clearPrimary((int) $vehicle->organisation_id);
            $vehicle->is_primary = true;
        }

        if (!$vehicle->save()) {
            throw new BadRequestHttpException($this->firstError($vehicle));
        }

        return $this->serialize($vehicle);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(int $id, ?int $year = null): array
    {
        $vehicle = $this->findVehicle($id);
        $org = $this->requireOrganisation();
        $year = $year ?? (int) (new DateTimeImmutable('now'))->format('Y');
        $fromDay = $year . '-01-01';
        $toDay = $year . '-12-31';

        $expenseByCategory = [];
        foreach (Expense::categories() as $cat) {
            $sum = TenantContext::scopeByOrganisation(Expense::find())
                ->andWhere([
                    'voided_at' => null,
                    'vehicle_id' => (int) $vehicle->id,
                    'category' => $cat,
                ])
                ->andWhere(['>=', 'spent_on', $fromDay])
                ->andWhere(['<=', 'spent_on', $toDay])
                ->sum('amount_pence');
            $pence = (int) ($sum ?? 0);
            if ($pence > 0) {
                $expenseByCategory[] = [
                    'category' => $cat,
                    'label' => Expense::categoryLabel($cat),
                    'amount_pence' => $pence,
                    'amount_label' => Money::formatPence($pence),
                ];
            }
        }

        $mileageSum = TenantContext::scopeByOrganisation(MileageLog::find())
            ->andWhere(['vehicle_id' => (int) $vehicle->id, 'purpose' => MileageLog::PURPOSE_BUSINESS])
            ->andWhere(['>=', 'logged_on', $fromDay])
            ->andWhere(['<=', 'logged_on', $toDay])
            ->sum('distance_miles');

        return array_merge($this->serialize($vehicle), [
            'year' => $year,
            'expenses_by_category' => $expenseByCategory,
            'mileage_miles' => round((float) ($mileageSum ?? 0), 1),
            'mileage_label' => number_format((float) ($mileageSum ?? 0), 0) . ' mi',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFields(Vehicle $vehicle, array $data): void
    {
        if (isset($data['registration'])) {
            $vehicle->registration = strtoupper(trim((string) $data['registration']));
        }
        if (array_key_exists('make', $data)) {
            $vehicle->make = $this->nullableText($data['make']);
        }
        if (array_key_exists('model', $data)) {
            $vehicle->model = $this->nullableText($data['model']);
        }
        if (isset($data['transmission'])) {
            $tx = strtolower(trim((string) $data['transmission']));
            if (!in_array($tx, Vehicle::transmissions(), true)) {
                throw new BadRequestHttpException('Transmission must be manual or automatic.');
            }
            $vehicle->transmission = $tx;
        }
        if (array_key_exists('is_active', $data)) {
            $vehicle->is_active = (bool) $data['is_active'];
        }
        if (array_key_exists('notes', $data)) {
            $vehicle->notes = $this->nullableText($data['notes']);
        }
    }

    private function clearPrimary(int $orgId): void
    {
        Vehicle::updateAll(['is_primary' => false], ['organisation_id' => $orgId]);
    }

    private function findVehicle(int $id): Vehicle
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = TenantContext::scopeByOrganisation(Vehicle::find())
            ->andWhere(['id' => $id])
            ->one();
        if ($vehicle === null) {
            throw new NotFoundHttpException('Vehicle not found.');
        }

        return $vehicle;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Vehicle $vehicle): array
    {
        return [
            'id' => (int) $vehicle->id,
            'registration' => $vehicle->registration,
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'display_name' => $vehicle->displayName(),
            'transmission' => $vehicle->transmission,
            'transmission_label' => $vehicle->transmissionLabel(),
            'is_primary' => (bool) $vehicle->is_primary,
            'is_active' => (bool) $vehicle->is_active,
            'notes' => $vehicle->notes,
        ];
    }

    private function requireOrganisation(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $organisation = Organisation::findOne(['id' => $orgId]);
        if ($organisation === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $organisation;
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function firstError(\yii\base\Model $model): string
    {
        $errors = $model->getFirstErrors();

        return $errors !== [] ? (string) reset($errors) : 'Could not save.';
    }
}
