<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Guardian / emergency contact on pupil records.
 */
class m260918_100000_learner_emergency_contact extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%learners}}', 'emergency_contact_name', $this->string(120)->null());
        $this->addColumn('{{%learners}}', 'emergency_contact_phone', $this->string(32)->null());
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%learners}}', 'emergency_contact_phone');
        $this->dropColumn('{{%learners}}', 'emergency_contact_name');
    }
}
