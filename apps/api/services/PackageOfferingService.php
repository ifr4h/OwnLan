<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\TenantContext;
use app\models\PackageOffering;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class PackageOfferingService
{
    /**
     * @return array<string, mixed>
     */
    public function list(): array
    {
        $orgId = TenantContext::requireOrganisationId();
        /** @var PackageOffering[] $rows */
        $rows = PackageOffering::find()
            ->andWhere(['organisation_id' => $orgId])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return ['offerings' => array_map(fn (PackageOffering $o) => $this->serialize($o), $rows)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $offering = $this->buildFromData(new PackageOffering(), $data);
        $offering->organisation_id = $orgId;
        $offering->created_at = gmdate('Y-m-d H:i:s');
        $offering->updated_at = gmdate('Y-m-d H:i:s');
        if (!$offering->save()) {
            throw new BadRequestHttpException('Could not save package.');
        }

        return ['offering' => $this->serialize($offering)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(int $id, array $data): array
    {
        $orgId = TenantContext::requireOrganisationId();
        $offering = PackageOffering::findOne(['id' => $id, 'organisation_id' => $orgId]);
        if ($offering === null) {
            throw new NotFoundHttpException('Package not found.');
        }
        $offering = $this->buildFromData($offering, $data);
        $offering->updated_at = gmdate('Y-m-d H:i:s');
        if (!$offering->save()) {
            throw new BadRequestHttpException('Could not save package.');
        }

        return ['offering' => $this->serialize($offering)];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildFromData(PackageOffering $offering, array $data): PackageOffering
    {
        if (isset($data['label'])) {
            $offering->label = mb_substr(trim((string) $data['label']), 0, 120);
        }
        if (isset($data['purchased_hours'])) {
            $hoursRaw = trim((string) $data['purchased_hours']);
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $hoursRaw)) {
                throw new BadRequestHttpException('Enter package hours as a number, e.g. 10 or 10.5.');
            }
            $parts = explode('.', $hoursRaw, 2);
            $wholeHours = (int) $parts[0];
            $extraMinutes = 0;
            if (isset($parts[1]) && $parts[1] !== '') {
                $dec = str_pad(substr($parts[1], 0, 2), 2, '0');
                $extraMinutes = (int) round(((int) $dec) / 100 * 60);
            }
            $offering->purchased_minutes = $wholeHours * 60 + $extraMinutes;
        } elseif (isset($data['purchased_minutes'])) {
            $offering->purchased_minutes = (int) $data['purchased_minutes'];
        }
        if (isset($data['price_pence'])) {
            $offering->price_pence = (int) $data['price_pence'];
        } elseif (isset($data['price'])) {
            $offering->price_pence = Money::poundsToPence((string) $data['price']);
        }
        if (array_key_exists('active', $data)) {
            $offering->active = (bool) $data['active'];
        }
        if (array_key_exists('portal_visible', $data)) {
            $offering->portal_visible = (bool) $data['portal_visible'];
        }
        if (isset($data['sort_order'])) {
            $offering->sort_order = (int) $data['sort_order'];
        }
        if ($offering->label === '' || $offering->purchased_minutes < 15 || $offering->price_pence <= 0) {
            throw new BadRequestHttpException('Package needs a name, duration and price.');
        }

        return $offering;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PackageOffering $offering): array
    {
        return [
            'id' => (int) $offering->id,
            'label' => $offering->label,
            'purchased_minutes' => (int) $offering->purchased_minutes,
            'purchased_hours' => round((int) $offering->purchased_minutes / 60, 1),
            'price_pence' => (int) $offering->price_pence,
            'price_label' => Money::formatPence((int) $offering->price_pence),
            'active' => (bool) $offering->active,
            'portal_visible' => (bool) $offering->portal_visible,
            'sort_order' => (int) $offering->sort_order,
        ];
    }
}
