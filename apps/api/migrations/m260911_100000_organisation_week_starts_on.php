<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Diary week start day (ISO weekday 1=Mon … 7=Sun). Default Monday.
 */
class m260911_100000_organisation_week_starts_on extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%organisations}}',
            'week_starts_on',
            $this->smallInteger()->notNull()->defaultValue(1),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%organisations}}', 'week_starts_on');
    }
}
