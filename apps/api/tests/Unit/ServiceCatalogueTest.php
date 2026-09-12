<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\ServicePriceResolver;
use app\components\TenantContext;
use app\models\Organisation;
use app\models\OrganisationPricingRule;
use app\models\OrganisationService;
use app\services\AuthService;
use app\services\ServiceCatalogueService;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;
use Yii;

class ServiceCatalogueTest extends Unit
{
    private Organisation $org;
    private AuthService $auth;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, '
            . 'service_price_changes, learner_service_rates, organisation_pricing_rules, organisation_services, '
            . 'lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users '
            . 'RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();

        $this->auth = new AuthService();
        $this->auth->register([
            'name' => 'Services Tester',
            'email' => 'services-' . uniqid('', true) . '@example.com',
            'password' => 'password123',
        ]);
        $orgId = TenantContext::requireOrganisationId();
        $org = Organisation::findOne($orgId);
        $this->assertNotNull($org);
        $org->default_hourly_rate_pence = 4000;
        $org->save(false, ['default_hourly_rate_pence']);
        $this->org = $org;
    }

    public function testHomeSeedsStandardLesson(): void
    {
        $home = (new ServiceCatalogueService())->home();
        $this->assertNotEmpty($home['services']);
        $this->assertSame('Standard lesson', $home['services'][0]['name']);
        $this->assertSame(4000, $home['services'][0]['price_pence']);
    }

    public function testCreateServiceAndEveningRule(): void
    {
        $catalogue = new ServiceCatalogueService();
        $created = $catalogue->createService([
            'name' => 'Extended lesson',
            'kind' => 'lesson',
            'duration_minutes' => 120,
            'price' => '78',
            'booking_access' => 'request',
            'visibility_public' => true,
            'status' => 'active',
        ]);
        $this->assertSame(7800, $created['service']['price_pence']);
        $this->assertSame('Request only', $created['service']['booking_access_label']);

        $catalogue->createPricingRule([
            'label' => 'Evenings after 18:00',
            'days' => [],
            'time_after' => '18:00',
            'adjustment_kind' => OrganisationPricingRule::ADJUST_ADD,
            'price' => '8',
        ]);

        $service = OrganisationService::findOne((int) $created['service']['id']);
        $this->assertNotNull($service);

        $evening = new DateTimeImmutable('2026-09-07 19:00:00', new DateTimeZone('Europe/London'));
        $daytime = new DateTimeImmutable('2026-09-07 10:00:00', new DateTimeZone('Europe/London'));

        $eveningPrice = ServicePriceResolver::resolvePence(
            $this->org,
            null,
            $service,
            null,
            $evening,
            120,
        );
        $dayPrice = ServicePriceResolver::resolvePence(
            $this->org,
            null,
            $service,
            null,
            $daytime,
            120,
        );

        $this->assertSame(8600, $eveningPrice);
        $this->assertSame(7800, $dayPrice);
    }
}
