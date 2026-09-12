<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Learner;
use app\models\Lesson;
use app\services\AuthService;
use app\services\DiaryBlockService;
use app\services\LearnerLocationService;
use app\services\LearnerService;
use app\services\LessonMessageService;
use app\services\LessonService;
use app\services\PortalAuthService;
use app\services\PortalHomeService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\NotFoundHttpException;

class LocationsNotesBlocksServiceTest extends Unit
{
    protected UnitTester $tester;

    private LessonService $lessons;
    private LearnerService $learners;
    private LearnerLocationService $locations;
    private LessonMessageService $messages;
    private DiaryBlockService $blocks;
    private PortalAuthService $portalAuth;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE lesson_messages, diary_blocks, learner_locations, lessons, lesson_series, '
            . 'learner_portal_accounts, learners, memberships, instructors, organisations, users '
            . 'RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        if (Yii::$app->has('portalUser')) {
            Yii::$app->portalUser->logout();
        }
        TenantContext::clear();

        $this->lessons = new LessonService();
        $this->learners = new LearnerService();
        $this->locations = new LearnerLocationService();
        $this->messages = new LessonMessageService();
        $this->blocks = new DiaryBlockService();
        $this->portalAuth = new PortalAuthService();
    }

    public function testLocationCrudTenantIsolationAndDefaultMigration(): void
    {
        (new AuthService())->register([
            'name' => 'Loc Instructor',
            'email' => 'loc-a@example.com',
            'password' => 'password123',
        ]);

        $pupil = $this->learners->create([
            'first_name' => 'Sam',
            'last_name' => 'Lee',
            'mobile' => '07700906001',
            'default_pickup_address' => '14 Station Road',
        ]);

        // Migration seeds default_pickup into a location; create also works without seed for fresh pupils.
        $seeded = $this->locations->listForLearner((int) $pupil['id']);
        if ($seeded === []) {
            $home = $this->locations->create((int) $pupil['id'], [
                'label' => 'Home',
                'icon' => 'home',
                'address' => '14 Station Road',
                'is_default' => true,
            ]);
        } else {
            $home = $seeded[0];
            $this->assertTrue((bool) $home['is_default']);
            $this->assertSame('14 Station Road', $home['address']);
        }

        $work = $this->locations->create((int) $pupil['id'], [
            'label' => 'Work',
            'icon' => 'work',
            'address' => '2 Business Park',
        ]);
        $this->assertSame('work', $work['icon']);
        $this->assertFalse((bool) $work['is_default']);

        $this->locations->setDefault((int) $pupil['id'], (int) $work['id']);
        $learner = Learner::findOne(['id' => (int) $pupil['id']]);
        $this->assertSame('2 Business Park', $learner?->default_pickup_address);

        (new AuthService())->logout();
        (new AuthService())->register([
            'name' => 'Other Instructor',
            'email' => 'loc-b@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(NotFoundHttpException::class);
        $this->locations->listForLearner((int) $pupil['id']);
    }

    public function testBookWithLocationIdVsFreeform(): void
    {
        (new AuthService())->register([
            'name' => 'Book Instructor',
            'email' => 'book-loc@example.com',
            'password' => 'password123',
        ]);
        $pupil = $this->learners->create([
            'first_name' => 'Pat',
            'last_name' => 'Ng',
            'mobile' => '07700906002',
        ]);
        $place = $this->locations->create((int) $pupil['id'], [
            'label' => 'Gym',
            'icon' => 'gym',
            'address' => '9 Fitness Way',
            'is_default' => true,
        ]);

        $withPlace = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-11-02 10:00',
            'pickup_location_id' => $place['id'],
        ]);
        $this->assertSame((int) $place['id'], $withPlace['pickup_location_id']);
        $this->assertSame('9 Fitness Way', $withPlace['pickup_address']);
        $this->assertSame(Lesson::PICKUP_BY_INSTRUCTOR, $withPlace['pickup_set_by']);
        $this->assertSame('Gym', $withPlace['pickup_location']['label'] ?? null);

        $freeform = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-11-03 10:00',
            'pickup_address' => 'Corner of High St',
            'pickup_location_id' => null,
            'focus_tags' => ['Roundabouts', 'Mirrors'],
        ]);
        $this->assertNull($freeform['pickup_location_id']);
        $this->assertSame('Corner of High St', $freeform['pickup_address']);
        $this->assertSame(['Roundabouts', 'Mirrors'], $freeform['focus_tags']);

        $updated = $this->lessons->update((int) $freeform['id'], [
            'focus_tags' => ['Parking'],
        ]);
        $this->assertSame(['Parking'], $updated['focus_tags']);
    }

    public function testLearnerPickupChangeSetsAlertAndAcknowledgeClears(): void
    {
        (new AuthService())->register([
            'name' => 'Alert Instructor',
            'email' => 'alert@example.com',
            'password' => 'password123',
        ]);
        $pupil = $this->learners->create([
            'first_name' => 'Rae',
            'last_name' => 'Cox',
            'mobile' => '07700906003',
            'email' => 'rae@example.com',
        ]);
        $home = $this->locations->create((int) $pupil['id'], [
            'label' => 'Home',
            'icon' => 'home',
            'address' => '1 Home Lane',
            'is_default' => true,
        ]);
        $work = $this->locations->create((int) $pupil['id'], [
            'label' => 'Work',
            'icon' => 'work',
            'address' => '88 Office Ave',
        ]);

        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-03-10 14:00',
            'pickup_location_id' => $home['id'],
        ]);
        $this->assertFalse((bool) $lesson['pickup_changed']);

        $this->loginPortal((int) $pupil['id'], 'rae@example.com');

        $changed = $this->lessons->updatePickupAsLearner(
            (int) $lesson['id'],
            (int) $pupil['id'],
            ['pickup_location_id' => $work['id']],
        );
        $this->assertTrue((bool) $changed['pickup_changed']);
        $this->assertSame('88 Office Ave', $changed['pickup_address']);

        Yii::$app->portalUser->logout();
        // Instructor session still active via TenantContext from register; re-login instructor.
        (new AuthService())->login([
            'email' => 'alert@example.com',
            'password' => 'password123',
        ]);

        $view = $this->lessons->get((int) $lesson['id']);
        $this->assertTrue((bool) $view['pickup_changed']);

        $acked = $this->lessons->acknowledgePickupChange((int) $lesson['id']);
        $this->assertFalse((bool) $acked['pickup_changed']);
    }

    public function testMessagesAclAndPrivateNotesStayOutOfPortalHome(): void
    {
        (new AuthService())->register([
            'name' => 'Chat Instructor',
            'email' => 'chat@example.com',
            'password' => 'password123',
        ]);
        $pupil = $this->learners->create([
            'first_name' => 'Jo',
            'last_name' => 'Park',
            'mobile' => '07700906004',
            'email' => 'jo@example.com',
        ]);
        $lesson = $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2030-04-01 09:00',
        ]);
        $this->lessons->update((int) $lesson['id'], [
            'instructor_notes' => 'SECRET: clutch concern',
        ]);

        $instructorMsg = $this->messages->postAsInstructor((int) $lesson['id'], 'Bring your provisional');
        $this->assertSame('instructor', $instructorMsg['author_role']);

        $this->loginPortal((int) $pupil['id'], 'jo@example.com');
        $learnerMsg = $this->messages->postAsLearner((int) $lesson['id'], 'Will do');
        $this->assertSame('learner', $learnerMsg['author_role']);

        $thread = $this->messages->listForLesson((int) $lesson['id']);
        $this->assertCount(2, $thread);

        $home = (new PortalHomeService())->home();
        $encoded = json_encode($home);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('SECRET: clutch concern', $encoded);
        $this->assertStringNotContainsString('instructor_notes', $encoded);

        Yii::$app->portalUser->logout();
        (new AuthService())->logout();
        (new AuthService())->register([
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'password123',
        ]);
        $this->expectException(NotFoundHttpException::class);
        $this->messages->listForLesson((int) $lesson['id']);
    }

    public function testDiaryBlocksAppearInDiaryAndStayInstructorOnly(): void
    {
        (new AuthService())->register([
            'name' => 'Block Instructor',
            'email' => 'blocks@example.com',
            'password' => 'password123',
        ]);
        $pupil = $this->learners->create([
            'first_name' => 'Kim',
            'last_name' => 'Wu',
            'mobile' => '07700906005',
            'email' => 'kim@example.com',
        ]);
        $this->lessons->create([
            'learner_id' => $pupil['id'],
            'starts_at_local' => '2026-09-15 10:00',
        ]);
        $block = $this->blocks->create([
            'starts_at_local' => '2026-09-15 12:00',
            'duration_minutes' => 60,
            'label' => 'Admin',
        ]);
        $this->assertTrue((bool) $block['is_private']);
        $this->assertSame('private', $block['kind']);
        $this->assertSame('Admin', $block['label']);

        $diary = $this->lessons->diary('day', '2026-09-15');
        $this->assertNotEmpty($diary['blocks']);
        $this->assertSame('Admin', $diary['blocks'][0]['label']);
        $this->assertSame('Admin', $diary['days'][0]['blocks'][0]['label'] ?? null);

        $this->loginPortal((int) $pupil['id'], 'kim@example.com');
        $home = (new PortalHomeService())->home();
        $encoded = json_encode($home);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('"kind":"private"', $encoded);
        $this->assertStringNotContainsString('diary_blocks', $encoded);
        $this->assertStringNotContainsString('"is_private"', $encoded);
    }

    /**
     * Activate portal for the pupil (invite + activate) and log in.
     */
    private function loginPortal(int $learnerId, string $email): void
    {
        $invite = $this->portalAuth->inviteForLearner($learnerId);
        parse_str(parse_url($invite['invite_path'], PHP_URL_QUERY) ?: '', $query);
        $token = (string) ($query['token'] ?? '');
        $this->portalAuth->activate([
            'token' => $token,
            'password' => 'learnerpass1',
        ]);
        // activate may already log in; ensure email login works if not.
        if (Yii::$app->portalUser->isGuest) {
            $this->portalAuth->login([
                'email' => $email,
                'password' => 'learnerpass1',
            ]);
        }
    }
}
