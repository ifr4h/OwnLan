<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Basic lessons — scheduled teaching appointments.
 * History stays queryable for later pattern derivation (frequency, duration, slots).
 */
class m260831_193000_create_lessons_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%lessons}}', [
            'id' => $this->primaryKey(),
            'organisation_id' => $this->integer()->notNull(),
            'instructor_id' => $this->integer()->notNull(),
            'learner_id' => $this->integer()->notNull(),
            // Always stored as UTC wall time (no TZ column); convert via organisation.timezone.
            'starts_at' => $this->dateTime()->notNull(),
            'duration_minutes' => $this->integer()->notNull()->defaultValue(60),
            'pickup_address' => $this->text()->null(),
            'status' => $this->string(32)->notNull(),
            'cancelled_at' => $this->dateTime()->null(),
            'completed_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('ix_lessons_organisation_id', '{{%lessons}}', 'organisation_id');
        $this->createIndex(
            'ix_lessons_org_upcoming',
            '{{%lessons}}',
            ['organisation_id', 'status', 'starts_at'],
        );
        $this->createIndex(
            'ix_lessons_learner_history',
            '{{%lessons}}',
            ['organisation_id', 'learner_id', 'starts_at'],
        );

        $this->addForeignKey(
            'fk_lessons_organisation',
            '{{%lessons}}',
            'organisation_id',
            '{{%organisations}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lessons_instructor',
            '{{%lessons}}',
            'instructor_id',
            '{{%instructors}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
        $this->addForeignKey(
            'fk_lessons_learner',
            '{{%lessons}}',
            'learner_id',
            '{{%learners}}',
            'id',
            'RESTRICT',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%lessons}}');
    }
}
