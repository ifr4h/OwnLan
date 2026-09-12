<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Pupil DOB/gender, plus instructor preference for who they teach.
 */
class m260919_100000_learner_dob_gender_and_teach_preference extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%learners}}', 'date_of_birth', $this->date()->null());
        $this->addColumn('{{%learners}}', 'gender', $this->string(32)->null());

        $this->addColumn(
            '{{%organisations}}',
            'profile_teaches_gender',
            $this->string(16)->notNull()->defaultValue('any'),
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%organisations}}', 'profile_teaches_gender');
        $this->dropColumn('{{%learners}}', 'gender');
        $this->dropColumn('{{%learners}}', 'date_of_birth');
    }
}
