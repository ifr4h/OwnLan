<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Saved pickup locations, lesson chat, private diary blocks, focus tags.
 */
class m260905_140000_locations_notes_private_blocks extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%learner_locations}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'label' => $this->string(64)->notNull(),
            'icon' => $this->string(32)->notNull()->defaultValue('pin'),
            'address' => $this->text()->notNull(),
            'is_default' => $this->boolean()->notNull()->defaultValue(false),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_learner_locations_learner',
            '{{%learner_locations}}',
            ['organisation_id', 'learner_id'],
        );
        $this->addForeignKey(
            'fk_learner_locations_learner',
            '{{%learner_locations}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->addColumn('{{%lessons}}', 'pickup_location_id', $this->integer()->null());
        $this->addColumn(
            '{{%lessons}}',
            'pickup_set_by',
            $this->string(16)->null(),
        );
        $this->addColumn('{{%lessons}}', 'pickup_changed_at', $this->dateTime()->null());
        $this->addColumn('{{%lessons}}', 'pickup_change_acked_at', $this->dateTime()->null());
        $this->addColumn('{{%lessons}}', 'focus_tags_json', $this->text()->null());
        $this->addForeignKey(
            'fk_lessons_pickup_location',
            '{{%lessons}}',
            'pickup_location_id',
            '{{%learner_locations}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%lesson_messages}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'lesson_id' => $this->integer()->notNull(),
            'author_role' => $this->string(16)->notNull(),
            'author_user_id' => $this->integer()->null(),
            'author_portal_account_id' => $this->integer()->null(),
            'body' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_lesson_messages_lesson',
            '{{%lesson_messages}}',
            ['lesson_id', 'created_at'],
        );
        $this->addForeignKey(
            'fk_lesson_messages_lesson',
            '{{%lesson_messages}}',
            'lesson_id',
            '{{%lessons}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        $this->createTable('{{%diary_blocks}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->notNull(),
            'starts_at' => $this->dateTime()->notNull(),
            'duration_minutes' => $this->integer()->notNull(),
            'label' => $this->string(120)->notNull()->defaultValue('Private'),
            'kind' => $this->string(32)->notNull()->defaultValue('private'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'idx_diary_blocks_org_starts',
            '{{%diary_blocks}}',
            ['organisation_id', 'starts_at'],
        );
        $this->addForeignKey(
            'fk_diary_blocks_instructor',
            '{{%diary_blocks}}',
            'instructor_id',
            '{{%instructors}}',
            'id',
            'CASCADE',
            'CASCADE',
        );

        // Seed locations from existing default pickup text.
        $rows = $this->db->createCommand(
            'SELECT id, organisation_id, default_pickup_address FROM {{%learners}}
             WHERE default_pickup_address IS NOT NULL AND TRIM(default_pickup_address) <> \'\'',
        )->queryAll();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $this->insert('{{%learner_locations}}', [
                'organisation_id' => (int) $row['organisation_id'],
                'learner_id' => (int) $row['id'],
                'label' => 'Home',
                'icon' => 'home',
                'address' => trim((string) $row['default_pickup_address']),
                'is_default' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_diary_blocks_instructor', '{{%diary_blocks}}');
        $this->dropTable('{{%diary_blocks}}');

        $this->dropForeignKey('fk_lesson_messages_lesson', '{{%lesson_messages}}');
        $this->dropTable('{{%lesson_messages}}');

        $this->dropForeignKey('fk_lessons_pickup_location', '{{%lessons}}');
        $this->dropColumn('{{%lessons}}', 'focus_tags_json');
        $this->dropColumn('{{%lessons}}', 'pickup_change_acked_at');
        $this->dropColumn('{{%lessons}}', 'pickup_changed_at');
        $this->dropColumn('{{%lessons}}', 'pickup_set_by');
        $this->dropColumn('{{%lessons}}', 'pickup_location_id');

        $this->dropForeignKey('fk_learner_locations_learner', '{{%learner_locations}}');
        $this->dropTable('{{%learner_locations}}');
    }
}
