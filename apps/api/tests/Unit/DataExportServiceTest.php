<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Organisation;
use app\services\AuthService;
use app\services\DataExportService;
use app\services\LearnerService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;

class DataExportServiceTest extends Unit
{
    protected UnitTester $tester;

    private DataExportService $exports;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->exports = new DataExportService();
        $this->learners = new LearnerService();

        (new AuthService())->register([
            'name' => 'Export Instructor',
            'email' => 'export@example.com',
            'password' => 'password123',
        ]);
    }

    public function testPupilExportExcludesPrivateNotesAndEscapesFormulas(): void
    {
        $this->learners->create([
            'first_name' => '=CMD',
            'last_name' => 'Test',
            'mobile' => '07700908001',
            'private_notes' => 'Secret note',
        ]);

        $csv = $this->exports->pupilsCsv();
        $this->assertStringContainsString("'=CMD", $csv);
        $this->assertStringNotContainsString('Secret note', $csv);
        $this->assertStringContainsString('first_name', $csv);
    }

    public function testPupilExportTenantIsolation(): void
    {
        $this->learners->create([
            'first_name' => 'Local',
            'last_name' => 'Only',
            'mobile' => '07700908002',
        ]);

        TenantContext::clear();
        (new AuthService())->register([
            'name' => 'Other',
            'email' => 'other-export@example.com',
            'password' => 'password123',
        ]);

        $csv = $this->exports->pupilsCsv();
        $this->assertStringNotContainsString('Local', $csv);
        $this->assertStringNotContainsString('Only', $csv);
    }
}
