<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\TenantContext;
use app\models\Expense;
use app\models\MileageLog;
use app\models\Organisation;
use app\models\Vehicle;
use DateTimeImmutable;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class MileageService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $fromDay = null, ?string $toDay = null): array
    {
        $query = TenantContext::scopeByOrganisation(MileageLog::find())
            ->with(['vehicle'])
            ->orderBy(['logged_on' => SORT_DESC, 'id' => SORT_DESC]);

        if ($fromDay !== null) {
            $query->andWhere(['>=', 'logged_on', $fromDay]);
        }
        if ($toDay !== null) {
            $query->andWhere(['<=', 'logged_on', $toDay]);
        }

        return array_map(
            fn (MileageLog $log) => $this->serialize($log),
            $query->limit(200)->all(),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $org = $this->requireOrganisation();
        $vehicleId = (int) ($data['vehicle_id'] ?? 0);
        $this->assertVehicle($vehicleId);

        $loggedOn = trim((string) ($data['logged_on'] ?? ''));
        if ($loggedOn === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $loggedOn)) {
            throw new BadRequestHttpException('logged_on must be YYYY-MM-DD.');
        }

        $distance = (float) ($data['distance_miles'] ?? 0);
        if ($distance <= 0) {
            throw new BadRequestHttpException('Distance must be greater than zero.');
        }

        $purpose = strtolower(trim((string) ($data['purpose'] ?? MileageLog::PURPOSE_BUSINESS)));
        if (!in_array($purpose, MileageLog::purposes(), true)) {
            throw new BadRequestHttpException('Purpose must be business or personal.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $log = new MileageLog();
        $log->organisation_id = (int) $org->id;
        $log->vehicle_id = $vehicleId;
        $log->logged_on = $loggedOn;
        $log->distance_miles = (string) round($distance, 1);
        $log->purpose = $purpose;
        $log->start_reading = isset($data['start_reading']) ? (int) $data['start_reading'] : null;
        $log->end_reading = isset($data['end_reading']) ? (int) $data['end_reading'] : null;
        $log->notes = $this->nullableText($data['notes'] ?? null);
        $log->created_by_user_id = Yii::$app->user->isGuest ? null : (int) Yii::$app->user->id;
        $log->created_at = $now;
        $log->updated_at = $now;

        if (!$log->save()) {
            throw new BadRequestHttpException($this->firstError($log));
        }
        $log->refresh();

        return $this->serialize($log);
    }

    public function delete(int $id): void
    {
        /** @var MileageLog|null $log */
        $log = TenantContext::scopeByOrganisation(MileageLog::find())
            ->andWhere(['id' => $id])
            ->one();
        if ($log === null) {
            throw new NotFoundHttpException('Mileage entry not found.');
        }
        $log->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(MileageLog $log): array
    {
        $vehicle = $log->vehicle;

        return [
            'id' => (int) $log->id,
            'vehicle_id' => (int) $log->vehicle_id,
            'vehicle_registration' => $vehicle?->registration,
            'vehicle_display_name' => $vehicle?->displayName(),
            'logged_on' => $log->logged_on,
            'logged_on_display' => (new DateTimeImmutable($log->logged_on))->format('D j M Y'),
            'distance_miles' => (float) $log->distance_miles,
            'distance_label' => number_format((float) $log->distance_miles, 1) . ' mi',
            'purpose' => $log->purpose,
            'purpose_label' => $log->purposeLabel(),
            'start_reading' => $log->start_reading,
            'end_reading' => $log->end_reading,
            'notes' => $log->notes,
        ];
    }

    private function assertVehicle(int $vehicleId): void
    {
        if ($vehicleId <= 0) {
            throw new BadRequestHttpException('vehicle_id is required.');
        }
        $exists = TenantContext::scopeByOrganisation(Vehicle::find())
            ->andWhere(['id' => $vehicleId, 'is_active' => true])
            ->exists();
        if (!$exists) {
            throw new BadRequestHttpException('Vehicle not found.');
        }
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
