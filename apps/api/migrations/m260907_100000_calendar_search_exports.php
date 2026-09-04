<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Calendar feed tokens, privacy preferences.
 */
class m260907_100000_calendar_search_exports extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%organisations}}', 'calendar_feed_token', $this->string(64)->null()->unique());
        $this->addColumn('{{%organisations}}', 'calendar_feed_created_at', $this->dateTime()->null());
        $this->addColumn(
            '{{%organisations}}',
            'calendar_privacy_mode',
            $this->string(16)->notNull()->defaultValue('private'),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%organisations}}', 'calendar_privacy_mode');
        $this->dropColumn('{{%organisations}}', 'calendar_feed_created_at');
        $this->dropColumn('{{%organisations}}', 'calendar_feed_token');
    }
}
