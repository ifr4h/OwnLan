<?php

declare(strict_types=1);

namespace app\services;

use app\components\Money;
use app\components\PublicWebsiteFields;
use app\components\TenantContext;
use app\models\Learner;
use app\models\LearnerServiceRate;
use app\models\Organisation;
use app\models\OrganisationPricingRule;
use app\models\OrganisationService;
use app\models\PackageOffering;
use app\models\ServicePriceChange;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

/**
 * Instructor commercial catalogue: services, packages, pricing rules, pupil rates.
 */
class ServiceCatalogueService
{
    private PackageOfferingService $packages;

    public function __construct(?PackageOfferingService $packages = null)
    {
        $this->packages = $packages ?? new PackageOfferingService();
    }

    /**
     * @return array<string, mixed>
     */
    public function home(): array
    {
        $org = $this->requireOrg();
        $this->ensureSeeded($org);

        $services = OrganisationService::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $packages = PackageOffering::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $rules = OrganisationPricingRule::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $pupilRates = LearnerServiceRate::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->orderBy(['effective_from' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(50)
            ->all();

        return [
            'services' => array_map(fn (OrganisationService $s) => $this->serializeService($s), $services),
            'packages' => array_map(fn (PackageOffering $p) => $this->serializePackage($p), $packages),
            'pricing_rules' => array_map(fn (OrganisationPricingRule $r) => $this->serializeRule($r), $rules),
            'pupil_rates' => array_map(fn (LearnerServiceRate $r) => $this->serializePupilRate($r), $pupilRates),
            'kind_options' => $this->kindOptions(),
            'booking_access_options' => $this->bookingAccessOptions(),
            'status_options' => $this->statusOptions(),
            'duration_presets' => [30, 45, 60, 90, 120],
            'weekday_options' => $this->weekdayOptions(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createService(array $data): array
    {
        $org = $this->requireOrg();
        $this->ensureSeeded($org);

        $service = new OrganisationService();
        $service->organisation_id = (int) $org->id;
        $service->created_at = gmdate('Y-m-d H:i:s');
        $service->updated_at = gmdate('Y-m-d H:i:s');
        $service->sort_order = (int) (OrganisationService::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->max('sort_order') ?? 0) + 1;
        $this->applyServiceData($service, $data, true);
        if (!$service->save()) {
            throw new BadRequestHttpException($this->firstError($service) ?: 'Could not save service.');
        }
        if ($service->is_default) {
            $this->clearOtherDefaults((int) $org->id, (int) $service->id);
        }
        $this->syncPublicServices($org);

        return ['service' => $this->serializeService($service, true)];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewService(int $id): array
    {
        $org = $this->requireOrg();
        $service = $this->findService($org, $id);
        $changes = ServicePriceChange::find()
            ->andWhere(['service_id' => (int) $service->id])
            ->orderBy(['effective_on' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return [
            'service' => $this->serializeService($service, true),
            'price_changes' => array_map(fn (ServicePriceChange $c) => [
                'id' => (int) $c->id,
                'price_pence' => (int) $c->price_pence,
                'price_label' => Money::formatPence((int) $c->price_pence),
                'effective_on' => (string) $c->effective_on,
            ], $changes),
            'kind_options' => $this->kindOptions(),
            'booking_access_options' => $this->bookingAccessOptions(),
            'status_options' => $this->statusOptions(),
            'duration_presets' => [30, 45, 60, 90, 120],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateService(int $id, array $data): array
    {
        $org = $this->requireOrg();
        $service = $this->findService($org, $id);
        $this->applyServiceData($service, $data, false);
        $service->updated_at = gmdate('Y-m-d H:i:s');
        if (!$service->save()) {
            throw new BadRequestHttpException($this->firstError($service) ?: 'Could not save service.');
        }
        if ($service->is_default) {
            $this->clearOtherDefaults((int) $org->id, (int) $service->id);
        }
        $this->syncPublicServices($org);

        return ['service' => $this->serializeService($service, true)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function schedulePriceChange(int $serviceId, array $data): array
    {
        $org = $this->requireOrg();
        $service = $this->findService($org, $serviceId);
        $on = trim((string) ($data['effective_on'] ?? ''));
        if ($on === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) {
            throw new BadRequestHttpException('Choose a date for the new price.');
        }
        $pence = $this->resolvePricePence($data);
        if ($pence <= 0) {
            throw new BadRequestHttpException('Enter a price greater than zero.');
        }

        $change = new ServicePriceChange();
        $change->organisation_id = (int) $org->id;
        $change->service_id = (int) $service->id;
        $change->price_pence = $pence;
        $change->effective_on = $on;
        $change->created_at = gmdate('Y-m-d H:i:s');
        if (!$change->save()) {
            throw new BadRequestHttpException('Could not save price change.');
        }

        // If effective today or earlier in org timezone, bump current list price too.
        $today = (new \DateTimeImmutable('now', new \DateTimeZone($org->timezone ?: 'Europe/London')))->format('Y-m-d');
        if ($on <= $today) {
            $service->price_pence = $pence;
            $service->updated_at = gmdate('Y-m-d H:i:s');
            $service->save(false, ['price_pence', 'updated_at']);
            $this->syncPublicServices($org);
        }

        return [
            'price_change' => [
                'id' => (int) $change->id,
                'price_pence' => (int) $change->price_pence,
                'price_label' => Money::formatPence((int) $change->price_pence),
                'effective_on' => (string) $change->effective_on,
            ],
            'service' => $this->serializeService($service, true),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPricingRule(array $data): array
    {
        $org = $this->requireOrg();
        $rule = new OrganisationPricingRule();
        $rule->organisation_id = (int) $org->id;
        $rule->created_at = gmdate('Y-m-d H:i:s');
        $rule->updated_at = gmdate('Y-m-d H:i:s');
        $rule->sort_order = (int) (OrganisationPricingRule::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->max('sort_order') ?? 0) + 1;
        $this->applyRuleData($rule, $data, true);
        if (!$rule->save()) {
            throw new BadRequestHttpException('Could not save pricing rule.');
        }

        return ['pricing_rule' => $this->serializeRule($rule)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updatePricingRule(int $id, array $data): array
    {
        $org = $this->requireOrg();
        $rule = OrganisationPricingRule::findOne(['id' => $id, 'organisation_id' => (int) $org->id]);
        if ($rule === null) {
            throw new NotFoundHttpException('Pricing rule not found.');
        }
        $this->applyRuleData($rule, $data, false);
        $rule->updated_at = gmdate('Y-m-d H:i:s');
        if (!$rule->save()) {
            throw new BadRequestHttpException('Could not save pricing rule.');
        }

        return ['pricing_rule' => $this->serializeRule($rule)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPupilRate(array $data): array
    {
        $org = $this->requireOrg();
        $learnerId = (int) ($data['learner_id'] ?? 0);
        $learner = Learner::findOne(['id' => $learnerId, 'organisation_id' => (int) $org->id]);
        if ($learner === null) {
            throw new NotFoundHttpException('Pupil not found.');
        }
        $from = trim((string) ($data['effective_from'] ?? ''));
        if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            throw new BadRequestHttpException('Choose when this rate starts.');
        }
        $to = trim((string) ($data['effective_to'] ?? ''));
        if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            throw new BadRequestHttpException('End date looks wrong.');
        }
        if ($to !== '' && $to < $from) {
            throw new BadRequestHttpException('End date must be on or after the start date.');
        }
        $pence = $this->resolvePricePence($data);
        if ($pence <= 0) {
            throw new BadRequestHttpException('Enter a price greater than zero.');
        }
        $serviceId = null;
        if (isset($data['service_id']) && $data['service_id'] !== null && $data['service_id'] !== '') {
            $service = $this->findService($org, (int) $data['service_id']);
            $serviceId = (int) $service->id;
        }

        $rate = new LearnerServiceRate();
        $rate->organisation_id = (int) $org->id;
        $rate->learner_id = $learnerId;
        $rate->service_id = $serviceId;
        $rate->price_pence = $pence;
        $rate->effective_from = $from;
        $rate->effective_to = $to !== '' ? $to : null;
        $rate->note = isset($data['note']) ? mb_substr(trim((string) $data['note']), 0, 255) : null;
        $rate->created_at = gmdate('Y-m-d H:i:s');
        $rate->updated_at = gmdate('Y-m-d H:i:s');
        if (!$rate->save()) {
            throw new BadRequestHttpException('Could not save pupil rate.');
        }

        return ['pupil_rate' => $this->serializePupilRate($rate)];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updatePupilRate(int $id, array $data): array
    {
        $org = $this->requireOrg();
        $rate = LearnerServiceRate::findOne(['id' => $id, 'organisation_id' => (int) $org->id]);
        if ($rate === null) {
            throw new NotFoundHttpException('Pupil rate not found.');
        }
        if (isset($data['effective_from'])) {
            $from = trim((string) $data['effective_from']);
            if ($from === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                throw new BadRequestHttpException('Choose when this rate starts.');
            }
            $rate->effective_from = $from;
        }
        if (array_key_exists('effective_to', $data)) {
            $to = trim((string) ($data['effective_to'] ?? ''));
            $rate->effective_to = $to === '' ? null : $to;
        }
        if (isset($data['price_pence']) || isset($data['price'])) {
            $rate->price_pence = $this->resolvePricePence($data);
        }
        if (array_key_exists('note', $data)) {
            $rate->note = $data['note'] !== null && $data['note'] !== ''
                ? mb_substr(trim((string) $data['note']), 0, 255)
                : null;
        }
        if (array_key_exists('service_id', $data)) {
            if ($data['service_id'] === null || $data['service_id'] === '') {
                $rate->service_id = null;
            } else {
                $service = $this->findService($org, (int) $data['service_id']);
                $rate->service_id = (int) $service->id;
            }
        }
        $rate->updated_at = gmdate('Y-m-d H:i:s');
        if (!$rate->save()) {
            throw new BadRequestHttpException('Could not save pupil rate.');
        }

        return ['pupil_rate' => $this->serializePupilRate($rate)];
    }

    public function ensureSeeded(Organisation $org): void
    {
        $exists = OrganisationService::find()
            ->andWhere(['organisation_id' => (int) $org->id])
            ->exists();
        if ($exists) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $seeded = false;
        foreach (PublicWebsiteFields::decodeServices($org) as $i => $row) {
            $service = new OrganisationService();
            $service->organisation_id = (int) $org->id;
            $service->name = (string) ($row['name'] ?? 'Lesson');
            $service->kind = in_array($row['type'] ?? '', OrganisationService::kinds(), true)
                ? (string) $row['type']
                : OrganisationService::KIND_LESSON;
            $service->description = isset($row['description']) ? (string) $row['description'] : null;
            $service->duration_minutes = max(15, (int) ($row['duration_minutes'] ?? 60));
            $service->price_pence = max(0, (int) ($row['price_pence'] ?? 0));
            if ($service->price_pence <= 0) {
                continue;
            }
            $service->status = OrganisationService::STATUS_ACTIVE;
            $service->visibility_public = !isset($row['public']) || (bool) $row['public'];
            $service->booking_access = OrganisationService::BOOKING_INSTRUCTOR;
            $service->is_default = $i === 0;
            $service->sort_order = $i;
            $service->created_at = $now;
            $service->updated_at = $now;
            $service->save(false);
            $seeded = true;
        }

        if (!$seeded) {
            $duration = (int) ($org->default_lesson_duration_minutes ?: 60);
            $rate = (int) ($org->default_hourly_rate_pence ?: Organisation::DEFAULT_HOURLY_RATE_PENCE);
            $price = Money::lessonPriceFromHourlyRate($rate, $duration);
            $service = new OrganisationService();
            $service->organisation_id = (int) $org->id;
            $service->name = 'Standard lesson';
            $service->kind = OrganisationService::KIND_LESSON;
            $service->duration_minutes = $duration;
            $service->price_pence = $price;
            $service->status = OrganisationService::STATUS_ACTIVE;
            $service->visibility_public = true;
            $service->booking_access = OrganisationService::BOOKING_INSTRUCTOR;
            $service->is_default = true;
            $service->sort_order = 0;
            $service->created_at = $now;
            $service->updated_at = $now;
            $service->save(false);
        }
    }

    public function syncPublicServices(Organisation $org): void
    {
        /** @var OrganisationService[] $rows */
        $rows = OrganisationService::find()
            ->andWhere([
                'organisation_id' => (int) $org->id,
                'status' => OrganisationService::STATUS_ACTIVE,
                'visibility_public' => true,
            ])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $payload = [];
        foreach ($rows as $service) {
            $payload[] = [
                'id' => 'service-' . $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'duration_minutes' => (int) $service->duration_minutes,
                'price_pence' => (int) $service->price_pence,
                'type' => $service->kind,
                'public' => true,
            ];
        }
        $encoded = PublicWebsiteFields::encodeServices($payload);
        $org->profile_services = $encoded === [] ? null : json_encode($encoded, JSON_THROW_ON_ERROR);
        $org->profile_public_pricing = PublicWebsiteFields::servicesToLegacyPricing($encoded);
        $org->save(false, ['profile_services', 'profile_public_pricing']);
    }

    private function requireOrg(): Organisation
    {
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne($orgId);
        if ($org === null) {
            throw new NotFoundHttpException('Organisation not found.');
        }

        return $org;
    }

    private function findService(Organisation $org, int $id): OrganisationService
    {
        $service = OrganisationService::findOne(['id' => $id, 'organisation_id' => (int) $org->id]);
        if ($service === null) {
            throw new NotFoundHttpException('Service not found.');
        }

        return $service;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyServiceData(OrganisationService $service, array $data, bool $creating): void
    {
        if ($creating || isset($data['name'])) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw new BadRequestHttpException('Give this service a name.');
            }
            $service->name = mb_substr($name, 0, 120);
        }
        if ($creating || isset($data['kind'])) {
            $kind = (string) ($data['kind'] ?? OrganisationService::KIND_LESSON);
            if (!in_array($kind, OrganisationService::kinds(), true)) {
                throw new BadRequestHttpException('Choose a service type.');
            }
            $service->kind = $kind;
        }
        if (array_key_exists('description', $data)) {
            $desc = trim((string) ($data['description'] ?? ''));
            $service->description = $desc === '' ? null : mb_substr($desc, 0, 500);
        }
        if ($creating || isset($data['duration_minutes'])) {
            $mins = (int) ($data['duration_minutes'] ?? 60);
            if ($mins < 15 || $mins > 480) {
                throw new BadRequestHttpException('Duration must be between 15 minutes and 8 hours.');
            }
            $service->duration_minutes = $mins;
        }
        if ($creating || isset($data['price_pence']) || isset($data['price'])) {
            $pence = $this->resolvePricePence($data);
            if ($pence <= 0) {
                throw new BadRequestHttpException('Enter a price greater than zero.');
            }
            $service->price_pence = $pence;
        }
        if ($creating || isset($data['status'])) {
            $status = (string) ($data['status'] ?? OrganisationService::STATUS_ACTIVE);
            if (!in_array($status, OrganisationService::statuses(), true)) {
                throw new BadRequestHttpException('Choose a status.');
            }
            $service->status = $status;
        }
        if (array_key_exists('visibility_public', $data) || $creating) {
            $service->visibility_public = array_key_exists('visibility_public', $data)
                ? (bool) $data['visibility_public']
                : true;
        }
        if ($creating || isset($data['booking_access'])) {
            $access = (string) ($data['booking_access'] ?? OrganisationService::BOOKING_INSTRUCTOR);
            if (!in_array($access, OrganisationService::bookingAccesses(), true)) {
                throw new BadRequestHttpException('Choose who can book this.');
            }
            $service->booking_access = $access;
        }
        if (array_key_exists('is_default', $data)) {
            $service->is_default = (bool) $data['is_default'];
        } elseif ($creating) {
            $hasDefault = OrganisationService::find()
                ->andWhere(['organisation_id' => (int) $service->organisation_id, 'is_default' => true])
                ->exists();
            $service->is_default = !$hasDefault;
        }
        if (isset($data['sort_order'])) {
            $service->sort_order = (int) $data['sort_order'];
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyRuleData(OrganisationPricingRule $rule, array $data, bool $creating): void
    {
        if ($creating || isset($data['label'])) {
            $label = trim((string) ($data['label'] ?? ''));
            if ($label === '') {
                throw new BadRequestHttpException('Give this rule a name.');
            }
            $rule->label = mb_substr($label, 0, 120);
        }
        if (array_key_exists('active', $data) || $creating) {
            $rule->active = array_key_exists('active', $data) ? (bool) $data['active'] : true;
        }
        if ($creating || isset($data['days_of_week']) || isset($data['days'])) {
            $days = $data['days_of_week'] ?? $data['days'] ?? [];
            if (is_string($days)) {
                $rule->days_of_week = preg_replace('/[^1-7,]/', '', $days) ?? '';
            } elseif (is_array($days)) {
                $clean = [];
                foreach ($days as $d) {
                    $n = (int) $d;
                    if ($n >= 1 && $n <= 7) {
                        $clean[] = $n;
                    }
                }
                $clean = array_values(array_unique($clean));
                sort($clean);
                $rule->days_of_week = implode(',', $clean);
            } else {
                $rule->days_of_week = '';
            }
        }
        if (array_key_exists('time_after', $data) || $creating) {
            $rule->time_after = $this->normalizeHm($data['time_after'] ?? null);
        }
        if (array_key_exists('time_before', $data) || $creating) {
            $rule->time_before = $this->normalizeHm($data['time_before'] ?? null);
        }
        if ($creating || isset($data['adjustment_kind'])) {
            $kind = (string) ($data['adjustment_kind'] ?? '');
            if (!in_array($kind, [
                OrganisationPricingRule::ADJUST_ADD,
                OrganisationPricingRule::ADJUST_PERCENT,
                OrganisationPricingRule::ADJUST_SET,
            ], true)) {
                throw new BadRequestHttpException('Choose how the price changes.');
            }
            $rule->adjustment_kind = $kind;
        }
        if ($creating || isset($data['adjustment_value']) || isset($data['price']) || isset($data['price_pence']) || isset($data['percent'])) {
            if ($rule->adjustment_kind === OrganisationPricingRule::ADJUST_PERCENT) {
                $rule->adjustment_value = (int) ($data['percent'] ?? $data['adjustment_value'] ?? 0);
            } elseif (isset($data['price']) || isset($data['price_pence'])) {
                $rule->adjustment_value = $this->resolvePricePence($data);
            } else {
                $rule->adjustment_value = (int) ($data['adjustment_value'] ?? 0);
            }
        }
        if (array_key_exists('service_id', $data)) {
            if ($data['service_id'] === null || $data['service_id'] === '') {
                $rule->service_id = null;
            } else {
                $org = Organisation::findOne((int) $rule->organisation_id);
                if ($org === null) {
                    throw new NotFoundHttpException('Organisation not found.');
                }
                $service = $this->findService($org, (int) $data['service_id']);
                $rule->service_id = (int) $service->id;
            }
        }
        if (isset($data['sort_order'])) {
            $rule->sort_order = (int) $data['sort_order'];
        }
    }

    private function normalizeHm(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $v = trim((string) $raw);
        if (!preg_match('/^\d{2}:\d{2}$/', $v)) {
            throw new BadRequestHttpException('Use a time like 18:00.');
        }

        return $v;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolvePricePence(array $data): int
    {
        if (isset($data['price_pence']) && $data['price_pence'] !== null && $data['price_pence'] !== '') {
            return Money::requireNonNegativePence($data['price_pence'], 'Price');
        }
        if (isset($data['price']) && $data['price'] !== null && $data['price'] !== '') {
            $pence = Money::poundsToPence($data['price']);
            if ($pence < 0) {
                throw new BadRequestHttpException('Price cannot be negative.');
            }

            return $pence;
        }
        throw new BadRequestHttpException('Enter a price.');
    }

    private function clearOtherDefaults(int $orgId, int $keepId): void
    {
        OrganisationService::updateAll(
            ['is_default' => false],
            ['and', ['organisation_id' => $orgId], ['!=', 'id', $keepId]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeService(OrganisationService $service, bool $detail = false): array
    {
        $out = [
            'id' => (int) $service->id,
            'name' => $service->name,
            'kind' => $service->kind,
            'kind_label' => $this->kindLabel($service->kind),
            'duration_minutes' => (int) $service->duration_minutes,
            'duration_label' => $this->durationLabel((int) $service->duration_minutes),
            'price_pence' => (int) $service->price_pence,
            'price_label' => Money::formatPence((int) $service->price_pence),
            'status' => $service->status,
            'status_label' => $this->statusLabel($service->status),
            'visibility_public' => (bool) $service->visibility_public,
            'booking_access' => $service->booking_access,
            'booking_access_label' => $this->bookingAccessLabel($service->booking_access),
            'is_default' => (bool) $service->is_default,
            'sort_order' => (int) $service->sort_order,
        ];
        if ($detail) {
            $out['description'] = $service->description;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePackage(PackageOffering $offering): array
    {
        $hours = round((int) $offering->purchased_minutes / 60, 1);
        $hoursLabel = fmod($hours, 1.0) === 0.0
            ? ((int) $hours) . ' hours'
            : $hours . ' hours';

        return [
            'id' => (int) $offering->id,
            'label' => $offering->label,
            'purchased_minutes' => (int) $offering->purchased_minutes,
            'hours_label' => $hoursLabel,
            'price_pence' => (int) $offering->price_pence,
            'price_label' => Money::formatPence((int) $offering->price_pence),
            'active' => (bool) $offering->active,
            'portal_visible' => (bool) $offering->portal_visible,
            'availability_label' => !$offering->active
                ? 'Inactive'
                : ($offering->portal_visible ? 'Pupils can buy' : 'Hidden from portal'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRule(OrganisationPricingRule $rule): array
    {
        return [
            'id' => (int) $rule->id,
            'label' => $rule->label,
            'active' => (bool) $rule->active,
            'days_of_week' => $rule->days_of_week === ''
                ? []
                : array_map('intval', explode(',', $rule->days_of_week)),
            'days_label' => $this->daysLabel($rule->days_of_week),
            'time_after' => $rule->time_after,
            'time_before' => $rule->time_before,
            'adjustment_kind' => $rule->adjustment_kind,
            'adjustment_value' => (int) $rule->adjustment_value,
            'adjustment_label' => $this->adjustmentLabel($rule),
            'service_id' => $rule->service_id !== null ? (int) $rule->service_id : null,
            'summary' => $this->ruleSummary($rule),
            'sort_order' => (int) $rule->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePupilRate(LearnerServiceRate $rate): array
    {
        $learner = Learner::findOne((int) $rate->learner_id);
        $service = $rate->service_id !== null
            ? OrganisationService::findOne((int) $rate->service_id)
            : null;

        return [
            'id' => (int) $rate->id,
            'learner_id' => (int) $rate->learner_id,
            'learner_name' => $learner?->fullName ?? 'Pupil',
            'service_id' => $rate->service_id !== null ? (int) $rate->service_id : null,
            'service_name' => $service?->name ?? 'All lessons',
            'price_pence' => (int) $rate->price_pence,
            'price_label' => Money::formatPence((int) $rate->price_pence),
            'effective_from' => (string) $rate->effective_from,
            'effective_to' => $rate->effective_to,
            'note' => $rate->note,
        ];
    }

    private function ruleSummary(OrganisationPricingRule $rule): string
    {
        $when = trim($this->daysLabel($rule->days_of_week));
        if ($rule->time_after || $rule->time_before) {
            $time = trim(($rule->time_after ?: '00:00') . '–' . ($rule->time_before ?: '24:00'));
            $when = $when === 'Any day' ? $time : $when . ' · ' . $time;
        }
        $adj = $this->adjustmentLabel($rule);

        return trim($when . '  ' . $adj);
    }

    private function adjustmentLabel(OrganisationPricingRule $rule): string
    {
        $v = (int) $rule->adjustment_value;
        return match ($rule->adjustment_kind) {
            OrganisationPricingRule::ADJUST_ADD => ($v >= 0 ? '+' : '') . Money::formatPence($v),
            OrganisationPricingRule::ADJUST_PERCENT => ($v >= 0 ? '+' : '') . $v . '%',
            OrganisationPricingRule::ADJUST_SET => Money::formatPence($v),
            default => Money::formatPence($v),
        };
    }

    private function daysLabel(string $raw): string
    {
        if (trim($raw) === '') {
            return 'Any day';
        }
        $map = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $days = array_map('intval', explode(',', $raw));
        if ($days === [6]) {
            return 'Saturday';
        }
        if ($days === [7]) {
            return 'Sunday';
        }
        if ($days === [6, 7]) {
            return 'Weekend';
        }
        $labels = [];
        foreach ($days as $d) {
            if (isset($map[$d])) {
                $labels[] = $map[$d];
            }
        }

        return $labels === [] ? 'Any day' : implode(', ', $labels);
    }

    private function durationLabel(int $mins): string
    {
        if ($mins % 60 === 0) {
            $h = intdiv($mins, 60);

            return $h === 1 ? '1 hour' : $h . ' hours';
        }
        if ($mins > 60 && $mins % 30 === 0) {
            return (intdiv($mins, 60)) . 'h ' . ($mins % 60) . 'm';
        }

        return $mins . ' min';
    }

    private function kindLabel(string $kind): string
    {
        return match ($kind) {
            OrganisationService::KIND_MOCK_TEST => 'Mock test',
            OrganisationService::KIND_REFRESHER => 'Refresher',
            OrganisationService::KIND_MOTORWAY => 'Motorway',
            OrganisationService::KIND_TEST_DAY => 'Test day',
            default => 'Lesson',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            OrganisationService::STATUS_DRAFT => 'Draft',
            OrganisationService::STATUS_INACTIVE => 'Inactive',
            default => 'Active',
        };
    }

    private function bookingAccessLabel(string $access): string
    {
        return match ($access) {
            OrganisationService::BOOKING_REQUEST => 'Request only',
            OrganisationService::BOOKING_INSTANT => 'Available to book',
            default => 'You book it',
        };
    }

    /** @return list<array{value: string, label: string}> */
    private function kindOptions(): array
    {
        return [
            ['value' => OrganisationService::KIND_LESSON, 'label' => 'Lesson'],
            ['value' => OrganisationService::KIND_MOCK_TEST, 'label' => 'Mock test'],
            ['value' => OrganisationService::KIND_REFRESHER, 'label' => 'Refresher'],
            ['value' => OrganisationService::KIND_MOTORWAY, 'label' => 'Motorway'],
            ['value' => OrganisationService::KIND_TEST_DAY, 'label' => 'Test day'],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function bookingAccessOptions(): array
    {
        return [
            ['value' => OrganisationService::BOOKING_INSTRUCTOR, 'label' => 'You book it'],
            ['value' => OrganisationService::BOOKING_REQUEST, 'label' => 'Pupils can request'],
            ['value' => OrganisationService::BOOKING_INSTANT, 'label' => 'Pupils can book instantly'],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return [
            ['value' => OrganisationService::STATUS_ACTIVE, 'label' => 'Active'],
            ['value' => OrganisationService::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => OrganisationService::STATUS_INACTIVE, 'label' => 'Inactive'],
        ];
    }

    /** @return list<array{value: int, label: string, short: string}> */
    private function weekdayOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'Monday', 'short' => 'Mon'],
            ['value' => 2, 'label' => 'Tuesday', 'short' => 'Tue'],
            ['value' => 3, 'label' => 'Wednesday', 'short' => 'Wed'],
            ['value' => 4, 'label' => 'Thursday', 'short' => 'Thu'],
            ['value' => 5, 'label' => 'Friday', 'short' => 'Fri'],
            ['value' => 6, 'label' => 'Saturday', 'short' => 'Sat'],
            ['value' => 7, 'label' => 'Sunday', 'short' => 'Sun'],
        ];
    }

    private function firstError(\yii\db\ActiveRecord $model): ?string
    {
        $errors = $model->getFirstErrors();

        return $errors === [] ? null : reset($errors);
    }
}
