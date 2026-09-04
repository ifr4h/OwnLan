<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Weekly recurring lesson series + occurrence link.
 * Not a universal RRULE engine — weekly only.
 */
class m260831_220000_lesson_series_and_availability extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%lesson_series}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            'duration_minutes' => $this->integer()->notNull()->defaultValue(60),
            'pickup_address' => $this->text()->null(),
            // First occurrence local wall start (org TZ), used as weekly anchor.
            'anchor_starts_at_local' => $this->string(32)->notNull(),
            'until_date' => $this->date()->null(),
            'occurrence_count' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('ix_lesson_series_org', '{{%lesson_series}}', 'organisation_id');
        $this->addForeignKey(
            'fk_lesson_series_organisation',
            '{{%lesson_series}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_series_instructor',
            '{{%lesson_series}}',
            'instructor_id',
            '{{%instructors}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lesson_series_learner',
            '{{%lesson_series}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );

        $this->addColumn('{{%lessons}}', 'series_id', $this->integer()->null());
        $this->createIndex('ix_lessons_series_id', '{{%lessons}}', 'series_id');
        $this->addForeignKey(
            'fk_lessons_series',
            '{{%lessons}}',
            'series_id',
            '{{%lesson_series}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $this->createTable('{{%learner_availability}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            // ISO weekday: 1=Monday … 7=Sunday
            'weekday' => $this->smallInteger()->notNull(),
            // flexible | after | before | between
            'mode' => $this->string(16)->notNull(),
            'start_time' => $this->string(5)->null(), // HH:MM
            'end_time' => $this->string(5)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'ix_learner_availability_learner',
            '{{%learner_availability}}',
            ['organisation_id', 'learner_id', 'weekday'],
        );
        $this->addForeignKey(
            'fk_learner_availability_organisation',
            '{{%learner_availability}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_learner_availability_learner',
            '{{%learner_availability}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%learner_availability}}');
        $this->dropForeignKey('fk_lessons_series', '{{%lessons}}');
        $this->dropIndex('ix_lessons_series_id', '{{%lessons}}');
        $this->dropColumn('{{%lessons}}', 'series_id');
        $this->dropTable('{{%lesson_series}}');
    }
}
