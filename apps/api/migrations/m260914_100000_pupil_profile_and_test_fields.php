<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Pupil middle name, licence, waiting-list start date, practical test details,
 * theory booked date, reminder offsets, and portal profile-change events.
 */
class m260914_100000_pupil_profile_and_test_fields extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%learners}}', 'middle_name', $this->string(100)->null());
        $this->addColumn('{{%learners}}', 'licence_number', $this->string(32)->null());
        $this->addColumn('{{%learners}}', 'licence_expiry_date', $this->date()->null());
        $this->addColumn('{{%learners}}', 'available_from', $this->date()->null());
        $this->addColumn('{{%learners}}', 'practical_test_booking_ref', $this->string(64)->null());
        $this->addColumn('{{%learners}}', 'practical_test_cancel_by', $this->date()->null());
        $this->addColumn('{{%learners}}', 'practical_test_reminder_offsets', $this->text()->null());
        $this->addColumn('{{%learners}}', 'theory_test_date', $this->date()->null());

        $this->createTable('{{%learner_profile_change_events}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'actor' => $this->string(32)->notNull()->defaultValue('learner'),
            'source' => $this->string(64)->notNull(),
            'summary' => $this->string(255)->notNull(),
            'changes_json' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'seen_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex(
            'idx_profile_change_org_seen',
            '{{%learner_profile_change_events}}',
            ['organisation_id', 'seen_at', 'created_at'],
        );
        $this->createIndex(
            'idx_profile_change_learner',
            '{{%learner_profile_change_events}}',
            ['learner_id', 'created_at'],
        );
        $this->addForeignKey(
            'fk_profile_change_org',
            '{{%learner_profile_change_events}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_profile_change_learner',
            '{{%learner_profile_change_events}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learner_profile_change_events}}');
        $this->dropColumn('{{%learners}}', 'theory_test_date');
        $this->dropColumn('{{%learners}}', 'practical_test_reminder_offsets');
        $this->dropColumn('{{%learners}}', 'practical_test_cancel_by');
        $this->dropColumn('{{%learners}}', 'practical_test_booking_ref');
        $this->dropColumn('{{%learners}}', 'available_from');
        $this->dropColumn('{{%learners}}', 'licence_expiry_date');
        $this->dropColumn('{{%learners}}', 'licence_number');
        $this->dropColumn('{{%learners}}', 'middle_name');
    }
}
