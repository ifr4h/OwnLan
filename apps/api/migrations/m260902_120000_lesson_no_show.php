<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * No-show lesson outcome — distinct from completed and cancelled.
 */
class m260902_120000_lesson_no_show extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%lessons}}', 'no_show_at', $this->dateTime()->null()->after('completed_at'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%lessons}}', 'no_show_at');
    }
}
