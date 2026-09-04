<?php

declare(strict_types=1);

namespace app\commands;

use app\seeders\DemoSeeder;
use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Demo data for product walkthroughs.
 *
 *   php yii demo/seed
 *   php yii demo/seed --fresh=1
 *   php yii demo/seed --email=you@example.com --name="Your Name" --resetPassword=1
 */
class DemoController extends Controller
{
    /** Wipe existing target account data before seeding. */
    public bool $fresh = false;

    /** Target instructor login (defaults to demo). */
    public string $email = '';

    /** Display name when creating / updating the instructor. */
    public string $name = '';

    /** Organisation / school name. */
    public string $organisation = '';

    /** Password when creating a new account (or with --resetPassword). */
    public string $password = '';

    /** Reset password on an existing account to --password. */
    public bool $resetPassword = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'fresh',
            'email',
            'name',
            'organisation',
            'password',
            'resetPassword',
        ]);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'f' => 'fresh',
            'e' => 'email',
        ]);
    }

    public function actionSeed(): int
    {
        $env = strtolower(trim((string) (getenv('APP_ENV') ?: 'development')));
        if ($env === 'production' && !filter_var(getenv('ALLOW_DEMO_SEED') ?: '', FILTER_VALIDATE_BOOL)) {
            $this->stderr("Demo seeding is disabled in production. Set ALLOW_DEMO_SEED=1 to override.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("OwnLane demo seeder\n");
        $this->stdout(str_repeat('─', 40) . "\n");

        $options = [];
        if (trim($this->email) !== '') {
            $options['email'] = trim($this->email);
        }
        if (trim($this->name) !== '') {
            $options['name'] = trim($this->name);
        }
        if (trim($this->organisation) !== '') {
            $options['organisation'] = trim($this->organisation);
        }
        if (trim($this->password) !== '') {
            $options['password'] = $this->password;
        }
        if ($this->resetPassword) {
            $options['reset_password'] = true;
            if (trim($this->password) === '') {
                $options['password'] = DemoSeeder::DEMO_PASSWORD;
            }
        }

        try {
            $result = (new DemoSeeder($this->fresh, $options))->run();
        } catch (Throwable $e) {
            $this->stderr('Failed: ' . $e->getMessage() . "\n");
            Yii::error($e, __METHOD__);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Instructor login\n");
        $this->stdout('  Email:    ' . $result['email'] . "\n");
        if ($result['password'] !== null) {
            $this->stdout('  Password: ' . $result['password'] . "\n");
        } else {
            $this->stdout('  Password: (unchanged — ' . $result['password_note'] . ")\n");
        }
        $this->stdout('  Org:      ' . $result['organisation'] . "\n\n");

        $this->stdout("Seeded\n");
        foreach ($result['counts'] as $label => $count) {
            $this->stdout(sprintf("  %-22s %d\n", $label, $count));
        }

        if (!empty($result['sample_intake_path'])) {
            $this->stdout("\nOpen intake (learner phone flow)\n");
            $this->stdout('  ' . $result['sample_intake_path'] . "\n");
            $this->stdout("  Full URL: http://127.0.0.1:3000" . $result['sample_intake_path'] . "\n");
        }

        if (!empty($result['portal_email'])) {
            $this->stdout("\nLearner portal (showcase)\n");
            $this->stdout('  Email:    ' . $result['portal_email'] . "\n");
            $this->stdout('  Password: ' . $result['portal_password'] . "\n");
            $this->stdout("  Open:     http://127.0.0.1:3000/portal/login\n");
        }

        if (!empty($result['companions']) && is_array($result['companions'])) {
            $this->stdout("\nCompanions (showcase)\n");
            foreach ($result['companions'] as $c) {
                $this->stdout('  ' . ($c['role'] ?? 'Companion') . "\n");
                $this->stdout('    Email:    ' . ($c['email'] ?? '') . "\n");
                $this->stdout('    Password: ' . ($c['password'] ?? '') . "\n");
            }
            $this->stdout("  Open:     http://127.0.0.1:3000/companion/login\n");
        }

        $this->stdout("\nDone — open http://127.0.0.1:3000 and sign in.\n");

        return ExitCode::OK;
    }
}
