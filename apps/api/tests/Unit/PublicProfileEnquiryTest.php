<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\PublicSlug;
use app\components\TenantContext;
use app\models\Enquiry;
use app\models\Instructor;
use app\models\Organisation;
use app\services\AuthService;
use app\services\EnquiryService;
use app\services\PublicProfileService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class PublicProfileEnquiryTest extends Unit
{
    protected UnitTester $tester;

    private Organisation $org;
    private Instructor $instructor;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE enquiries, profile_slug_redirects, payment_allocations, package_credit_usages, lesson_charges, '
            . 'payments, learner_packages, lessons, lesson_series, learner_availability, learners, learner_intakes, '
            . 'memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();

        (new AuthService())->register([
            'name' => 'Amina Yusuf',
            'email' => 'amina-profile@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->instructor = Instructor::find()->one();
    }

    public function testSlugGenerationAndSafety(): void
    {
        $slug = PublicSlug::fromName('Amina Yusuf');
        $this->assertSame('amina-yusuf', $slug);
        $this->assertTrue(PublicSlug::isReserved('login'));
        $this->assertFalse(PublicSlug::isReserved('amina-yusuf'));
    }

    public function testDraftProfileNotPublic(): void
    {
        $profiles = new PublicProfileService();
        $this->org->profile_slug = 'amina-yusuf';
        $this->org->profile_status = PublicProfileService::STATUS_DRAFT;
        $this->org->save(false);

        $this->expectException(\yii\web\NotFoundHttpException::class);
        $profiles->publicBySlug('amina-yusuf');
    }

    public function testPublishedProfileAllowList(): void
    {
        $profiles = new PublicProfileService();
        $this->org->profile_slug = 'amina-yusuf';
        $this->org->profile_status = PublicProfileService::STATUS_PUBLISHED;
        $this->org->profile_transmission = 'automatic';
        $this->org->profile_teaching_areas = json_encode(['Bletchley', 'MK3'], JSON_THROW_ON_ERROR);
        $this->org->profile_public_pricing = json_encode([
            ['duration_minutes' => 60, 'price_pence' => 4200],
        ], JSON_THROW_ON_ERROR);
        $this->org->profile_acquisition_mode = PublicProfileService::ACQUISITION_OPEN;
        $this->org->save(false);

        $public = $profiles->publicBySlug('amina-yusuf');
        $this->assertSame('Amina Yusuf', $public['display_name']);
        $this->assertArrayNotHasKey('contact_phone', $public);
        $this->assertArrayNotHasKey('organisation_id', $public);
        $this->assertTrue($public['allows_enquiry']);
    }

    public function testEnquirySubmissionAndTenantIsolation(): void
    {
        $this->org->profile_slug = 'amina-yusuf';
        $this->org->profile_status = PublicProfileService::STATUS_PUBLISHED;
        $this->org->profile_transmission = 'automatic';
        $this->org->profile_teaching_areas = json_encode(['MK3'], JSON_THROW_ON_ERROR);
        $this->org->profile_public_pricing = json_encode([
            ['duration_minutes' => 60, 'price_pence' => 4200],
        ], JSON_THROW_ON_ERROR);
        $this->org->profile_acquisition_mode = PublicProfileService::ACQUISITION_OPEN;
        $this->org->save(false);

        $service = new EnquiryService();
        $result = $service->submitPublic('amina-yusuf', [
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700900421',
            'postcode' => 'MK3 5QF',
            'transmission' => 'automatic',
            'experience_band' => 'few',
            'desired_start' => 'asap',
            'availability' => [
                'days' => [
                    ['weekday' => 4, 'slots' => ['after_4']],
                ],
            ],
        ]);
        $this->assertSame('submitted', $result['status']);

        /** @var Enquiry|null $enquiry */
        $enquiry = Enquiry::find()->one();
        $this->assertNotNull($enquiry);
        $this->assertSame((int) $this->org->id, (int) $enquiry->organisation_id);

        TenantContext::clear();
        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'other@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(\yii\web\NotFoundHttpException::class);
        (new EnquiryService())->view((int) $enquiry->id);
    }

    public function testEnquiryConvertCreatesLearner(): void
    {
        $this->org->profile_slug = 'amina-yusuf';
        $this->org->profile_status = PublicProfileService::STATUS_PUBLISHED;
        $this->org->profile_transmission = 'automatic';
        $this->org->profile_teaching_areas = json_encode(['MK3'], JSON_THROW_ON_ERROR);
        $this->org->profile_public_pricing = json_encode([
            ['duration_minutes' => 60, 'price_pence' => 4200],
        ], JSON_THROW_ON_ERROR);
        $this->org->profile_acquisition_mode = PublicProfileService::ACQUISITION_OPEN;
        $this->org->save(false);

        $service = new EnquiryService();
        $service->submitPublic('amina-yusuf', [
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700900421',
            'email' => 'sarah@example.com',
            'postcode' => 'MK3 5QF',
            'transmission' => 'automatic',
            'experience_band' => 'few',
            'desired_start' => 'asap',
        ]);

        $enquiry = Enquiry::find()->one();
        $this->assertNotNull($enquiry);
        $converted = $service->convert((int) $enquiry->id, waiting: false);
        $this->assertNotEmpty($converted['learner_id']);
        $enquiry->refresh();
        $this->assertSame(Enquiry::STATUS_CONVERTED, $enquiry->status);
        $this->assertSame('Sarah', $enquiry->first_name);
    }
}
