<?php

declare(strict_types=1);

namespace app\tests\Unit;

use app\components\TenantContext;
use app\models\Learner;
use app\services\AuthService;
use app\services\LearnerImportService;
use app\services\LearnerService;
use app\tests\Support\UnitTester;
use Codeception\Test\Unit;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\UploadedFile;

class LearnerImportServiceTest extends Unit
{
    protected UnitTester $tester;

    private LearnerImportService $import;
    private LearnerService $learners;

    protected function _before(): void
    {
        Yii::$app->db->createCommand(
            'TRUNCATE payment_allocations, package_credit_usages, lesson_charges, payments, learner_packages, expenses, learner_portal_accounts, lessons, lesson_series, learner_availability, learners, memberships, instructors, organisations, users RESTART IDENTITY CASCADE',
        )->execute();
        Yii::$app->user->logout();
        TenantContext::clear();
        $this->import = new LearnerImportService();
        $this->learners = new LearnerService();
        UploadedFile::reset();
    }

    public function testTemplateContainsRequiredHeaders(): void
    {
        $csv = $this->import->templateCsv();
        $this->assertStringContainsString('First name', $csv);
        $this->assertStringContainsString('Last name', $csv);
        $this->assertStringContainsString('Mobile', $csv);
        $this->assertStringContainsString('Aisha', $csv);
    }

    public function testPreviewValidatesAndFlagsDuplicates(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'import@example.com',
            'password' => 'password123',
        ]);

        $this->learners->create([
            'first_name' => 'Existing',
            'last_name' => 'Pupil',
            'mobile' => '07700900999',
            'email' => 'existing@example.com',
        ]);

        $csv = implode("\n", [
            'First name,Last name,Mobile,Email,Usual pickup,Private notes',
            'Ready,One,07700 900111,ready@example.com,1 High St,Hello',
            'Bad,,07700111,,,', // missing last name + short mobile
            'Dup,Existing,07700 900999,other@example.com,,', // existing mobile
            'File,Dup,07700900111,filedup@example.com,,', // same mobile as Ready row
            ',,, , ,', // blank skipped
        ]) . "\n";

        $preview = $this->import->previewFromUpload($this->tempCsv($csv));
        $this->assertSame(4, $preview['summary']['total']);
        $this->assertSame(1, $preview['summary']['ready']);
        $this->assertSame(1, $preview['summary']['errors']);
        $this->assertSame(2, $preview['summary']['duplicates']);

        $byStatus = [];
        foreach ($preview['rows'] as $row) {
            $byStatus[$row['status']][] = $row;
        }
        $this->assertSame('Ready One', $byStatus['ready'][0]['display_name']);
        $this->assertNotEmpty($byStatus['error'][0]['errors']);
        $this->assertSame('existing', $byStatus['duplicate'][0]['duplicate']['source']);
        $this->assertSame('file', $byStatus['duplicate'][1]['duplicate']['source']);
    }

    public function testConfirmImportsReadyRowsOnlyAndStaysInOrganisation(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'import-a@example.com',
            'password' => 'password123',
        ]);
        $orgA = TenantContext::requireOrganisationId();

        $preview = $this->import->previewFromUpload($this->tempCsv(
            "First name,Last name,Mobile,Email,Usual pickup,Private notes\n"
            . "Sam,Lee,07700900101,sam@example.com,Pickup A,Note A\n"
            . "Pat,Lee,07700900102,,Pickup B,\n",
        ));

        $rows = array_map(
            static fn (array $row) => [
                'row_number' => $row['row_number'],
                'data' => $row['data'],
            ],
            array_values(array_filter(
                $preview['rows'],
                static fn (array $row) => $row['status'] === 'ready',
            )),
        );

        $result = $this->import->confirm(['rows' => $rows]);
        $this->assertSame(2, $result['imported_count']);
        $this->assertSame(0, $result['skipped_count']);

        $countA = (int) Learner::find()->andWhere(['organisation_id' => $orgA])->count();
        $this->assertSame(2, $countA);

        Yii::$app->user->logout();
        TenantContext::clear();

        (new AuthService())->register([
            'name' => 'Beta Instructor',
            'email' => 'import-b@example.com',
            'password' => 'password123',
        ]);
        $orgB = TenantContext::requireOrganisationId();
        $this->assertNotSame($orgA, $orgB);

        $listB = $this->learners->listActive();
        $this->assertSame([], $listB);

        // Confirming Alpha's rows while logged in as Beta must not create into Alpha
        // and should create for Beta if mobiles don't collide in B.
        $resultB = $this->import->confirm(['rows' => $rows]);
        $this->assertSame(2, $resultB['imported_count']);
        $this->assertSame(2, (int) Learner::find()->andWhere(['organisation_id' => $orgB])->count());
        $this->assertSame(2, (int) Learner::find()->andWhere(['organisation_id' => $orgA])->count());
    }

    public function testConfirmSkipsDuplicatesUnlessForced(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'import-dup@example.com',
            'password' => 'password123',
        ]);
        $this->learners->create([
            'first_name' => 'Existing',
            'last_name' => 'Pupil',
            'mobile' => '07700900333',
        ]);

        $rows = [[
            'row_number' => 2,
            'data' => [
                'first_name' => 'New',
                'last_name' => 'Name',
                'mobile' => '07700 900333',
                'email' => null,
                'default_pickup_address' => null,
                'private_notes' => null,
            ],
        ]];

        $skipped = $this->import->confirm(['rows' => $rows]);
        $this->assertSame(0, $skipped['imported_count']);
        $this->assertSame(1, $skipped['skipped_count']);
        $this->assertSame(1, (int) Learner::find()->count());

        $forced = $this->import->confirm([
            'rows' => $rows,
            'include_duplicates' => true,
        ]);
        $this->assertSame(1, $forced['imported_count']);
        $this->assertSame(2, (int) Learner::find()->count());
    }

    public function testRejectsMissingRequiredColumns(): void
    {
        (new AuthService())->register([
            'name' => 'Alex Instructor',
            'email' => 'import-bad@example.com',
            'password' => 'password123',
        ]);

        $this->expectException(BadRequestHttpException::class);
        $this->import->previewFromUpload($this->tempCsv(
            "Name,Phone\nAisha,07700900111\n",
        ));
    }

    private function tempCsv(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'olcsv');
        $this->assertNotFalse($path);
        file_put_contents($path, $contents);

        return new UploadedFile([
            'name' => 'pupils.csv',
            'tempName' => $path,
            'type' => 'text/csv',
            'size' => strlen($contents),
            'error' => UPLOAD_ERR_OK,
        ]);
    }
}
