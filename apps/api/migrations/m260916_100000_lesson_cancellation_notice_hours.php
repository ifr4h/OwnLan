<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Record how much notice was given when a lesson was cancelled.
 */
class m260916_100000_lesson_cancellation_notice_hours extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%lessons}}',
            'cancellation_notice_hours',
            $this->integer()->null(),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%lessons}}', 'cancellation_notice_hours');
    }
}
