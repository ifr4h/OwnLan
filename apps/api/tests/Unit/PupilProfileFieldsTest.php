<?php

declare(strict_types=1);

namespace tests\Unit;

use app\components\LicenceStatus;
use app\models\Learner;
use app\services\TestJourneyService;
use Codeception\Test\Unit;
use DateTimeImmutable;
use DateTimeZone;

class PupilProfileFieldsTest extends Unit
{
    public function testFullNameIncludesMiddleName(): void
    {
        $learner = new Learner();
        $learner->first_name = 'Sarah';
        $learner->middle_name = 'Jane';
        $learner->last_name = 'Smith';
        $this->assertSame('Sarah Jane Smith', $learner->fullName);

        $learner->middle_name = null;
        $this->assertSame('Sarah Smith', $learner->fullName);
    }

    public function testLicenceUrgencyBands(): void
    {
        $now = new DateTimeImmutable('2026-09-06', new DateTimeZone('UTC'));
        $soon = LicenceStatus::statusPayload('ABC123', '2026-09-20', $now);
        $this->assertSame('soon', $soon['urgency']);

        $expired = LicenceStatus::statusPayload('ABC123', '2026-08-01', $now);
        $this->assertSame('expired', $expired['urgency']);

        $ok = LicenceStatus::statusPayload('ABC123', '2027-09-06', $now);
        $this->assertSame('ok', $ok['urgency']);
    }

    public function testThreeClearWorkingDaysBefore(): void
    {
        $service = new TestJourneyService();
        // Friday 12 Sep 2026 → three clear working days: Thu 11, Wed 10, Tue 9 → cancel by Tue 9
        $test = new DateTimeImmutable('2026-09-12', new DateTimeZone('Europe/London'));
        $cancel = $service->threeClearWorkingDaysBefore($test);
        $this->assertNotNull($cancel);
        $this->assertSame('2026-09-09', $cancel->format('Y-m-d'));
    }

    public function testDefaultReminderOffsets(): void
    {
        $learner = new Learner();
        $this->assertSame([7, 1, 0], $learner->reminderOffsets());
        $learner->practical_test_reminder_offsets = '[7,3,1,0]';
        $this->assertSame([7, 3, 1, 0], $learner->reminderOffsets());
    }
}
