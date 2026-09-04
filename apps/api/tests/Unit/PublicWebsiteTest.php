<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\PublicContentSanitizer;
use app\components\PublicSlug;
use app\components\PublicWebsiteFields;
use app\components\TenantContext;
use app\models\Enquiry;
use app\models\Instructor;
use app\models\Organisation;
use app\services\AuthService;
use app\services\EnquiryService;
use app\services\PublicAnalyticsService;
use app\services\PublicProfileService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class PublicWebsiteTest extends Unit
{
    protected UnitTester $tester;

    private Organisation $org;
    private Instructor $instructor;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE profile_analytics_events, enquiries, profile_slug_redirects, payment_allocations, package_credit_usages, lesson_charges, '
            . 'payments, learner_packages, lessons, lesson_series, learner_availability, learners, learner_intakes, '
            . 'memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();

        (new AuthService())->register([
            'name' => 'Amina Yusuf',
            'email' => 'amina-website@example.com',
            'password' => 'password123',
        ]);
        $this->org = Organisation::find()->one();
        $this->instructor = Instructor::find()->one();
    }

    public function testDraftAndUnpublishedAreInaccessible(): void
    {
        $profiles = new PublicProfileService();
        $this->seedPublishedBase();
        $this->org->profile_status = PublicProfileService::STATUS_DRAFT;
        $this->org->save(false);

        $this->expectException(\yii\web\NotFoundHttpException::class);
        $profiles->publicBySlug('amina-yusuf');
    }

    public function testPublishedWebsiteIncludesServicesSeoAndCta(): void
    {
        $this->seedFullWebsite();
        $public = (new PublicProfileService())->publicBySlug('amina-yusuf');

        $this->assertSame('Ask about lessons', $public['cta_label']);
        $this->assertNotEmpty($public['services']);
        $names = array_column($public['services'], 'name');
        $this->assertContains('Mock test', $names);
        $this->assertStringContainsString('Automatic driving lessons', $public['headline']);
        $this->assertStringContainsString('Amina Yusuf', $public['seo']['title']);
        $this->assertSame('index,follow', $public['seo']['robots']);
        $this->assertArrayNotHasKey('aggregateRating', $public['structured_data']);
    }

    public function testBioAndFaqAreSanitised(): void
    {
        $this->seedPublishedBase();
        $this->org->profile_intro = '<script>alert(1)</script>Calm lessons';
        $this->org->profile_faqs = json_encode([
            ['question' => 'Safe?', 'answer' => '<b>Yes</b>'],
        ], JSON_THROW_ON_ERROR);
        $this->org->save(false);

        $public = (new PublicProfileService())->publicBySlug('amina-yusuf');
        $this->assertSame('Calm lessons', $public['intro']);
        $this->assertSame('Yes', $public['faqs'][0]['answer']);
    }

    public function testServiceInterestOnEnquiry(): void
    {
        $this->seedPublishedBase();
        (new EnquiryService())->submitPublic('amina-yusuf', [
            'first_name' => 'Sarah',
            'last_name' => 'Ahmed',
            'mobile' => '07700900421',
            'postcode' => 'MK3 5QF',
            'transmission' => 'automatic',
            'service_interest' => 'Mock test',
        ]);

        $enquiry = Enquiry::find()->one();
        $this->assertNotNull($enquiry);
        $this->assertSame('Mock test', $enquiry->service_interest);
    }

    public function testWaitingListCta(): void
    {
        $this->seedPublishedBase();
        $this->org->profile_acquisition_mode = PublicProfileService::ACQUISITION_WAITING_LIST;
        $this->org->save(false);

        $public = (new PublicProfileService())->publicBySlug('amina-yusuf');
        $this->assertSame('Join waiting list', $public['cta_label']);
        $this->assertFalse($public['allows_enquiry']);
        $this->assertTrue($public['allows_waiting_list']);
    }

    public function testAnalyticsTracksWithoutPii(): void
    {
        $this->seedPublishedBase();
        $analytics = new PublicAnalyticsService();
        $analytics->track((int) $this->org->id, PublicAnalyticsService::EVENT_VIEW, 'instagram');
        $analytics->track((int) $this->org->id, PublicAnalyticsService::EVENT_ENQUIRY_SUBMITTED, 'instagram');

        $row = Yii::$app->db->createCommand(
            'SELECT * FROM profile_analytics_events WHERE organisation_id = :id LIMIT 1',
            [':id' => (int) $this->org->id],
        )->queryOne();

        $this->assertIsArray($row);
        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('postcode', $row);
    }

    public function testInvalidSocialUrlRejectedOnUpdate(): void
    {
        $profiles = new PublicProfileService();
        $this->seedPublishedBase();
        $result = $profiles->update($this->org, $this->instructor, [
            'social_links' => ['instagram' => 'javascript:alert(1)'],
        ]);

        $this->assertSame([], $result['profile']['social_links']);
    }

    public function testAccentColourValidation(): void
    {
        $this->assertNull(PublicContentSanitizer::accentColour('not-a-colour'));
        $this->assertSame('#2D6A4F', PublicContentSanitizer::accentColour('#2d6a4f'));
    }

    private function seedPublishedBase(): void
    {
        $this->org->profile_slug = 'amina-yusuf';
        $this->org->profile_status = PublicProfileService::STATUS_PUBLISHED;
        $this->org->profile_transmission = 'automatic';
        $this->org->profile_teaching_areas = json_encode(['Milton Keynes'], JSON_THROW_ON_ERROR);
        $this->org->profile_public_pricing = json_encode([
            ['duration_minutes' => 60, 'price_pence' => 4200],
        ], JSON_THROW_ON_ERROR);
        $this->org->profile_acquisition_mode = PublicProfileService::ACQUISITION_OPEN;
        $this->org->save(false);
    }

    private function seedFullWebsite(): void
    {
        $this->seedPublishedBase();
        $this->org->profile_services = json_encode([
            ['id' => 'lesson-60', 'name' => '60 minutes', 'duration_minutes' => 60, 'price_pence' => 4200, 'type' => 'lesson', 'public' => true],
            ['id' => 'lesson-90', 'name' => '90 minutes', 'duration_minutes' => 90, 'price_pence' => 6300, 'type' => 'lesson', 'public' => true],
            ['id' => 'lesson-120', 'name' => '2 hours', 'duration_minutes' => 120, 'price_pence' => 8400, 'type' => 'lesson', 'public' => true],
            ['id' => 'mock-test', 'name' => 'Mock test', 'duration_minutes' => 90, 'price_pence' => 6300, 'type' => 'mock_test', 'public' => true],
        ], JSON_THROW_ON_ERROR);
        $this->org->profile_faqs = json_encode([
            ['question' => 'Do you teach complete beginners?', 'answer' => 'Yes.'],
        ], JSON_THROW_ON_ERROR);
        $this->org->save(false);
    }
}
