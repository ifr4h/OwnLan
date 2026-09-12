<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Structured pupil cancellation: notice hours, late outcome, cancel metadata.
 */
class m260912_100000_learner_cancellation_policy extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%organisations}}',
            'cancellation_notice_hours',
            $this->integer()->notNull()->defaultValue(48),
        );
        $this->addColumn(
            '{{%organisations}}',
            'cancellation_late_policy',
            $this->string(16)->notNull()->defaultValue('decide'),
        );

        $this->addColumn(
            '{{%lessons}}',
            'cancelled_by',
            $this->string(16)->null(),
        );
        $this->addColumn(
            '{{%lessons}}',
            'cancellation_reason',
            $this->string(500)->null(),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%lessons}}', 'cancellation_reason');
        $this->dropColumn('{{%lessons}}', 'cancelled_by');
        $this->dropColumn('{{%organisations}}', 'cancellation_late_policy');
        $this->dropColumn('{{%organisations}}', 'cancellation_notice_hours');
    }
}
