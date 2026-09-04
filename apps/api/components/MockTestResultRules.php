<?php

declare(strict_types=1);

namespace app\components;

use app\models\MockTest;

/**
 * Deterministic mock test result rules.
 *
 * Based on the UK practical driving test fault allowance:
 * - 15 or fewer driving faults (minors)
 * - 0 serious faults
 * - 0 dangerous faults
 * = Pass standard
 *
 * This is a mock assessment, not an official DVSA test.
 */
class MockTestResultRules
{
    public const MAX_DRIVING_FAULTS_PASS = 15;

    /**
     * @return array{result: string, result_label: string, explanation: string}
     */
    public static function calculate(int $driving, int $serious, int $dangerous): array
    {
        $pass = $driving <= self::MAX_DRIVING_FAULTS_PASS
            && $serious === 0
            && $dangerous === 0;

        if ($pass) {
            return [
                'result' => MockTest::RESULT_PASS,
                'result_label' => 'Pass standard',
                'explanation' => '15 or fewer driving faults with no serious or dangerous faults.',
            ];
        }

        $reasons = [];
        if ($driving > self::MAX_DRIVING_FAULTS_PASS) {
            $reasons[] = $driving . ' driving faults (more than 15)';
        }
        if ($serious > 0) {
            $reasons[] = $serious . ' serious ' . ($serious === 1 ? 'fault' : 'faults');
        }
        if ($dangerous > 0) {
            $reasons[] = $dangerous . ' dangerous ' . ($dangerous === 1 ? 'fault' : 'faults');
        }

        return [
            'result' => MockTest::RESULT_NOT_PASS,
            'result_label' => 'Not at pass standard',
            'explanation' => implode('; ', $reasons) . '.',
        ];
    }
}
